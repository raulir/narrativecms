<div class="cms_input cms_input_page_panels_inline_container" data-cms_input_height="3" data-cms_input_width="2">
	
	<div class="cms_input_page_panels_inline_content">

		<?php /* header */ ?>
	
		<div class="cms_input_page_panels_inline_label">
			<div class="cms_input_page_panels_inline_label_text"><?= $label ?></div>
			<?php _panel('cms/cms_help', ['help' => $help, ]); ?>
		</div>

		<?php /* existing panels */ ?>
		
		<div class="cms_input_page_panels_inline_panels ui-sortable">
			<?php if (!empty($cms_page_panels)): ?>

				<?php foreach($cms_page_panels as $cms_page_panel): ?>
					<?php _panel('cms/cms_page_panels_panel', ['cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'], ]) ?>
				<?php endforeach ?>

			<?php else: ?>
			
				<div class="cms_input_page_panels_inline_message">No panels added</div>
			
			<?php endif ?>
		</div>
		
		<?php /* footer */ ?>
	
		<div class="cms_input_page_panels_inline_footer">
	
			<div class="admin_small_button cms_input_page_panels_inline_add"
					data-target="<?php _l('admin/cms_page_panel/0/0/'.$cms_page_panel_id.'/'.$name_clean.'/'); ?>" 
					data-name="<?= $name_clean ?>">Add page panel</div>
	
		</div>
	
	</div>

</div>
