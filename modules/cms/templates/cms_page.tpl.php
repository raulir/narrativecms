
<div class="cms_toolbar">
	<a class="admin_tool_text" href="<?php print($GLOBALS['config']['base_url']); ?>admin/pages/">Pages</a>
	<div class="admin_tool_text">
		&nbsp; &gt; &nbsp; 
		<div class="cms_page_toolbar_title"></div>
	</div>
	<div class="cms_page_save admin_tool_button admin_right">Save</div>
	<a class="cms_page_delete admin_tool_button admin_right">Delete</a>
</div>

<div>

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" class="cms_page_id" id="page_id" name="page_id" value="<?php print($page['page_id']); ?>">
		<input type="hidden" class="cms_page_sort" name="sort" value="<?php print($page['sort']); ?>">
		
		<div class="admin_block">
			<div class="admin_column admin_column_left">
				
				<div class="admin_input admin_input_text">
					<label for="page_title">Title</label> 
					<input  class="cms_page_title" id="page_title" type="text" name="title" value="<?php print($page['title']); ?>">
				</div>
				
				<div class="admin_input admin_input_text">
					<label for="page_slug">Slug</label> 
					<input class="cms_page_slug" id="page_slug" type="text" name="slug" value="<?php print($page['slug']); ?>">
				</div>
				
				<?php
					_panel('cms_input_select', array(
								'label' => 'Layout', 
								'value' => $page['layout'], 
								'values' => $layouts,
								'name' => 'cms_page_layout',
								'name_clean' => 'layout',
						));
				?>
				
				<?php 
					_panel('cms_input_textarea', array(
							'label' => 'SEO description',
							'value' => (!empty($page['description']) ? $page['description'] : ''),
							'name' => 'cms_page_description',
							'extra_data' => ' data-lines="4" ',
					));
				?>

				<?php 
					_panel('cms_input_image', array(
								'label' => 'SEO image', 
								'value' => !empty($page['image']) ? $page['image'] : '', 
								'name' => 'cms_page_image', 
								'category' => 'content',
						));
				?>
				
			</div>
			<div class="admin_column admin_column_right">
				
				<?php
					_panel('cms_input_page_panels', array(
							'value' => $block_list,
							'page_id' => $page['page_id'],
							'sortable_class' => 'cms_page_sortable',
					)); 
				?>
			
			</div>
			<div style="clear: both; "></div>
		</div>
	
	</form>
	
</div>
