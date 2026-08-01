<?php
	$sticky = !empty($sticky) && is_array($sticky) ? $sticky : [];
	$sticky_keys = !empty($sticky_keys) && is_array($sticky_keys) ? $sticky_keys : array_keys($sticky);
	$value = !empty($value) && is_array($value) ? $value : [];
	$values = !empty($values) && is_array($values) ? $values : [];
?>
<div class="cms_input cms_input_multi <?= !empty($mandatory) ? ' cms_input_mandatory ' : '' ?>" data-name="<?= $name ?>"
		data-bg="<?= $GLOBALS['config']['base_url'] ?>modules/cms/img/cms_drag.png"
		data-sticky="<?= htmlspecialchars(implode(',', $sticky_keys), ENT_QUOTES) ?>"
		data-cms_input_height="4">

	<label><?= $label ?></label>
	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="cms_input_multi_values">
		<?php foreach($value as $key): ?>
			<?php if (!array_key_exists($key, $values)): ?>
				<?php continue; ?>
			<?php endif ?>
			<?php $is_sticky = in_array((string)$key, array_map('strval', $sticky_keys), true); ?>

			<div class="cms_input_multi_item<?= $is_sticky ? ' cms_input_multi_item_sticky' : '' ?>">
				<input type="hidden" name="<?= $name ?>[]" value="<?= htmlspecialchars((string)$key, ENT_QUOTES) ?>">
				<div class="cms_input_multi_item_label"><?= htmlspecialchars((string)$values[$key], ENT_QUOTES) ?></div>
			</div>

		<?php endforeach ?>
	</div>

	<div class="cms_input_multi_bottom">
		<div class="cms_input_button cms_input_multi_add">Add</div>
		<select class="cms_input_multi_select">
			<?php foreach($values as $key => $item): ?>
				<?php if (in_array((string)$key, array_map('strval', $sticky_keys), true)): ?>
					<?php continue; ?>
				<?php endif ?>
				<?php if (!in_array($key, $value) && !in_array((string)$key, array_map('strval', $value), true)): ?>
					<option value="<?= htmlspecialchars((string)$key, ENT_QUOTES) ?>"><?= htmlspecialchars((string)$item, ENT_QUOTES) ?></option>
				<?php endif ?>
			<?php endforeach ?>
		</select>
	</div>

</div>
