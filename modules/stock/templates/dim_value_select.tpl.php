<div class="dim_value_select_container">
	<div class="dim_value_select_content">

		<select class="dim_value_select_select" data-item_id="<?= $item_id ?>" data-dimension="<?= $dimension ?>">
			<?php if(empty($current) || empty($available[$current])): ?>
				<option value="">--</option>
			<?php endif ?>
			<?php foreach ($available as $key => $value): ?>
				<option value="<?= $key ?>" <?= $key == $current ? ' selected="selected" ' : '' ?>><?= $value ?></option>
			<?php endforeach ?>
		</select>

	</div>
</div>