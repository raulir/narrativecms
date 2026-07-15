<?php
	$area_attr = htmlspecialchars($row['area'] ?? '', ENT_QUOTES, 'UTF-8');
	$label = $row['label'] ?? ((!empty($row['area'])) ? $row['area'] : 'Narrative CMS');
?>
<div class="cms_update_row" data-area="<?= $area_attr ?>">
	<div class="cms_update_cell"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
	<div class="cms_update_cell"><?= htmlspecialchars($row['local_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
	<?php if(!empty($row['error'])): ?>
		<div class="cms_update_cell cms_update_error"><?= htmlspecialchars($row['error'], ENT_QUOTES, 'UTF-8') ?></div>
		<div class="cms_update_cell cms_update_cell_right"></div>
	<?php else: ?>
		<div class="cms_update_cell"><?= htmlspecialchars($row['master_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
		<div class="cms_update_cell cms_update_cell_right">
			<?php if(!empty($row['can_update'])): ?>
				<div class="cms_update_button cms_tool_button" data-area="<?= $area_attr ?>">Update</div>
			<?php endif ?>
		</div>
	<?php endif ?>
</div>
