
<div class="cms_input_area">
	
	<div class="cms_input_content cms_input_select">

		<div class="cms_input_label"><?= $label ?></div>
		
		<?php _panel('cms/cms_help', ['help' => $help, ]); ?>
		
		<select name="<?= $name ?>">
			<?php foreach($values as $key => $val): ?>
				<option value="<?= $key ?>"<?= $key == $value ? ' selected="selected" ' : '' ?>><?= $val ?></option>
			<?php endforeach ?>
		</select>

	</div>

</div>
