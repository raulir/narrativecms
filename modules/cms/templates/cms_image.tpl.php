
<div class="cms_image_container">

	<div class="cms_image_toolbar cms_toolbar">
		
		<div class="cms_image_filename admin_tool_text"><?php print($filename); ?></div>

		<a class="cms_image_save admin_tool_button admin_right" data-filename="<?php print($filename); ?>">Save</a>
		<a class="cms_image_cancel admin_tool_button admin_right">Cancel</a>
		<!-- a class="cms_image_replace admin_tool_button admin_right">Reupload</a -->

	</div>

	<div class="cms_image_area">
		<div class="cms_image_cell" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/opacity.png'); ">
			<img class="cms_image_image" src="<?php print($GLOBALS['config']['upload_url'].$filename); ?>">
		</div>
	</div>
	
	<div class="cms_image_left">
		
		<div class="cms_image_input admin_input">
			<label for="cms_image_description">Description</label>
			<textarea id="cms_image_description" class="cms_image_input cms_image_description"><?php print($description); ?></textarea>
		</div>
		
		<div class="cms_image_input admin_input">
			<label for="cms_image_author">Author</label>
			<input id="cms_image_author" type="text" class="cms_image_input cms_image_author" value="<?php print($author); ?>">
		</div>
		
		<div class="cms_image_input admin_input">
			<label for="cms_image_copyright">Copyright</label>
			<input id="cms_image_copyright" type="text" class="cms_image_input cms_image_copyright" value="<?php print($copyright); ?>">
		</div>
		
	</div>
	
	<div class="cms_image_right">
		
		<div class="cms_image_input admin_input">
			<label for="cms_image_category">Category</label>
			<select id="cms_image_category" class="cms_image_category">
				<?php foreach($categories as $key => $cat): ?>
					<option value="<?php print($key); ?>" <?php print($key === $category ? 'selected="selected"' : ''); ?>><?php print($cat); ?></option>
				<?php endforeach ?>
			</select>
		</div>
		
		<?php if(in_array('keyword', $GLOBALS['config']['modules'])): ?>
			<div class="cms_image_input admin_input">
				<select class="cms_image_keywords_select">
					<option value="">-- select keyword --</option>
					<?php foreach($possible_keywords as $keyword): ?>
						<option value="<?php print($keyword['cms_keyword_id']); ?>"><?php print($keyword['cms_keyword_id']); ?></option>
					<?php endforeach ?>
				</select>
				<div class="cms_image_add_keyword">Add</div>
				<div class="cms_image_keywords_container">
					<?php if (!empty($keywords)) foreach($keywords as $keyword): ?>
						<div class="cms_image_keywords_item" data-keyword="<?php print($keyword); ?>" 
								style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/close.png'); ">
							
							<?php print($keyword); ?>
						
						</div>
					<?php endforeach ?>
				</div>
			</div>
		<?php endif ?>
		
	</div>

</div>
