<?php

/**
 * Класс для взаимодействия с WSDL
 * Используется для подключения к компонентам YourCraft и сайту
 */
abstract class SoapClientModel extends CModel
{
    public $soapUrl;
    
    /**
     * @var SoapClient
     */
    protected $_soapClient;
    
    /**
     * Массив полей объекта
     * @var array
     */
    protected $_params;
    
    public function attributeNames() 
    {
        ;
    }
    
    /**
     * Если не установлен ранее, создает и устанавливает экземпляр SoapClient 
     * по WSDL, указанному в настройках.
     * 
     * Если не удалось подключиться, возвращает null.
     */
    public function setSoapClient()
    {
        if ( !isset($this->_soapClient) && isset($this->soapUrl))
        {
            Yii::beginProfile('setSoap');
            try {
                $this->_soapClient = @new SoapClient($this->soapUrl, array( 'cache_wsdl' => WSDL_CACHE_MEMORY, 'exceptions' => 1, 'timeout' => 10));
            }
            catch (SoapFault $e)
            {
                Yii::log('Could not connect to '.$this->soapUrl, 'error');
                return null;
            }
            Yii::endProfile('setSoap');
        }
    }
    
    /**
     * Осуществляет вызов метода веб-сервиса.
     * 
     * @param string $name название метода
     * @param array $parameters список параметров метода в виде ассоциативного массива ["name" : "val"]
     * @return mixed объект, возвращаемый методом веб-сервиса
     * 
     * @throws CException
     */
    public function call($name, $parameters = null) 
    {
        $this->setSoapClient();
        if ($this->_soapClient instanceof SoapClient)
        {
            Yii::log('Вызван метод '.$name.
                    ' c параметрами '.serialize($parameters).
                    ' пользователем '.Yii::app()->user->name, 'info', 'application.soap');
            try
            {
                Yii::beginProfile('soapCall');
                Yii::beginProfile('soapCall_function_'.$name);
                $res = $this->_soapClient->{$name}($parameters);
                Yii::endProfile('soapCall_function_'.$name);
                Yii::endProfile('soapCall');
                return $res;
            }
            catch (SoapFault $e)
            {
                Yii::log($this->_soapClient->__getLastRequest(), 'error');
                throw new CException(Yii::t('soap_error', $e->__toString()));
            }
        }
        else
            throw new CException("SOAP fail");
        
        return false;
    }
    
    /**
     * Присваивает значения полям модели (@see __set).
     * 
     * @param array $params - ассоциативный массив атрибутов модели. 
     */
    public function setParams($params = array())
    {
        $this->_params = CMap::mergeArray($this->_params, $params);
    }
    
    /**
     * Возвращает значения динамических полей класса
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }
    
    /**
     * В случае, если переданный объект не является массивом,
     * возвращает массив из одного элемента, в противном случае просто
     * возвращает массив.
     * @param mixed $object - объект или массив объектов
     * @return array 
     */
    public static function toArray($object)
    {
        return is_array($object) ? $object : array($object);
    }
    
    /**
     * В случае, если переданный объект является ассоциативным массивом,
     * приводит переменную к типу object.
     * @param mixed $array - объект или массив
     * @return object
     */
    public static function toObject($array)
    {
        return is_array($array) ? (object)$array : $array;
    }
    
    /**
     * Переопределенный магический метод php.
     * Вызывается при попытке присвоения значения несуществующему полю класса.
     * 
     * @param string $name название поля
     * @param string $value значение поля
     */
    public function __set($name, $value) 
    {
        $this->_params[$name] = $value;
    }
    
    public function __isset($name) 
    {
        return isset($this->_params[$name]);
    }

    /**
     * Переопределенный магический метод php.
     * Вызывается при попытке получения значения несуществующего поля класса.
     * 
     * @param string $name название поля
     * @return mixed значение элемента массива params с данным ключом, если значение не найдено возвращает null
     */
    public function __get($name) 
    {
        if (isset($this->_params[$name]))
            return $this->_params[$name];
        
        return null;
    }
}
?>
