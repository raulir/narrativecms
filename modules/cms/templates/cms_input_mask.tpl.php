<div class="cms_input cms_input_mask_container cms_input_mask_target_<?= $target ?>" data-cms_input_height="4" 
		data-target_image="<?= $target_image ?>" data-definition="<?= $definition ?>" data-name="<?= $name ?>">
		
	<div class="cms_input_mask_content">
	
		<label class="cms_input_label"><?= $label ?></label>
		<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
		
		<div class="cms_input_button cms_input_mask_set cms_input_mask_set_button">Edit</div>
		<div class="cms_input_button cms_input_mask_clear">Clear</div>
	
		<textarea class="cms_input_mask_value" style="display: none; " name="<?= $name ?>"><?= !empty($value) ? $value : '' ?></textarea>
		
		<div class="cms_input_mask_image cms_input_mask_set_button" <?php _ib('cms/cms_opacity.png', 40) ?>>
		
			<?php if(!empty($target_image)): ?>
				<div class="cms_input_mask_image_inner" <?php $i = _ib($target_image, 300) ?> data-w="<?= $i['width'] ?>" data-h="<?= $i['height'] ?>"></div>
			<?php else: ?>
				-- empty target --
			<?php endif ?>

		</div>
	
	</div>
	
</div>