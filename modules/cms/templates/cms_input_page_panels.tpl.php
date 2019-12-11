<div class="cms_input_container">
	<div class="cms_input cms_input_page_panels" data-cms_input_height="<?= (!empty($size) ? $size : 4) + 2 ?>"
			data-panels="<?= !empty($panels) ? $panels : '' ?>">
	
		<?php if (!empty($label)): ?>
			<div class="admin_block_label">
				<div class="admin_block_title"><?php print($label); ?></div>
				<?php _panel('cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
			</div>
		<?php endif ?>
		
		<?php if (!empty($cms_page_panels)): ?>
			<ul class=" <?php print( empty($sortable_class) ? ' cms_list_sortable ' : $sortable_class ); ?> cms_input_page_panels_list">
				<?php foreach($cms_page_panels as $block): ?>
				
					<li class="cms_list_sortable_item cms_input_page_panels_item <?php print(empty($block['show']) ? 'cms_item_hidden' : ''); ?>" 
							<?php _ib('cms/cms_drag.png', 14) ?>>
					
						<?php if(empty($name)): ?>
							<input type="hidden" class="block_id" value="<?php print($block['cms_page_panel_id']); ?>">
						<?php else: ?>
							<input type="hidden" name="<?php print($name); ?>[]" value="<?php print($block['cms_page_panel_id']); ?>">
						<?php endif ?>
					
						<div class="admin_text cms_input_page_panels_item_heading"><?= !empty($block['title']) ? $block['title'] : '[ no title ]' ?></div>
						
						<div class="cms_input_page_panels_item_buttons">
						
							<?php if (!empty($block['_delete'])): ?>
								<div class="cms_list_item_button cms_page_panel_delete" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">
									remove
								</div>
							<?php endif ?>
							
							<?php if (!empty($block['_edit'])): ?>
								<a class="cms_list_item_button" <?php _lh('admin/cms_page_panel/'.$block['cms_page_panel_id'].'/'); ?>>edit</a>
							<?php endif ?>
							
							<div class="cms_list_item_button cms_page_panel_show" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">
								<?php print($block['show'] ? 'hide' : 'show'); ?>
							</div>
							
							<?php if (!empty($block['_goto'])): ?>
								<a class="cms_list_item_button" <?php _lh('admin/cms_page_panel/'.$block['goto_id'].'/'); ?>>goto</a>
							<?php endif ?>
						
						</div>
						
					</li>
					
				<?php endforeach ?>
			</ul>
		<?php else: ?>
			<div class="cms_input_page_panels_message">No panels added</div>
		<?php endif ?>
		
		<div style="clear: both; "><!-- --></div>
	
		<?php if (isset($cms_page_id)): ?>
			<div class="admin_small_button admin_right cms_input_page_panels_add" data-page_id="<?= $cms_page_id ?>">Add page panel</div>
		<?php else: ?>
			<div class="admin_small_button admin_right cms_input_page_panels_add" data-parent_id="<?= $cms_page_panel_id ?>" 
					data-name="<?= $name_clean ?>">Add page panel</div>
		<?php endif ?>
	
		<div style="clear: both; "><!-- --></div>
	
	</div>
</div>
