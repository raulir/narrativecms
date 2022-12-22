<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>" 
		<?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

	<div class="cms_input cms_input_text <?= $extra_class ?> <?= $mandatory_class ?> <?= $default_class ?>" data-cms_input_height="1">
	
		<label for="<?= $name_clean ?>"><?= $label ?></label>
		
		<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>
		
		<?php if (!empty($translate) && !empty($GLOBALS['language'])): ?>
			<div class="cms_translate_icon" <?php _ib('cms/cms_translate.png', 14) ?>></div>
		<?php endif ?>
		
		<input id="<?= $name_clean ?>" type="text" class="cms_input_text_input <?= $max_chars_class ?> <?= $meta_class ?> <?= $extra_data ?>"
				name="<?= $name ?>" value="<?= !is_array($value) ? $value : '' ?>">
				
		<?php if(!empty($default_class)): ?>
			<div class="cms_input_text_default" <?php _ib('cms/cms_default.png', 11) ?> data-value="<?= $default ?>"></div>
		<?php endif ?>
	
	</div>
	
</div>
