<div class="cms_input cms_input_xy_container cms_input_xy_target_<?= $target ?>" data-cms_input_height="4" data-target_image="<?= $target_image ?>">
	<div class="cms_input_xy_content">
	
		<label class="cms_input_label"><?= $label ?></label>
		<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
		
		<div class="cms_input_button cms_input_xy_set cms_input_xy_set_button">Pick</div>
		<div class="cms_input_button cms_input_xy_clear">Clear</div>
	
		<div class="cms_input_xy_x_label">X</div>
		<input class="cms_input_xy_x" name="<?= $name ?>[x]" value="<?= !empty($value['x']) ? $value['x'] : '' ?>">
		
		<div class="cms_input_xy_y_label">Y</div>
		<input class="cms_input_xy_y" name="<?= $name ?>[y]" value="<?= !empty($value['y']) ? $value['y'] : '' ?>">
		
		<div class="cms_input_xy_image cms_input_xy_set_button" <?php _ib('cms/cms_opacity.png', 40) ?>>
			<?php if(!empty($target_image)): ?>
				<div class="cms_input_xy_image_inner" <?php $i = _ib($target_image, 300) ?> data-w="<?= $i['width'] ?>" data-h="<?= $i['height'] ?>">
					<div class="cms_input_xy_pointer"></div>
				</div>
			<?php else: ?>
				-- empty target --
			<?php endif ?>
		</div>
	
	</div>
</div>