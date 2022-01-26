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
					
					<div class="cms_list_list_item_heading"><?= $block['_panel_heading'] ?></div>
					
					<div class="cms_list_list_item_buttons">
					
						<div class="cms_small_button cms_page_panel_copy" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">copy</div>
	
						<?php _panel('cms/cms_page_panel_button_show', ['cms_page_panel_id' => $block['cms_page_panel_id'], ]) ?>
		
						<a class="cms_small_button" href="<?= $edit_base.$block[$id_field] ?>/">edit</a>
						
					</div>
	
				</li>
			<?php endforeach ?>
		</ul>
		
	<?php else: ?>
		
		<div class="cms_list_list_message">Nothing to show</div>
	
	<?php endif ?>

</div>
