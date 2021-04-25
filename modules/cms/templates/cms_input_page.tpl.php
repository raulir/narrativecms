<div class="cms_input cms_input_content cms_input_select" data-cms_input_height="1">
	
	<div class="cms_input_content cms_input_select">

		<div class="cms_input_label"><?= $label ?></div>
		
		<?php _panel('cms/cms_help', ['help' => $help, ]); ?>
		
		<select class="cms_input_select_select" name="<?= $name ?>">
		<option value="">-- page --</option>
			<?php foreach($values as $val): ?>
				<option value="<?= $val['cms_page_id'] ?>"<?= $val['cms_page_id'] == $value ? ' selected="selected" ' : '' ?>><?= $val['title'] ?></option>
			<?php endforeach ?>
		</select>

	</div>

</div>
