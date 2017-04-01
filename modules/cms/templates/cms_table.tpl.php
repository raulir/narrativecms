<div class="cms_toolbar">
	<div class="admin_tool_text">
		<?php print(str_limit($table_title, 40)); ?>
	</div>
	<a class="cms_table_save admin_tool_button admin_right">Save</a>
</div>

<div>

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" name="do" value="cms_table_save">
		<input type="hidden" name="table" value="<?php print($table); ?>">

		<?php 

			function print_fields($structure, $data = array(), $fk_data = array(), $prefix = '', $key = ''){

				$return = '';
								
				foreach($structure as $field){
					
					$field_empty = !isset($data[$field['name']]);
					$field_data = !empty($data[$field['name']]) ? $data[$field['name']] : '';
					
					if ($field['type'] == 'repeater' && $prefix == ''){ // only one sublevel
						
						// print repeater header
						$return .= '<div style="clear: both; "></div>' .
								'<div class="admin_block admin_repeater_container ui-sortable">'; /* data-label="'.$field['label'].'">'; */
						
						// print fields for existing data
						if (!empty($data[$field['name']])){
							foreach($data[$field['name']] as $repeater_key => $repeater_data){
								$return .= '<div class="admin_repeater_block ui-sortable-handle" ' .
										'style="background-image: url(\'' . $GLOBALS['config']['base_url'] . 'modules/cms/img/drag.png\'); ">'.
										'<div class="admin_repeater_block_toolbar"><div class="admin_repeater_block_delete">Remove</div></div>'.
										print_fields($field['fields'], $repeater_data, $fk_data, $field['name'], $repeater_key).
										'</div>';
							}
						}
						
						// print add another button
						$return .= '<div style="clear: both; " class="admin_repeater_line"></div>';
						$return .= '<div class="admin_small_button admin_right admin_repeater_button" ';
						$return .= ' data-html="'.
								str_replace('"', '#', '<div class="admin_repeater_block" ' .
										'style="background-image: url(\'' . $GLOBALS['config']['base_url'] . 'modules/cms/img/drag.png\'); ">'.
										'<div class="admin_repeater_block_toolbar"><div class="admin_repeater_block_delete">Remove</div></div>'.
										print_fields($field['fields'], array(), $fk_data, $field['name'], '###random###').
										'</div>').
								'" data-name="'.$field['name'].'">Add element</div><div style="clear: both; "></div>';
						
						$return .= '</div>';
					
					} elseif ($field['type'] == 'text'){
						
						$return .= '<div class="cms_input admin_input_text'.($prefix ? '' : ' admin_column').'">';
						$return .= '<label for="'.($prefix ? $prefix.'_' : '').$field['name'].'">'.$field['label'].'</label>';
						$return .= '<input id="'.($prefix ? $prefix.'_' : '').$field['name'].'" type="text" class="admin_input_'.$field['name'].'" ' .
								' name="data'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']').'" ' .
								' value="'.($field_empty && isset($field['default']) ? $field['default'] : $field_data ).'">';
						$return .= '</div>';
						
					} elseif ($field['type'] == 'hidden'){
						
						$return .= '<input id="'.($prefix ? $prefix.'_' : '').$field['name'].'" class="admin_input_'.$field['name'].'" ' .
								' type="hidden" name="data'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']').'" ' .
								' value="'.($field_empty && isset($field['default']) ? $field['default'] : $field_data ).'">';

					} elseif ($field['type'] == 'image'){
						
						$return .= _panel('cms_input_image', array(
								'label' => $field['label'], 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ), 
								'name' => 'data'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']), 
								'category' => !empty($field['category']) ? $field['category'] : '',
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'), 
						));
						
					} elseif ($field['type'] == 'select'){
						
						$return .= _panel('admin_input_select', array(
								'label' => $field['label'], 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
								'values' => $field['values'],
								'name' => 'data'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']), 
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'), 
						));
						
					} elseif ($field['type'] == 'fk'){
						
						// possible values have to be in array key=>value under name
						$return .= _panel('admin_input_select', array(
								'label' => $field['label'], 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
								'values' => $fk_data[$field['name']],
								'name' => 'data'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']), 
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'), 
						));
						
					} elseif ($field['type'] == 'file'){
						
						$return .= _panel('cms_input_file', array(
								'label' => $field['label'], 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
								'name' => 'data'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']), 
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'), 
						));
						
					}
				}
				
				return $return;
				
			}

			print('<div class="admin_block_container">'.print_fields($table_structure, $data, $fk_data).'</div>');
		?>
		
	</form>
	
</div>
