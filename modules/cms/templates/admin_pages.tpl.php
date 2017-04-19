<div class="cms_toolbar">
	<div class="admin_tool_text">Pages</div>
	<a class="admin_tool_button admin_right" href="<?php print($GLOBALS['config']['base_url']); ?>admin/page/0">New Page</a>
</div>

<div>

	<?php if (!(empty($pages) || count($pages) == 0)): ?>
		<ul id="pages_list" class="admin_list_sortable">
			<?php foreach($pages as $page): ?>
				<li class="cms_list_sortable_item" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); ">
					<input type="hidden" class="page_id" value="<?php print($page['page_id']); ?>">
					<div class="admin_list_sortable_div admin_text"><?php print(!empty($page['title']) ? $page['title'] : '[ no title ]'); ?></div>
					<a class="cms_list_item_button" href="<?php print($GLOBALS['config']['base_url']); ?>admin/page/<?php print($page['page_id']); ?>">edit</a>
				</li>
			<?php endforeach ?>
		</ul>
	<?php else: ?>
		<ul>
			<div class="admin_text">No pages found</div>
		</ul>
	<?php endif ?>

</div>
