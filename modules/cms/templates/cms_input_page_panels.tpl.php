<div class="admin_input cms_input_page_panels">

	<?php if (!empty($label)): ?>
		<div class="admin_block_label">
			<div class="admin_block_title"><?php print($label); ?></div>
		</div>
	<?php endif ?>
	
	<ul class="admin_list_sortable <?php print( empty($sortable_class) ? ' cms_list_sortable ' : $sortable_class ); ?>">
		<?php foreach($cms_page_panels as $block): ?>
		
			<li class="<?php print(empty($block['show']) ? 'cms_input_page_panel_hidden' : ''); ?>" 
					style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); ">
			
				<?php if(empty($name)): ?>
					<input type="hidden" class="block_id" value="<?php print($block['block_id']); ?>">
				<?php else: ?>
					<input type="hidden" name="<?php print($name); ?>[]" value="<?php print($block['block_id']); ?>">
				<?php endif ?>
			
				<div class="admin_text"><?php print(!empty($block['title']) ? $block['title'] : '[ no title ]'); ?></div>
				<?php if (!empty($block['_delete'])): ?>
					<div class="cms_list_item_button cms_page_panel_delete" data-cms_page_panel_id="<?php print($block['block_id']); ?>">
						delete
					</div>
				<?php endif ?>
				<?php if (!empty($block['_edit'])): ?>
					<a class="cms_list_item_button" <?php _lh('admin/block/'.$block['block_id'].'/'); ?>>edit</a>
				<?php endif ?>
				
				<div class="cms_list_item_button cms_page_panel_show" data-cms_page_panel_id="<?php print($block['block_id']); ?>">
					<?php print($block['show'] ? 'hide' : 'show'); ?>
				</div>
				
			</li>
			
		<?php endforeach ?>
	</ul>
	
	<div style="clear: both; "><!-- --></div>
	
	<?php if (isset($page_id)): ?>
		<div class="admin_small_button admin_right cms_input_page_panels_add" data-target="<?php _l('admin/block/0/'.$page_id.'/'); ?>">Add Block</div>
	<?php else: ?>
		<div class="admin_small_button admin_right cms_input_page_panels_add" data-target="<?php _l('admin/block/0/0/'.$block_id.'/'.$name_clean.'/'); ?>">Add Block</div>
	<?php endif ?>

	<div style="clear: both; "><!-- --></div>

</div>