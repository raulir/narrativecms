
<?php if (!(empty($list) || count($list) == 0)): ?>
	
	<ul id="list_list" class="admin_list_sortable">
		<?php foreach($list as $block): ?>
			<li class="cms_list_sortable_item <?php print($no_sort !== 'no_sort' ? 'ui-sortable-handle block_dragable' : ''); ?>
					<?php print($block['show'] ? '' : 'cms_item_hidden'); ?>" 
					data-block_id="<?php print($block[$id_field]); ?>" 
					<?php if($no_sort !== 'no_sort'): ?>
						<?php _ib('cms/cms_drag.png', 14) ?>
					<?php else: ?>
						style="padding-left: 10px; "
					<?php endif ?>>
			
				<input type="hidden" class="block_id" value="<?php print($block[$id_field]); ?>">
				
				<?php if(!empty($title_field)): ?>
					<div class="admin_list_sortable_div admin_text cms_list_list_item_heading"><?= preg_replace('#<[^>]+>#', ' ', $block[$title_field]) ?></div>
				<?php elseif(!empty($title_panel)): ?>
					<?php _panel($title_panel, array('id' => $block[$id_field], 'edit_base' => $edit_base, 'list_block' => $block['list_block'], '_no_css' => 1, )); ?>
				<?php else: ?>
					<div class="admin_list_sortable_div admin_text cms_list_list_item_heading"><?= $block['title'] ?></div>
				<?php endif ?>
				
				<a class="cms_list_item_button" href="<?php print($edit_base.$block[$id_field]); ?>/">edit</a>

				<div class="admin_list_sortable_div cms_list_item_button cms_page_panel_show" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">
					<?php print($block['show'] ? 'hide' : 'show'); ?>
				</div>
			
				<div class="admin_list_sortable_div cms_list_item_button cms_page_panel_copy" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">copy</div>

			</li>
		<?php endforeach ?>
	</ul>
	
<?php else: ?>
	
	<div class="admin_text cms_list_list_message">Nothing to show</div>

<?php endif ?>
