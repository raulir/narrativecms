<?php
// One module block for full schema page and updater popup fragment.
// Expects: $module, $items, $panel_table_modules_pending (array)
$module = $module ?? '';
$items = $items ?? [];
$panel_table_modules_pending = $panel_table_modules_pending ?? [];
?>
<div class="cms_schema_module" data-module="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>">

	<div class="cms_schema_module_header">
		<div class="cms_schema_module_title">
			<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>
		</div>
		<div class="cms_schema_module_actions">
			<?php if (in_array($module, $panel_table_modules_pending, true)): ?>
				<div class="cms_schema_sync cms_small_button"
				     data-module="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>">
					sync panel tables
				</div>
			<?php endif ?>
			<div class="cms_schema_fix cms_small_button"
			     data-key="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>">
				fix module
			</div>
		</div>
	</div>

	<div class="cms_schema_items">

		<?php foreach ($items as $item): ?>
			<div class="cms_schema_item_row">
				<div class="cms_schema_location">
					<?= htmlspecialchars($item['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>
				</div>
				<div class="cms_schema_description">
					<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
				</div>
				<div class="cms_schema_action">
					<?php if (!empty($item['enabled'])): ?>
						<div class="cms_schema_fix cms_small_button"
						     data-key="<?= htmlspecialchars($item['key'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
							fix
						</div>
					<?php endif ?>
				</div>
			</div>
		<?php endforeach ?>

	</div>

</div>
