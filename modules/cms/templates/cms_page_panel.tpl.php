<?php
	
	_panel('cms_page_panel_toolbar', [
			'cms_page_panel_id' => $block['block_id'],
			'cms_page_id' => $block['page_id'],
			'breadcrumb' => !empty($breadcrumb) ? $breadcrumb : [],
			'parent_id' => $block['parent_id'],
	]);

?>

<div>

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" name="do" value="cms_page_panel_save">
		<input type="hidden" class="cms_page_panel_id" id="block_id" name="block_id" value="<?php print($block['block_id']); ?>">
		<input type="hidden" id="parent_id" name="parent_id" value="<?php print($block['parent_id']); ?>">
		<?php if(!empty($parent_field_name)): ?>
			<input class="cms_page_panel_parent_name" type="hidden" name="parent_name" value="<?php print($parent_field_name); ?>">
		<?php endif ?>
		<input type="hidden" class="cms_page_id" id="page_id" name="page_id" value="<?php print( isset($_admin_title) ? $block['page_id'] : $cms_page_id); ?>">
		<input type="hidden" name="sort" value="<?php print($block['sort']); ?>">
		<?php if(!empty($_mode)): ?>
			<input class="cms_page_panel_mode" type="hidden" name="_mode" value="<?php print($_mode); ?>">
		<?php endif ?>

		<?php if (empty($independent_block) || !empty($block['parent_id'])): ?>
			<div class="admin_block">
				<div class="admin_column admin_column_left">
				
					<?php _panel('cms_input_text', [
							'name' => 'title',
							'value' => $block['title'],
							'name_clean' => 'block_title',
							'label' => 'Title',
							'help' => '[Page panel title]||Not visible in frontend. When page panel has {heading} field, this is overwritten from there',
					]); ?>
				
					<div class="cms_input cms_input_select">
						<label for="panel_name">Block type</label>
						<?php _panel('cms_help', ['help' => '[Page panel type]||Select page panel type from available panel types in installed modules.||When adding a new page panel, '.
								'one can select an existing panel from {Shortcut to} dropdown instead.||Changing this field may cause losing data already entered for this page panel', ]); ?>
						<select class="admin_block_panel_name" name="panel_name" id="panel_name">
							<option value="">-- select block type --</option>
							<?php foreach ($panel_types as $panel_type => $panel_type_label): ?>
							
								<?php // make panel names w/o module work: 
									if (!stristr($block['panel_name'], '/')){
										list($_module, $_panel_type) = explode('/', $panel_type); // w/o module
									} else {
										$_panel_type = $panel_type; // w/ module
									}
								?>
								
								<option value="<?= $panel_type ?>" <?= $block['panel_name'] == $_panel_type ? 'selected="selected"' : '' ?>><?= $panel_type_label ?></option>
							
							<?php endforeach ?>
						</select>
					</div>
					
					<?php if($block['panel_name'] === '' && empty($block['parent_id'])): /* last cond == no shortcuts for panel in panel */ ?>
						<div class="cms_input cms_input_select">
							<label for="shortcut_to">Shortcut to</label>
							<select class="admin_block_shortcut_to" name="shortcut_to" id="shortcut_to">
								<option value="">-- shortcut to --</option>
								<?php foreach ($shortcuts as $key => $value): ?>
									<option value="<?php print($key); ?>"><?php print($value); ?></option>
								<?php endforeach ?>
							</select>
						</div>
					<?php endif ?>
								
				</div>
				<div class="admin_column admin_column_right">
					
					<?php _panel('cms_input_text', [
							'name' => 'submenu_title',
							'value' => $block['submenu_title'],
							'name_clean' => 'block_submenu_title',
							'label' => 'Menu title',
							'help' => '[In page menu label]||When {Menu anchor} is set, this field could be used as menu item label',
					]); ?>
					
					<?php _panel('cms_input_text', [
							'name' => 'submenu_anchor',
							'value' => $block['submenu_anchor'],
							'name_clean' => 'block_submenu_anchor',
							'label' => 'Menu anchor',
							'help' => '[Page panel anchor]||Use only for in page anchors. Non-empty value may affect page layout',
					]); ?>
				
				</div>
				<div style="clear: both; "></div>
			
			</div>
		<?php else: ?>
			<input type="hidden" name="panel_name" value="<?php print($block['panel_name']); ?>">
			<input type="hidden" name="title" value="<?php print($block['title']); ?>">
			<input type="hidden" name="submenu_title" value="<?php print($block['submenu_title']); ?>">
			<input type="hidden" name="submenu_anchor" value="<?php print($block['submenu_anchor']); ?>">
		<?php endif ?>

		<?php
		
			// TODO: move this php to some better place
		
			function print_fields($structure, $data = array(), $fk_data = array(), $prefix = '', $key = ''){
				
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
						
						// print repeater header
						$return .= '<div style="clear: both; "></div>' .
								'<div class="admin_block admin_repeater_container ui-sortable admin_repeater_container_' . $field['name'] . 
								'" data-label="'.$field['label'].'">';
						
						// print fields for existing data
						if (!empty($data[$field['name']])){
							
							foreach($data[$field['name']] as $repeater_key => $repeater_data){
								$return .= '<div class="cms_repeater_block ui-sortable-handle" ' .
										'style="background-image: url(\'' . $GLOBALS['config']['base_url'] . 'modules/cms/img/drag.png\'); ">'.
										'<div class="cms_repeater_block_toolbar"><div class="cms_repeater_block_delete">Remove</div></div>'.
										print_fields($field['fields'], $repeater_data, $fk_data, $field['name'], $repeater_key).
										'</div>';
							}
						}
						
						// print add another button
						$return .= '<div style="clear: both; " class="admin_repeater_line"></div>';
						$return .= '<div class="admin_small_button admin_right cms_repeater_button" ';
						$return .= ' data-html="'.
								str_replace('"', '#', '<div class="cms_repeater_block" ' .
										'style="background-image: url(\'' . $GLOBALS['config']['base_url'] . 'modules/cms/img/drag.png\'); ">'.
										'<div class="cms_repeater_block_toolbar"><div class="cms_repeater_block_delete">Remove</div></div>'.
										print_fields($field['fields'], array(), $fk_data, $field['name'], '###random###').
										'</div>').
								'" data-name="'.$field['name'].'">Add element</div><div style="clear: both; "></div>';
						
						$return .= '</div>';
					
					} elseif ($field['type'] == 'text'){
						
						$return .= _panel('cms_input_text', [
								'label' => $field['label'].$mandatory_label,
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ),
								'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'),
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']),
								'extra_class' => ($prefix ? '' : 'admin_column'),
								'max_chars_class' => $max_chars_class,
								'meta_class' => $meta_class,
								'mandatory_class' => $mandatory_class,
								'extra_data' => $max_chars_data.' '.$meta_data.' ',
								'_return' => true,
								'help' => !empty($field['help']) ? $field['help'] : '',
								'params' => $field,
						]);
												
					} elseif ($field['type'] == 'textarea'){
						
						$return .= _panel('cms_input_textarea', array(
								'label' => $field['label'].$mandatory_label,
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ),
								'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'),
								'extra_class' => (($prefix || empty($field['width'])) ? ' admin_column ' : '')
										.((!empty($field['width']) && $field['width'] == 'wide') ? ' cms_input_wide_textarea ' : ''),
								'extra_data' => $max_chars_data.' '.$meta_data.' '
										.'data-lines="'.(!empty($field['lines']) ? $field['lines'] : '3' ).'" '
										.' data-html="'.(!empty($field['html']) ? $field['html'] : '').'" '
										.' data-html_class="'.(!empty($field['html_class']) ? $field['html_class'] : '').'" '
										.' data-html_css="'.(!empty($field['html_css']) ? $field['html_css'] : '').'" ',
								'max_chars_class' => $max_chars_class,
								'meta_class' => $meta_class,
								'mandatory_class' => $mandatory_class,
								'tinymce' => !empty($field['html']),
								'_return' => true,
								'help' => !empty($field['help']) ? $field['help'] : '',
								'params' => $field,
						));

					} elseif ($field['type'] == 'image'){
						
						$return .= _panel('cms_input_image', array(
								'label' => $field['label'].$mandatory_label, 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ), 
								'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']), 
								'category' => !empty($field['category']) ? $field['category'] : '',
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'),
								'mandatory_class' => $mandatory_class,
								'extra_data' => ' data-name="'.$field['name'].'" ',
								'help' => !empty($field['help']) ? $field['help'] : '',
								'params' => $field,
						));
						
					} elseif ($field['type'] == 'cms_page_panels' || $field['type'] == 'panels'){
						
						$return .= _panel('cms_input_page_panels', array(
								'label' => $field['label'], 
								'value' => ($field_empty ? array() : $field_data ), 
								'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']),
								'panels' => !empty($field['panels']) ? $field['panels'] : '',
								'extra_class' => ($prefix ? '' : 'admin_column'),
								'block_id' => $block_id,
								'_return' => true, 
								'help' => !empty($field['help']) ? $field['help'] : '',
						));
						
					} elseif ($field['type'] == 'link'){
						
						$return .= _panel('cms_input_link', array(
								'label' => $field['label'], 
								'value' => ($field_empty && isset($field['default']) ? (!empty($field['default']) ? $field['default'] : '') : $field_data ),
								'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']),
								'extra_class' => ($prefix ? '' : 'admin_column'),
								'mandatory_class' => $mandatory_class,
								'targets' => !empty($field['targets']) ? $field['targets'] : '',
								'_return' => true,
								'help' => !empty($field['help']) ? $field['help'] : '',
						));
						
					} elseif ($field['type'] == 'select'){
						
						$name = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
						$name_clean = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
						
						$return .= _panel('cms_input_select', array(
								'label' => $field['label'].$mandatory_label, 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
								'values' => $field['values'],
								'name' => $name, 
								'name_clean' => !empty($name_clean) ? $name_clean : $name, 
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'), 
								'mandatory_class' => $mandatory_class,
								'help' => !empty($field['help']) ? $field['help'] : '', 
								'params' => $field,
						));
						
					} elseif ($field['type'] == 'fk'){
						
						if (empty($field['add_empty'])){
							$field_name = (!empty($field['field']) ? $field['field'] : $field['name']);
							if (isset($fk_data[$field_name][0]) && $fk_data[$field_name][0] == '-- not specified --'){
								unset($fk_data[$field_name][0]);
							}
						}

						$name = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
						$name_clean = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
						
						$return .= _panel('cms_input_fk', [
								'select_params' => [
										'label' => $field['label'].$mandatory_label, 
										'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
										'values' => !empty($fk_data[$field['target']]) ? $fk_data[$field['target']] : $fk_data[(!empty($field['field']) ? $field['field'] : $field['name'])],
										'name' => $name, 
										'name_clean' => !empty($name_clean) ? $name_clean : $name, 
										'extra_class' => ($prefix ? '' : 'admin_column'), 
										'mandatory_class' => $mandatory_class,
										'help' => !empty($field['help']) ? $field['help'] : '',
										'params' => $field,
								],
								'_return' => true,
						]);
						
					} elseif ($field['type'] == 'repeater_select'){
						
						$repeater_select_data = [
								'selected' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ),
								'target' => $field['target'],
								'field' => $field['field'],
								'add_empty' => !empty($field['add_empty']) ? '1' : '0',
								'labels' => !empty($field['labels']) ? $field['labels'] : '',
						];
						
						
						$name = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
						$name_clean = ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']);
						
						$return .= _panel('cms_input_repeater_select', [
								'select_params' => [
										'label' => $field['label'].$mandatory_label, 
										'value' => ($field_empty && isset($field['default']) ? $field['default'] : $field_data ), 
										'values' => [],
										'name' => $name, 
										'name_clean' => !empty($name_clean) ? $name_clean : $name, 
										'extra_class' => ($prefix ? '' : 'admin_column').' cms_input_repeater_select',
										'mandatory_class' => $mandatory_class,
										'help' => !empty($field['help']) ? $field['help'] : '',
										'extra_data' => $repeater_select_data,
								],
								'_return' => true,
						]);
						
					} elseif ($field['type'] == 'file'){
						
						$return .= _panel('cms_input_file', array(
								'label' => $field['label'].$mandatory_label, 
								'value' => ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) ), 
								'name' => 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']'), 
								'name_clean' => ($prefix ? $prefix.'_'.$field['name'].'_'.$key : $field['name']), 
								'_return' => true, 
								'extra_class' => ($prefix ? '' : 'admin_column'),
								'mandatory_class' => $mandatory_class,
								'accept' => !empty($field['accept']) ? $field['accept'] : '',
								'help' => !empty($field['help']) ? $field['help'] : '',
						));
						
					} else {
						
						$field['_return'] = true;
						$field['value'] = ($field_empty && isset($field['default']) ? $field['default'] : str_replace('"', '&quot;', $field_data) );
						$field['name'] = 'panel_params'.($prefix ? '['.$prefix.']['.$field['name'].'][]' : '['.$field['name'].']');
						
						// add field
						$return .= _panel('cms/cms_input', $field);
						
					}

				}
				
				return $return;
				
			}

			print('<div class="admin_block_container">'.print_fields($panel_params_structure, $block, $fk_data).'</div>');
			
		?>
		
	</form>
	
</div>
