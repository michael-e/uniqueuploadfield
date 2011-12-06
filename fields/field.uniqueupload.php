<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.upload.php');

	class FieldUniqueUpload extends FieldUpload {
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = __('Unique File Upload');
		}

		private static function getUniqueFilename($filename) {
			## since uniqid() is 13 bytes, the unique filename will be limited to ($crop+1+13) characters;
			$crop  = '30';
			return preg_replace("/([^\/]*)(\.[^\.]+)$/e", "substr('$1', 0, $crop).'-'.uniqid().'$2'", $filename);
		}

		private static function getOriginalFilename($filename) {
			var_dump($filename);
			return preg_replace("/([^\/]*)(\-[a-f0-9]{13})(\.[^\.]+)$/", '$1$3', $filename);
		}

		public function checkPostFieldData($data, &$message, $entry_id = NULL) {
			if (is_array($data) and isset($data['name'])) $data['name'] = self::getUniqueFilename($data['name']);
			return parent::checkPostFieldData($data, $message, $entry_id);
		}

		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = NULL) {
			if (is_array($data) and isset($data['name'])) $data['name'] = self::getUniqueFilename($data['name']);
			return parent::processRawFieldData($data, $status, $simulate, $entry_id);
		}

		public function appendFormattedElement(&$wrapper, $data){
			parent::appendFormattedElement($wrapper, $data);
			$field = $wrapper->getChildrenByName($this->get('element_name'));
			if(!empty($field))
				end($field)->appendChild(new XMLElement('original-filename', General::sanitize(self::getOriginalFilename(basename($data['file'])))));
		}
	}
