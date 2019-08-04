
<div class="cms_toolbar">
	
	<a class="cms_tool_text" <?php _lh('admin/pages/') ?>><?= (empty($page['position']) || $page['position'] == 'main') ? 'Pages' : $page['position'] ?></a>
	<div class="cms_tool_text">
		&nbsp; &gt; &nbsp; 
		<div class="cms_page_toolbar_title"></div>
	</div>
	
	<?php if (!empty($GLOBALS['language']['languages'])): ?>
		<?php _panel('cms/cms_language_select') ?>
	<?php endif ?>
	
	<div class="cms_page_save cms_tool_button admin_right">Save</div>
	<a class="cms_page_delete cms_tool_button admin_right">Delete</a>

</div>

<div class="cms_page_container">

	<form method="post" class="admin_form" style="display: inline; ">
	
		<input type="hidden" class="cms_page_id" name="page_id" value="<?php print($page['page_id']); ?>">
		<input type="hidden" class="cms_page_sort" name="sort" value="<?php print($page['sort']); ?>">
		<input type="hidden" class="cms_page_position" name="position" value="<?= $page['position'] ?>">
		
		<div class="admin_block">
			<div class="admin_column admin_column_left">
				
				<?php _panel('cms/cms_input_text', [
						'name' => 'title',
						'value' => $page['title'],
						'name_clean' => 'page_title',
						'label' => 'Name',
						'help' => '[Page name]||CMS page name. If {SEO title} is empty, this can be seen as page title in the frontend.',
						'meta_class' => 'cms_page_title',
				]); ?>
				
				<?php if (in_array($page['position'], ['', 'main', ])): ?>
				
					<?php if(!$is_list_item): ?>
							
						<?php _panel('cms/cms_input_select', array(
								'label' => 'Published status', 
								'value' => (!empty($page['status']) ? $page['status'] : 0), 
								'values' => ['0' => 'Automatic', '1' => 'Hidden', ],
								'name' => 'cms_page_status',
								'name_clean' => 'status',
								'help' => '[Page published status]||"{Automatic}" - hidden when page doesn\'t have any panels added, otherwise visible.||"{Hidden}" - visible only while logged into CMS admin.',
						)); ?>
							
					<?php else: ?>
						
						<input type="hidden" name="cms_page_status" class="cms_page_status" value="0">
						
					<?php endif ?>
					
					<?php
						_panel('cms/cms_input_layout', [
								'label' => 'Layout', 
								'value' => !empty($page['layout']) ? $page['layout'] : (!empty($GLOBALS['config']['layout']) ? $GLOBALS['config']['layout'] : 'rem'), 
								'name' => 'cms_page_layout', 
								'help' => '[Page layout]||CMS "Default fixed" is fixed pixel size layout.||CMS "Default rems" layout changes rem size with page size.||There might be more layouts available, defined in other modules.',
						]);
					?>
	
					<?php _panel('cms/cms_input_subtitle', ['label' => 'SEO', 'width' => 'narrow', 'help' => '[SEO]||These fields are very important for search engines, page sharing in social media etc']) ?>
					
					<?php _panel('cms/cms_input_text', [
							'name' => 'slug',
							'value' => $page['slug'],
							'name_clean' => 'page_slug',
							'label' => 'Slug',
							'help' => '[Page slug]||Can be seen on browser address bar following the main address part of the site. Important for SEO.||Can contain only letters, numbers, hyphens and underscores (_).',
							'meta_class' => 'cms_page_slug',
					]); ?>
					
					<?php _panel('cms/cms_input_text', [
							'name' => 'seo_title',
							'translate' => 1,
							'value' => (!empty($page['seo_title']) ? $page['seo_title'] : ''),
							'name_clean' => 'cms_page_seo_title',
							'label' => 'Title',
							'help' => '[SEO title]||Can be seen on browser title bar, search engine results and social media shares. Important for SEO',
							'meta_class' => 'cms_page_seo_title',
					]); ?>
	
					<?php 
						_panel('cms/cms_input_textarea', array(
								'label' => 'Description',
								'value' => (!empty($page['description']) ? $page['description'] : ''),
								'name' => 'cms_page_description',
								'translate' => 1,
								'extra_data' => ' data-lines="4" ',
								'help' => '[Page description]||Very important for SEO.||Can be seen in search engine results and social media shares. Shouldn\'t be left empty and should be different for all pages. '.
										'||This text might be ignored when page is list item (like an article or blog post) template. In this case this can be left empty.',
						));
					?>
	
					<?php 
						_panel('cms/cms_input_image', array(
								'label' => 'Image', 
								'value' => !empty($page['image']) ? $page['image'] : '', 
								'name' => 'cms_page_image', 
								'category' => 'content',
								'help' => '[Page image]||Important for social media sharing.||Can be seen in social media shares. Shouldn\'t be left empty.||There might be additional images added when page is list item page. In this case this should be left empty.',
						));
					?>
					
				<?php endif ?>
				
			</div>
			<div class="admin_column admin_column_right">
				
				<?php
					_panel('cms/cms_input_page_panels', array(
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
