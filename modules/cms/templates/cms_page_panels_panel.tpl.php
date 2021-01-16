<div class="cms_page_panels_panel_container <?= empty($block['show']) ? ' cms_page_panels_panel_hidden ' : '' ?>"
		data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>" data-panel_name="<?= $block['panel_name'] ?>">
		
	<div class="cms_page_panels_panel_content">
	
		<div class="cms_page_panels_panel_heading ui-sortable-handle" <?php _ib('cms/cms_drag.png', 12) ?>>

			<div class="cms_page_panels_panel_toolbar">
				<div class="cms_page_panels_panel_title"><?= $block['title'] ?></div>
				<div class="cms_page_panels_panel_delete">Delete</div>
				<div class="cms_page_panels_panel_hide"><?= empty($block['show']) ? 'Show' : 'Hide' ?></div>
			</div>

		</div>

		<div class="cms_page_panels_panel_area">
			<?= print_fields($panel_params_structure, $block) ?>
		</div>
	
	</div>
	
</div>