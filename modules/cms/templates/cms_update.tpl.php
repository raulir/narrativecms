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
				// Order: Narrative CMS → masters → local-only → installed from remote master
				$row_core = $row_core ?? null;
				$rows_masters = $rows_masters ?? [];
				$rows_local_only = $rows_local_only ?? [];
				$rows_modules = $rows_modules ?? [];
			?>

			<?php if(!empty($row_core)): ?>
				<?php $row = $row_core; include __DIR__.'/cms_update_row.tpl.php'; ?>
			<?php endif ?>

			<?php foreach($rows_masters as $row): ?>
				<?php include __DIR__.'/cms_update_row.tpl.php'; ?>
			<?php endforeach ?>

			<?php foreach($rows_local_only as $row): ?>
				<?php include __DIR__.'/cms_update_row.tpl.php'; ?>
			<?php endforeach ?>

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
					<?php include __DIR__.'/cms_update_available_row.tpl.php'; ?>
				<?php endforeach ?>

			</div>

		<?php endif ?>
	
	</div>
	
<?php else: ?>
	<pre><?php print_r($result); ?></pre>
<?php endif ?>
