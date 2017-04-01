
<?php if (!(empty($list) || count($list) == 0)): ?>
	
	<ul id="list_list" class="admin_list_sortable">
		<?php foreach($list as $block): ?>
			<li class="<?php print($no_sort !== 'no_sort' ? 'ui-sortable-handle block_dragable' : ''); ?>
					<?php print($block['show'] ? '' : 'cms_item_hidden'); ?>" 
					data-block_id="<?php print($block[$id_field]); ?>" 
					<?php if($no_sort !== 'no_sort'): ?>
						style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); "
					<?php else: ?>
						style="padding-left: 10px; "
					<?php endif ?>>
			
				<input type="hidden" class="block_id" value="<?php print($block[$id_field]); ?>">
				
				<?php if(!empty($title_field)): ?>
					<div class="admin_text"><?php print($block[$title_field]); ?></div>
				<?php else: ?>
					<?php _panel($title_panel, array('id' => $block[$id_field], 'edit_base' => $edit_base, 'list_block' => $block['list_block'], '_no_css' => 1, )); ?>
				<?php endif ?>
				
				<a class="cms_list_item_button" href="<?php print($edit_base.$block[$id_field]); ?>/">edit</a>

				<div class="cms_list_item_button cms_page_panel_show" data-cms_page_panel_id="<?php print($block['block_id']); ?>">
					<?php print($block['show'] ? 'hide' : 'show'); ?>
				</div>
			
				<div class="cms_list_item_button cms_page_panel_copy" data-cms_page_panel_id="<?php print($block['block_id']); ?>">copy</div>

			</li>
		<?php endforeach ?>
	</ul>
	
<?php else: ?>
	
	<div class="admin_text">Nothing to show</div>

<?php endif ?>
