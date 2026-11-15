<?php
// Single-module schema body for updater popup (no page toolbar).
// Expects: $filter_module, $has_errors, $grouped_errors, $latest_fix_errors
$filter_module = $filter_module ?? '';
$has_errors = !empty($has_errors);
$grouped_errors = $grouped_errors ?? [];
$latest_fix_errors = $latest_fix_errors ?? [];
?>
<div class="cms_schema_container cms_schema_fragment"
		data-module="<?= $filter_module ?>"
		data-fragment="1">

	<div class="cms_schema_content">

		<?php if (!$has_errors): ?>

			<div class="cms_schema_status cms_schema_status_none">
				No schema updates available for this module
			</div>

		<?php else: ?>

			<?php foreach ($grouped_errors as $module => $items): ?>
				<?php include __DIR__.'/module_section.tpl.php'; ?>
			<?php endforeach ?>

		<?php endif ?>

		<?php if (!empty($latest_fix_errors)): ?>
			<?php include __DIR__.'/latest_errors.tpl.php'; ?>
		<?php endif ?>

	</div>
</div>
