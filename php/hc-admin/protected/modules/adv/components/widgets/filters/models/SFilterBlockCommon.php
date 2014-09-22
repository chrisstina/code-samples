<?php
/**
 * Класс для построения дерева фильтров,
 * которые отображаются в форме редактирования (COMMON)
 *
 * @author chriss
 */
class SFilterBlockCommon extends SFilterBlock 
{
    private $_commonFilters = array();
    
    /**
     * id условий для построения плоского массива
     * @var int 
     */
    private $_nextId = 1;
    
    /**
     * Список элементов not
     * @var array
     */
    private $_notElements = array();
    
    /**
     * Список id элементов, которым нужно установить not
     * @var array
     */
    private $_collapsibleAsNot = array();
    
    /**
     * Количество выражений в дереве
     * @var int
     */
    private $_totalExpressions = 0;
    
    public function getItems()
    {
        return $this->_commonFilters;
    }
    
    /**
     * Возвращает главный логического оператора в плоском массиве.
     * Используется временно, пока интерфейс позволяет объединить не более двух блоков условий.
     * @return int
     */
    public function getRootOperator()
    {
        if ( ! empty($this->_commonFilters))
        {
            return end($this->_commonFilters); // предполагаем, что самый верхний элемент добавился к концу списка
        }
        
        return null;
    }
    
    public function getTotalExpressions()
    {
        return $this->_totalExpressions;
    }
    
    /**
     *
     * @param SimpleXMLObject $rawStructure
     * @return array() 
     */
    public function processRawStructure($rawStructure)
    {
        $this->_generateFlatHierarchy($rawStructure, 0);
        $this->_collapseNot();
        
        return $this->_commonFilters;
    }
    
    /**
     * Генерирует блок common по данным формы
     * 
     * @param array $expressions - данные формы фильтра
     * @param array $containers - данные формы фильтра (логические операции)
     * @return string
     * @throws CException если валидация не прошла, кидает эксепшн с текстом ошибки
     */
    public function generateXML($expressions, $containers)
    {
        $xmlstr = '';
        foreach ($expressions as $expression)
        {
            if (isset($expression['action']) && isset($expression['variable']) && isset($expression['value']) &&
                ! empty ($expression['action']) && ! empty($expression['variable']))
            {
                $conditionModel = new SFilterCondition();
                
                if ($expression['variable'] == SFilterCondition::VAR_VERSION)
                    $conditionModel->value = $expression['value'];
                
                if ($conditionModel->validate())
                    $xmlstr .= $conditionModel->generateExpressionLine($expression['action'], $expression['variable'], $expression['value'], (bool)$expression['isNot']);                    
                else
                    throw new CException(CHtml::errorSummary($conditionModel));
            }
        }
        
        // временно - берем единственный в дереве containter
        $containers = array_values($containers); 
        $lastIdx = count($containers) - 1;
        $operator = (isset($containers[$lastIdx]['logicalOperator']) && ! empty($containers[$lastIdx]['logicalOperator'])) ?
            $containers[$lastIdx]['logicalOperator'] :
            SFilterCondition::ACTION_AND;
        
        if ( ! empty($xmlstr))
        {
            // Данные формы оборачиваем в COMMON
            return $conditionModel->generateConditionLine(
                    SFilterCondition::ACTION_OR, 
                    $conditionModel->generateConditionLine($operator, $xmlstr), 
                    SFilterBlock::COMMON_BLOCK);
        }
        
        return '';
    }
    
    /**
     * Формирует плоский массив $_commonFilters из древовидного массива SimpleXMLObject. 
     * Сохраняет в структуре not. Для последующего использования необходимо обработать not (@see _collapseNot()).
     * 
     * @param array $objects
     * @param int $parentId 
     */
    private function _generateFlatHierarchy($objects, $parentId)
    {
        $duplicateValues = array();
        foreach ($objects as $operation => $object)
        {
            $conditionObject = new SFilterCondition();
            $conditionObject->parent = $parentId;
            $conditionObject->id = $this->_nextId++;

            if ( ! empty($object))
            {
                $this->_generateFlatHierarchy($object, $conditionObject->id);
            }
            
            if ( ! isset($object['desc'])) // не тэг блока
            {
                if ($conditionObject->isExpression($operation))
                {
                    $conditionObject->action    = $operation;
                    $conditionObject->variable  = (string)$object['variable'];
                    $conditionObject->fieldAction = $conditionObject->action;

                    if ( ! empty($duplicateValues[$conditionObject->variable]))
                    {
                        $this->_commonFilters[$duplicateValues[$conditionObject->variable][0]]->value[] = (string)$object['value'];
                    }
                    else
                    {
                        if ($conditionObject->variable != SFilterCondition::VAR_VERSION)
                            $conditionObject->value[] = (string)$object['value'];
                        else
                            $conditionObject->value = (string)$object['value'];
                        $duplicateValues[$conditionObject->variable][] = $conditionObject->id;
                        $this->_commonFilters[$conditionObject->id] = $conditionObject;
                        $this->_totalExpressions++;
                    }
                }
                else if ($conditionObject->isLogicalOperation($operation))
                {
                    if ($conditionObject->isNot($operation))
                        $this->_notElements[] = $conditionObject->id;
                    // устанавливаем logicalOperator всех детей текущего родителя
                    foreach ($this->_commonFilters as &$filter)
                    {
                        if ($filter->parent == $conditionObject->id)
                        {
                            if ($conditionObject->isNot($operation))
                                $this->_collapsibleAsNot[] = $filter->id;
                            if ( ! $filter instanceof SFilterContainer)
                                $filter->logicalOperator = $operation;
                        }
                    }

                    // записываем в дерево объект контейнер
                    $containerObject = new SFilterContainer();
                    $containerObject->logicalOperator = $operation;
                    $containerObject->parent = $parentId;
                    $containerObject->id = $conditionObject->id;
                    $this->_commonFilters[$containerObject->id] = $containerObject;
                }
            }
        }
    }
        
    /**
     * Преобразует структуру commonFilters
     * в соответствии с условиями, содержащими not.
     * Убирает элементы not из дерева
     * 
     * @return array 
     */
    private function _collapseNot()
    {
        foreach ($this->_collapsibleAsNot as $id)
        {
            $childFilter = $this->_commonFilters[$id];
            if ( ! $childFilter instanceof SFilterContainer)
            {
                $childFilter->fieldAction = SFilterCondition::ACTION_NOT.' '.$childFilter->action;
                $childFilter->isNot = true;
            }
            else
            {
                foreach ($this->_commonFilters as &$childrenFilter)
                {
                    if ($childrenFilter->parent == $childFilter->id && ! $childrenFilter instanceof SFilterContainer)
                    {
                        $childrenFilter->fieldAction = SFilterCondition::ACTION_NOT.' '.$childrenFilter->action;
                        $childrenFilter->isNot = true;
                    }
                }
            }
        }
        
        foreach ($this->_notElements as $notId)
            unset($this->_commonFilters[$notId]);
        
        return $this->_commonFilters;
    }
}

?>
