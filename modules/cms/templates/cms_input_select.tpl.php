<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>" 
		<?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

	<div class="cms_input cms_input_select <?= !empty($mandatory_class) ? $mandatory_class : '' ?>
			<?= !empty($extra_class) ? $extra_class : '' ?>"
			<?php if(!empty($extra_data)): ?>
				<?php foreach($extra_data as $data_key => $data_data): ?>
					data-<?= $data_key ?>="<?= $data_data ?>"
				<?php endforeach ?>
			<?php endif ?>
			data-cms_input_height="1">
	
		<label for="select_<?= $name_clean ?>"><?= $label ?></label> 
	
		<?php _panel('cms_help', ['help' => !empty($help) ? $help : '', ]); ?>
	
		<select class="cms_input_select_select <?= !empty($name) ? $name : '' ?>" name="<?= !empty($name) ? $name : '' ?>" id="select_<?= $name_clean ?>">
			<?php foreach($values as $key => $val): ?>
				<option value="<?php print($key); ?>"<?php print($key == $value ? ' selected="selected"' : ''); ?>><?php print($val); ?></option>
			<?php endforeach ?>
		</select>
	
	</div>
	
</div>