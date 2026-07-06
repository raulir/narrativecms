<?php
if (!function_exists('cms_repeater_resolve_field_panel_name')){

	function cms_repeater_resolve_field_panel_name($field){

		if (!is_array($field)){
			return '';
		}

		$type = $field['type'] ?? '';

		if ($type === '' || $type === 'repeater'){
			return '';
		}

		if ($type === 'select'){
			return 'cms/cms_input_select';
		}

		if ($type === 'grid'){
			return 'cms/cms_input_grid';
		}

		if ($type === 'repeater_select'){
			return 'cms/cms_input_repeater_select';
		}

		if ($type === 'cms_page_panels' || $type === 'panels'){
			return 'cms/cms_input_page_panels';
		}

		if (stristr($type, '/')){
			return $type;
		}

		$normalized = $type;

		if ($normalized === 'color'){
			$normalized = 'colour';
		}

		$normalized = str_replace('cms_', '', $normalized);
		$tpl = $GLOBALS['config']['base_path'].'modules/cms/templates/cms_input_'.$normalized.'.tpl.php';

		if (file_exists($tpl)){
			return 'cms/cms_input_'.$normalized;
		}

		return '';

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

			$panel_name = cms_repeater_resolve_field_panel_name($field);

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

			$panel_name = cms_repeater_resolve_field_panel_name($field);

			if ($panel_name !== ''){
				cms_repeater_preload_panel_js($panel_name);
			}

		}

	}

}

if (!function_exists('print_fields')){

	function print_fields($structure, $data = array(), $prefix = '', $key = ''){
		
		if (empty($data)){
			$data = [];
		}

		$block_id = !empty($data['block_id']) ? $data['block_id'] : 0;

		$return = '';
						
		foreach($structure as $field){
			
			if (empty($field['name'])){
				$field['name'] = '_noname';
			}
			
			$field_empty = !isset($data[$field['name']]);
			$field_data = !empty($data[$field['name']]) ? $data[$field['name']] : '';
			
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
			
			if ($field['type'] == 'repeater' && $prefix == ''){ // only one sublevel
				
				foreach($field['fields'] as $kf => $vf){
					$field['fields'][$kf]['base_id'] = (!empty($data['cms_page_panel_id']) ? $data['cms_page_panel_id'] : '');
				}

				cms_repeater_preload_field_js($field['fields']);

				$return .= _panel('cms/cms_input_repeater', [
						'label' => $field['label'],
						'name' => $field['name'],
						'fields' => $field['fields'],
						'data' => !empty($data[$field['name']]) ? $data[$field['name']] : [],
						'height' => !empty($field['height']) ? $field['height'] : 0,
						'readonly' => $field['readonly'] ?? 0,
						'_return' => true,
				]); 
			
			} elseif ($field['type'] == 'cms_page_panels' || $field['type'] == 'panels'){
				
				$return .= _panel('cms/cms_input_page_panels', array(
						'label' => $field['label'], 
						'value' => ($field_empty ? array() : $field_data ), 
						'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
						'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']),
						'panels' => !empty($field['panels']) ? $field['panels'] : '',
						'block_id' => $block_id,
						'_return' => true, 
						'help' => !empty($field['help']) ? $field['help'] : '',
						'size' => !empty($field['size']) ? $field['size'] : '4',
				));
				
			} elseif ($field['type'] == 'select'){
				
				$name = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
				$name_clean = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
				$mandatory_class = !empty($field['mandatory']) ? ' cms_input_mandatory ' : '';
				$label = $field['label'].(!empty($field['mandatory']) ? ' *' : '');
				$return .= _panel('cms/cms_input_select', array(
						'label' => $label,
						'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
						'values' => $field['values'],
						'name' => $name, 
						'name_clean' => !empty($name_clean) ? $name_clean : $name, 
						'_return' => true, 
						'mandatory_class' => $mandatory_class,
						'help' => !empty($field['help']) ? $field['help'] : '', 
						'params' => $field,
						'readonly' => $field['readonly'] ?? '0',
				));
				
			} elseif ($field['type'] == 'grid' && $prefix == ''){

				$return .= '<div class="cms_input_container cms_input_container_full">';
				$return .= _panel('cms/cms_input_grid', [
						'label' => $field['label'],
						'name' => $field['name'],
						'ds' => $field['ds'],
						'operations' => $field['operations'] ?? 'SL',
						'fields' => $field['fields'] ?? [],
						'base_id' => !empty($data['cms_page_panel_id']) ? $data['cms_page_panel_id'] : '',
						'base_name' => !empty($data['panel_name']) ? $data['panel_name'] : (!empty($field['base_name']) ? $field['base_name'] : ''),
						'_return' => true,
				]);
				$return .= '</div>';

			} elseif ($field['type'] == 'repeater_select'){
				
				$repeater_select_data = [
						'selected' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ),
						'target' => $field['target'],
						'field' => !empty($field['field']) ? $field['field'] : 'heading',
						'add_empty' => !empty($field['add_empty']) ? '1' : '0',
						'labels' => !empty($field['labels']) ? $field['labels'] : '',
				];

				$name = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
				$name_clean = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
				
				$return .= _panel('cms/cms_input_repeater_select', [
						'select_params' => [
								'label' => $field['label'], // .$mandatory_label, 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
								'values' => [],
								'name' => $name, 
								'name_clean' => !empty($name_clean) ? $name_clean : $name, 
								'extra_class' => ' cms_input_repeater_select',
								'mandatory_class' => '', // $mandatory_class,
								'help' => !empty($field['help']) ? $field['help'] : '',
								'extra_data' => $repeater_select_data,
						],
						'_return' => true,
				]);
				
			} else {
				
				$field['_return'] = true;
				$field_value = ($field_empty && isset($field['default']) ? $field['default'] : $field_data);
				if (empty($field['type']) || !stristr($field['type'], '/')){
					$field_value = is_string($field_value) ? str_replace('"', '&quot;', $field_value) : $field_value;
				}
				$field['value'] = $field_value;
				$field['name_clean'] = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
				$field['name'] = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
				$field['panel_structure'] = $structure;
				if (isset($field['default']) && !is_array($field['default'])){
					$field['default'] = str_replace('"', '&quot;', $field['default']);
				}
				if (empty($field['base_id'])){
					$field['base_id'] = (!empty($data['cms_page_panel_id']) ? $data['cms_page_panel_id'] : '');
				}
				
				// add field
				$return .= _panel('cms/cms_input', $field);

			}

		}
		
		return $return;
		
	}

}
