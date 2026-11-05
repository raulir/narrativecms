<?php
	$area_attr = htmlspecialchars($row['area'] ?? '', ENT_QUOTES, 'UTF-8');
	$label = $row['label'] ?? ((!empty($row['area'])) ? $row['area'] : 'Narrative CMS');
	$row_class = 'cms_update_row';
	if (!empty($row['is_master'])){
		$row_class .= ' cms_update_row_master';
	}
	if (isset($row['active']) && !$row['active']){
		$row_class .= ' cms_update_row_inactive';
	}
?>
<div class="<?= $row_class ?>" data-area="<?= $area_attr ?>" data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
	<div class="cms_update_cell cms_update_cell_label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?php if (isset($row['active']) && !$row['active']): ?><div class="cms_update_inactive">inactive</div><?php endif ?></div>
	<div class="cms_update_cell"><?= htmlspecialchars($row['local_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
	<?php if(!empty($row['error'])): ?>
		<div class="cms_update_cell cms_update_error"><?= htmlspecialchars($row['error'], ENT_QUOTES, 'UTF-8') ?></div>
		<div class="cms_update_cell cms_update_cell_right"></div>
	<?php else: ?>
		<div class="cms_update_cell"><?= htmlspecialchars($row['master_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
		<div class="cms_update_cell cms_update_cell_right">
			<?php if(!empty($row['can_remove'])): ?>
				<div class="cms_update_remove_button cms_tool_button" data-area="<?= $area_attr ?>">Remove</div>
			<?php endif ?>
			<?php if(!empty($row['can_release'])): ?>
				<div class="cms_update_release_button cms_tool_button" data-area="<?= $area_attr ?>">Release</div>
			<?php elseif(!empty($row['can_update'])): ?>
				<div class="cms_update_button cms_tool_button" data-area="<?= $area_attr ?>">Update</div>
			<?php elseif(!empty($row['not_allowed'])): ?>
				<div class="cms_update_not_allowed">Not allowed</div>
			<?php endif ?>
		</div>
	<?php endif ?>
</div>
