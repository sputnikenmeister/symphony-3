<?php

	Class fieldUser extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = __('User');
		}

		public function canToggle(){
			return ($this->properties()->{'allow-multiple-selection'} == 'yes' ? false : true);
		}

		public function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggle();
		}

		public function getToggleStates(){

		    $users = UserManager::fetch();

			$states = array();
			foreach($users as $u){
				$states[$u->id] = $u->getFullName();
			}

			return $states;
		}

		public function toggleFieldData($data, $newState){
			$data['user_id'] = $newState;
			return $data;
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::STATUS_OK;

			if(!is_array($data) && !is_null($data)) return array('user_id' => $data);

			if(empty($data)) return NULL;

			$result = array();
			foreach($data as $id) $result['user_id'][] = $id;

			return $result;
		}

		public function displayPublishPanel(SymphonyDOMElement $wrapper, $data=NULL, $flagWithError=NULL, $entry_id=NULL){

			$value = (isset($data['user_id']) ? $data['user_id'] : NULL);

			$callback = Administration::instance()->getPageCallback();

			if ($this->properties()->{'default-to-current-user'} == 'yes' && empty($data) && empty($_POST)) {
				$value = array(Administration::instance()->User->id);
			}

			if (!is_array($value)) {
				$value = array($value);
			}

		    $users = UserManager::fetch();

			$options = array();

			foreach($users as $u){
				$options[] = array($u->id, in_array($u->id, $value), $u->getFullName());
			}

			$fieldname = 'fields['.$this->properties()->{'element-name'}.']';
			if($this->properties()->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$attr = array();

			if($this->properties()->{'allow-multiple-selection'} == 'yes') $attr['multiple'] = 'multiple';

			$label = Widget::Label($this->properties()->label);
			$label->appendChild(Widget::Select($fieldname, $options, $attr));
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		public function prepareTableValue(StdClass $data, SymphonyDOMElement $link=NULL){

			if(!is_array($data->{'user_id'})) $data->{'user_id'} = array($data->{'user_id'});

			if(empty($data->{'user_id'})) return __('None');

			$value = array();

			$fragment = Symphony::Parent()->Page->createDocumentFragment();

			foreach($data->{'user_id'} as $user_id){

				if(is_null($user_id)) continue;

				$user = new User($user_id);

				if($user instanceof User){

					if($fragment->hasChildNodes()) $fragment->appendChild(new DOMText(', '));

					if(is_null($link)){
						$fragment->appendChild(
							Widget::Anchor(
								General::sanitize($user->getFullName()),
								ADMIN_URL . '/system/users/edit/' . $user->get('id') . '/'
							)
						);
					}

					else{
						$link->setValue($user->getFullName());
						$fragment->appendChild($link);
					}
				}
			}

			if(!$fragment->hasChildNodes()) {
				return __('None');
			}
			else{
				return $fragment;
			}
		}

		public function isSortable(){
			return ($this->properties()->{'allow-multiple-selection'} == 'yes' ? false : true);
		}

		public function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "LEFT OUTER JOIN `tbl_entries_data_".$this->properties()->id."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (in_array(strtolower($order), array('random', 'rand')) ? 'RAND()' : "`ed`.`user_id` $order");
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->properties()->id;

			if (self::isFilterRegex($data[0])) {
				self::$key++;
				$pattern = str_replace('regexp:', '', $this->escape($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND t{$field_id}_{self::$key}.user_id REGEXP '{$pattern}'
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
						AND t{$field_id}_{self::$key}.user_id = '{$value}'
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
					AND t{$field_id}_{self::$key}.user_id IN ('{$data}')
				";
			}

			return true;
		}

		public function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->properties()->id;
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'allow-multiple-selection' => ($this->properties()->{'allow-multiple-selection'} ? $this->properties()->{'allow-multiple-selection'} : 'no'),
				'default-to-current-user' => ($this->properties()->{'default-to-current-user'} ? $this->properties()->{'default-to-current-user'} : 'no')
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}

		public function appendFormattedElement(&$wrapper, $data, $encode=false){
	        if(!is_array($data['user_id'])) $data['user_id'] = array($data['user_id']);

	        $list = Symphony::Parent()->Page->createElement($this->properties()->{'element-name'});
	        foreach($data['user_id'] as $user_id){
	            $user = new User($user_id);
	            $list->appendChild(
					Symphony::Parent()->Page->createElement('item', $user->getFullName(), array(
						'id' => $user->id,
						'username' => $user->username
					))
				);
	        }
	        $wrapper->appendChild($list);
	    }

		public function findDefaults(&$fields){
			if(!isset($fields['allow-multiple-selection'])) $fields['allow-multiple-selection'] = 'no';
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$options_list = Symphony::Parent()->Page->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			## Allow multiple selection
			$label = Widget::Label(__('Allow selection of multiple users'));
			$input = Widget::Input('allow-multiple-selection', 'yes', 'checkbox');
			if($this->properties()->{'allow-multiple-selection'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$options_list->appendChild($label);

			## Default to current logged in user
			$label = Widget::Label(__('Select current user by default'));
			$input = Widget::Input('default-to-current-user', 'yes', 'checkbox');
			if($this->properties()->{'default-to-current-user'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$options_list->appendChild($label);

			$this->appendShowColumnCheckbox($options_list);
			$wrapper->appendChild($options_list);

		}

		public function createTable(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`user_id` int(11) unsigned NOT NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `user_id` (`user_id`)
					)',
					$this->properties()->section,
					$this->properties()->{'element-name'}
				)
			);
		}

		public function getExampleFormMarkup(){

		    $users = UserManager::fetch();

			$options = array();

			foreach($users as $u){
				$options[] = array($u->id, NULL, $u->getFullName());
			}

			$fieldname = 'fields['.$this->properties()->{'element-name'}.']';
			if($this->properties()->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$attr = array();

			if($this->properties()->{'allow-multiple-selection'} == 'yes') $attr['multiple'] = 'multiple';

			$label = Widget::Label($this->properties()->label);
			$label->appendChild(Widget::Select($fieldname, $options, $attr));

			return $label;
		}


	}

	return 'fieldUser';