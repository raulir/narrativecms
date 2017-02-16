<div class="popup_container cms_images_container" style="display: none; ">
	<div class="cms_images_cell">
		<div class="cms_images_content cms_images_<?= !empty($GLOBALS['config']['images_rows']) ? $GLOBALS['config']['images_rows'] : 4 ?>">

			<div class="cms_images_toolbar cms_toolbar">
				
				<div class="admin_tool_text">Select image</div>
				
				<a class="cms_images_select admin_tool_button admin_right popup_select" data-value="<?php print($filename); ?>">Select</a>
				
				<a class="cms_images_cancel admin_tool_button admin_right popup_cancel">Cancel</a>
				
				<a class="cms_images_upload admin_tool_button admin_right">Upload</a>
				<form id="new_image_form" class="cms_images_new_image_form" method="post" enctype="multipart/form-data" style="display: inline; ">
					<input type="hidden" name="do" value="cms_images_upload">
					<input type="file" name="new_image[]" class="cms_images_new_image" style="display: none; " multiple>
				</form>
				
				<div class="admin_tool_button admin_right cms_paging_last cms_images_paging_last cms_images_paging_disabled"></div>
				<div class="admin_tool_button admin_right cms_paging_next cms_images_paging_next cms_images_paging_disabled"></div>
				<div class="admin_tool_text admin_right cms_images_paging_page">
					<span class="cms_images_paging_current">&nbsp;</span>/<span class="cms_images_paging_total">&nbsp;</span>
				</div>
				<div class="admin_tool_button admin_right cms_paging_previous cms_images_paging_previous cms_images_paging_disabled"></div>
				<div class="admin_tool_button admin_right cms_paging_first cms_images_paging_first cms_images_paging_disabled"></div>
				
				<select class="cms_images_category admin_tool_select admin_right">
					<option value="">-- category --</option>
					<?php foreach($categories as $key => $cat): ?>
						<option value="<?php print($key); ?>" <?php print($key === $category ? 'selected="selected"' : ''); ?>><?php print($cat); ?></option>
					<?php endforeach ?>
				</select>
				
				<input class="admin_tool_input cms_images_search_input admin_right" placeholder="Search ...">
			
			</div>
			
			<div class="cms_images_area" data-page="0" data-limit="<?= 6 * (!empty($GLOBALS['config']['images_rows']) ? $GLOBALS['config']['images_rows'] : 4) ?>" data-filename="<?php print($filename); ?>">
				<div style="text-align: center; ">Loading ...</div>
			</div>
		
		</div>
	</div>
</div>