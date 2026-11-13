<?php
if (!function_exists('cms_input_panel_name')){

	/**
	 * Input panel for a definition field type: 'module/panel' as is, a built-in type as 'cms/cms_input_<type>'
	 * when its template exists, '' otherwise. Aliases: color → colour, panels → cms_page_panels.
	 */
	function cms_input_panel_name($type){

		$type = (string)$type;

		if ($type === ''){
			return '';
		}

		if (stristr($type, '/')){
			return $type;
		}

		$type = cms_input_type_alias($type);
		$type = str_replace('cms_', '', $type);

		if (file_exists($GLOBALS['config']['base_path'].'modules/cms/templates/cms_input_'.$type.'.tpl.php')){
			return 'cms/cms_input_'.$type;
		}

		return '';

	}

	/**
	 * One name per built-in type: color → colour, panels → cms_page_panels.
	 */
	function cms_input_type_alias($type){

		if ($type === 'color'){
			return 'colour';
		}

		if ($type === 'panels'){
			return 'cms_page_panels';
		}

		return $type;

	}

	/**
	 * Rendering flags an input panel declares in its definition under "input" (cms_input.md):
	 * container: '' (.cms_input_container) or 'none' (no wrapper; the input is laid out as it is);
	 * value_raw: '1' when the value is passed as it is, without " → &quot;.
	 */
	function cms_input_flags($panel_name){

		if ($panel_name === '' || !stristr($panel_name, '/')){
			return ['container' => '', 'value_raw' => ''];
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_panel_model');
		$config = $CI->cms_panel_model->get_cms_panel_config($panel_name);
		$input = is_array($config['input'] ?? null) ? $config['input'] : [];

		return [
				'container' => ($input['container'] ?? '') === 'none' ? $input['container'] : '',
				'value_raw' => !empty($input['value_raw']) ? '1' : '',
		];

	}

	function cms_repeater_panel_init_hook($panel_name){

		if ($panel_name === '' || !stristr($panel_name, '/')){
			return '';
		}

		list($module, $panel) = explode('/', $panel_name, 2);
		$panel = str_replace('-', '_', $panel);

		return $panel.'_init';

	}

	function cms_repeater_preload_panel_js($panel_name){

		if ($panel_name === '' || !stristr($panel_name, '/')){
			return;
		}

		if (empty($GLOBALS['_panel_js']) || !is_array($GLOBALS['_panel_js'])){
			$GLOBALS['_panel_js'] = [];
		}

		list($module, $panel) = explode('/', $panel_name, 2);
		$panel = str_replace('-', '_', $panel);

		$js_path = 'modules/'.$module.'/js/'.$panel.'.js';
		$full = $GLOBALS['config']['base_path'].$js_path;

		if (file_exists($full) && !in_array($js_path, $GLOBALS['_panel_js'], true)){
			$GLOBALS['_panel_js'][] = $js_path;
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_panel_model');
		$config = $CI->cms_panel_model->get_cms_panel_config($panel_name);

		if (!empty($config['js']) && is_array($config['js'])){
			foreach ($config['js'] as $js){
				if (!is_string($js) || !stristr($js, '/')){
					continue;
				}
				list($js_module, $js_file) = explode('/', $js, 2);
				$def_path = 'modules/'.$js_module.'/js/'.$js_file.'.js';
				$def_full = $GLOBALS['config']['base_path'].$def_path;
				if (file_exists($def_full) && !in_array($def_path, $GLOBALS['_panel_js'], true)){
					$GLOBALS['_panel_js'][] = $def_path;
				}
			}
		}

	}

	/**
	 * Input panel of a field inside a repeater, for its JS and init hook; a nested repeater has none.
	 */
	function cms_repeater_field_panel_name($field){

		if (!is_array($field) || ($field['type'] ?? '') === 'repeater'){
			return '';
		}

		return cms_input_panel_name($field['type'] ?? '');

	}

	function cms_repeater_field_init_hooks($fields){

		$hooks = [];

		if (!is_array($fields)){
			return $hooks;
		}

		foreach ($fields as $field){

			if (!is_array($field)){
				continue;
			}

			$type = $field['type'] ?? '';

			if ($type === 'grid' && !empty($field['fields'])){
				$hooks = array_merge($hooks, cms_repeater_field_init_hooks($field['fields']));
			}

			$panel_name = cms_repeater_field_panel_name($field);

			if ($panel_name === ''){
				continue;
			}

			$hook = cms_repeater_panel_init_hook($panel_name);

			if ($hook !== ''){
				$hooks[] = $hook;
			}

		}

		return array_values(array_unique($hooks));

	}

	function cms_repeater_preload_field_js($fields){

		if (!is_array($fields)){
			return;
		}

		foreach ($fields as $field){

			if (!is_array($field)){
				continue;
			}

			$type = $field['type'] ?? '';

			if ($type === 'grid' && !empty($field['fields'])){
				cms_repeater_preload_field_js($field['fields']);
			}

			$panel_name = cms_repeater_field_panel_name($field);

			if ($panel_name !== ''){
				cms_repeater_preload_panel_js($panel_name);
			}

		}

	}

	/**
	 * A repeater field from a panel definition: its "item" or "settings" section, by name. [] when there is none.
	 * The panel name is checked first, because it becomes a definition file path.
	 */
	function cms_repeater_definition($panel_name, $section, $name){

		$panel_name = (string)$panel_name;
		$name = (string)$name;

		if (!preg_match('#^[a-z0-9_]+/[a-z0-9_]+$#', $panel_name) || !in_array($section, ['item', 'settings'], true)
				|| !preg_match('#^[a-z][a-z0-9_]*$#', $name)){
			return [];
		}

		$CI =& get_instance();
		$CI->load->model('cms/cms_panel_model');
		$config = $CI->cms_panel_model->get_cms_panel_config($panel_name);

		foreach (($config[$section] ?? []) as $field){
			if (is_array($field) && ($field['type'] ?? '') === 'repeater' && ($field['name'] ?? '') === $name){
				return $field;
			}
		}

		return [];

	}

	/**
	 * A repeater's row fields as a row prints them: the block id in each field (base_id), and the fields of a
	 * groups tab right after the groups input, in their own order (the row is laid out in field order).
	 */
	function cms_repeater_row_fields($fields, $base_id){

		if (!is_array($fields)){
			return [];
		}

		$tabs_found = false;
		$all = [];
		$tab_fields = [];
		$other = [];

		foreach ($fields as $field){
			if (!is_array($field)){
				continue;
			}
			$field['base_id'] = $base_id;
			$all[] = $field;
			if (($field['type'] ?? '') === 'groups'){
				$tabs_found = true;
			}
			if (($field['type'] ?? '') !== 'groups' && !empty($field['groups'])){
				$tab_fields[] = $field;
			} else {
				$other[] = $field;
			}
		}

		if (!$tabs_found || !$tab_fields){
			return $all;
		}

		$return = [];
		foreach ($other as $field){
			$return[] = $field;
			if (($field['type'] ?? '') === 'groups'){
				$return = array_merge($return, $tab_fields);
				$tab_fields = [];
			}
		}

		return $return;

	}

}

if (!function_exists('print_fields')){

	/**
	 * Admin form fields of a definition structure. Every field is rendered the same way: the shared params
	 * below, then the input dispatcher cms/cms_input, which picks the input panel. Type-specific params
	 * are made by each input panel (cms_input.md § How an input is rendered).
	 * $prefix and $key are the repeater name and row when the fields are a repeater row.
	 */
	function print_fields($structure, $data = array(), $prefix = '', $key = ''){

		if (empty($data)){
			$data = [];
		}

		$return = '';

		foreach($structure as $field){

			if (empty($field['name'])){
				$field['name'] = '_noname';
			}

			// Use isset/array_key_exists — do not use empty(): select "0" / "No" is a valid value
			$field_empty = !array_key_exists($field['name'], $data);
			$field_data = $field_empty ? '' : $data[$field['name']];

			if (!empty($field['default']) && substr($field['default'],0,6) == ':date:'){
				$defparams = explode(':',$field['default']);
				if (empty($defparams[3])){
					$field['default'] = date(substr($field['default'], 6));
				} else {
					$field['default'] = date($defparams[2], time() + (int)$defparams[3]);
				}
			}

			if (!empty($field['default']) && substr($field['default'],0,5) == ':rnd:'){
				$length = date(substr($field['default'], 5));
				$chars = '0123456789abcdefghijklmnopqrstuvwxyz';
				$field['default'] = '';
				while (strlen($field['default']) < $length){
					$pos = mt_rand(0, strlen($chars) - 1);
					$field['default'] .= $chars[$pos];
				}
			}

			$field['_return'] = true;
			$field['value'] = ($field_empty && isset($field['default'])) ? $field['default'] : $field_data;
			$field['field_name'] = $field['name'];
			$field['prefix'] = $prefix;
			$field['key'] = $key;
			$field['name_clean'] = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
			$field['name'] = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
			$field['panel_structure'] = $structure;
			if (empty($field['base_id'])){
				$field['base_id'] = (!empty($data['cms_page_panel_id']) ? $data['cms_page_panel_id'] : '');
			}
			if (!empty($data['panel_name'])){
				$field['base_name'] = $data['panel_name'];
			}

			$return .= cms_input($field);

		}

		return $return;

	}

}

if (!function_exists('cms_input')){

	/**
	 * One admin input: the params every input shares, the input panel for the type, and the wrapper by the
	 * input's "input" flags (cms_input.md § How an input is rendered). Prints it, or returns it with _return.
	 */
	function cms_input($params){

		if (empty($_SESSION['cms_user']['cms_user_id'])){
			return '';
		}

		$print = empty($params['_return']);
		$type = (string)($params['type'] ?? '');
		$panel_name = cms_input_panel_name($type);

		if ($panel_name === ''){
			ob_start();
			_html_error('Undefined cms input: "'.$type.'"');
			$html = ob_get_clean();
		} else {

			$flags = cms_input_flags($panel_name);
			$params['panel_name'] = $panel_name;
			$params['input_container'] = $flags['container'];

			// Values go into html attributes as they are stored; " is the one character that would end one.
			// Module inputs and inputs that declare value_raw get the value as it is.
			if (empty($flags['value_raw'])){
				if (!stristr($type, '/') && isset($params['value']) && is_string($params['value'])){
					$params['value'] = str_replace('"', '&quot;', $params['value']);
				}
				if (isset($params['default']) && !is_array($params['default'])){
					$params['default'] = str_replace('"', '&quot;', $params['default']);
				}
			}

			$params['mandatory_class'] = !empty($params['mandatory']) ? ' cms_input_mandatory ' : '';
			$params['label'] = (array_key_exists('label', $params) ? (string)$params['label'] : '[no label]')
					.(!empty($params['mandatory']) ? ' *' : '');
			$params['help'] = !empty($params['help']) ? $params['help'] : '';
			$params['readonly'] = $params['readonly'] ?? '0';
			$groups = $params['groups'] ?? [];
			$params['groups'] = empty($groups) ? [] : (is_array($groups) ? $groups : [$groups]);

			// The input type comes from a definition, never from the request, and panel "access" keys are visitor
			// keys, which an admin form must not be judged by: the input panel gets the access pass.
			$params['_access_ok'] = 1;
			$params['_return'] = true;

			add_css('cms/cms_input.scss');
			$html = _panel($panel_name, $params);

			if ($flags['container'] !== 'none'){
				$html = '<div class="cms_input_container'.($params['groups'] ? ' cms_input_container_groups' : '').'"'
						.($params['groups'] ? ' data-groups="'.implode(',', $params['groups']).'"' : '').'>'.$html.'</div>';
			}

		}

		if ($print){
			print($html);
			return '';
		}

		return $html;

	}

	/**
	 * A field's help icon, with its text on hover (cms_help.js). In the text, | is a line break, {…} italic
	 * and […] bold.
	 */
	function cms_help($help){

		if (!is_string($help) || $help === ''){
			return;
		}

		add_css('cms/cms_help.scss');
		add_js('modules/cms/js/cms_help.js');

		print('<div class="cms_help"><div class="cms_help_icon" ');
		_ib('cms/cms_help.png', 12);
		print('></div><div class="cms_help_text">'
				.str_replace(['|', '{', '}', '[', ']'], ['<br>', '<i>', '</i>', '<b>', '</b>'], $help).'</div></div>');

	}

	/**
	 * The head an input prints inside its .cms_input root: the label (for $for when given), the help icon, and
	 * for a field that translates (the template passes its data-field_type) the translate icon (cms_translate.js).
	 */
	function cms_input_label($label, $help = '', $for = '', $translate_type = '', $icon_size = 14){

		print('<label'.($for !== '' ? ' for="'.$for.'"' : '').'>'.$label.'</label>');

		cms_help($help);

		if ($translate_type !== '' && !empty($GLOBALS['language'])){
			print('<button type="button" class="cms_translate_icon" data-field_type="'.$translate_type.'" ');
			_ib('cms/cms_translate.png', $icon_size);
			print('></button>');
		}

	}

}
