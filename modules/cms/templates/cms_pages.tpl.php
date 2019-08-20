<div class="cms_pages_container">

	<div class="cms_pages_left">

		<div class="cms_toolbar">
			<div class="cms_tool_text">Pages</div>
			<a class="cms_tool_button admin_right" <?php _lh('admin/page/0') ?>>New Page</a>
		</div>

		<?php if (!(empty($pages['main']) || count($pages['main']) == 0)): ?>
			<ul class="cms_pages_list admin_list_sortable">
				<?php cms_pages_list($pages['main']) ?>
			</ul>
		<?php else: ?>
			<ul>
				<div class="admin_text">No pages defined</div>
			</ul>
		<?php endif ?>

	</div>
	
	<div class="cms_pages_right" style="font-size: 10px;">
	
		<?php foreach($positions as $position): ?>

			<div class="cms_pages_position">
				<div class="cms_toolbar">
					<div class="cms_tool_text"><?= $position['plural'] ?></div>
					<a class="cms_tool_button admin_right" <?php _lh('admin/page/0/'.$position['id']) ?>>New <?= $position['id'] ?></a>
				</div>
			</div>
			
			<?php if (!(empty($pages[$position['id']]) || count($pages[$position['id']]) == 0)): ?>
				<ul class="cms_pages_list admin_list_sortable">
					<?php cms_pages_list($pages[$position['id']]) ?>
				</ul>
			<?php else: ?>
				<div class="cms_pages_position_empty">No <?= $position['plural'] ?> defined</div>
			<?php endif ?>
		
		<?php endforeach ?>

	</div>

</div>

<?php function cms_pages_list($pages){ ?>

	<?php foreach($pages as $page): ?>

		<li class="cms_pages_page cms_list_sortable_item <?= !empty($page['status']) ? ' cms_item_hidden ' : '' ?>
				<?= $page['cms_page_id'] == $GLOBALS['config']['landing_page']['_value'] ? ' cms_pages_page_landing ' : '' ?>"
				<?php _ib('cms/cms_drag.png', 14) ?>>
		
			<input type="hidden" class="page_id" value="<?php print($page['page_id']); ?>">
		
			<?php if($page['cms_page_id'] == $GLOBALS['config']['landing_page']['_value']): ?>
				<div class="cms_pages_landing" <?php _ib('cms/cms_landing.png', 16) ?>></div>
			<?php endif ?>

			<div class="admin_list_sortable_div admin_text"><?php print(!empty($page['title']) ? $page['title'] : '[ no title ]'); ?></div>
			<a class="cms_list_item_button" <?php _lh('admin/page/' . $page['cms_page_id']) ?>>edit</a>
		
		</li>

	<?php endforeach ?>

<?php } ?>
