<?php
if (!function_exists('print_fields')){

	function print_fields($structure, $data = array(), $prefix = '', $key = ''){

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
			
			// :meta:
			if (!empty($field['default']) && substr($field['default'],0,6) == ':meta:'){
				list($a, $b, $meta_src, $meta_field) = explode(':', $field['default']);
				$meta_class = ' cms_meta ';
				$meta_data = ' data-meta_src="'.$meta_src.'" data-meta_field="'.$meta_field.'" ';
				$field['default'] = '';
			} else {
				$meta_class = '';
				$meta_data = '';
			}
			
			if (!empty($field['max_chars'])){
				$max_chars_class = ' admin_max_chars ';
				$max_chars_data = ' data-max_chars="'.$field['max_chars'].'" ';
			} else {
				$max_chars_class = '';
				$max_chars_data = '';
			}
			
			if (!empty($field['mandatory'])){
				$mandatory_class = ' cms_input_mandatory ';
				$mandatory_label = ' *';
			} else {
				$mandatory_class = '';
				$mandatory_label = '';
			}
			
			if ($field['type'] == 'repeater' && $prefix == ''){ // only one sublevel

				$return .= _panel('cms/cms_input_repeater', [
						'label' => $field['label'],
						'name' => $field['name'],
						'fields' => $field['fields'],
						'data' => !empty($data[$field['name']]) ? $data[$field['name']] : [],
						'height' => !empty($field['height']) ? $field['height'] : 0,
						'_return' => true,
				]); 
			
			} elseif ($field['type'] == 'text'){
				
				$return .= _panel('cms/cms_input_text', [
						'label' => $field['label'].$mandatory_label,
						'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ),
						'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'),
						'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']),
						'max_chars_class' => $max_chars_class,
						'meta_class' => $meta_class,
						'mandatory_class' => $mandatory_class,
						'extra_data' => $max_chars_data.' '.$meta_data.' ',
						'_return' => true,
						'help' => !empty($field['help']) ? $field['help'] : '',
						'translate' => !empty($field['translate']) ? 1 : 0,
						'params' => $field,
				]);
										
			} elseif ($field['type'] == 'textarea'){
				
				$return .= _panel('cms/cms_input_textarea', array(
						'label' => $field['label'].$mandatory_label,
						'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ),
						'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'),
						'lines' => (!empty($field['lines']) ? $field['lines'] : '3' ),
						'width' => !empty($field['width']) ? $field['width'] : 'narrow',
						'extra_data' => $max_chars_data.' '.$meta_data.' '
								.' data-html="'.(!empty($field['html']) ? $field['html'] : '').'" '
								.' data-html_class="'.(!empty($field['html_class']) ? $field['html_class'] : '').'" '
								.' data-html_css="'.(!empty($field['html_css']) ? $field['html_css'] : '').'" '
								.(!empty($field['styles']) ? ' data-styles="'.str_replace('"','~',json_encode($field['styles'])).'"' : ''),
						'max_chars_class' => $max_chars_class,
						'meta_class' => $meta_class,
						'mandatory_class' => $mandatory_class,
						'tinymce' => !empty($field['html']),
						'_return' => true,
						'help' => !empty($field['help']) ? $field['help'] : '',
						'translate' => !empty($field['translate']) ? 1 : 0,
						'params' => $field,
				));

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
				));
				
			} elseif ($field['type'] == 'select'){
				
				$name = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
				$name_clean = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
				
				$return .= _panel('cms/cms_input_select', array(
						'label' => $field['label'].$mandatory_label, 
						'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
						'values' => $field['values'],
						'name' => $name, 
						'name_clean' => !empty($name_clean) ? $name_clean : $name, 
						'_return' => true, 
						'mandatory_class' => $mandatory_class,
						'help' => !empty($field['help']) ? $field['help'] : '', 
						'params' => $field,
				));
				
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
								'label' => $field['label'].$mandatory_label, 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
								'values' => [],
								'name' => $name, 
								'name_clean' => !empty($name_clean) ? $name_clean : $name, 
								'extra_class' => ' cms_input_repeater_select',
								'mandatory_class' => $mandatory_class,
								'help' => !empty($field['help']) ? $field['help'] : '',
								'extra_data' => $repeater_select_data,
						],
						'_return' => true,
				]);
				
			} else {
				
				$field['_return'] = true;
				$field['value'] = ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) );
				$field['name_clean'] = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
				$field['name'] = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
				$field['panel_structure'] = $structure;
				
				// add field
				$return .= _panel('cms/cms_input', $field);

			}

		}
		
		return $return;
		
	}

}
