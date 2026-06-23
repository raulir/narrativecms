<div class="cms_image_container" data-source_cms_image_id="<?= $source_cms_image_id ?>" data-is_child="<?= !empty($is_child) ? '1' : '0' ?>" data-is_source_video="<?= !empty($is_source_video) ? '1' : '0' ?>" data-zoom="<?= $zoom ?>" data-pan_x="<?= $pan_x ?>" data-pan_y="<?= $pan_y ?>" data-brightness="<?= $brightness ?>" data-contrast="<?= $contrast ?>" data-overlay_colour="<?= $overlay_colour ?>" data-overlay_opacity="<?= $overlay_opacity ?>" data-rotation="<?= $rotation ?>" data-rotation_fixed="<?= $rotation_fixed ?>">

	<div class="cms_image_left">
		<div class="cms_image_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
		
			<div class="cms_image_image_pan cms_image_image_hidden">
				<div class="cms_image_image_rotate">
					<div class="cms_image_image_source<?= !empty($is_source_video) ? ' cms_image_image_source_video' : '' ?>" <?php $source_ib = _ib($source_filename, 1200) ?> data-w="<?= !empty($is_source_video) ? (int)$source_width : $source_ib['width'] ?>" data-h="<?= !empty($is_source_video) ? (int)$source_height : $source_ib['height'] ?>"></div>
				</div>
				<div class="cms_image_crop_overlay">
					<div class="cms_image_crop_rect">
						<div class="cms_image_overlay_preview"></div>
					</div>
					<div class="cms_image_crop_handle cms_image_crop_handle_tl"></div>
					<div class="cms_image_crop_handle cms_image_crop_handle_tr"></div>
					<div class="cms_image_crop_handle cms_image_crop_handle_bl"></div>
					<div class="cms_image_crop_handle cms_image_crop_handle_br"></div>
				</div>
			</div>
		
		</div>
	</div>
	
	<div class="cms_image_right">
	
		<div class="cms_image_toolbar cms_toolbar">
		
			<div class="cms_image_filename cms_tool_text<?= !empty($is_child) ? ' cms_image_filename_child' : '' ?>"><?= $filename ?></div>
	
			<div class="cms_image_toolbar_buttons">
				<div class="cms_image_save cms_tool_button" data-filename="<?= $filename ?>">
					<div class="cms_tool_button_inner" <?php _ib('cms/cms_save.png', 30) ?>></div>
				</div>
				<div class="cms_image_cancel cms_tool_button">
					<div class="cms_tool_button_inner" <?php _ib('cms/cms_cancel.png', 30) ?>></div>
				</div>
			</div>
	
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

				<div class="cms_image_element cms_image_element_crop">
					<div class="cms_image_label">Crop</div>
					<div class="cms_image_crop_row">
						<div class="cms_image_crop_pair">
							<span class="cms_image_crop_label">P1</span>
							<input type="text" class="cms_image_crop_x1 cms_image_crop_input" value="<?= $crop['x1'] ?>">
							<input type="text" class="cms_image_crop_y1 cms_image_crop_input" value="<?= $crop['y1'] ?>">
						</div>
						<div class="cms_image_crop_pair">
							<span class="cms_image_crop_label">P2</span>
							<input type="text" class="cms_image_crop_x2 cms_image_crop_input" value="<?= $crop['x2'] ?>">
							<input type="text" class="cms_image_crop_y2 cms_image_crop_input" value="<?= $crop['y2'] ?>">
						</div>
					</div>
				</div>

				<div class="cms_image_element cms_image_element_zoom">
					<div class="cms_image_label">Zoom</div>
					<div class="cms_image_zoom_row">
						<input type="text" class="cms_image_zoom_input" value="<?= $zoom ?>">
						<div class="cms_image_zoom_slider">
							<div class="cms_image_zoom_slider_track">
								<div class="cms_image_zoom_slider_inner">
									<div class="cms_image_zoom_slider_line"></div>
									<?php foreach ([0.5, 1.0, 2.0, 4.0, 8.0, 16.0] as $tick_index => $tick_value): ?>
										<div class="cms_image_zoom_slider_tick" style="left: <?= ($tick_index / 5) * 100 ?>%">
											<span class="cms_image_zoom_slider_tick_label"><?= number_format($tick_value, 1, '.', '') ?></span>
										</div>
									<?php endforeach ?>
									<div class="cms_image_zoom_slider_handle"></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="cms_image_element cms_image_element_brightness">
					<div class="cms_image_label">Brightness</div>
					<div class="cms_image_level_row">
						<input type="text" class="cms_image_brightness_input cms_image_level_input" value="<?= $brightness ?>">
						<div class="cms_image_level_slider cms_image_brightness_slider">
							<div class="cms_image_level_slider_track">
								<div class="cms_image_level_slider_inner cms_image_brightness_slider_inner">
									<div class="cms_image_level_slider_line"></div>
									<?php foreach (['min', 'normal', 'max'] as $tick_index => $tick_label): ?>
										<div class="cms_image_level_slider_tick" style="left: <?= ($tick_index / 2) * 100 ?>%">
											<span class="cms_image_level_slider_tick_label"><?= $tick_label ?></span>
										</div>
									<?php endforeach ?>
									<div class="cms_image_level_slider_handle cms_image_brightness_slider_handle"></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="cms_image_element cms_image_element_contrast">
					<div class="cms_image_label">Contrast</div>
					<div class="cms_image_level_row">
						<input type="text" class="cms_image_contrast_input cms_image_level_input" value="<?= $contrast ?>">
						<div class="cms_image_level_slider cms_image_contrast_slider">
							<div class="cms_image_level_slider_track">
								<div class="cms_image_level_slider_inner cms_image_contrast_slider_inner">
									<div class="cms_image_level_slider_line"></div>
									<?php foreach (['min', 'normal', 'max'] as $tick_index => $tick_label): ?>
										<div class="cms_image_level_slider_tick" style="left: <?= ($tick_index / 2) * 100 ?>%">
											<span class="cms_image_level_slider_tick_label"><?= $tick_label ?></span>
										</div>
									<?php endforeach ?>
									<div class="cms_image_level_slider_handle cms_image_contrast_slider_handle"></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="cms_image_element cms_image_element_overlay">
					<div class="cms_image_label">Overlay</div>
					<div class="cms_image_overlay_colour_row cms_image_overlay_grid_row">
						<span class="cms_image_overlay_sub_label">Colour</span>
						<div class="cms_image_overlay_colour_field">
							<?php _panel('cms/cms_input_colour', [
								'name' => 'overlay_colour',
								'name_clean' => 'cms_image_overlay_colour',
								'label' => 'Colour',
								'value' => $overlay_colour,
							]) ?>
						</div>
					</div>
					<div class="cms_image_overlay_opacity_row cms_image_overlay_grid_row">
						<span class="cms_image_overlay_sub_label">Opacity</span>
						<input type="text" class="cms_image_opacity_input cms_image_level_input" value="<?= $overlay_opacity ?>">
						<div class="cms_image_level_slider cms_image_opacity_slider cms_image_overlay_slider_col">
							<div class="cms_image_level_slider_track">
								<div class="cms_image_level_slider_inner cms_image_opacity_slider_inner">
									<div class="cms_image_level_slider_line"></div>
									<?php foreach ([0, 0.2, 0.4, 0.6, 0.8, 1.0] as $tick_index => $tick_value): ?>
										<div class="cms_image_level_slider_tick" style="left: <?= ($tick_index / 5) * 100 ?>%">
											<span class="cms_image_level_slider_tick_label"><?= number_format($tick_value, 1, '.', '') ?></span>
										</div>
									<?php endforeach ?>
									<div class="cms_image_level_slider_handle cms_image_opacity_slider_handle"></div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="cms_image_element cms_image_element_rotation">
					<div class="cms_image_label">Rotation</div>
					<div class="cms_image_rotation_row cms_image_overlay_grid_row">
						<input type="text" class="cms_image_rotation_input cms_image_level_input" value="<?= $rotation ?>">
						<button type="button" class="cms_image_rotation_fixed<?= $rotation_fixed === '1' ? ' cms_image_rotation_fixed_on' : '' ?>" aria-pressed="<?= $rotation_fixed === '1' ? 'true' : 'false' ?>"></button>
						<div class="cms_image_rotation_slider">
							<div class="cms_image_rotation_slider_track">
								<div class="cms_image_rotation_slider_inner">
									<div class="cms_image_rotation_slider_line"></div>
									<?php foreach ([-180, -90, 0, 90, 180] as $tick_index => $tick_value): ?>
										<div class="cms_image_rotation_slider_tick" style="left: <?= ($tick_index / 4) * 100 ?>%">
											<span class="cms_image_rotation_slider_tick_label"><?= $tick_value ?></span>
										</div>
									<?php endforeach ?>
									<div class="cms_image_rotation_slider_handle"></div>
								</div>
							</div>
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

	</div>

	<div class="cms_image_save_overlay">
		<div class="cms_image_save_overlay_label"><?= !empty($is_source_video) ? 'Saving ...' : 'Exporting ...' ?></div>
	</div>

</div>