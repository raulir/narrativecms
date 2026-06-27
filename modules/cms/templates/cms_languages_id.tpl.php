<div class="cms_languages_id_container">
	<?php if (!empty($readonly)): ?>
		<div class="cms_languages_id_readonly"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></div>
	<?php else: ?>
		<input class="cms_languages_id_input" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
				data-item_id="<?= $item_id ?>"
				data-name="language_id"
				<?= !empty($base_id) ? ' data-base_id="'.$base_id.'" ' : '' ?>
				<?= !empty($ds) ? ' data-ds="'.$ds.'" ' : '' ?>>
	<?php endif ?>
</div>