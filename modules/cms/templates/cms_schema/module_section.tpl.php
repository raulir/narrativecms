<?php
// One module block — full schema page + updater embed.
// Expects: $module, $items
$module = $module ?? '';
$items = $items ?? [];
?>
<div class="cms_schema_module" data-module="<?= $module ?>">

	<div class="cms_schema_module_header">
		<div class="cms_schema_module_title">
			<?= $module ?>
		</div>
		<div class="cms_schema_module_actions">
			<div class="cms_schema_fix cms_small_button"
			     data-key="<?= $module ?>">
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
