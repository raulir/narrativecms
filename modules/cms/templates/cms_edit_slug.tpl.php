<?php
	$heading = 'Edit slug';
	$save_button = '';
	include __DIR__.'/cms_popup_toolbar.tpl.php';
?>
<div class="cms_edit_slug_container" data-cms_page_panel_id="<?= (int)$cms_page_panel_id ?>" data-current_slug="<?= htmlspecialchars($current_slug, ENT_QUOTES, 'UTF-8') ?>">

	<div class="cms_edit_slug_content">

		<div class="cms_edit_slug_label">Current slug:</div>
		<div class="cms_edit_slug_current"><?= htmlspecialchars($current_slug, ENT_QUOTES, 'UTF-8') ?></div>

		<div class="cms_edit_slug_label">New slug:</div>
		<input type="text" class="cms_edit_slug_input" name="new_slug" value="<?= htmlspecialchars($current_slug, ENT_QUOTES, 'UTF-8') ?>">

		<div class="cms_edit_slug_status"></div>

		<div class="cms_edit_slug_actions">
			<div class="cms_tool_button cms_edit_slug_update">Update</div>
		</div>

	</div>

</div>