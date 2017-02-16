<div class="cms_toolbar">
	<?php if(!empty($menu['menu_item_id'])): ?>
		<a class="admin_tool_text" href="<?php print($GLOBALS['config']['base_url']); ?>admin/menu/">Main menu</a>
		<div class="admin_tool_text">
			&nbsp; &gt; &nbsp; <?php print(str_limit($menu['text'], 40)); ?>
		</div>
	<?php else: ?>
		<div class="admin_tool_text">Main menu</div>
	<?php endif ?>
	<div class="admin_tool_button admin_right admin_main_menu_save_button">Save</div>
	<div class="admin_tool_button admin_right admin_main_menu_add_button">Add item</div>
</div>

<div>

	<form class="admin_main_menu_form" method="post" style="display: inline; ">
	
		<input type="hidden" name="do" value="admin_main_menu_save">
		<input type="hidden" name="menu_id" value="<?php print(!empty($menu['menu_item_id']) ? $menu['menu_item_id'] : '0') ?>">

		<script type="text/javascript">
			var admin_main_menu_items = [];
			var admin_main_menu_pages = [];
			var admin_main_menu_page_panels = [];

			<?php foreach($menu_items as $menu_item): ?>
				admin_main_menu_items.push({
					'menu_item_id': '<?php print($menu_item['menu_item_id']); ?>',
					'menu_id': '<?php print($menu_item['menu_id']); ?>',
					'sort': '<?php print($menu_item['sort']); ?>',
					'mode': '<?php print($menu_item['mode']); ?>',
					'link': '<?php print(addslashes($menu_item['link'])); ?>',
					'text': '<?php print(addslashes($menu_item['text'])); ?>',
					'new_window': '<?php print($menu_item['new_window']); ?>',
					'hide_from_menu': '<?php print($menu_item['hide_from_menu']); ?>',
					'is_submenu': '<?php print($menu_item['is_submenu']); ?>'
				});
			<?php endforeach ?>

			<?php foreach($pages as $page): ?>
				admin_main_menu_pages.push({
					'page_id': '<?php print($page['page_id']); ?>',
					'title': '<?php print(addslashes($page['title'])); ?>',
					'slug': '<?php print(addslashes($page['slug'])); ?>'
				});
			<?php endforeach ?>

			<?php foreach($cms_page_panels as $page_panel): ?>
				admin_main_menu_page_panels.push({
					'page_panel_id': '<?php print($page_panel['block_id']); ?>',
					'submenu_title': '<?php print(addslashes($page_panel['submenu_title'])); ?>',
					'page_id': '<?php print($page_panel['page_id']); ?>',
					'submenu_anchor': '<?php print(addslashes($page_panel['submenu_anchor'])); ?>'
				});
			<?php endforeach ?>

		</script>
	
		<?php if (!(empty($menu_items) || count($menu_items) == 0)): ?>
		
			<ul class="admin_list_sortable ui-sortable menu_items_list"></ul>
			
		<?php else: ?>
			<ul class="admin_list_sortable ui-sortable menu_items_list">
				<div class="admin_text">No menu items found</div>
			</ul>
		<?php endif ?>
	
	</form>

	<div class="menu_item_template">
		<li class="ui-sortable-handle cms_main_menu_item" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/drag.png'); ">
		
			<div class="admin_main_menu_item_toolbar">
				<div class="admin_main_menu_item_delete">delete</div>
				<a class="admin_main_menu_item_submenu" 
						href="<?php print($GLOBALS['config']['base_url']); ?>admin/menu/###menu_item_id###/"
						data-is_submenu="###is_submenu###">
					<?php /* insert appropriate by is_submenu: edit/add submenu */ ?>
				</a> 
			</div>
			
			<input type="hidden" name="menu_item_id[]" value="###menu_item_id###">
		
			<div class="cms_main_menu_first">
			
				<div class="cms_main_menu_label_mode">Mode</div>
				<select name="mode[]" class="admin_main_menu_mode_select" data-selected="###mode###">
					<option value="0">Manual</option>
					<option value="1">Automatic</option>
					<option value="2">Label manual, url auto</option>
				</select>
				
				<div class="cms_main_menu_label_target">Target</div>
				<select name="page_id[]" class="admin_main_menu_target_select" data-menu_item_link="###link###">
					<option value="0">-- no page --</option>
				</select>
				<select name="block_id[]" class="admin_main_menu_block_select" data-menu_item_link="###link###">
					<option value="0">-- no block --</option>
				</select>
				
				<select name="new_window[]" class="admin_menu_input_select admin_menu_new_window" data-menu_item_new_window="###new_window###">
					<option value="0">Same window</option>
					<option value="1">New window</option>
				</select>
			
			</div>

			<div class="cms_main_menu_second">
			
				<div class="cms_main_menu_label_text">Text</div>
				<input type="text" class="admin_menu_input_text" name="text[]" value="###text###" placeholder="Text">
				
				<div class="cms_main_menu_label_url">URL</div>
				<input type="text" class="admin_menu_input_link" name="link[]" value="###link###" placeholder="Link">			
				
				<select name="hide_from_menu[]" class="admin_menu_input_select admin_menu_hide_from_menu" data-menu_item_hide_from_menu="###hide_from_menu###">
					<option value="0">Visible</option>
					<option value="1">Hidden</option>
				</select>

			</div>

		</li>
	</div>

</div>
