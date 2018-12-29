<div class="cms_toolbar">
	<div class="admin_tool_text">Pages</div>
	<a class="cms_tool_button admin_right" href="<?php print($GLOBALS['config']['base_url']); ?>admin/page/0">New Page</a>
</div>

<div>

	<?php if (!(empty($pages) || count($pages) == 0)): ?>
		<ul id="pages_list" class="admin_list_sortable">
			<?php foreach($pages as $page): ?>
			
				<li class="cms_pages_page cms_list_sortable_item <?= !empty($page['status']) ? ' cms_item_hidden ' : '' ?>
						<?= $page['cms_page_id'] == $GLOBALS['config']['landing_page._value'] ? ' cms_pages_page_landing ' : '' ?>"
						style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/drag.png'); ">
				
					<input type="hidden" class="page_id" value="<?php print($page['page_id']); ?>">
				
					<?php if($page['cms_page_id'] == $GLOBALS['config']['landing_page._value']): ?>
						<div class="cms_pages_landing" style="background-image: url('<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_landing.png'); "></div>
					<?php endif ?>

					<div class="admin_list_sortable_div admin_text"><?php print(!empty($page['title']) ? $page['title'] : '[ no title ]'); ?></div>
					<a class="cms_list_item_button" <?php _lh('admin/page/' . $page['cms_page_id']) ?>>edit</a>
				
				</li>
			
			<?php endforeach ?>
		</ul>
	<?php else: ?>
		<ul>
			<div class="admin_text">No pages found</div>
		</ul>
	<?php endif ?>

</div>
