<div class="cms_input cms_input_select <?= !empty($mandatory_class) ? $mandatory_class : '' ?>
		<?= !empty($extra_class) ? $extra_class : '' ?><?= !empty($readonly) ? ' cms_input_select_readonly ' : '' ?>"
		<?php if(!empty($extra_data)): ?>
			<?php foreach($extra_data as $data_key => $data_data): ?>
				data-<?= $data_key ?>="<?= $data_data ?>"
			<?php endforeach ?>
		<?php endif ?>
		data-cms_input_height="1">

	<?php cms_input_label($label, $help ?? '', 'select_'.$name_clean) ?>

	<select class="cms_input_select_select <?= !empty($name) ? $name : '' ?>"
			name="<?= !empty($name) ? $name : '' ?>" id="select_<?= $name_clean ?>"
			<?= !empty($readonly) ? ' style="pointer-events:none" tabindex="-1" aria-disabled="true" ' : '' ?>>

		<?php foreach($values as $key => $key_label): ?>
			<option value="<?= $key ?>"<?= $key == $value ? ' selected="selected"' : '' ?>><?= $key_label ?></option>
		<?php endforeach ?>

	</select>

</div>
