<?php if (!empty($panel_name)): ?>

	<?= _panel($panel_name, $params) ?>

<?php else: ?>

	<?= html_error('Undefined cms input: "'.$params['type'].'"') ?>
	
<?php endif ?>
