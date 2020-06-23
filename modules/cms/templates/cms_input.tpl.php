<?php if (!empty($panel_name)): ?>

	<div class="cms_input_container <?= !empty($params['groups']) ? ' cms_input_container_groups ' : '' ?>"
			<?= !empty($params['groups']) ? ' data-groups="'.implode(',', $params['groups']).'" ' : '' ?>>

		<?= _panel($panel_name, $params) ?>

	</div>
	
<?php else: ?>

	<?= html_error('Undefined cms input: "'.$params['type'].'"') ?>
	
<?php endif ?>
