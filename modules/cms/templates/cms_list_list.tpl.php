<div class="cms_list_list_container">

	<?php if (!(empty($list) || count($list) == 0)): ?>
		
		<ul id="list_list" class="admin_list_sortable">
			<?php foreach($list as $block): ?>
				<li class="cms_list_sortable_item <?= $no_sort !== 'no_sort' ? 'ui-sortable-handle block_dragable' : '' ?>
						<?= $block['show'] ? '' : 'cms_item_hidden' ?>" data-block_id="<?= $block[$id_field] ?>" 
						<?php if($no_sort !== 'no_sort'): ?>
							<?php _ib('cms/cms_drag.png', 14) ?>
						<?php else: ?>
							style="padding-left: 10px; "
						<?php endif ?>>
				
					<input type="hidden" class="block_id" value="<?php print($block[$id_field]); ?>">
					
					<div class="cms_list_list_item_heading"><?= $block['title'] ?></div>
					
					<div class="cms_list_list_item_buttons">
					
						<div class="cms_small_button cms_page_panel_copy" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">copy</div>
	
						<div class="cms_tool_button cms_right cms_page_panel_show" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>" data-cms_ctrl="h">
							<div class="cms_page_panel_show_label"><?= $block['show'] ? 'hide' : 'show' ?></div>
						</div>
		
						<a class="cms_small_button cms_list_list_edit" href="<?= $edit_base.$block[$id_field] ?>/">edit</a>
						
					</div>
	
				</li>
			<?php endforeach ?>
		</ul>
		
	<?php else: ?>
		
		<div class="cms_list_list_message">Nothing to show</div>
	
	<?php endif ?>

</div>
