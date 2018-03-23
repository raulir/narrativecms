
<div class="cms_input_area">
	
	<div class="cms_input_content cms_input_select">

		<div class="cms_input_label"><?= $label ?></div>
		
		<?php _panel('cms_help', ['help' => $help, ]); ?>
		
		<select name="<?= $name ?>">
			<?php foreach($values as $val): ?>
				<option value="<?= $val['cms_page_id'] ?>"<?= $val['cms_page_id'] == $value ? ' selected="selected" ' : '' ?>><?= $val['title'] ?></option>
			<?php endforeach ?>
		</select>

	</div>

</div>
