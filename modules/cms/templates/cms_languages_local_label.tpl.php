<div class="cms_languages_local_label_container">
	<input class="cms_languages_local_label_input" value="<?= htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8') ?>"
			data-item_id="<?= $item_id ?>"
			data-name="local_label"
			<?= !empty($base_id) ? ' data-base_id="'.$base_id.'" ' : '' ?>
			<?= !empty($ds) ? ' data-ds="'.$ds.'" ' : '' ?>>
</div>