<div class="cms_image_container">

	<div class="cms_image_left">
		<div class="cms_image_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
		
			<div class="cms_image_image cms_image_image_hidden">
				<div class="cms_image_image_source" <?php _ib($filename, ['width' => 1200, 'pb' => 1, ]) ?>></div>
			</div>
		
		</div>
	</div>
	
	<div class="cms_image_right">
	
		<div class="cms_image_toolbar cms_toolbar">
		
			<div class="cms_image_filename cms_tool_text"><?= $filename ?></div>
	
			<div class="cms_image_cancel cms_tool_button cms_right" <?php _ib('cms/cms_cancel.png', 30) ?>></div>
			<a class="cms_image_save cms_tool_button cms_right" data-filename="<?= $filename ?>" <?php _ib('cms/cms_save.png', 30) ?>></a>
	
		</div>
		
		<div class="cms_image_tools">
		
			<div class="cms_image_image_data">
				
				<div class="cms_image_element">
					<label for="cms_image_category" class="cms_image_category_label">Category</label>
					<select id="cms_image_category" class="cms_image_category">
						<?php foreach($categories as $key => $cat): ?>
							<option value="<?= $key ?>" <?= $key === $category ? 'selected="selected"' : '' ?>><?= $cat ?></option>
						<?php endforeach ?>
					</select>
				</div>
				
				<div class="cms_image_element">
					<label for="cms_image_author" class="cms_image_label">Author</label>
					<input id="cms_image_author" type="text" class="cms_image_author" value="<?= $author ?>">
				</div>
						
				<div class="cms_image_element cms_image_element_description">
					<label for="cms_image_description" class="cms_image_label">Description</label>
					<textarea id="cms_image_description" class="cms_image_description"><?= $description ?></textarea>
				</div>
		
			</div>
		
			<div class="cms_image_image_tools">
			
				<div class="cms_image_element">
					<div class="cms_image_label">Crop</div>
					<div class="cms_image_crop_values">
						<div class="cms_image_crop_p1">
							P1: 
							<input class="cms_image_crop_x1" value="<?= $transform['crop']['x1'] ?? '-50' ?>"> ,
							<input class="cms_image_crop_y1" value="<?= $transform['crop']['y1'] ?? '-50' ?>">
						</div>
						<div class="cms_image_crop_p2">
							P2: 
							<input class="cms_image_crop_x2" value="<?= $transform['crop']['x2'] ?? '50' ?>"> ,
							<input class="cms_image_crop_y2" value="<?= $transform['crop']['y2'] ?? '50' ?>">
						</div>
					</div>
				</div>
			
			</div>
			
		</div>
		
		<div class="cms_image_preview" <?php _ib('cms/cms_opacity.png', 40) ?>>

		</div>

		<!-- div class="cms_image_preview_stats">
			<div class="cms_image_preview_width">w: <?= 1 ?>px</div>
			<div class="cms_image_preview_height">h: <?= 1 ?>px</div>
			<div class="cms_image_preview_size">s: <?= 1 ?>b</div>
		</div -->
		
<?php _print_r($image); ?>

	</div>

</div>
