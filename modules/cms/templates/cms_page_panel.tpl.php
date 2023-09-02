<?php

	_panel('cms/cms_page_panel_toolbar', [
			'target_id' => $block['cms_page_panel_id'],
			'target_page_id' => $block['cms_page_id'],
			'target_parent_id' => $block['parent_id'],
	]);

?>

<div class="cms_page_panel_container">

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" class="cms_page_panel_id" name="cms_page_panel_id" value="<?= $block['cms_page_panel_id'] ?>">
		<input type="hidden" id="parent_id" name="parent_id" value="<?= $block['parent_id'] ?>">
		<?php if(!empty($parent_name)): ?>
			<input class="cms_page_panel_parent_name" type="hidden" name="parent_name" value="<?= $parent_name ?>">
		<?php endif ?>
		<input type="hidden" class="cms_page_id" name="cms_page_id" value="<?= isset($_admin_title) ? $block['cms_page_id'] : $cms_page_id ?>">
		<input type="hidden" name="sort" value="<?= $block['sort'] ?>">
		<?php if(!empty($_mode)): ?>
			<input class="cms_page_panel_mode" type="hidden" name="_mode" value="<?php print($_mode); ?>">
		<?php endif ?>

		<?php if (empty($independent_block) || !empty($block['parent_id'])): ?>
			<div class="cms_page_panel_content">

				<?php _panel('cms/cms_input_text', [
						'name' => 'title',
						'value' => $block['title'],
						'name_clean' => 'block_title',
						'label' => 'Title',
						'help' => '[Page panel title]||Not visible in frontend. When page panel has {heading} field, this is overwritten from there',
						'extra_class' => 'cms_page_panel_title',
				]); ?>
				
				<?php _panel('cms/cms_input_text', [
						'name' => 'submenu_anchor',
						'value' => $block['submenu_anchor'],
						'name_clean' => 'block_submenu_anchor',
						'label' => 'Anchor',
						'help' => '[Page panel anchor]||Use only for in page anchors and specifying id-s for panel ajax operations.'.
								' Non-empty value may affect page layout',
				]); ?>
				
				<input class="cms_page_panel_panel_name" type="hidden" name="panel_name" value="<?= $block['panel_name'] ?>">
		
			</div>
		<?php else: ?>
			<input class="cms_page_panel_panel_name" type="hidden" name="panel_name" value="<?= $block['panel_name'] ?>">
			<input type="hidden" name="title" value="<?php print($block['title']); ?>">
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
		
		<?php _panel('cms/cms_page_panel_fields', ['panel_params_structure' => $panel_params_structure, 'block' => $block]) ?>

	</form>
	
	<div class="cms_page_panel_info">
		<div class="cms_page_panel_info_column">
			<div class="cms_page_panel_info_label">panel:</div>
			<div class="cms_page_panel_info_value"><?= $block['panel_name'] ?></div>
		</div>
		<div class="cms_page_panel_info_column">
			<div class="cms_page_panel_info_label">created:</div>
			<div class="cms_page_panel_info_value"><?= !empty($block['create_time']) ? date('Y-m-d H:i:s', $block['create_time']) : '' ?>
			<?= !empty($block['create_user']['username']) ? $block['create_user']['username'] : '-' ?></div>
		</div>
		<div class="cms_page_panel_info_column">
			<div class="cms_page_panel_info_label">updated:</div>
			<div class="cms_page_panel_info_value"><?= !empty($block['update_time']) ? date('Y-m-d H:i:s', $block['update_time']) : '' ?>
			<?= !empty($block['update_user']['username']) ? $block['update_user']['username'] : '-' ?></div>
		</div>
	</div>
	
</div>
