<?php if (!empty($fragment)): ?>

	<?php include __DIR__.'/cms_schema/fragment.tpl.php'; ?>

<?php else: ?>

<div class="cms_schema_container">
	<div class="cms_schema_content">
		
		<div class="cms_toolbar">
			<div class="cms_tool_text">Database schema</div>
			<div class="cms_toolbar_buttons">
				<!-- intentionally empty – no global fix button -->
			</div>
		</div>

		<?php if (!empty($panel_table_modules_pending)): ?>

			<div class="cms_schema_panel_tables">
				<div class="cms_schema_panel_tables_title">Panel table data</div>
				<?php foreach ($panel_table_modules_pending as $module): ?>
					<div class="cms_schema_panel_tables_row">
						<div class="cms_schema_panel_tables_module"><?= $module ?></div>
						<div class="cms_schema_sync cms_small_button"
						     data-module="<?= $module ?>">
							sync panel tables
						</div>
					</div>
				<?php endforeach ?>
			</div>

		<?php endif ?>
		
		<?php if (!$has_errors): ?>
			
			<div class="cms_schema_status cms_schema_status_ok">
				All database tables match the schema definition files
			</div>
			
		<?php else: ?>
			
			<?php foreach ($grouped_errors as $module => $items): ?>
				<?php include __DIR__.'/cms_schema/module_section.tpl.php'; ?>
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

<?php endif ?>
