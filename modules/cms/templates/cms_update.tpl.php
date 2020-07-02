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

		<div class="cms_update_table">
			<div class="cms_update_row">
				<div class="cms_update_head">Module</div>
				<div class="cms_update_head">Local</div>
				<div class="cms_update_head">Master</div>
				<div class="cms_update_head cms_update_cell_right"></div>
			</div>

			<?php foreach($data as $row): ?>
				<div class="cms_update_row">
					<div class="cms_update_cell"><?= !empty($row['area']) ? $row['area'] : 'BC CMS' ?></div>
					<div class="cms_update_cell">
						
						<?php if($row['local_version'] !== '0.0.0'): ?>
							<?= $row['local_version'] ?> <?= date('(Y-m-d H:i)', $row['local_version_time']) ?>
							#<?= substr($row['local_version_hash'], 0, 16) ?>
						<?php else: ?>
							version unknown <?= '#'.substr($row['local_version_hash'], 0, 16) ?>
						<?php endif ?>
						
						<?php if($row['local_current_hash'] !== $row['local_version_hash']): ?>
							<br>local <?= !empty($row['local_updated']) ? date('(Y-m-d H:i)', $row['local_updated']) : '' ?>
							#<?= substr($row['local_current_hash'], 0, 16) ?>
						<?php endif ?>

					</div>
					<div class="cms_update_cell">
						<?php if(!in_array($row['area'], $GLOBALS['config']['update']['master'])): ?>
							<?= $row['master_version'] ?> <?= !empty($row['master_time']) ? date('(Y-m-d H:i)', $row['master_time']) : '' ?>
							#<?= substr($row['master_hash'], 0, 16) ?>
						<?php endif ?>
					</div>
					<div class="cms_update_cell cms_update_cell_right">
						<?php if((!empty($GLOBALS['config']['update']['allow'])
								&& (in_array($row['area'], $GLOBALS['config']['update']['allow']) || ($GLOBALS['config']['update']['allow'][0] == '*') ))
								&& !empty($row['master_hash'])
								&& $row['local_current_hash'] !== $row['master_hash']): ?>
								
							<div class="cms_update_button cms_tool_button" data-area="<?= $row['area'] ?>">Update</div>
						
						<?php endif ?>
					</div>
				</div>
			<?php endforeach ?>

	</div>
	
	<div class="cms_update_result"></div>
	
<?php else: ?>
	<pre><?php print_r($result); ?></pre>
<?php endif ?>