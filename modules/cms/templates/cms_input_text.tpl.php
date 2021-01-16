<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>" 
		<?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

	<div class="cms_input cms_input_text <?= !empty($extra_class) ? $extra_class : '' ?> <?= !empty($mandatory_class) ? $mandatory_class : '' ?>"
			data-cms_input_height="1">
	
		<label for="<?= (!empty($base_id) ? $base_id.'_' : '').$name_clean ?>"><?= $label ?></label>
		
		<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>
		
		<?php if (!empty($translate) && !empty($GLOBALS['language'])): ?>
			<div class="cms_translate_icon" <?php _ib('cms/cms_translate.png', 12) ?>></div>
		<?php endif ?>
		
		<input id="<?= (!empty($base_id) ? $base_id.'_' : '').$name_clean ?>" type="text" 
				class="cms_input_text_input <?= !empty($max_chars_class) ? $max_chars_class : '' ?> <?= !empty($meta_class) ? $meta_class : '' ?>"
				<?= !empty($extra_data) ? $extra_data : '' ?> name="<?= $name ?>" value="<?= !is_array($value) ? $value : '' ?>">
	
	</div>
	
</div>
