<?php

Yii::import('application.modules.adv.components.widgets.filter.models.*');

/**
 * Виджет, выводящий форму фильтров. Данный виджет работает ТОЛЬКО с фильтрами из блока COMMON.
 * В скрытых полях выводит все фильтры, прикрепленные к элементы (platform, excluded game и тд)
 *
 * @author chriss
 */
class FilterForm extends CWidget 
{
    /**
     * Фильтры в формате XML
     * @var string
     */
    public $filterXML = '';
    
    /**
     * Объект формы, в которой выводится виджет
     * @var CActiveForm
     */
    public $parentForm;
    
    /**
     * Выводить ли форму меньших размеров (например, в редактировании новостей)
     * @var bool
     */
    public $isCondensed = false;
    
    /**
     * Выводить ли фильтр как форму, или как фразу.
     * true - просмотр (фраза), false - форма
     * @var bool
     */
    public $isView = false;

    public static function actions() 
    {
        return array(
            'getActionOptions'  => 'application.modules.adv.components.widgets.filter.actions.getActionOptions',
            'getValueOptions'   => 'application.modules.adv.components.widgets.filter.actions.getValueOptions',
            'getPreview'        => 'application.modules.adv.components.widgets.filter.actions.getPreview'
        );
    }
    
    public function init() 
    {
        Yii::app()->clientScript->registerScriptFile($this->getController()->getModule()->widgetsAssetsUrl. '/js/filters.js');
        parent::init();
    }

    public function run()
    {
        $filter = new SFilter($this->filterXML);
        $commonBlock = $filter->getCommonBlock(); // получаем плоский массив из блока common
        
        if ($commonBlock != null)
        {
            $items          = $commonBlock->getItems();
            $rootOperator   = $commonBlock->getRootOperator();
            $rootOperatorVars = ($rootOperator != null) ? 
                array(
                    'rootOperatorId'    => $rootOperator->id, // временно, пока интерфейс не поддерживает вложенность
                    'rootOperation'     => $rootOperator->logicalOperator) : 
                array(
                    'rootOperatorId'    => 0,
                    'rootOperation'     => SFilterCondition::ACTION_AND);
            
            $filterHelper = new SFilterFormHelper();
            $commonParams = array(
                'items'             => $items,
                'totalExpressions'  => $commonBlock->getTotalExpressions(),
                'varNames'          => $filterHelper->getVariableNames(),
                'actionNames'       => $filterHelper->getActionNames(),
                'actionNamesExt'    => $filterHelper->getActionNamesExtended(),
                'filterHelper'      => $filterHelper
            ) + $rootOperatorVars;

            if ($this->isView)
            {
                $this->render('filter_view', $commonParams + array(
                    'operatorNames'     => array('and' => Yii::t('FilterForm.filters', 'and_lower'), 'or' => Yii::t('FilterForm.filters', 'or_lower')),
                ));
            }
            else
            {
                $this->render('filter_form', $commonParams + array(
                    'allFilters'        => $filter->getAllFiltersXML(),
                    'filterXML'         => $filter->getCommonFilterXML(),
                    'parentForm'        => $this->parentForm,
                    'isCondensed'       => $this->isCondensed,
                    'operatorNames'     => array('and' => Yii::t('FilterForm.filters', 'and'), 'or' => Yii::t('FilterForm.filters', 'or')),
                ));
            }
        }
    }
}