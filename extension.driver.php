<?php

class extension_uniqueuploadfield extends Extension {

    /****************************
     * Class methods
     ****************************/

    protected static $fields = array();

    public static function registerField($field) {
        self::$fields[] = $field;
    }

    /****************************
     * Instance methods
     ****************************/
    public function about() {
        return array(
            'name' => 'Field: Unique File Upload' ,
            'version' => '1.4.2' ,
            'release-date' => '2011-08-19' ,
            'author' => array(
                'name' => 'Michael Eichelsdoerfer' ,
                'website' => 'http://www.michael-eichelsdoerfer.de' ,
                'email' => 'info@michael-eichelsdoerfer.de'
            ) ,
            'description' => 'Upload files with unique names, using a unique ID or reflected entry fields'
        );
    }

    public function update($previousVersion) {
        $symphony_version = Symphony::Configuration()->get('version' , 'symphony');
        if (version_compare($symphony_version , '2.0.8RC3' , '>=') && version_compare($previousVersion , '1.1' , '<')) {
            $uniqueupload_entry_tables =
                    Symphony::Database()->fetchCol("field_id" , "SELECT `field_id` FROM `tbl_fields_uniqueupload`");
            if (is_array($uniqueupload_entry_tables) && !empty($uniqueupload_entry_tables)) {
                foreach ($uniqueupload_entry_tables as $field)
                {
                    Symphony::Database()->query(sprintf(
                                                    "ALTER TABLE `tbl_entries_data_%d` CHANGE `size` `size` INT(11) UNSIGNED NULL DEFAULT NULL" ,
                                                    $field
                                                ));
                }
            }
        }
        /*
         * Update field table to add expression.
        if(version_compare($previousVersion, '1.4.2','<=')){
            Symphony::Database()->query("ALTER TABLE  `thl_fields_uniqueupload` ADD  `expression` VARCHAR( 255 ) NOT NULL");
        }
        */
    }

    public function uninstall() {
        Symphony::Database()->query("DROP TABLE `tbl_fields_uniqueupload`");
    }

    public function install() {
        return Symphony::Database()->query(
            "CREATE TABLE `tbl_fields_uniqueupload` (
				 `id` int(11) unsigned NOT NULL auto_increment,
				 `field_id` int(11) unsigned NOT NULL,
				 `destination` varchar(255) NOT NULL,
				 `validator` varchar(50),
				 `expression` VARCHAR(255) DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
        );
    }


    /*
    * Added functionality for the "reflected naming" functionality
    */

    public function getSubscribedDelegates() {
        return array(
            array(
                'page' => '/publish/new/' ,
                'delegate' => 'EntryPostCreate' ,
                'callback' => 'compileBackendFields'
            ) ,

            array(
                'page' => '/publish/edit/' ,
                'delegate' => 'EntryPostEdit' ,
                'callback' => 'compileBackendFields'
            )
        );
    }

    public function compileBackendFields($context) {
        foreach (self::$fields as $field) {
            if (!$field->compile($context['entry'])) {
                //TODO:Error
            }
        }
    }

    /**
     * @param  $entry
     * @return DOMXPath
     * Gets XPATH Dom for entry.
     * Function by Rowan Lewis <me@rowanlewis.com>
     */
    public function getXPath($entry) {
        $entry_xml = new XMLElement('entry');

        $section_id = $entry->get('section_id');
        $data = $entry->getData();
        $fields = array();
        $entry_xml->setAttribute('id' , $entry->get('id'));

        $associated = $entry->fetchAllAssociatedEntryCounts();

        if (is_array($associated) and !empty($associated)) {
            foreach ($associated as $section => $count) {
                $handle = $this->_Parent->Database->fetchVar('handle' , 0 , "
                       SELECT
                           s.handle
                       FROM
                           `tbl_sections` AS s
                       WHERE
                           s.id = '{$section}'
                       LIMIT 1
                   ");

                $entry_xml->setAttribute($handle , (string)$count);
            }
        }

        // Add fields:
        foreach ($data as $field_id => $values) {
            if (empty($field_id)) continue;
            $fm = new FieldManager($entry);
            $field =& $fm->fetch($field_id);
            $field->appendFormattedElement($entry_xml , $values , false , null);
        }

        $xml = new XMLElement('data');
        $xml->appendChild($entry_xml);
        $dom = new DOMDocument();
        $dom->strictErrorChecking = false;
        $dom->loadXML($xml->generate(true));

        $xpath = new DOMXPath($dom);

        if (version_compare(phpversion() , '5.3' , '>=')) {
            $xpath->registerPhpFunctions();
        }

        return $xpath;
    }




}
