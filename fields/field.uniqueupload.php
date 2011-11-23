<?php

    if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

    require_once(TOOLKIT . '/fields/field.upload.php');

class FieldUniqueUpload extends FieldUpload {
    protected $_driver = null;
    protected static $ready = true;

    public function __construct(&$parent) {
        parent::__construct($parent);
        $this->_name = __('Unique File Upload');
        $this->_driver = Symphony::$ExtensionManager->create('uniqueuploadfield');
    }

    public function displaySettingsPanel(&$wrapper , $errors = null) {
        parent::displaySettingsPanel(&$wrapper , $errors);
        $div = new XMLElement('div');
        $label = Widget::Label('Naming Expression (leave empty for normal uniqueupload behavior)');
        $label->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][expression]' , $this->get('expression')));

        $help = new XMLElement('p');
        $help->setAttribute('class' , 'help');
        $help->setValue('To access the other fields, use XPath: <code>{entry/field-one} static text {entry/field-two}</code>.');

        $div->appendChild($label);
        $div->appendChild($help);
        $wrapper->appendChild($div);
    }

    public function commit() {
        if (!parent::commit()) return false;

        $id = $this->get('id');

        if ($id === false) return false;

        $fields = array();

        $fields['field_id'] = $id;
        $fields['destination'] = $this->get('destination');
        $fields['validator'] = ($fields['validator'] == 'custom' ? NULL : $this->get('validator'));
        $fields['expression'] = $this->get('expression');

        Symphony::Database()->query("DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '$id' LIMIT 1");
        return Symphony::Database()->insert($fields , 'tbl_fields_' . $this->handle());
    }

    private function getUniqueFilename($filename) {
        ## since uniqid() is 13 bytes, the unique filename will be limited to ($crop+1+13) characters;
        $crop = '30';
        return preg_replace("/([^\/]*)(\.[^\.]+)$/e" , "substr('$1', 0, $crop).'-'.uniqid().'$2'" , $filename);
    }

    public function checkPostFieldData($data , &$message , $entry_id = NULL) {
        if ($this->get('expression') == '') {
            if (is_array($data) and isset($data['name'])) $data['name'] = $this->getUniqueFilename($data['name']);
            return parent::checkPostFieldData($data , $message , $entry_id);
        } else {
            $this->_driver->registerField($this);
            return self::__OK__;
        }
    }

    public function processRawFieldData($data , &$status , $simulate = false , $entry_id = NULL) {
        if (is_array($data) and isset($data['name'])) $data['name'] = $this->getUniqueFilename($data['name']);
        return parent::processRawFieldData($data , $status , $simulate , $entry_id);
    }

    /**
     * @param  $entry
     * @return boolean
     * Renames the file based on the expression.
     * Inspired by Rowan Lewis <me@rowanlewis.com>
     */
    public function compile($entry) {
        self::$ready = false;
        $xpath = $this->_driver->getXPath($entry);
        self::$ready = true;

        $entry_id = $entry->get('id');
        $field_id = $this->get('id');
        $expression = $this->get('expression');
        $replacements = array();

        $old_value = $entry->getData($field_id);
        preg_match("/([^\/]*)(\.[^\.]+)/e" , $old_value['file'] , $oldMatches);
        $old_filename = $oldMatches[1];
        $file_extension = $oldMatches[2];

        // Find queries:
        preg_match_all('/\{[^\}]+\}/' , $expression , $matches);

        // Find replacements:
        foreach ($matches[0] as $match) {
            $result = @$xpath->evaluate('string(' . trim($match , '{}') . ')');
            if (!is_null($result)) {
                $replacements[$match] = trim($result);
            } else {
                $replacements[$match] = '';
            }
        }
        
        // Apply replacements:
        $value = str_replace(
            array_keys($replacements) ,
            array_values($replacements) ,
            $expression
        );
        
        $new_value = Lang::createFilename($value . $file_extension);
        
        $abs_path = DOCROOT . '/' . trim($this->get('destination') , '/');
        $rel_path = str_replace('/workspace' , '' , $this->get('destination'));
        
        $old = $abs_path . '/' . $old_filename . $file_extension;
        $new = $abs_path . '/' . $new_value;
        if (rename($old , $new)) {
            $new_value = $rel_path . '/' . $new_value;
            // Save:
            $result = $this->Database->update(
                array(
                     'file' => $new_value
                ) ,
                "tbl_entries_data_{$field_id}" ,
                "`entry_id` = '{$entry_id}'"
            );
            return true;
        } else {
            $message = __("Naming '%s' failed. File naming remains unchanged." , array($this->get('label')));
            return false;
        }
    }
}
