<div class="cms_input cms_input_textarea <?= !empty($width) && $width == 'wide' ? 'cms_input_wide_textarea' : '' ?>
		<?= !empty($mandatory_class) ? $mandatory_class : '' ?>
		<?= !empty($readonly) ? ' cms_input_textarea_readonly ' : '' ?>
		<?= !empty($md) && empty($readonly) ? ' cms_input_textarea_md ' : '' ?>"
		data-cms_input_height="<?= ceil((21 * (!empty($lines) ? $lines : 3 ) + 32)/35) ?>"
		data-cms_input_width="<?= !empty($width) && $width == 'wide' ? '2' : '1' ?>">

	<label><?= $label ?></label>
	
	<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>

	<?php if (!empty($md) && empty($readonly)): ?>
		<button type="button" class="cms_input_textarea_md_button">Preview</button>
	<?php endif ?>
	
	<?php if (!empty($translate) && !empty($GLOBALS['language']) && empty($readonly)): ?>
		<button type="button" class="cms_translate_icon" data-field_type="textarea" <?php _ib('cms/cms_translate.png', 12) ?>></button>
	<?php endif ?>

	<?php if (!empty($md) && empty($readonly)): ?>
		<div class="cms_input_textarea_md_preview" style="display:none;"></div>
	<?php endif ?>

	<textarea name="<?= $name ?>" class="<?= $name ?> <?= !empty($html) && empty($readonly) && empty($md) ? ' cms_tinymce ' : '' ?>
			<?= !empty($max_chars_class) ? $max_chars_class : '' ?> <?= !empty($meta_class) ? $meta_class : '' ?>" 
			data-lines="<?= !empty($lines) ? $lines : '3' ?>"
			<?= !empty($extra_data) ? $extra_data : '' ?>
			<?= !empty($readonly) ? ' readonly="readonly" ' : '' ?>><?= !empty($value) ? $value : '' ?></textarea>

</div>
