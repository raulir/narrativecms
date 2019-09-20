<?php if(empty($ajax)): ?>
	<div class="cms_toolbar">
	
		<div class="cms_tool_text">
			<?php print('System update'); ?>
		</div>

	</div>
	
	<div>

		<div class="cms_update_table">
			<div class="cms_update_row">
				<div class="cms_update_head">Module</div>
				<div class="cms_update_head">Local</div>
				<div class="cms_update_head">Master</div>
				<div class="cms_update_head">Status</div>
			</div>
			
			<?php foreach($data as $row): ?>
				<div class="cms_update_row">
					<div class="cms_update_cell"><?= !empty($row['module']) ? $row['module'] : 'BC CMS' ?></div>
					<div class="cms_update_cell">
						<?= $row['local_version'] ?> <?= !empty($row['local_updated']) ? date('(Y-m-d H:i)', $row['local_updated']) : '' ?><br>
						#<?= $row['local_hash'] ?>
					</div>
					<div class="cms_update_cell">
						<?php if(!in_array($row['module'], $GLOBALS['config']['update']['master'])): ?>
							<?= $row['master_version'] ?> <?= !empty($row['master_time']) ? date('(Y-m-d H:i)', $row['master_time']) : '' ?><br>
							#<?= $row['master_hash'] ?>
						<?php endif ?>
					</div>
					<div class="cms_update_cell">
						<?php if(((!empty($GLOBALS['config']['update']['allow']) && (in_array($row['module'], $GLOBALS['config']['update']['allow']))) 
								|| empty($GLOBALS['config']['update']['allow']) )
								&& !empty($row['master_hash'])
								&& $row['local_hash'] !== $row['master_hash']): ?>
								
							<div class="cms_update_button cms_tool_button admin_right" data-module="<?= $row['module'] ?>">Update</div>
						
						<?php endif ?>
					</div>
				</div>
			<?php endforeach ?>

		<div class="cms_update_result"></div>

	</div>
	
<?php else: ?>
	<pre><?php print_r($result); ?></pre>
<?php endif ?>