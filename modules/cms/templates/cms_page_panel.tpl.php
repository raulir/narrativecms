<?php
	
	_panel('cms_page_panel_toolbar', [
			'cms_page_panel_id' => $block['cms_page_panel_id'],
			'cms_page_id' => $block['cms_page_id'],
			'breadcrumb' => !empty($breadcrumb) ? $breadcrumb : [],
			'parent_id' => $block['parent_id'],
	]);

?>

<div class="cms_page_panel_container">

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" class="cms_page_panel_id" name="cms_page_panel_id" value="<?= $block['cms_page_panel_id'] ?>">
		<input type="hidden" id="parent_id" name="parent_id" value="<?php print($block['parent_id']); ?>">
		<?php if(!empty($parent_field_name)): ?>
			<input class="cms_page_panel_parent_name" type="hidden" name="parent_name" value="<?php print($parent_field_name); ?>">
		<?php endif ?>
		<input type="hidden" class="cms_page_id" name="cms_page_id" value="<?= isset($_admin_title) ? $block['cms_page_id'] : $cms_page_id ?>">
		<input type="hidden" name="sort" value="<?php print($block['sort']); ?>">
		<?php if(!empty($_mode)): ?>
			<input class="cms_page_panel_mode" type="hidden" name="_mode" value="<?php print($_mode); ?>">
		<?php endif ?>

		<?php if (empty($independent_block) || !empty($block['parent_id'])): ?>
			<div class="cms_page_panel_content">

				<?php _panel('cms_input_text', [
						'name' => 'title',
						'value' => $block['title'],
						'name_clean' => 'block_title',
						'label' => 'Title',
						'help' => '[Page panel title]||Not visible in frontend. When page panel has {heading} field, this is overwritten from there',
						'extra_class' => 'cms_page_panel_title',
				]); ?>
			
				<?php _panel('cms_input_text', [
						'name' => 'submenu_title',
						'value' => $block['submenu_title'],
						'name_clean' => 'block_submenu_title',
						'label' => 'Menu title',
						'help' => '[In page menu label]||When {Menu anchor} is set, this field could be used as menu item label',
				]); ?>
				
				<div class="cms_input cms_input_select">
					<label for="panel_name">Panel type</label>
					<?php _panel('cms_help', ['help' => '[Page panel type]||Select page panel type from available panel types in installed modules.||When adding a new page panel, '.
							'one can select an existing panel from {Shortcut to} dropdown instead.||Changing this field may cause losing data already entered for this page panel', ]); ?>
					<select class="cms_page_panel_panel_name" name="panel_name" id="panel_name">
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
				
				<?php _panel('cms_input_text', [
						'name' => 'submenu_anchor',
						'value' => $block['submenu_anchor'],
						'name_clean' => 'block_submenu_anchor',
						'label' => 'Menu anchor',
						'help' => '[Page panel anchor]||Use only for in page anchors. Non-empty value may affect page layout',
				]); ?>
				
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
		<?php else: ?>
			<input type="hidden" name="panel_name" value="<?php print($block['panel_name']); ?>">
			<input type="hidden" name="title" value="<?php print($block['title']); ?>">
			<input type="hidden" name="submenu_title" value="<?php print($block['submenu_title']); ?>">
			<input type="hidden" name="submenu_anchor" value="<?php print($block['submenu_anchor']); ?>">
			
			<?php // if list item, allow to select template ?>
			<?php if(!empty($list_templates)): ?>
				<div class="cms_page_panel_template">
					
					<?php _panel('cms/cms_input_select', [
							'name' => '_template_page_id',
							'name_clean' => '_template_page_id',
							'values' => $list_templates,
							'label' => 'Template',
							'value' => !empty($block['_template_page_id']) ? $block['_template_page_id'] : '',
					]) ?>
					
				</div>
			<?php endif ?>
			
		<?php endif ?>
		
		<div class="cms_page_panel_fields">
			<?php _panel('cms/cms_page_panel_fields', ['panel_params_structure' => $panel_params_structure, 'block' => $block]) ?>
		</div>

	</form>
	
</div>
