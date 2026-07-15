<?php if(empty($GLOBALS['config']['update']['allow'])): ?>
	<div class="cms_toolbar">
		<div class="cms_tool_text">System modules update</div>
	</div>
	<div class="cms_update_message">
		Updates for this installation are disabled.<br>
		In case you need to update, please use other means or contact webmaster.
		<?php if(!empty($GLOBALS['config']['email'])): ?>
			<br><?= $GLOBALS['config']['email'] ?>
		<?php endif ?>
	</div>
<?php elseif(empty($ajax)): ?>
	<div class="cms_toolbar">
	
		<div class="cms_tool_text">System modules update</div>

	</div>
	
	<div>

		<div class="cms_update_table cms_update_table_installed">
			<div class="cms_update_row">
				<div class="cms_update_head">Module</div>
				<div class="cms_update_head">Local</div>
				<div class="cms_update_head">Master</div>
				<div class="cms_update_head cms_update_cell_right"></div>
			</div>

			<?php
				$rows_local_only = $rows_local_only ?? [];
				$row_core = $row_core ?? null;
				$rows_modules = $rows_modules ?? [];
			?>

			<?php foreach($rows_local_only as $row): ?>
				<?php include __DIR__.'/cms_update_row.tpl.php'; ?>
			<?php endforeach ?>

			<?php if(!empty($row_core)): ?>
				<?php $row = $row_core; include __DIR__.'/cms_update_row.tpl.php'; ?>
			<?php endif ?>

			<?php foreach($rows_modules as $row): ?>
				<?php include __DIR__.'/cms_update_row.tpl.php'; ?>
			<?php endforeach ?>

		</div>

		<?php if(!empty($available)): ?>

			<div class="cms_update_section_label">Available to install</div>

			<div class="cms_update_table cms_update_table_available">
				<div class="cms_update_row">
					<div class="cms_update_head">Module</div>
					<div class="cms_update_head">Local</div>
					<div class="cms_update_head">Master</div>
					<div class="cms_update_head cms_update_cell_right"></div>
				</div>

				<?php foreach($available as $row): ?>
					<div class="cms_update_row" data-area="<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
						<div class="cms_update_cell"><?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
						<div class="cms_update_cell">not installed</div>
						<div class="cms_update_cell">
							<?= htmlspecialchars($row['version'] ?? '', ENT_QUOTES, 'UTF-8') ?>
							<?= !empty($row['version_time']) ? date('(Y-m-d H:i)', (int)$row['version_time']) : '' ?>
						</div>
						<div class="cms_update_cell cms_update_cell_right">
							<div class="cms_update_install_button cms_tool_button"
									data-area="<?= htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">Install</div>
						</div>
					</div>
				<?php endforeach ?>

			</div>

		<?php endif ?>
	
	</div>
	
<?php else: ?>
	<pre><?php print_r($result); ?></pre>
<?php endif ?>
