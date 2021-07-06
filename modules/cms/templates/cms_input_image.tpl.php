<div class="cms_input cms_input_image_container" data-cms_input_height="<?= (empty($size) || $size == 'normal') ? '7' : '3' ?>">
	<div class="cms_input_image cms_input_image_area_<?= $id = mt_rand(0, 9999999999) ?> cms_input_image_size_<?= !empty($size) ? $size : 'normal' ?>
			<?= !empty($extra_class) ? $extra_class : '' ?>	<?= !empty($mandatory) ? ' cms_input_mandatory' : '' ?>" 
			data-name="<?= $name ?>" data-value="<?= $value ?>">
	
		<label><?= $label ?><?= !empty($mandatory) ? ' *' : '' ?></label>
		
		<?php _panel('cms/cms_help', ['help' => $help]) ?>
		
		<div class="cms_input_image_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
			<div class="cms_input_image_content cms_input_image_content_<?= $id ?>">
				<?php if(!empty($error)): ?>
					<div class="cms_input_image_error"><?= $error ?></div>
				<?php elseif(!empty($value)): ?>
					<div class="cms_input_image_image" <?php $i = _ib($value, 300); ?>></div>
				<?php else: ?>
					-- no image --
				<?php endif ?>
			</div>
		</div>
		
		<div class="cms_input_image_button cms_input_button" data-name="<?= $id ?>" data-category="<?= $category ?>">
			Select
		</div>
		
		<div class="cms_input_image_clear cms_input_button" data-name="<?= $id ?>">
			Clear
		</div>
		
		<span class="cms_input_image_overlay">
			<input type="<?= (!empty($size) && $size == 'small') ? 'text' : 'hidden' ?>" class="cms_input_image_input cms_image_input_<?= $id ?>" 
					name="<?= $name ?>" value="<?= $value ?>">
		</span>
		
		<div style="clear: both; "></div>
	
	</div>
</div>