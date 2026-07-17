<?php
// Same select pattern as cms/cms_input_select
?>
<div class="cms_input cms_input_select shop_cms_input_provides <?= !empty($mandatory_class) ? $mandatory_class : '' ?>
		<?= !empty($extra_class) ? $extra_class : '' ?>"
		data-cms_input_height="1"
		data-service="<?= htmlspecialchars($service ?? ($params['service'] ?? 'shop_checkout'), ENT_QUOTES, 'UTF-8') ?>">

	<label for="select_<?= $name_clean ?>"><?= $label ?></label>

	<?php _panel('cms/cms_help', ['help' => !empty($help) ? $help : '', ]); ?>

	<select class="cms_input_select_select <?= !empty($name) ? $name : '' ?>"
			name="<?= !empty($name) ? $name : '' ?>" id="select_<?= $name_clean ?>"
			<?= !empty($readonly) ? ' style="pointer-events:none" tabindex="-1" aria-disabled="true" ' : '' ?>>

		<?php foreach(($values ?? []) as $key => $key_label): ?>
			<option value="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>"<?= (string)$key === (string)$value ? ' selected="selected"' : '' ?>><?= htmlspecialchars((string)$key_label, ENT_QUOTES, 'UTF-8') ?></option>
		<?php endforeach ?>

	</select>

</div>
