<?php
	$name = $row['name'] ?? '';
	$name_attr = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
	$version = $row['version'] ?? '';
	$version_time = !empty($row['version_time']) ? (int)$row['version_time'] : 0;
?>
<div class="cms_update_row" data-area="<?= $name_attr ?>">
	<div class="cms_update_cell"><?= $name_attr ?></div>
	<div class="cms_update_cell">not installed</div>
	<div class="cms_update_cell">
		<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
		<?= $version_time ? ' - '.date('j M Y', $version_time) : '' ?>
	</div>
	<div class="cms_update_cell cms_update_cell_right">
		<div class="cms_update_install_button cms_tool_button" data-area="<?= $name_attr ?>">Install</div>
	</div>
</div>
