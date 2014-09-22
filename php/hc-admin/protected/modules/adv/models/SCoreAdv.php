<?php

Yii::import('application.components.behaviors.UploadableResourceBehavior');
Yii::import('application.components.behaviors.WarningableModelBehavior');

/**
 * Core ADV - родительский класс для ADV компонентов (баннеры, новости).
 * Реализует функции, общие для всех (например, удаление).
 * Осуществляет работу с валидацией.
 *
 * @author chriss
 */
class SCoreAdv extends SoapClientModel
{
    /**
     * Для вывода списков (активные / неактивные / все)
     */
    const DISPLAY_STATUS_BOTH       = 'BOTH';
    const DISPLAY_STATUS_DISABLED   = 'DISABLED';
    const DISPLAY_STATUS_ENABLED    = 'ENABLED';
    
    /**
     * @var ResourceBehavior
     */
    public $resourceBehavior;
    
    /**
     *
     * @var WarningableModelBehavior 
     */
    public $warningsBehavior;
    
    /**
     * Комментарии к текущему объекту
     * @var AdvComment 
     */
    public $comments = '';
    
    /**
     * Является ли объект группой (@see AdvGroup)
     * @var bool 
     */
    public $isGroup = false;
    
    public function __construct() 
    {
        $this->soapUrl = Yii::app()->getModule('adv')->wsdlUrlCore;
        $this->warningsBehavior = new WarningableModelBehavior();
    }
    
    public function delete($id)
    {
        return $this->call('deleteADV', array('id' => $id));
    }
    
    /**
     * Сохраняет копию адв-объекта, копирует ресурсы объекта в нужную папку
     */
    public function copy()
    {
        $sourceFolder = null;
        if (method_exists($this, 'getResourceUploader'))
        {
            $sourceFolder = $this->getResourceUploader()->getDestinationFolder($this->getCode());
        }
        
        $this->setIsNewRecord();
        $this->code = null; // сбрасываем код для обновления путей
        $this->save(false);
        
        if ($sourceFolder)
        {
            $this->getResourceUploader()->copyDirectory('/' . trim($sourceFolder, '/'), $this->getResourceUploader()->getDestinationFolder($this->getCode()));
        }
    }

    /**
     * Осуществляет валидацию всех ресурсов, принадлежащих объекту.
     * @return array ассоциативный массив ошибок {название размера реурса:[данные по размеру]}
     */
    public function validateAllResources()
    {
        return $this->resourceBehavior->validateAll();
    }
    
    /**
     * Для клонирования - убирает параметр id из списка параметров,
     * чтобы редактирование заменить добавлением нового элемента.
     */
    public function setIsNewRecord()
    {
        $this->_params['id'] = null;
    }

    /**
     * Устанавливает в поле providers указанный id и обновляет приоритеты.
     * Метод используется для установки связи 1 - 1 (1 провайдер - 1 объект),
     * в отличие от предыдущей версии 1 - n.
     */
    public function setProvider($pid, $priority)
    {
        $this->providers = array($pid);
        $this->setProviderPriorities($pid, $priority);
    }
    
    /**
     * Устанавливает приоритеты объекта по провайдерам в нужном для сохранения формате
     * 
     * @param int $pid
     * @param int $i 
     */
    public function setProviderPriorities($pid, $i)
    {
        $priorities = array();
        $prioritySet = false;
        if (isset($this->providerPriorities->entry))
            $priorities = $this->toArray($this->providerPriorities->entry);
        
        foreach ($priorities as $priority)
        {
            if ($priority->key == $pid)
            {
                $priority->value = $i;
                $prioritySet = true;
            }
        }
        
        if ( ! $prioritySet )
            array_unshift($priorities, array('key' => $pid, 'value' => $i));
        
        $this->providerPriorities = array('entry' => $priorities);
    }
    
    /**
     * Удаляет приоритеты объекта для указанного провайдера
     * 
     * @param int $pid
     */
    public function removeProviderPrioritiy($pid)
    {
        $priorities = array();
        if (isset($this->providerPriorities->entry))
            $priorities = $this->toArray($this->providerPriorities->entry);
        
        $i = 0;
        foreach ($priorities as $priority)
        {
            if ($priority->key == $pid)
                unset($priorities[$i]);
            $i++;
        }
        $this->providerPriorities = array('entry' => $priorities);
    }
    
