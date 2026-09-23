<div class="cms_list_list_container">

	<?php if (!(empty($list) || count($list) == 0)): ?>
		
		<ul id="list_list" class="admin_list_sortable">
			<?php foreach($list as $block): ?>
				<?php $can_sort = ($no_sort !== 'no_sort'); ?>
				<li class="cms_list_sortable_item cms_list_list_item <?= $can_sort ? 'block_dragable' : '' ?>
						<?= $block['show'] ? '' : 'cms_item_hidden' ?>" data-block_id="<?= $block[$id_field] ?>">

					<input type="hidden" class="block_id" value="<?php print($block[$id_field]); ?>">

					<div class="cms_list_list_eye cms_page_panel_show<?= $block['show'] ? '' : ' cms_list_list_eye_off' ?>"
							data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>"
							data-show="<?= $block['show'] ? '1' : '0' ?>" data-cms_ctrl="h">
						<div class="cms_list_list_eye_on" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_eye.png');"></div>
						<div class="cms_list_list_eye_hidden" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_eye_hidden.png');"></div>
					</div>

					<div class="cms_list_list_menu">
						<div class="cms_list_list_more">...</div>
						<div class="cms_list_list_menu_pop">
							<div class="cms_page_panel_copy" data-cms_page_panel_id="<?= $block['cms_page_panel_id'] ?>">copy</div>
						</div>
					</div>

					<div class="cms_list_list_tick">[ ]</div>

					<?php if ($can_sort): ?>
						<div class="cms_list_list_drag" <?php _ib('cms/cms_drag.png', 14) ?>></div>
					<?php endif ?>

					<a class="cms_list_list_title" href="<?= $edit_base.$block[$id_field] ?>/"><?= $block['title'] ?></a>

				</li>
			<?php endforeach ?>
		</ul>
		
	<?php else: ?>
		
		<div class="cms_list_list_message">Nothing to show</div>
	
	<?php endif ?>

</div>
