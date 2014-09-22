<?php

/**
 * ForexComRateService class file
 * @author chriss
 */
Yii::import('application.modules.trader.components.brokers.ForexCom.*');
Yii::import('application.modules.trader.components.brokers.ForexCom.ForexComBrokerAuth');

/**
 * Rate data service implementation for the Forex.com rate data server.
 *
 * @package TraderModule
 */
class ForexComRateService extends TRateService {
    const BROKER_TITLE = 'ForexCom';

    /**
     * Authorisation object
     * @var ForexComBrokerAuth 
     */
    private $_auth;
    /**
     * Request instance
     * @var ForexComRequest
     */
    private $_request;
    /**
     * Response instance
     * @var ForexComResponse
     */
    private $_response;

    /**
     * Creates a ForexComRateService instance.
     * 
     * Initializes necessary objects and attaches SocketConnection and TMode behaviors.
     */
    public function __construct() {
        $this->attachBehaviors(array(
            'SocketConnection' => new SocketConnection(), // Works thru sockets
            'TMode' => new TMode()              // Supports variety of working modes
        ));

        // Initialize necessary objects
        $this->_response = new ForexComResponse();
        $this->_request = new ForexComRequest();
        $this->_auth = new ForexComBrokerAuth();
    }

    /**
     * Performs post-construct actions
     * 
     * Retrieves settings.
     * 
     * @throws CException in mode is undefined
     */
    public function afterConstruct() {
        if ($this->mode != TMode::MODE_MOCK) {
            // Get settings
            $type = strtolower(TMode::getTitle($this->mode));

            $port_field = $type . '_rate_data_connection_port';
            $host_field = $type . '_rate_data_connection_host';

            if ($this->broker->$host_field != null && $this->broker->$port_field != null) {
                $this->host = gethostbyname($this->broker->$host_field);
                $this->port = $this->broker->$port_field;
            }
            else
                throw new CException('Undefined ' . $type . ' mode rate data connection parameters for ' . $this->broker->full_title . '.');
        }
    }

    /**
     * Connects to the forex.com.		
     * If connection failed, tries to reconnect 
     * every 60 seconds until connected.
     * 
     * @todo send report on connection failure
     * 
     * @return bool 
     */
    public function executeConnect() {
        do {
            if ($this->_connected = $this->open()) {
                $this->log('Connected', 'info', 'application.TraderModule.' . $this->broker->title);
                $this->account->setNormalState();
                return true;
            }
            $this->log('Could not connect! Got error #' . $this->errno . ': ' . $this->errstr . "\n" . 'Reconnecting in ' . parent::RECONNECT_IN_SEC . ' seconds', 'error', 'application.TraderModule.' . $this->broker->title);
            $this->account->setErrorState();
            sleep(parent::RECONNECT_IN_SEC);
        } while (true);
    }

    /**
     * Disconnects form the forex.com server.
     * 
     * @return bool - whether disconnect succeeded
     */
    public function disconnect() {
        if ($this->close()) {
            $this->log('Disconnected', 'info', 'application.TraderModule.' . $this->broker->title);
            $this->_connected = false;
            return true;
        }
        return false;
    }

    /**
     * Reconnects to the forex.com server.
     * Performs authentication if needed.
     * 
     * @return bool connection result
     */
    public function reconnect() {
        parent::reconnect();
    }

