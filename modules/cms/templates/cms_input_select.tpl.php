<div class="cms_input admin_input_dropdown <?php print(!empty($extra_class) ? $extra_class : ''); ?>" style="display: block;">

	<label for="select_<?php print($name_clean); ?>"><?php print($label); ?></label> 

	<?php _panel('cms_help', ['help' => !empty($help) ? $help : '', ]); ?>

	<select class="<?= $name ?>" name="<?php print($name); ?>" id="select_<?php print($name_clean); ?>">
		<?php foreach($values as $key => $val): ?>
			<option value="<?php print($key); ?>"<?php print($key == $value ? ' selected="selected"' : ''); ?>><?php print($val); ?></option>
		<?php endforeach ?>
	</select>

</div>