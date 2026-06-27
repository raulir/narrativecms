<div class="cms_grid_editable_container">
	<input class="cms_grid_editable_input" value="<?= $value ?>"
			data-item_id="<?= $item_id ?>"
			data-name="<?= $name ?>"
			<?= !empty($base_id) ? ' data-base_id="'.$base_id.'" ' : '' ?>
			<?= !empty($ds) ? ' data-ds="'.$ds.'" ' : '' ?>>
</div>