    /**
     * Возвращает приоритет объекта для указанного провайдера
     * 
     * @param int $pid
     */
    public function getProviderPriority($pid)
    {
        if (isset($this->providerPriorities->entry))
        {
            $priorities = $this->toArray($this->providerPriorities->entry);
            foreach ($priorities as $priority)
            {
                if ($priority->key == $pid)
                    return $priority->value;
            }
        }
        else
            return $this->defaultPriority;
    }
    
    /**
     * Обновляет поле providrePriorities в соответствии с полем providers
     * для сохранения соответствия
     */
    public function updateProviderPriorities()
    {
        $providerPriorities = null;
        
        if (! empty($this->providerPriorities))
        {
            if (is_object($this->providerPriorities) && isset($this->providerPriorities->entry))
                $providerPriorities = $this->providerPriorities->entry;
            else if (is_array($this->providerPriorities) && isset($this->providerPriorities['entry']))
                $providerPriorities = $this->providerPriorities['entry'];

            if ( $providerPriorities != null)
            {
                $priorities = $this->toArray($providerPriorities);
                $providers = $this->toArray($this->providers);
                $i = 0;
                foreach ($priorities as $priority)
                {
                    if (is_object($priority))
                        $priority = (array)$priority;
                    
                    if ( !in_array($priority['key'], $providers))
                        unset($priorities[$i]);
                    $i++;
                }

                $this->providerPriorities = array('entry' => $priorities);
            }
        }
    }
    
    public function getCurrentProviderId()
    {
        return isset(Yii::app()->session['currentProviderId']) ? Yii::app()->session['currentProviderId'] : AppStore::getFirstForCurrentUser();
    }
    
    public function setCurrentProviderId($pid)
    {
        Yii::app()->session['currentProviderId'] = $pid;
    }
    
    /**
     * Проверяет, принадлежит ли текущий объект кампании.
     * 
     * @param int $excludeCampaign если указан, проверяет принадлежность кампаниям, КРОМЕ указанной
     * @return bool
     */
    public function isAttachedToCampaign($excludeCampaign = null)
    {
        $criteria = new CDbCriteria();
        $criteria->addCondition("item_id = :adv_id");
        $criteria->params = array(':adv_id' => $this->id);
        
        if ($excludeCampaign)
        {
            $criteria->addCondition("campaign_id != :excluded_campaign_id");
            $criteria->params['excluded_campaign_id'] = $excludeCampaign;
        }
        
        return CampaignItem::model()->exists($criteria);
    }
    
    public function setParams($params = array())
    {
        parent::setParams($params);
        if ($this->getScenario() == 'list' || $this->scenario == 'list') // загружаем комментарии
        {
            $commentModel = AdvComment::model();
            $commentModel->adv_id = $this->id;
            $commentModel->provider_id = $this->getCurrentProviderId();
            $this->comments = $commentModel->get();
        }
        
        if ($this->getScenario() == 'update' || $this->scenario == 'update' || $this->getScenario() == 'add' || $this->scenario == 'add')
        {
            if ( ! is_array($this->filters))
                $this->filters = json_decode($this->filters);
        }
    }
    
    /**
     * CAMPAIGN SUPPORT FUNCTIONS - MUST BE IMPLEMENTED IN ALL CHILD CLASSES!
     */
    
    public function getTitle()
    {
        ;
    }
    
    public function getImage()
    {
        ;
    }
    
    public function getListForCampaign($providerId)
    {
        ;
    }
    
    /**
     * Для включения посредством флага enabled
     */
    public function enable()
    {
        ;
    }
    
    public function disable()
    {
        ;
    }
    
    /*
     * Warningable model behavior inerface support
     */
    
    public function addWarning($attribute, $error) {
        $this->warningsBehavior->addWarning($attribute, $error);
    }
    
    public function addError($attribute, $error) {
        if (is_array($error))
        {
            if (isset($this->errors[$attribute]))
                $this->errors[$attribute] +=$error;
            else
                $this->errors[$attribute] =$error;
        }
        else
            parent::addError($attribute, $error);
    }
    
    public function getWarning($attribute) {
        return $this->warningsBehavior->getWarning($attribute);
    }
    
    public function getWarnings() {
        return $this->warningsBehavior->getWarnings();
    }
    
    public static function warningSummary($model,$header=null,$footer=null,$htmlOptions=array())
    {
        $warningModel = new WarningableModelBehavior();
        return $warningModel->warningSummary($model, $header, $footer, $htmlOptions);
    }
}