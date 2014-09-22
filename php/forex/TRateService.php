<?php

/**
 * TRateService class file
 * @author chriss
 */
Yii::import('application.modules.trader.components.TMode');

/**
 * Abstract class defining rate data feed daemon functions.
 * Broker-specific functions are implemented in corresponding classes.
 * 
 * @package TraderModule
 */
abstract class TRateService extends CComponent {
    /**
     * If connection failed, reconnecting in that number of seconds
     */
    const RECONNECT_IN_SEC = 30;

    /**
     * Forex.com broker record
     * @var TBroker
     */
    public $broker;
    /**
     * Broker account record
     * @var TAccount
     */
    public $account;
    /**
     * Service connection state
     * @var bool
     */
    protected $_connected = false;
    /**
     * Instrument model instance
     * @var TInstrument
     */
    protected $_instrumentModel;

    /**
     * ======================================================
     *          ABSTRACT PUBLIC METHODS
     * ======================================================
     */

    /**
     * Connects to the service provider
     * 
     * @return bool
     */
    abstract public function executeConnect();

    /**
     * Disconnects from the service provider
     * 
     * @return bool
     */
    abstract public function disconnect();

    /**
     * Authorisation routines
     */
    abstract public function executeLogin();

    /**
     * If required, subscription messages are sent here
     */
    abstract public function executeSubscribe();

    /**
     * Main process runs here
     */
    abstract public function executeRun();

    /**
     * This method is called upon new instance is created and @see init() method is invoked
     */
    abstract public function afterConstruct();

    /**
     * ======================================================
     *                  STATIC METHODS
     * ======================================================
     */

    /**
     * Creates RateService for given broker.
     * 
     * @param int $account_id - broker account id
     * 
     * @return Object, false if failed to load broker module or settings
     */
    public static function factory($account_id) {
        if (Yii::app()->getModule('trader') != null) {
            if ($accountModel = TAccount::model()->with('broker')->findByPk($account_id)) {
                $broker = $accountModel->broker->title;

                if (empty($broker)) {
                    self::log(sprintf(Yii::t('exceptions', 'broker_rate_service_not_set')), 'error', 'application.TraderModule');
                    return false;
                }

                if (empty($account_id)) {
                    self::log('Account id is empty', 'error', 'application.TraderModule');
                    return false;
                }

                try {
                    Yii::import('application.modules.trader.components.brokers.' . $broker . '.' . $broker . 'RateService', true);
                } catch (CException $e) {
                    self::log(sprintf(Yii::t('TraderModule.exceptions', 'broker_rate_service_not_found'), $broker), 'error', 'application.TraderModule');
                    return false;
                }

                try {
                    $service = $broker . 'RateService';
                    $serviceObject = new $service;
                    $serviceObject->account = $accountModel;
                    $serviceObject->init();

                    return $serviceObject;
                } catch (CException $e) {
                    self::log($e->getMessage());
                    return false;
                }
            }
            else
                self::log('Account with id ' . $account_id . ' is not found in the db', 'error');
        }
        else
            self::log('Trader module is not active', 'error');

        return false;
    }

    /**
     * Puts the message to the log and outputs in to the console
     * 
     * @param string $msg - message text
     * @param string $type - message type (@see Yii::log()) trace, info, profile, warning, error.
     * @param string $category - message category (@see Yii::log()), 'application.TraderModule' by default
     */
    public static function log($msg, $type = 'error', $category = 'application.TraderModule') {
        echo $msg;
        Yii::log($msg, $type, $category);
    }

    /*
     * ==================================================================
     *          PROCESS FLOW WRAPPER METHODS TO SUPPORT THE MOCK MODE
     * ==================================================================
     */

    /**
     * Performs the connect routines.
     * 
     * If the service is in mock mode, returns true straight away,
     * otherwise attempts to establish a connection depending on the service realisation.
     * 
     * @return bool
     */
    public function connect() {
        if ($this->account->rate_data_mode == TMode::MODE_MOCK) {
            $this->_connected = true;
            return true;
        }
        else
            return $this->executeConnect();
    }

