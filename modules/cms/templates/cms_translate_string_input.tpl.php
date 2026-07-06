<?php if ($field_type === 'textarea'): ?>

	<textarea class="cms_translate_string_input cms_translate_string_textarea" rows="3"
			data-language_id="<?= htmlspecialchars($language_id, ENT_QUOTES) ?>"><?= htmlspecialchars($value, ENT_QUOTES) ?></textarea>

<?php elseif ($field_type === 'colour'): ?>

	<div class="cms_translate_string_colour">
		<div class="cms_input_colour_helper_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
			<input type="color" class="cms_input_colour_helper cms_translate_string_colour_helper">
		</div>
		<input type="text" class="cms_translate_string_input cms_translate_string_colour_input"
				data-language_id="<?= htmlspecialchars($language_id, ENT_QUOTES) ?>"
				value="<?= htmlspecialchars($value, ENT_QUOTES) ?>">
	</div>

<?php else: ?>

	<input type="text" class="cms_translate_string_input cms_translate_string_text_input"
			data-language_id="<?= htmlspecialchars($language_id, ENT_QUOTES) ?>"
			value="<?= htmlspecialchars($value, ENT_QUOTES) ?>">

<?php endif ?>