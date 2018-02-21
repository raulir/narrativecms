<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>" <?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

	<div class="cms_input cms_input_text <?= !empty($extra_class) ? $extra_class : '' ?> <?= !empty($mandatory_class) ? $mandatory_class : '' ?>">
	
		<label for="<?= $name_clean ?>"><?= $label ?></label>
		<?php if (!empty($help)) _panel('cms_help', ['help' => $help, ]); ?>
		<input id="<?= $name_clean ?>" type="text" class="<?= !empty($max_chars_class) ? $max_chars_class : '' ?> <?= !empty($meta_class) ? $meta_class : '' ?>"
			<?= !empty($extra_data) ? $extra_data : '' ?> name="<?= $name ?>" value="<?= $value ?>">
	
	</div>
	
</div>