    /**
     * Authorisation routines
     */
    public function login() {
        if ($this->account->rate_data_mode == TMode::MODE_MOCK)
            return true;
        else
            return $this->executeLogin();
    }

    /**
     * If required, subscription messages are sent here
     */
    public function subscribe() {
        if ($this->account->rate_data_mode == TMode::MODE_MOCK)
            return true;
        else
            return $this->executeSubscribe();
    }

    /**
     * Main process runs here.
     * For MOCK mode process loops here and retrieves archive rates imitating rate refresh.
     */
    public function run() {
        if ($this->account->rate_data_mode == TMode::MODE_MOCK) {
            if ($this->account->canRunMock($this->account->id)) {
                $start = 1;
                $this->log('Daemon for account ' . $this->account->id . ' is up and running in the mock mode' . "\n", 'info', 'application.TraderModule.' . $this->broker->title);
                do {
                    $start++;
                    $result = $this->_instrumentModel->getRandomRate($this->account->id, $start);
                    try {
                        $this->_instrumentModel->set($result[0], $result[1], $result[2], $result[3]);
                        $this->onRateChanged(
                                new CEvent($this, array(
                                    'instrument' => $result[0], 'bid' => $result[1], 'ask' => $result[2], 'decimals' => $result[3], 'account' => $this->account->id
                                )));

                        usleep(1000);
                    } catch (CException $e) {
                        $this->log($e->getMessage());
                    }
                } while (true);
            }
            else
                self::log('Account ' . $account . ' cannot be launched in the MOCK mode - not enough rate data', 'error', 'application.TraderModule.' . $broker);
        }
        else
            $this->executeRun();
    }

    /*
     * ==================================================================
     *                          DAEMON METHODS
     *                  IMPORTANT: WORK ONLY FOR *NIX SYSTEMS
     * ==================================================================
     */

    /**
     * Launches daemon process for the specified broker
     * 
     * @param string $broker - broker title
     * @param int $account - trader account id
     * @param bool $isTest - if console app is in test mode, optional
     * @return int pid - process id of the launched daemon
     */
    public static function start($broker, $account, $isTest = null) {
        $cmd = 'nohup ' . Yii::app()->basePath . '/yiic ratedatafeed ' . $account . ($isTest ? ' test' : '') . ' > /dev/null 2>/dev/null < /dev/null & echo $!';
        exec($cmd, $op);

        $allBrokerPids = self::getAllBrokerPids($broker);
        $allBrokerPids[$account] = $op[0];
        Yii::app()->cache->set('pid_ratedata_' . $broker . '_' . $account, $op[0]);
        sleep(1);

        if (is_numeric($op[0]) && TRateService::isRunning($broker, $account)) {
            Yii::app()->cache->set('pid_ratedata_' . $broker . '_all', $allBrokerPids);
            self::log('Launching daemon process for account ' . $account, 'info', 'application.TraderModule.' . $broker);

            return $op[0];
        }
        else
            Yii::app()->cache->set('pid_ratedata_' . $broker . '_' . $account, null);

        return false;
    }

    /**
     * Stops datedata feed for the indicated broker / account pair
     * 
     * @param string $broker - broker title
     * @param int $account - trader account id
     * @return bool
     */
    public static function stop($broker, $account) {
        if ($pid = self::isRunning($broker, $account)) {
            if (posix_kill($pid, 9)) {
                Yii::app()->cache->set('pid_ratedata_' . $broker . '_' . $account, null);
                $allBrokerPids = self::getAllBrokerPids($broker);
                unset($allBrokerPids[$account]);
                Yii::app()->cache->set('pid_ratedata_' . $broker . '_all', $allBrokerPids);
                self::log('Daemon process for account ' . $account . ' stopped', 'info', 'application.TraderModule.' . $broker);
            }
            else
                throw new CException('Could not stop service with pid ' . $pid . '! Error : ' . posix_strerror(posix_get_last_error()));
        }

        return false;
    }