    /**
     * Authenticates account on the forex.com side.
     * Returns the 'key' string valid for 24hrs used to authenticate with the Rates Server.
     * 
     * Use ForexComBrokerAuth::getKey() (@see ForexComBrokerAuth) to retrieve the key.
     * If connection failed, attempts to login again until success of auth fail.
     * 
     * @return bool 
     */
    public function executeLogin() {
        if (!empty($this->account->id)) {
            $this->_instrumentModel->account = $this->account->id;

            try {
                $this->_auth->terminal = TTerminal::factory($this->account->id, $this->mode);
                if ($this->_auth->login()) {
                    $this->log('Authorisation by ' . $this->broker->full_title . ' succeeded, key acquired' . "\n", 'info', 'application.TraderModule.' . $this->broker->title);
                    return true;
                }
                else
                    $this->log('Authorisation by ' . $this->broker->full_title . ' failed!' . "\n", 'error', 'application.TraderModule.' . $this->broker->title);
            } catch (CException $e) { // Connection failed
                if ($this->available == 0 && $this->state == 'ok')
                    $this->log('Authorisation failed due to broker unavailability: ' . $e->getMessage() . "\n", 'error', 'application.TraderModule.' . $this->broker->title);
                else {
                    $this->log('Authorisation failed due to connection failure: ' . $e->getMessage() . "\n" .
                            'Trying to relogin...Relogin attempt in ' . (parent::RECONNECT_IN_SEC / 2) . "seconds \n", 'error', 'application.TraderModule.' . $this->broker->title);
                    sleep(parent::RECONNECT_IN_SEC / 2);
                    $this->login();
                }
            }
        }
        else
            $this->log('Account is missing', 'error', 'application.TraderModule.' . $this->broker->title);

        return false;
    }

    /**
     * Acquires auth key (@see ForexComBrokerAuth::getKey()) and
     * sends a subscription message to the forex.com server.
     * 
     * If subscription failed, reconnects and relogins until success.
     * 
     * @return bool - whether subscription succeeded 
     */
    public function executeSubscribe() {
        do {
            if ($key = $this->_auth->getKey()) {
                $this->log('Subscribing to ' . $this->broker->full_title . '...' . "\n", 'info', 'application.TraderModule.' . $this->broker->title);

                // Send request
                $this->write($this->_request->initialMsg($key));
                sleep(3);

                // Read response
                $responseText = $this->readUntil('$');
                if (!empty($responseText)) {
                    $this->log('Successfully subscribed to ' . $this->broker->full_title . ' rate data service ', 'info', 'application.TraderModule.' . $this->broker->title);
                    return true;
                }
                $this->log('Could not subscribe to ' . $this->broker->full_title . '!' . "\n" .
                        'Trying to reconnect and relogin in ' . parent::RECONNECT_IN_SEC . ' seconds', 'error', 'application.TraderModule.' . $this->broker->title);
                sleep(parent::RECONNECT_IN_SEC);
                $this->reconnect();
            }
            else
                $this->log('No key acquired for ' . $this->broker->full_title, 'error', 'application.TraderModule.' . $this->broker->title);
        }
        while (empty($responseText) || $responseText === false);

        return false;
    }

    /**
     * Main socket read-write process. Creates an infinite loop.
     */
    public function executeRun() {
        $this->log('Daemon is up and running' . "\n", 'info', 'application.TraderModule.' . $this->broker->title);
        do {
            if ($response = $this->readBySeparator($this->_response->ratemsgSeparator)) {
                if ($parsedData = $this->_response->parse($response)) {
                    if (isset($parsedData['order']))
                        $this->onOrderExecuted(new CEvent($this, array('order_reference' => $parsedData['data'])));
                    else { // manage rates
                        try {
                            if (strlen($parsedData[0]) >= 7 && strpos($parsedData[0], '/')) {
                                var_dump($parsedData[0]);
                                echo "\n";
                                $this->_instrumentModel->set($parsedData[0], $parsedData[2], $parsedData[3], $parsedData[8]);
                                $this->onRateChanged(
                                        new CEvent($this, array(
                                            'instrument' => $parsedData[0], 'bid' => $parsedData[2], 'ask' => $parsedData[3], 'decimals' => $parsedData[8], 'account' => $this->account->id
                                        )));
                            }
                        } catch (CException $e) {
                            $this->log($e->getMessage());
                        }
                    }
                }
                echo $response . "\n" . "\n";
            } else {
                $this->log('Socket error: ' . $this->errstr, 'error', 'application.TraderModule.ForexCom');
                $this->account->setErrorState();
                $this->reconnect();
            }
        } while (true);
    }

}

?>
