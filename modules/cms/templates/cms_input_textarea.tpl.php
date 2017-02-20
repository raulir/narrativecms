<div class="cms_input admin_input_textarea <?php print(!empty($extra_class) ? $extra_class : ''); ?>">
	
	<label><?php print($label); ?></label>
	
	<?php _panel('cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
	
	<textarea name="<?php print($name); ?>" class="<?= $name ?> 
			<?php print(!empty($tinymce) ? ' admin_tinymce ' : ''); ?>
			<?php print(!empty($max_chars_class) ? $max_chars_class : ''); ?>
			<?php print(!empty($meta_class) ? $meta_class : ''); ?>
			" <?php print(!empty($extra_data) ? $extra_data : ''); ?>><?php print(!empty($value) ? $value : ''); ?></textarea>

</div>
