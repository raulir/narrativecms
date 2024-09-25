
<div class="cms_image_container">

	<div class="cms_image_toolbar cms_toolbar">
		
		<div class="cms_image_filename cms_tool_text"><?php print($filename); ?></div>

		<a class="cms_image_save cms_tool_button cms_right" data-filename="<?php print($filename); ?>">Save</a>
		<a class="cms_image_cancel cms_tool_button cms_right">Cancel</a>

	</div>

	<div class="cms_image_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
		<div class="cms_image_cell">
			<img class="cms_image_image" src="<?php $image = _i($filename, ['data' => true, ]) ?>?v=<?= time() ?>">
		</div>
	</div>
	
	<div class="cms_image_left">
	
		<div class="cms_image_stats">Size: &nbsp; <?= $image['width'] ?> x <?= $image['height'] ?> &nbsp; <?= $image['size'] ?>b</div>
		
		<div class="cms_input cms_image_description_input">
			<label for="cms_image_description">Description</label>
			<textarea id="cms_image_description" class="cms_image_input cms_image_description"><?php print($description); ?></textarea>
		</div>
		
	</div>
	
	<div class="cms_image_right">
		
		<div class="cms_image_input">
			<label for="cms_image_category">Category</label>
			<select id="cms_image_category" class="cms_image_category">
				<?php foreach($categories as $key => $cat): ?>
					<option value="<?php print($key); ?>" <?php print($key === $category ? 'selected="selected"' : ''); ?>><?php print($cat); ?></option>
				<?php endforeach ?>
			</select>
		</div>
		
		<div class="cms_image_input">
			<label for="cms_image_author">Author</label>
			<input id="cms_image_author" type="text" class="cms_image_input cms_image_author" value="<?php print($author); ?>">
		</div>
		
		<div class="cms_image_input">
			<label for="cms_image_copyright">Copyright</label>
			<input id="cms_image_copyright" type="text" class="cms_image_input cms_image_copyright" value="<?php print($copyright); ?>">
		</div>
		
	</div>

</div>
