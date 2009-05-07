<?php

	class extension_uniqueuploadfield extends Extension {

		public function about() {
			return array(
				'name'			=> 'Field: Unique File Upload',
				'version'		=> '1.0',
				'release-date'	=> '2009-05-06',
				'author'		=> array(
					'name'			=> 'Michael Eichelsdoerfer',
					'website'		=> 'http://www.michael-eichelsdoerfer.de',
					'email'			=> 'info@michael-eichelsdoerfer.de'
				),
				'description'	=> 'Upload files with unique names, using the UNIX timestamp.'
			);
		}

		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_uniqueupload`");
		}

		public function install() {
			return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_uniqueupload` (
				`id` int(11) unsigned NOT NULL auto_increment,
				`field_id` int(11) unsigned NOT NULL,
				`destination` varchar(255) NOT NULL,
				`validator` varchar(50),
				PRIMARY KEY (`id`),
				KEY `field_id` (`field_id`))"
			);
		}

	}
