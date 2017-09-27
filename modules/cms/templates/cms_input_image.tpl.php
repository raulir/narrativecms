
<div class="cms_input cms_input_image cms_input_image_container_<?php print($name_clean); ?> <?php print(!empty($extra_class) ? $extra_class : ''); ?> <?= !empty($mandatory_class) ? $mandatory_class : '' ?>"
		<?php print(!empty($extra_data) ? $extra_data : ''); ?>>

	<label for="cms_input_image_<?php print($name_clean); ?>"><?php print($label); ?></label>
	
	<?php _panel('cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
	
	<div class="admin_image_container" style="background-image: url('<?php print($GLOBALS['config']['base_url']); ?>modules/cms/img/opacity.png'); ">
		<div class="admin_image_content cms_input_image_content_<?php print($name_clean); ?>">
			<?php if(!empty($error)): ?>
				<div class="cms_input_image_error"><?= $error ?></div>
			<?php elseif(!empty($value)): ?>
				<div class="cms_input_image_image" <?php $i = _ib($value, ['width' => 600, 'maxwidth' => true, ]); ?>></div>
			<?php else: ?>
				-- no image --
			<?php endif ?>
		</div>
	</div>
	
	<div class="cms_input_image_button cms_input_button" data-name="<?php print($name_clean); ?>" data-category="<?php print($category); ?>">
		Select
	</div>
	
	<div class="cms_input_image_clear cms_input_button" data-name="<?php print($name_clean); ?>">
		Clear
	</div>
	
	<input type="hidden" class="cms_input_image_input cms_image_input_<?php print($name_clean); ?> <?= $name ?>" name="<?php print($name); ?>" value="<?php print($value); ?>">
	
	<div style="clear: both; "></div>

</div>
