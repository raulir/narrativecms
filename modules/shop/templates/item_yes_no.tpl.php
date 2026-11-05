<div class="item_yes_no_container">
	<select class="item_yes_no_select" data-item_id="<?= $item_id ?>" data-field="<?= $field ?>">
		<option value="1"<?php if ((string)$value === '1'): ?> selected="selected"<?php endif ?>>Yes</option>
		<option value="0"<?php if ((string)$value === '0'): ?> selected="selected"<?php endif ?>>No</option>
	</select>
</div>
