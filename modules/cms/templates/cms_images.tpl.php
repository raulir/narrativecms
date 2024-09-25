<div class="popup_container cms_images_container" style="display: none; ">
	<div class="cms_images_content">

		<div class="cms_toolbar cms_images_toolbar">
			
			<div class="cms_tool_text">Select image</div>
			
			<a class="cms_images_select cms_tool_button cms_right popup_select" data-value="<?= $filename ?>">Select</a>
			
			<a class="cms_images_cancel cms_tool_button cms_right popup_cancel">Cancel</a>
			
			<a class="cms_images_upload cms_tool_button cms_right">Upload</a>
			
			<form id="new_image_form" class="cms_images_new_image_form" method="post" enctype="multipart/form-data" style="display: inline; ">
				<input type="hidden" name="do" value="cms_images_upload">
				<input type="file" name="new_image[]" class="cms_images_new_image" multiple>
			</form>
			
			<form id="replace_image_form" class="cms_images_replace_image_form" method="post" enctype="multipart/form-data" style="display: inline; ">
				<input type="hidden" name="do" value="cms_images_replace">
				<input type="file" name="replace_image" class="cms_images_replace_image" style="display: none; ">
			</form>
			
			<div class="cms_tool_button cms_right cms_paging_button cms_paging_last cms_images_paging_last cms_images_paging_disabled"
					<?php _ib('cms/cms_paging_last.png', ['height' => 12]) ?>></div>
			<div class="cms_tool_button cms_right cms_paging_button cms_paging_next cms_images_paging_next cms_images_paging_disabled"
					<?php _ib('cms/cms_paging_next.png', ['height' => 12]) ?>></div>
			<div class="cms_tool_button cms_right cms_images_paging_page">
				<span class="cms_images_paging_current">&nbsp;</span>/<span class="cms_images_paging_total">&nbsp;</span>
			</div>
			<div class="cms_tool_button cms_right cms_paging_button cms_paging_previous cms_images_paging_previous cms_images_paging_disabled"
					<?php _ib('cms/cms_paging_previous.png', ['height' => 12]) ?>></div>
			<div class="cms_tool_button cms_right cms_paging_button cms_paging_first cms_images_paging_first cms_images_paging_disabled"
					<?php _ib('cms/cms_paging_first.png', ['height' => 12]) ?>></div>
			
			<select class="cms_images_category admin_tool_select cms_right">
				<option value="">-- category --</option>
				<?php foreach($categories as $key => $cat): ?>
					<option value="<?php print($key); ?>" <?php print($key === $category ? 'selected="selected"' : ''); ?>><?php print($cat); ?></option>
				<?php endforeach ?>
			</select>
			
			<input class="cms_images_search_input cms_right" placeholder="Search ...">
		
		</div>
		
		<div class="cms_images_area" data-page="0" data-limit="<?= 6 * $GLOBALS['config']['images_rows'] ?>" data-filename="<?= $filename ?>">
			<div style="text-align: center; ">Loading ...</div>
		</div>
	
	</div>
</div>