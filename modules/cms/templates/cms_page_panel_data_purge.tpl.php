<?php
	$heading = 'Orphaned panel data purge';
	$save_button = '';
	include __DIR__.'/cms_popup_toolbar.tpl.php';

	$scan = $scan ?? [];
	$panel_fields = $scan['panel_fields'] ?? [];
	$settings_fields = $scan['settings_fields'] ?? [];
	$languages = $scan['languages'] ?? [];
	$has_orphans = !empty($has_orphans);
?>
<div class="cms_page_panel_data_purge_container" data-cms_page_panel_id="<?= (int)$cms_page_panel_id ?>">

	<div class="cms_page_panel_data_purge_content">

		<?php if (!$has_orphans): ?>

			<div class="cms_page_panel_data_purge_empty">No orphaned data to purge.</div>

		<?php else: ?>

			<?php if (!empty($panel_fields)): ?>
				<div class="cms_page_panel_data_purge_section">
					<div class="cms_page_panel_data_purge_section_title">Panel</div>
					<?php foreach ($panel_fields as $name => $preview): ?>
						<div class="cms_page_panel_data_purge_row" data-group="panel_fields">
							<div class="cms_page_panel_data_purge_col_check">
								<span class="cms_page_panel_data_purge_fake_check">[v]</span>
								<input type="checkbox" class="cms_page_panel_data_purge_opt_hidden"
										name="panel_fields[]"
										value="<?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?>" checked>
							</div>
							<div class="cms_page_panel_data_purge_col_label cms_page_panel_data_purge_settings_text">
								<span class="cms_page_panel_data_purge_key"><?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?></span>:
								<span class="cms_page_panel_data_purge_val"><?= htmlspecialchars((string)$preview, ENT_QUOTES, 'UTF-8') ?></span>
							</div>
						</div>
					<?php endforeach ?>
				</div>
			<?php endif ?>

			<?php if (!empty($settings_fields)): ?>
				<div class="cms_page_panel_data_purge_section">
					<div class="cms_page_panel_data_purge_section_title">Settings</div>
					<?php foreach ($settings_fields as $name => $preview): ?>
						<div class="cms_page_panel_data_purge_row" data-group="settings_fields">
							<div class="cms_page_panel_data_purge_col_check">
								<span class="cms_page_panel_data_purge_fake_check">[v]</span>
								<input type="checkbox" class="cms_page_panel_data_purge_opt_hidden"
										name="settings_fields[]"
										value="<?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?>" checked>
							</div>
							<div class="cms_page_panel_data_purge_col_label cms_page_panel_data_purge_settings_text">
								<span class="cms_page_panel_data_purge_key"><?= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') ?></span>:
								<span class="cms_page_panel_data_purge_val"><?= htmlspecialchars((string)$preview, ENT_QUOTES, 'UTF-8') ?></span>
							</div>
						</div>
					<?php endforeach ?>
				</div>
			<?php endif ?>

			<?php if (!empty($languages)): ?>
				<div class="cms_page_panel_data_purge_section">
					<div class="cms_page_panel_data_purge_section_title">Translations</div>
					<?php foreach ($languages as $lid): ?>
						<div class="cms_page_panel_data_purge_row" data-group="languages">
							<div class="cms_page_panel_data_purge_col_check">
								<span class="cms_page_panel_data_purge_fake_check">[v]</span>
								<input type="checkbox" class="cms_page_panel_data_purge_opt_hidden"
										name="languages[]"
										value="<?= htmlspecialchars((string)$lid, ENT_QUOTES, 'UTF-8') ?>" checked>
							</div>
							<div class="cms_page_panel_data_purge_col_label cms_page_panel_data_purge_settings_text">
								<span class="cms_page_panel_data_purge_key"><?= htmlspecialchars((string)$lid, ENT_QUOTES, 'UTF-8') ?></span>
							</div>
						</div>
					<?php endforeach ?>
				</div>
			<?php endif ?>

		<?php endif ?>

	</div>

	<?php if ($has_orphans): ?>
		<div class="cms_page_panel_data_purge_footer">
			<div class="cms_tool_button cms_page_panel_data_purge_run">Purge</div>
		</div>
	<?php endif ?>

</div>
