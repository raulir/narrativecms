<?php if (($view ?? '') === 'compare'): ?>
<?php
	$heading = 'Compare with current'.(!empty($version_time) ? ': '.htmlspecialchars($version_time) : '');
	$save_button = '';
	$cancel_label = 'Close';
	include __DIR__.'/cms_popup_toolbar.tpl.php';
?>
<div class="cms_page_panel_versions_container cms_page_panel_versions_compare">
	<div class="cms_page_panel_versions_content">

		<?php if (!empty($message)): ?>
			<div class="cms_page_panel_versions_message"><?= htmlspecialchars($message) ?></div>
		<?php else: ?>

			<div class="cms_page_panel_versions_compare_row cms_page_panel_versions_compare_head">
				<div class="cms_page_panel_versions_compare_label">Field</div>
				<div class="cms_page_panel_versions_compare_value">This version</div>
				<div class="cms_page_panel_versions_compare_value">Current</div>
			</div>

			<?php $any = false ?>
			<?php foreach ($groups as $group): ?>
				<?php if ($group['title'] !== ''): ?>
					<div class="cms_page_panel_versions_compare_group">
						<?= htmlspecialchars($group['title']) ?><?= $group['note'] !== '' ? ' — '.htmlspecialchars($group['note']) : '' ?>
					</div>
				<?php endif ?>
				<?php foreach ($group['rows'] as $row): $any = true ?>
					<div class="cms_page_panel_versions_compare_row">
						<div class="cms_page_panel_versions_compare_label"><?= htmlspecialchars($row['label']) ?></div>
						<div class="cms_page_panel_versions_compare_value"><?= htmlspecialchars($row['version']) ?></div>
						<div class="cms_page_panel_versions_compare_value"><?= htmlspecialchars($row['live']) ?></div>
					</div>
				<?php endforeach ?>
			<?php endforeach ?>

			<?php if (!$any && empty($resources)): ?>
				<div class="cms_page_panel_versions_message">No differences in text values.</div>
			<?php endif ?>

			<?php if (!empty($resources)): ?>
				<div class="cms_page_panel_versions_compare_row cms_page_panel_versions_compare_resources">
					<div class="cms_page_panel_versions_compare_label">Resources only in this version</div>
					<div class="cms_page_panel_versions_compare_value cms_page_panel_versions_compare_wide">
						<?php foreach ($resources as $resource): ?>
							<div><?= htmlspecialchars($resource) ?></div>
						<?php endforeach ?>
					</div>
				</div>
			<?php endif ?>

		<?php endif ?>

	</div>
</div>
<?php else: ?>
<?php
	$heading = 'Versions';
	$save_button = '';
	$cancel_label = 'Cancel';
	include __DIR__.'/cms_popup_toolbar.tpl.php';
?>
<div class="cms_page_panel_versions_container cms_page_panel_versions_list" data-version_panel_id="<?= (int)$version_panel_id ?>">
	<div class="cms_page_panel_versions_content">

		<?php if (empty($versions)): ?>
			<div class="cms_page_panel_versions_message">No earlier versions yet. Each save keeps a version of what it replaces.</div>
		<?php else: ?>
			<div class="cms_page_panel_versions_note">Earlier versions, newest first (at most <?= (int)$max_versions ?>).</div>
			<?php foreach ($versions as $version): ?>
				<div class="cms_page_panel_versions_row" data-version_file="<?= htmlspecialchars($version['file'], ENT_QUOTES) ?>">
					<div class="cms_page_panel_versions_time"><?= htmlspecialchars($version['time']) ?></div>
					<div class="cms_page_panel_versions_user"><?= htmlspecialchars($version['user']) ?></div>
					<div class="cms_page_panel_versions_size"><?= htmlspecialchars($version['size']) ?></div>
					<div class="cms_page_panel_versions_actions">
						<div class="cms_tool_button cms_page_panel_versions_compare_button">Compare</div>
						<div class="cms_tool_button cms_page_panel_versions_restore_button">Restore</div>
					</div>
				</div>
			<?php endforeach ?>
		<?php endif ?>

		<div class="cms_page_panel_versions_error"></div>

	</div>
	<div class="cms_page_panel_versions_overlay"><div class="cms_page_panel_versions_overlay_label">Restoring...</div></div>
</div>
<?php endif ?>
