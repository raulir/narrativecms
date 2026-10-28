<div class="cms_languages_livestatus_container">
	<select class="cms_languages_livestatus_input"
			data-item_id="<?= (int)$item_id ?>"
			data-name="livestatus"
			<?= !empty($base_id) ? ' data-base_id="'.(int)$base_id.'" ' : '' ?>
			<?= !empty($ds) ? ' data-ds="'.htmlspecialchars($ds, ENT_QUOTES, 'UTF-8').'" ' : '' ?>>
		<option value="1"<?= $value === '1' ? ' selected' : '' ?>>Live</option>
		<option value="0"<?= $value !== '1' ? ' selected' : '' ?>>Test</option>
	</select>
</div>
