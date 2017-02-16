<?php if(empty($ajax)): ?>
	<div class="cms_toolbar">
	
		<div class="admin_tool_text">
			<?php print('System update'); ?>
		</div>
		
		<?php if($can_update && !empty($GLOBALS['config']['update']['allow_updates'])): ?>
			<div class="cms_update_button admin_tool_button admin_right">Update</div>
		<?php endif ?>
	
	</div>
	
	<div>
		<?php if(!empty($GLOBALS['config']['update']['allow_updates'])): ?>
			Your version: <span class="local_version"><?php print($local_version); ?></span><br>
			Latest version: <span class="master_version"><?php print($master_version); ?></span><br>
			<?php if(!empty($local_changes)): ?>
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