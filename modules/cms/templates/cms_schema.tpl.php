<?php if (!empty($fragment)): ?>

	<?php include __DIR__.'/cms_schema/fragment.tpl.php'; ?>

<?php else: ?>

<div class="cms_schema_container">
	<div class="cms_schema_content">
		
		<div class="cms_toolbar">
			<div class="cms_tool_text">Database schema</div>
			<div class="cms_schema_dump_structure cms_tool_button cms_right">Structure</div>
		</div>
		
		<?php if (!$has_errors): ?>
			
			<div class="cms_schema_status cms_schema_status_ok">
				All database tables and data match the schema definition files
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
								<?= $fix_error['module'] ?? '' ?>
							</div>
							<div class="cms_schema_location">
								<?= $fix_error['key'] ?? '' ?>
							</div>
							<div class="cms_schema_description cms_schema_latest_errors_message">
								<?= $fix_error['message'] ?? '' ?>
								<?php if (!empty($fix_error['sql'])): ?>
									<div class="cms_schema_latest_errors_sql"><?= $fix_error['sql'] ?></div>
								<?php endif ?>
							</div>
						</div>
					<?php endforeach ?>
				</div>
			</div>

		<?php endif ?>

		<?php if (!empty($structure_dump) && is_array($structure_dump)): ?>

			<div class="cms_schema_structure_dump">
				<div class="cms_schema_structure_dump_title">
					Live module table structure (<?= count($structure_dump) ?> table<?= count($structure_dump) === 1 ? '' : 's' ?> — {module}_* for installed modules)
				</div>
				<?php foreach ($structure_dump as $row): ?>
					<div class="cms_schema_structure_dump_table">
						<div class="cms_schema_structure_dump_name"><?= $row['name'] ?? '' ?></div>
						<pre class="cms_schema_structure_dump_create"><?= $row['create'] ?? '' ?></pre>
					</div>
				<?php endforeach ?>
			</div>

		<?php endif ?>
		
	</div>
</div>

<?php endif ?>
