<?php
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
