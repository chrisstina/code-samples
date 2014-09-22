<?php

/**
 * EObject Class file
 * 
 * Enhanced model to work with the data table
 */
class EObject extends CModel {
    /** @see _filterFields() */
    const OPTS_USAGE = 'opts'; // fields with this type of usage will be checked when printing forms 
    // Fields with this type of usage will be used for permission allocation (If ACL module is on).
    // String following the prefix will be used as the group name.
    const VIRTUAL_GROUP_USAGE = 'virtual_group';

    public $id = null;
    public $isNew;
    public $event;          // Event object
    public $rules;          // model rules
    private $_attributes;

    public function attributeNames() {
        return array();
    }

    public static function model($className=__CLASS__) {
        if (!isset(self::$_models[$className]))
            self::$_models[$className] = new $className(null);
        return self::$_models[$className];
    }

    public function rules() {
        return $this->rules;
    }

    /**
     * Model emulation
     * 
     * @param $values - prefilled values
     * @return EObject 
     */
    public function prepareModel($values = array()) {
        $types = $this->event->type->forms;

        if (is_array($types)) {
            foreach ($types as $type) {
                $fields = $this->_getFields();
                $rules = array(); // rules

                foreach ($fields as $field) {
                    $name = $field->param;
                    $value = isset($values[$name]) ? $values[$name] : '';
                    $this->$name = $value;
                    $rules[] = $name;
                }

                /** @todo: get real rules out of $_rules */
                $this->rules[] = array(implode(', ', $rules), 'required');
            }
        }

        return $this;
    }

    /**
     * ================================================
     *              DATA MANUPULATION
     * ================================================
     */
    public function save($runValidation = TRUE) {
        if (isset($this->_attributes) && (!$runValidation || $this->validate()))
            return $this->isNew ? $this->insert() : $this->update();
        return false;
    }

    public function insert() {
        $data = Data::model();
        $data->event_id = $this->event->id;

        foreach ($this->event->type->forms as $form) {
            $data->type = $form->type;

            $structure = DataStructure::model();
            $params = $structure->api_getParams($data->type);/** @todo: refactor this! */
            $needed_attributes = array();
            foreach ($params as $param)
                $needed_attributes[$param['param']] = $param['usage'];

            foreach ($this->_attributes as $name => $value) {
                if (in_array($name, array_keys($needed_attributes))) {
                    $data->setIsNewRecord(true);
                    $data->unsetAttributes(array('id'));
                    $data->usage = $needed_attributes[$name];
                    $data->param = $name;
                    $data->value = $value;

                    return $data->save();
                }
            }
        }

        return true;
    }

    public function update() {
        foreach ($this->_attributes as $name => $value) {
            $data = Data::model()->findByAttributes(array('object_id' => $this->id, 'param' => $name));
            if ($data) {
                $data->value = $value;
                return $data->save();
            }
        }

        return true;
    }

    /**
     * ================================================
     *              SETTERS, GETTERS
     * ================================================
     */

    /**
     * Sets the named attribute value.
     * You may also use $this->AttributeName to set the attribute value.
     * @param string $name the attribute name
     * @param mixed $value the attribute value.
     * @return boolean whether the attribute exists and the assignment is conducted successfully
     * @see hasAttribute
     */
    public function setAttribute($name, $value) {
        if (property_exists($this, $name))
            $this->$name = $value;
        else
            $this->_attributes[$name] = $value;
        return true;
    }

    /**
     * Returns the named attribute value.
     * If this is a new record and the attribute is not set before,
     * the default column value will be returned.
     * If this record is the result of a query and the attribute is not loaded,
     * null will be returned.
     * You may also use $this->AttributeName to obtain the attribute value.
     * @param string $name the attribute name
     * @return mixed the attribute value. Null if the attribute is not set or does not exist.
     * @see hasAttribute
     */
    public function getAttribute($name) {
        if (property_exists($this, $name))
            return $this->$name;
        else if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
    }

    /**
     * PHP getter magic method.
     * This method is overridden so that AR attributes can be accessed like properties.
     * @param string $name property name
     * @return mixed property value
     * @see getAttribute
     */
    public function __get($name) {
        return $this->getAttribute($name);
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that AR attributes can be accessed like properties.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name, $value) {
        $this->setAttribute($name, $value);
    }

    /**
     * ================================================
     *              PRIVATE FUNCTIONS
     * ================================================
     */

    /**
     * Generates field list according to data structure
     * 
     * @return DataStructure AR
     */
    private function _getFields() {
        $types = array();
        foreach ($this->event->type->forms as $form)
            $types[] = $form->type;

        $criteria = new CDbCriteria;
        $criteria->addInCondition('type', $types);
        $fields = DataStructure::model()->findAll($criteria);

        $records = $this->event->scenario->getData(null, null, 'opts');
        if (!empty($records))
            $this->_filterFields($fields, $records); // check what fields to display

        return $fields;
    }

    /**
     * Filters fields based on scenario opts fields
     * 
     * 
     * OPTS PARAM NAME MUST BE THE SAME AS CHECKED (INFO) PARAM NAME!!!
     * 
     * @param DataStructure $fields - list of fields (DataStructure active records)
     * @param array $opts - opts data
     */
    private function _filterFields(&$fields, $opts) {
        $fieldNames = array(); // prepare fields names array (name => pos)
        foreach ($fields as $pos => $field)
            $fieldNames[$field->param] = $pos;

        foreach ($opts as $type_param => $value) { //look for ancestors' USE_* fields
            if (!(bool) $value) { // unset info field with the same name
                $param = preg_replace('/([^\.]*)\./', '', $type_param); // Fetch param out of type.param
                unset($fields[$fieldNames[$param]]);
            }
        }

        echo count($fields);
    }

}