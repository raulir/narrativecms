<?php if (!empty($panel_name)): ?>

	<?php if ($input_container === 'none'): ?>

		<?= _panel($panel_name, $input_params) ?>

	<?php else: ?>

		<div class="cms_input_container<?= $groups ? ' cms_input_container_groups' : '' ?>"<?= $groups ? ' data-groups="'.implode(',', $groups).'"' : '' ?>>

			<?= _panel($panel_name, $input_params) ?>

		</div>

	<?php endif ?>

<?php else: ?>

	<?php _html_error('Undefined cms input: "'.($type ?? '').'"') ?>

<?php endif ?>
