<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>"
		<?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

	<div class="cms_input cms_input_textarea <?= !empty($width) && $width == 'wide' ? 'cms_input_wide_textarea' : '' ?>
			<?= !empty($mandatory_class) ? $mandatory_class : '' ?>" data-cms_input_height="<?= ceil((21 * (!empty($lines) ? $lines : 3 ) + 32)/35) ?>">

		<label><?= $label ?></label>
		
		<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
		
		<?php if (!empty($translate) && !empty($GLOBALS['language'])): ?>
			<div class="cms_translate_icon" <?php _ib('cms/cms_translate.png', 12) ?>></div>
		<?php endif ?>

		<textarea name="<?php print($name); ?>" class="<?= $name ?> <?php print(!empty($tinymce) ? ' admin_tinymce ' : ''); ?>
				<?php print(!empty($max_chars_class) ? $max_chars_class : ''); ?> <?php print(!empty($meta_class) ? $meta_class : ''); ?>" 
				data-lines="<?= !empty($lines) ? $lines : '3' ?>"
				<?php print(!empty($extra_data) ? $extra_data : ''); ?>><?php print(!empty($value) ? $value : ''); ?></textarea>

	</div>

</div>