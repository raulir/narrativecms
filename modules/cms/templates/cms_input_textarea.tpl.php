<div class="cms_input cms_input_textarea <?= !empty($width) && $width == 'wide' ? 'cms_input_wide_textarea' : '' ?>
		<?= !empty($mandatory_class) ? $mandatory_class : '' ?>" data-cms_input_height="<?= ceil((21 * (!empty($lines) ? $lines : 3 ) + 32)/35) ?>">

	<label><?= $label ?></label>
	
	<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
	
	<?php if (!empty($translate) && !empty($GLOBALS['language'])): ?>
		<div class="cms_translate_icon" <?php _ib('cms/cms_translate.png', 12) ?>></div>
	<?php endif ?>

	<textarea name="<?= $name ?>" class="<?= $name ?> <?= !empty($html) ? ' cms_tinymce ' : '' ?>
			<?= !empty($max_chars_class) ? $max_chars_class : '' ?> <?= !empty($meta_class) ? $meta_class : '' ?>" 
			data-lines="<?= !empty($lines) ? $lines : '3' ?>"
			<?= !empty($extra_data) ? $extra_data : '' ?>><?= !empty($value) ? $value : '' ?></textarea>

</div>
