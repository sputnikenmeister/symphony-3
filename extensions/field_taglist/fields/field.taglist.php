<?php

	Class fieldTagList extends Field {
		public function __construct(){
			parent::__construct();
			$this->_name = __('Tag List');

			$this->{'suggestion-source-threshold'} = 2;
			$this->{'tag-delimiter'} = ',';
		}

		public function requiresSQLGrouping() {
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (!is_array($data) or empty($data)) return;

			$list = Symphony::Parent()->Page->createElement($this->{'element-name'});

			if (!is_array($data['handle']) and !is_array($data['value'])) {
				$data = array(
					'handle'	=> array($data['handle']),
					'value'		=> array($data['value'])
				);
			}

			foreach ($data['value'] as $index => $value) {
				$list->appendChild(Symphony::Parent()->Page->createElement(
					'item', General::sanitize($value), array(
						'handle'	=> $data['handle'][$index]
					)
				));
			}

			$wrapper->appendChild($list);
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL){

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors);

			if(!is_null($this->{'suggestion-list-source'})) $this->prepopulateSource($wrapper);
		}

		public function displayPublishPanel(SymphonyDOMElement $wrapper, StdClass $data=NULL, $error=NULL, Entry $entry=NULL) {

			if(!isset($data->value)) {
				$data->value = NULL;
			}
			/*	TODO: Support Multiple
			$value = NULL;
			if(isset($data->value)){
				 $value = (is_array($data['value']) ? self::__tagArrayToString($data['value']) : $data['value']);

			}*/

			$label = Widget::Label($this->label);

			$label->appendChild(
				Widget::Input('fields['.$this->{'element-name'}.']', $data->value)
			);

			if (!is_null($error)) {
				$label = Widget::wrapFormElementWithError($label, $error['message']);
			}

			$wrapper->appendChild($label);

			if(!is_null($this->{'suggestion-list-source'})) $this->prepopulateSource($wrapper);
		}

		function prepopulateSource(&$wrapper) {

			$document = $wrapper->ownerDocument;

			$existing_tags = $this->findAllTags();

			if(is_array($existing_tags) && !empty($existing_tags)){
				$taglist = $document->createElement('ul');
				$taglist->setAttribute('class', 'tags');

				foreach($existing_tags as $tag) $taglist->appendChild($document->createElement('li', General::sanitize($tag)));

				$wrapper->appendChild($taglist);
			}

		}

		function findAllTags(){
			//	TODO: This will need to be updated once Section Editor can save multiple values
			//	foreach($this->{'suggestion-list-source'} as $item){
			list($section, $field_handle) = explode("::", $this->{'suggestion-list-source'});

			$values = array();

			$result = Symphony::Database()->query("
				SELECT
					`value`
				FROM
					`tbl_data_%s_%s`
				GROUP BY
					`value`
				HAVING
					COUNT(`value`) >= %d
				", array($section, $field_handle, $this->{'suggestion-source-threshold'})
			);

			if($result->valid()) $values = array_merge($values, $result->resultColumn('value'));

			return array_filter(array_unique($values), array($this, 'applyValidationRules'));
		}

		//	TODO: Make work with multiple tags!
		public function processFormData($data, Entry $entry=NULL){

			if(isset($entry->data()->{$this->{'element-name'}})){
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'value' => null,
					'handle' => null
				);
			}

			if(!is_null($data)){
				$result->value = $data;
				$result->handle = Lang::createHandle($data);
			}

			return $result;
		}