    /**
     * Attempts to stop all processes associated with given broker
     * 
     * @param string $broker - broker title
     */
    public static function stopAll($broker) {
        $allBrokerPids = self::getAllBrokerPids($broker);
        if (!empty($allBrokerPids)) {
            $allBrokerPidsUpdated = $allBrokerPids;

            foreach ($allBrokerPids as $account => $pid) {
                if ($pid == null || posix_kill($pid, 9)) {
                    unset($allBrokerPidsUpdated[$account]);
                    Yii::app()->cache->set('pid_ratedata_' . $broker . '_' . $account, null);
                    self::log('All daemon processes stopped', 'info', 'application.TraderModule.' . $broker);
                }
                else
                    throw new CException('Could not stop daemon process pid ' . $pid . '!');
            }

            Yii::app()->cache->set('pid_ratedata_' . $broker . '_all', $allBrokerPidsUpdated);
        }
    }

    /**
     * Checks if the associated daemon process is running
     * 
     * @param string $broker - broker title
     * @param int $account - trader account id
     * @return mixed - int pid of the process runnung, otherwise false
     */
    public static function isRunning($broker, $account) {
        $pid = Yii::app()->cache->get('pid_ratedata_' . $broker . '_' . $account);

        if (!empty($pid)) {
            exec("ps -p $pid", $output);
            if (count($output) > 1)
                return $pid;
            else { // forget this pid
                Yii::app()->cache->set('pid_ratedata_' . $broker . '_' . $account, null);
                $allBrokerPids = self::getAllBrokerPids($broker);
                unset($allBrokerPids[$account]);
                Yii::app()->cache->set('pid_ratedata_' . $broker . '_all', $allBrokerPids);
            }
        }

        return false;
    }

    /**
     * Retrieves all currently running rate service processes for the broker.
     * 
     * @param string $broker - broker name, e.g. ForexCom
     * @return array
     */
    public static function getAllBrokerPids($broker) {
        return Yii::app()->cache->get('pid_ratedata_' . $broker . '_all');
    }

    /**
     * ======================================================
     *                  EVENTS
     * ======================================================
     */

    /**
     * Raises an Order Executed event
     * @param CEvent $event 
     */
    public function onOrderExecuted($event) {
        $this->raiseEvent('onOrderExecuted', $event);
    }

    /**
     * Raises an Rate Changed event
     * @param CEvent $event 
     */
    public function onRateChanged($event) {
        $this->raiseEvent('onRateChanged', $event);
    }

    /**
     * Raises a Stop Loss event
     * @param CEvent $event 
     */
    public function onStopLossFired($event) {
        $this->raiseEvent('onStopLossFired', $event);
    }

    /**
     * ======================================================
     *                  PUBLIC METHODS
     * ======================================================
     */

    /**
     * Initializes all necessary common objects and values. Called by factory method.
     */
    public function init() {
        $this->account->broker_id;
        $this->broker = TBroker::model()->findByPk($this->account->broker_id);
        if ($this->broker == null)
            throw new CException('Broker ' . $this->account->broker_id . ' not found!');

        $this->setMode($this->account->rate_data_mode);
        if (TBrokerAllowedModes::model()->findByAttributes(array('broker_id' => $this->account->broker_id, 'mode' => $this->account->rate_data_mode, 'type' => 'service')) === null)
            throw new CException('Mode ' . TMode::getTitle($this->account->rate_data_mode) . ' is not allowed for the broker ' . $this->broker->title);

        $this->_instrumentModel = TInstrument::model();
        $this->_instrumentModel->account = $this->account->id;
        $this->_instrumentModel->broker = $this->account->broker_id;

        $this->afterConstruct();
    }

    /**
     * Attempts to reconnect
     * 
     * @return bool - reconnection result
     */
    public function reconnect() {
        $this->log('Reconnecting due to connection failure', 'info', 'application.TraderModule.' . $this->broker->title);
        $this->_lastResponseTime = time();
        if ($this->isConnected())
            $this->disconnect();
        if ($this->connect()) {
            return $this->subscribe();
        }
    }

    /**
     * Check if service is connected
     * @return bool
     */
    public function isConnected() {
        return $this->_connected;
    }

}

?>
