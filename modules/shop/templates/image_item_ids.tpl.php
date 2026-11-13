<div class="cms_input image_item_ids_container" data-cms_input_height="<?= $rows ?>">
	<?php cms_input_label($label ?? '', $help ?? '') ?>
	<input type="hidden" class="image_item_ids_value" name="<?= $name ?>" value="<?= $value_text ?>">
	<?php if (empty($choices)): ?>
		<div class="image_item_ids_empty">No variants for this product</div>
	<?php else: ?>
		<?php foreach ($choices as $id => $choice_label): ?>
			<div class="image_item_ids_choice">
				<input type="checkbox" class="image_item_ids_check" value="<?= (int)$id ?>"<?php if (in_array((string)$id, $selected, true)): ?> checked="checked"<?php endif ?>>
				<div class="image_item_ids_label"><?= $choice_label ?></div>
			</div>
		<?php endforeach ?>
	<?php endif ?>
</div>
