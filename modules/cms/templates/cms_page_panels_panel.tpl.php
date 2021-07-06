<div class="cms_page_panels_panel_container <?= empty($block['show']) ? ' cms_page_panels_panel_hidden ' : '' ?>"
		data-cms_page_panel_id="<?= $cms_page_panel_id ?>" data-panel_name="<?= $panel_name ?>" data-parent_field_name="<?= $parent_field_name ?>">
		
	<div class="cms_page_panels_panel_content">
	
		<div class="cms_page_panels_panel_heading ui-sortable-handle" <?php _ib('cms/cms_drag.png', 12) ?>>

			<div class="cms_page_panels_panel_toolbar">
				<div class="cms_page_panels_panel_title"><?= $title ?></div>
				<div class="cms_page_panels_panel_delete">Delete</div>
				<div class="cms_page_panels_panel_hide"><?= empty($block['show']) ? 'Show' : 'Hide' ?></div>
			</div>
			
			<?php if(!empty($parent_field_name)): ?>
				<input type="hidden" name="parent_name" value="<?= $parent_field_name ?>">
				<input class="cms_page_panels_panel_id" type="hidden" name="cms_page_panel_id" value="<?= $cms_page_panel_id ?>">
			<?php endif ?>

		</div>

		<div class="cms_page_panels_panel_area">
			<?= print_fields($panel_params_structure, $block) ?>
		</div>
	
	</div>
	
</div>