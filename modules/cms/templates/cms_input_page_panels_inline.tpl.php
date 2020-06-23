<div class="cms_input_page_panels_inline_container">
	
	<div class="cms_input_page_panels_inline_content">

		<?php /* header */ ?>
	
		<div class="cms_input_page_panels_inline_label">
			<div class="cms_input_page_panels_inline_label_text"><?= $label ?></div>
			<?php _panel('cms/cms_help', ['help' => $help, ]); ?>
		</div>

		<?php /* existing panels */ ?>
		
		<div class="cms_input_page_panels_inline_panels">
			<?php if (!empty($cms_page_panels)): ?>

				<?php foreach($cms_page_panels as $cms_page_panel): ?>
					<?php _panel('cms/cms_page_panel_inline', ['cms_page_panel_id' => $cms_page_panel['cms_page_panel_id'], ]) ?>
				<?php endforeach ?>

			<?php else: ?>
			
				<div class="cms_input_page_panels_inline_message">No panels added</div>
			
			<?php endif ?>
		</div>
		
		<?php /* footer */ ?>
	
		<div class="cms_input_page_panels_inline_footer">
	
			<div class="admin_small_button admin_right cms_input_page_panels_inline_add" data-target="<?php _l('admin/cms_page_panel/0/0/'.$cms_page_panel_id.'/'.$name_clean.'/'); ?>" data-name="<?= $name_clean ?>">Add page panel</div>
	
		</div>
	
	</div>

</div>








	
	<?php /* if (!empty($cms_page_panels)): ?>
		<ul class="admin_list_sortable <?php print( empty($sortable_class) ? ' cms_list_sortable ' : $sortable_class ); ?>">
			<?php foreach($cms_page_panels as $block): ?>
			
				<li class="cms_list_sortable_item cms_input_page_panels_item <?php print(empty($block['show']) ? 'cms_item_hidden' : ''); ?>" 
						style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); ">
				
					<?php if(empty($name)): ?>
						<input type="hidden" class="block_id" value="<?php print($block['block_id']); ?>">
					<?php else: ?>
						<input type="hidden" name="<?php print($name); ?>[]" value="<?php print($block['block_id']); ?>">
					<?php endif ?>
				
					<div class="admin_text cms_input_page_panels_item_heading"><?php print(!empty($block['title']) ? $block['title'] : '[ no title ]'); ?></div>
					
					<div class="cms_input_page_panels_item_buttons">
					
						<?php if (!empty($block['_delete'])): ?>
							<div class="admin_list_sortable_div cms_list_item_button cms_page_panel_delete" data-cms_page_panel_id="<?php print($block['cms_page_panel_id']); ?>">
								remove
							</div>
						<?php endif ?>
						
						<?php if (!empty($block['_edit'])): ?>
							<a class="cms_list_item_button" <?php _lh('admin/cms_page_panel/'.$block['block_id'].'/'); ?>>edit</a>
						<?php endif ?>
						
						<div class="admin_list_sortable_div cms_list_item_button cms_page_panel_show" data-cms_page_panel_id="<?php print($block['block_id']); ?>">
							<?php print($block['show'] ? 'hide' : 'show'); ?>
						</div>
						
						<?php if (!empty($block['_goto'])): ?>
							<a class="cms_list_item_button" <?php _lh('admin/cms_page_panel/'.$block['goto_id'].'/'); ?>>goto</a>
						<?php endif ?>
					
					</div>
					
				</li>
				
			<?php endforeach ?>
		</ul>
	*/ ?>
	
	<div style="clear: both; "><!-- --></div>
	
	


