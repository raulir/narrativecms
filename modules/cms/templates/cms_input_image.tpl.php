<div class="cms_input_container">
	<div class="cms_input cms_input_image cms_input_image_container_<?= $name_clean ?> cms_input_image_size_<?= !empty($size) ? $size : 'normal' ?>
			<?= !empty($extra_class) ? $extra_class : '' ?>	<?= !empty($mandatory) ? ' cms_input_mandatory' : '' ?>" 
			data-name="<?= $name ?>" data-value="<?= $value ?>">
	
		<label><?= $label ?><?= !empty($mandatory) ? ' *' : '' ?></label>
		
		<?php _panel('cms_help', ['help' => $help]) ?>
		
		<div class="cms_input_image_container" <?php _ib('cms/cms_opacity.png', 40) ?>>
			<div class="cms_input_image_content cms_input_image_content_<?= $name_clean ?>">
				<?php if(!empty($error)): ?>
					<div class="cms_input_image_error"><?= $error ?></div>
				<?php elseif(!empty($value)): ?>
					<div class="cms_input_image_image" <?php $i = _ib($value, 300); ?>></div>
				<?php else: ?>
					-- no image --
				<?php endif ?>
			</div>
		</div>
		
		<div class="cms_input_image_button cms_input_button" data-name="<?= $name_clean ?>" data-category="<?= $category ?>">
			Select
		</div>
		
		<div class="cms_input_image_clear cms_input_button" data-name="<?= $name_clean ?>">
			Clear
		</div>
		
		<span class="cms_input_image_overlay">
			<input type="<?= (!empty($size) && $size == 'small') ? 'text' : 'hidden' ?>" class="cms_input_image_input cms_image_input_<?= $name_clean ?>" 
					name="<?= $name ?>" value="<?= $value ?>">
		</span>
		
		<div style="clear: both; "></div>
	
	</div>
</div>