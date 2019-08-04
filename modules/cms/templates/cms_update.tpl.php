<?php if(empty($ajax)): ?>
	<div class="cms_toolbar">
	
		<div class="cms_tool_text">
			<?php print('System update'); ?>
		</div>
		
		<?php if($can_update && !empty($GLOBALS['config']['update']['allow_updates']) && !empty($master_version)): ?>
			<div class="cms_update_button cms_tool_button admin_right">Update</div>
		<?php endif ?>
	
	</div>
	
	<div>
		<?php if(!empty($GLOBALS['config']['update']['allow_updates'])): ?>
		
			<div class="cms_update_table">
				<div class="cms_update_row">
					<div class="cms_update_label">							</div>
					<div class="cms_update_version">Version					</div>
					<div class="cms_update_hash">Hash						</div>
				</div>
				<div class="cms_update_row">
					<div class="cms_update_label">Last updated <?= !empty($update_time) ? date('(Y-m-d H:i)', $update_time) : '' ?></div>
					<div class="cms_update_version"><?= $local_version ?> <?= !empty($version_time) ? date('(Y-m-d H:i)', $version_time) : '' ?>	</div>
					<div class="cms_update_hash">#<?= $local_hash ?>		</div>
				</div>
				<div class="cms_update_row">
					<div class="cms_update_label">Current state:			</div>
					<div class="cms_update_version"><?= $current_version ?>	</div>
					<div class="cms_update_hash">#<?= $current_hash ?>	</div>
				</div>
				<div class="cms_update_row">
					<div class="cms_update_label">Master version:			</div>
					<div class="cms_update_version"><?= $master_version ?> <?= !empty($master_time) ? date('(Y-m-d H:i)', $master_time) : '' ?></div>
					<div class="cms_update_hash">#<?= $master_hash ?>		</div>
				</div>
			</div>
		
			<?php if(empty($master_version)): ?>
				<span class="local_changes_warning">Can't connect to update server. Please check CMS settings.</span><br>
			<?php elseif(!empty($local_changes)): ?>
				<span class="local_changes_warning">There are local changes, update will revert these!</span><br>
			<?php endif ?>
			<div class="cms_update_result"></div>
		<?php else: ?>
			<?php if(!empty($GLOBALS['config']['update']['is_master'])): ?>
				This is master version: <span class="local_version"><?php print($local_version); ?></span><br>
			<?php endif ?>
			System updates through CMS are disabled for this environment. This installation is updated by other means.
		<?php endif ?>
	</div>
	
<?php else: ?>
	<pre><?php print_r($result); ?></pre>
<?php endif ?>