/*
		Deprecated

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::STATUS_OK;

			$data = preg_split('/\,\s/i', $data, -1, PREG_SPLIT_NO_EMPTY);
			$data = array_map('trim', $data);

			if(empty($data)) return;

			// Do a case insensitive removal of duplicates
			$data = General::array_remove_duplicates($data, true);

			sort($data);

			$result = array();
			foreach($data as $value){
				$result['value'][] = $value;
				$result['handle'][] = Lang::createHandle($value);
			}

			return $result;
		}

		function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->id;
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'pre-populate-source' => (is_null($this->{'pre-populate-source'})) ? NULL : implode(',', $this->{'pre-populate-source'}),
				'validator' => ($fields['validator'] == 'custom' ? NULL : $this->validator)
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}

*/
		public function findDefaultSettings(array &$fields){
			if(!isset($fields['suggestion-list-source'])) $fields['suggestion-list-source'] = array('existing');
		}

		static private function __tagArrayToString(array $tags){

			if(empty($tags)) return NULL;

			sort($tags);

			return implode($this->{'tag-delimiter'}, $tags);

		}

		public function prepareTableValue(StdClass $data, SymphonyDOMElement $link=NULL){
			$value = NULL;

			if(!is_null($data->value)){
				$value = (is_array($data->value) ? self::__tagArrayToString($data->value) : $data->value);
			}

			return parent::prepareTableValue((object)array('value' => General::sanitize($value)), $link);
		}

		public function displaySettingsPanel(SymphonyDOMElement &$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;

			$label = Widget::Label(__('Suggestion List'));

			$options = array(
				array('existing', ($this->{'suggestion-list-source'} == 'existing'), __('Existing Values')),
			);

			foreach (new SectionIterator as $section) {
				if(!is_array($section->fields) || $section->handle == $document->_context[1]) continue;

				$fields = array();

				foreach($section->fields as $field) {
					if($field->canPrePopulate()) {
						$fields[] = array(
							$section->handle . '::' .$field->{'element-name'},
							($this->{'suggestion-list-source'} == $section->handle . '::' .$field->{'element-name'}),
							$field->label
						);
					}
				}

				if(!empty($fields)) {
					$options[] = array(
						'label' => $section->name,
						'options' => $fields
					);
				}
			}

			$label->appendChild(Widget::Select('suggestion-list-source', $options, array('multiple' => 'multiple')));
			$wrapper->appendChild($label);

			$group = $document->createElement('div');
			$group->setAttribute('class', 'group');

			// Suggestion threshold
			$input = Widget::Input('suggestion-source-threshold',$this->{'suggestion-source-threshold'});
			$label = Widget::Label(__('Minimum Tag Suggestion Threshold'), $input);
			$group->appendChild($label);

			// Custom delimiter
			$input = Widget::Input('delimiter', $this->{'tag-delimiter'});
			$label = Widget::Label(__('Tag Delimiter'), $input);
			$group->appendChild($label);

			$wrapper->appendChild($group);

			// Validator
			$this->appendValidationSelect($wrapper, $this->validator, 'validator');

			$options_list = Symphony::Parent()->Page->createElement('ul');
			$options_list->setAttribute('class', 'options-list');
			$this->appendShowColumnCheckbox($options_list);
			$wrapper->appendChild($options_list);
		}

		public function applyValidationRules($data) {
			$rule = $this->{'validator'};

			return ($rule ? General::validateString($data, $rule) : true);
		}

		public function validateData(StdClass $data=NULL, MessageStack &$errors, Entry $entry) {
			// TODO: Support Multiple
			if ($this->{'required'} == 'yes' and strlen(trim($data->value)) == 0) {
				$errors->append(
					$this->{'element-name'},
					array(
					 	'message' => __("'%s' is a required field.", array($this->label)),
						'code' => self::ERROR_MISSING
					)
				);

				return self::STATUS_ERROR;
			}

			if (!isset($data->value)) return self::STATUS_OK;

			if (!$this->applyValidationRules($data->value)) {
				$errors->append(
					$this->{'element-name'},
					array(
					 	'message' => __("'%s' contains invalid data. Please check the contents.", array($this->label)),
						'code' => self::ERROR_INVALID
					)
				);

				return self::STATUS_ERROR;
			}

			return self::STATUS_OK;
		}

		public function saveData(StdClass $data=NULL, MessageStack &$errors, Entry $entry) {
			return parent::saveData($data, $errors, $entry);
		}

		public function createTable(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`handle` varchar(255) default NULL,
						`value` varchar(255) default NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `handle` (`handle`),
						KEY `value` (`value`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->id;

			if (self::isFilterRegex($data[0])) {
				self::$key++;
				$pattern = str_replace('regexp:', '', $this->escape($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{self::$key}.value REGEXP '{$pattern}'
						OR t{$field_id}_{self::$key}.handle REGEXP '{$pattern}'
					)
				";

			} elseif ($andOperation) {
				foreach ($data as $value) {
					self::$key++;
					$value = $this->escape($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
							ON (e.id = t{$field_id}_{self::$key}.entry_id)
					";
					$where .= "
						AND (
							t{$field_id}_{self::$key}.value = '{$value}'
							OR t{$field_id}_{self::$key}.handle = '{$value}'
						)
					";
				}

			} else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->escape($value);
				}

				self::$key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{self::$key}.value IN ('{$data}')
						OR t{$field_id}_{self::$key}.handle IN ('{$data}')
					)
				";
			}

			return true;
		}
	}

	return 'fieldTagList';