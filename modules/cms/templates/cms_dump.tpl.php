<div class="cms_dump_container">
	<div class="cms_dump_content">

		<div class="cms_toolbar">
			<div class="cms_tool_text"><?= !empty($page_title) ? $page_title : 'Data and backup' ?></div>
		</div>

		<?php /* —— Generate backup —— */ ?>
		<form class="cms_dump_generate_form" method="post" action="">

			<input type="hidden" name="do" value="generate_backup">

			<div class="cms_dump_section cms_dump_section_generate">

				<div class="cms_dump_section_header">
					<div class="cms_dump_section_title">Generate backup</div>
					<div class="cms_dump_section_actions">
						<div class="cms_tool_button cms_dump_options_btn">Options</div>
						<div class="cms_tool_button cms_dump_generate_btn">Generate</div>
					</div>
				</div>

				<div class="cms_dump_options" style="display: none;">

					<div class="cms_dump_options_columns">

						<div class="cms_dump_col cms_dump_col_tables">
							<div class="cms_dump_col_title">Tables</div>
							<?php foreach (($tables_by_module ?? []) as $module => $tables): ?>
								<div class="cms_dump_module_block">
									<div class="cms_dump_module_title"><?= htmlspecialchars($module) ?></div>
									<?php foreach ($tables as $table): ?>
										<div class="cms_dump_check_row cms_dump_toggle_row">
											<div class="cms_dump_col_check">
												<span class="cms_dump_fake_check">[v]</span>
												<input type="checkbox" class="cms_dump_opt_hidden" name="tables[]"
													value="<?= htmlspecialchars($table) ?>" checked>
											</div>
											<div class="cms_dump_check_label"><?= htmlspecialchars($table) ?></div>
										</div>
									<?php endforeach ?>
								</div>
							<?php endforeach ?>
						</div>

						<div class="cms_dump_col cms_dump_col_resources">
							<div class="cms_dump_col_title">Resources</div>
							<?php foreach (($resources_tree ?? []) as $block): ?>
								<?php if (($block['type'] ?? '') === 'year_months'): ?>
									<?php
										$year_months = $block['months'] ?? [];
										$year_all = !empty($year_months);
										foreach ($year_months as $_m){
											if (empty($_m['selected'])){
												$year_all = false;
												break;
											}
										}
									?>
									<div class="cms_dump_module_block cms_dump_year_block" data-year="<?= htmlspecialchars($block['year'] ?? '') ?>">
										<div class="cms_dump_check_row cms_dump_toggle_row cms_dump_year_row">
											<div class="cms_dump_col_check">
												<span class="cms_dump_fake_check"><?= $year_all ? '[v]' : '[ ]' ?></span>
												<input type="checkbox" class="cms_dump_opt_hidden cms_dump_year_check"
													value="<?= htmlspecialchars($block['year'] ?? '') ?>"
													<?= $year_all ? 'checked' : '' ?>>
											</div>
											<div class="cms_dump_check_label cms_dump_year_label"><?= htmlspecialchars($block['year'] ?? '') ?></div>
										</div>
										<?php foreach ($year_months as $month): ?>
											<div class="cms_dump_check_row cms_dump_toggle_row cms_dump_month_row">
												<div class="cms_dump_col_check">
													<span class="cms_dump_fake_check"><?= !empty($month['selected']) ? '[v]' : '[ ]' ?></span>
													<input type="checkbox" class="cms_dump_opt_hidden cms_dump_month_check" name="resources[]"
														value="<?= htmlspecialchars($month['key'] ?? '') ?>"
														<?= !empty($month['selected']) ? 'checked' : '' ?>>
												</div>
												<div class="cms_dump_check_label"><?= htmlspecialchars($month['label'] ?? '') ?></div>
											</div>
										<?php endforeach ?>
									</div>
								<?php else: ?>
									<div class="cms_dump_check_row cms_dump_toggle_row">
										<div class="cms_dump_col_check">
											<span class="cms_dump_fake_check"><?= !empty($block['selected']) ? '[v]' : '[ ]' ?></span>
											<input type="checkbox" class="cms_dump_opt_hidden" name="resources[]"
												value="<?= htmlspecialchars($block['key'] ?? '') ?>"
												<?= !empty($block['selected']) ? 'checked' : '' ?>>
										</div>
										<div class="cms_dump_check_label"><?= htmlspecialchars($block['label'] ?? '') ?> (full year)</div>
									</div>
								<?php endif ?>
							<?php endforeach ?>

							<div class="cms_dump_resize_row cms_dump_toggle_row">
								<div class="cms_dump_col_check">
									<span class="cms_dump_fake_check">[ ]</span>
									<input type="checkbox" class="cms_dump_opt_hidden" name="resize_images" value="1">
								</div>
								<div class="cms_dump_check_label">Resize images</div>
								<input type="text" class="cms_dump_resize_px" name="resize_max_px"
									value="<?= (int)($resize_max_px ?? 1400) ?>" maxlength="5">
								<span class="cms_dump_resize_px_unit">px</span>
							</div>
						</div>

					</div>

				</div>

			</div>

		</form>

		<?php /* —— Backups and restore —— */ ?>
		<div class="cms_dump_section cms_dump_section_restore">

			<div class="cms_dump_section_header">
				<div class="cms_dump_section_title">Backups and restore</div>
				<div class="cms_dump_section_actions cms_dump_restore_actions">
					<form class="cms_dump_upload_form" method="post" enctype="multipart/form-data">
						<input type="hidden" name="do" value="upload_backup">
						<span class="cms_dump_upload_label">Upload</span>
						<input type="file" name="file" class="cms_dump_upload_file" accept=".zip,application/zip">
						<div class="cms_tool_button cms_dump_upload_btn">Upload</div>
					</form>
					<div class="cms_tool_button cms_dump_backups_btn">Backups</div>
				</div>
			</div>

			<div class="cms_dump_section_body cms_dump_backups_body" style="display: none;">

				<?php if (empty($backups)): ?>
					<div class="cms_dump_empty">No backups in cache/backup/ yet. Generate one above or upload a zip.</div>
				<?php else: ?>
					<div class="cms_dump_backup_list">
						<div class="cms_dump_backup_row cms_dump_backup_row_head">
							<div class="cms_dump_backup_name">File</div>
							<div class="cms_dump_backup_created">Created</div>
							<div class="cms_dump_backup_size">Size</div>
							<div class="cms_dump_backup_meta">Contents</div>
							<div class="cms_dump_backup_actions">Actions</div>
						</div>
						<?php foreach ($backups as $backup): ?>
							<div class="cms_dump_backup_row" data-basename="<?= htmlspecialchars($backup['basename'] ?? '') ?>">
								<div class="cms_dump_backup_name"><?= htmlspecialchars($backup['filename'] ?? '') ?></div>
								<div class="cms_dump_backup_created"><?= htmlspecialchars($backup['created'] ?? '') ?></div>
								<div class="cms_dump_backup_size"><?= htmlspecialchars($backup['size_label'] ?? '') ?></div>
								<div class="cms_dump_backup_meta">
									<?php
										$bits = [];
										if (!empty($backup['tables'])){
											$bits[] = 'tables: '.implode(', ', $backup['tables']);
										}
										if (!empty($backup['resources'])){
											$bits[] = 'resources: '.implode(', ', $backup['resources']);
										}
										if (!empty($backup['resize_images'])){
											$bits[] = 'images ≤ '.((int)$backup['resize_max_px']).'px';
										}
										echo htmlspecialchars(implode(' · ', $bits));
									?>
								</div>
								<div class="cms_dump_backup_actions">
									<form method="post" class="cms_dump_row_form cms_dump_restore_form">
										<input type="hidden" name="do" value="restore_backup">
										<input type="hidden" name="basename" value="<?= htmlspecialchars($backup['basename'] ?? '') ?>">
										<div class="cms_small_button cms_dump_restore_btn">Restore</div>
									</form>
									<form method="post" class="cms_dump_row_form">
										<input type="hidden" name="do" value="download_backup">
										<input type="hidden" name="basename" value="<?= htmlspecialchars($backup['basename'] ?? '') ?>">
										<div class="cms_small_button cms_dump_download_btn">Download</div>
									</form>
									<form method="post" class="cms_dump_row_form cms_dump_delete_form">
										<input type="hidden" name="do" value="delete_backup">
										<input type="hidden" name="basename" value="<?= htmlspecialchars($backup['basename'] ?? '') ?>">
										<div class="cms_small_button cms_dump_delete_btn">Delete</div>
									</form>
								</div>
							</div>
						<?php endforeach ?>
					</div>
				<?php endif ?>

			</div>

		</div>

		<?php /* —— Other functions —— */ ?>
		<div class="cms_dump_section cms_dump_section_other">

			<div class="cms_dump_section_header">
				<div class="cms_dump_section_title">Other functions</div>
			</div>

			<div class="cms_dump_section_body">

				<?php _panel('cms/cms_rebuild_routes', [
						'label' => 'Public URL routes',
						'help' => '[Rebuild routes]||Writes a zipped SQL backup of cms_route to cache/db/, truncates the table, then rebuilds from main pages and all link_target list items (title/heading → slug). Use after bad slug data (e.g. numeric product URLs).',
				]); ?>

				<?php _panel('cms/cms_images_unused_purge', [
						'label' => 'Images older than months',
						'min_months' => 3,
						'category' => '',
						'help' => '[Purge unused images]||Moves unused dated library images (YYYY/MM/…) older than the given months to cache/tmp/img/. Skips module paths (cms/, timmy/, …), parents with children, and any file still referenced in panel params. Use Test to estimate count and disk size first.',
				]); ?>

			</div>

		</div>

	</div>
</div>
