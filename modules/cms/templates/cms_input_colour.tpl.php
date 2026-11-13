<div class="cms_input cms_input_colour <?= $mandatory_class ?> <?= $default_class ?>" data-cms_input_height="1">

	<?php cms_input_label($label, $help ?? '', $name_clean, !empty($translate) && empty($readonly) ? 'colour' : '') ?>

	<div class="cms_input_colour_helper_area" <?php _ib('cms/cms_opacity.png', 40) ?>>
		<input type="color" class="cms_input_colour_helper">
	</div>

	<input id="<?= $name_clean ?>" type="text" class="cms_input_colour_input" name="<?= $name ?>" value="<?= $value ?>">

	<?php if(!empty($default_class)): ?>
		<div class="cms_input_colour_default" <?php _ib('cms/cms_default.png', 11) ?> data-value="<?= $default ?>"></div>
	<?php endif ?>

</div>
