<?php
// Single-module schema body for updater popup (no page toolbar).
// Expects: $filter_module, $has_errors, $grouped_errors, $panel_table_modules_pending, $latest_fix_errors
$filter_module = $filter_module ?? '';
$has_errors = !empty($has_errors);
$grouped_errors = $grouped_errors ?? [];
$panel_table_modules_pending = $panel_table_modules_pending ?? [];
$latest_fix_errors = $latest_fix_errors ?? [];
?>
<div class="cms_schema_container cms_schema_fragment"
		data-module="<?= htmlspecialchars($filter_module, ENT_QUOTES, 'UTF-8') ?>"
		data-fragment="1">

	<div class="cms_schema_content">

		<?php if (!$has_errors): ?>

			<div class="cms_schema_status cms_schema_status_none">
				No schema updates available for this module
			</div>

			<?php if (in_array($filter_module, $panel_table_modules_pending, true)): ?>
				<div class="cms_schema_panel_tables">
					<div class="cms_schema_panel_tables_title">Panel table data</div>
					<div class="cms_schema_panel_tables_row">
						<div class="cms_schema_panel_tables_module"><?= htmlspecialchars($filter_module, ENT_QUOTES, 'UTF-8') ?></div>
						<div class="cms_schema_sync cms_small_button"
						     data-module="<?= htmlspecialchars($filter_module, ENT_QUOTES, 'UTF-8') ?>">
							sync panel tables
						</div>
					</div>
				</div>
			<?php endif ?>

		<?php else: ?>

			<?php foreach ($grouped_errors as $module => $items): ?>
				<?php include __DIR__.'/module_section.tpl.php'; ?>
			<?php endforeach ?>

		<?php endif ?>

		<?php if (!empty($latest_fix_errors)): ?>

			<div class="cms_schema_latest_errors">
				<div class="cms_schema_latest_errors_title">Latest fix errors</div>
				<div class="cms_schema_latest_errors_items">
					<?php foreach ($latest_fix_errors as $fix_error): ?>
						<div class="cms_schema_item_row cms_schema_latest_errors_row">
							<div class="cms_schema_latest_errors_module">
								<?= htmlspecialchars($fix_error['module'] ?? '') ?>
							</div>
							<div class="cms_schema_location">
								<?= htmlspecialchars($fix_error['key'] ?? '') ?>
							</div>
							<div class="cms_schema_description cms_schema_latest_errors_message">
								<?= htmlspecialchars($fix_error['message'] ?? '') ?>
								<?php if (!empty($fix_error['sql'])): ?>
									<div class="cms_schema_latest_errors_sql"><?= htmlspecialchars($fix_error['sql']) ?></div>
								<?php endif ?>
							</div>
						</div>
					<?php endforeach ?>
				</div>
			</div>

		<?php endif ?>

	</div>
</div>
