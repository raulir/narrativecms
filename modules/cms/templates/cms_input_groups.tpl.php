
<div class="cms_input_area">
	
	<div class="cms_input_content cms_input_groups">

		<div class="cms_input_label"><?= $label ?></div>
		
		<?php _panel('cms_help', ['help' => $help, ]); ?>
		
		<div class="cms_input_groups_values">
			<?php foreach($values as $key => $val): ?>
				<div class="cms_input_groups_value <?= $key == $value ? ' cms_input_groups_value_selected ' : '' ?>" data-value="<?= $key ?>"><?= $val ?></div>
			<?php endforeach ?>
		</div>
		
		<input class="cms_input_groups_input" type="hidden" name="<?= $name ?>" value="<?= $value ?>">

	</div>

</div>
