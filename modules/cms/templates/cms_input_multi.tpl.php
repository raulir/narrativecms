<div class="cms_input cms_input_multi <?= !empty($mandatory) ? ' cms_input_mandatory ' : '' ?>" data-name="<?= $name ?>"
		data-bg="<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/drag.png">

	<label for="<?= $name_clean ?>"><?= $label ?></label>
	<?php if (!empty($help)) _panel('cms_help', ['help' => $help, ]); ?>

	<div class="cms_input_multi_values">
		<?php foreach($value as $key): ?>

		    	<div class="cms_input_multi_item">
						
					<input type="hidden" name="<?= $name ?>[]" value="<?= $key ?>">
					
					<div class="cms_input_multi_item_label"><?= $values[$key] ?></div>
				
				</div>
			
		<?php endforeach ?>
	</div>

	<div class="cms_input_multi_bottom">
		<div class="cms_input_button cms_input_multi_add">Add</div>
		<select class="cms_input_multi_select">
			<?php foreach($values as $key => $item): ?>
				<?php if (!in_array($key, $value)): ?>
					<option value="<?= $key ?>"><?= $item ?></option>
				<?php endif ?>
			<?php endforeach ?>
		</select>
	</div>

</div>
