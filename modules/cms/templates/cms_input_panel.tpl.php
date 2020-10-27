
<div class="cms_input_area">
	
	<div class="cms_input cms_input_content cms_input_select" data-cms_input_height="1">

		<div class="cms_input_label"><?= $label ?></div>
		
		<?php _panel('cms/cms_help', ['help' => $help, ]); ?>
		
		<select class="cms_input_select_select" name="<?= $name ?>">
			<option value="">-- panel --</option>
			<?php foreach($values as $key => $val): ?>
				<option value="<?= $key ?>"<?= $key == $value ? ' selected="selected" ' : '' ?>><?= $val ?></option>
			<?php endforeach ?>
		</select>

	</div>

</div>
