<div class="cms_toolbar cms_popup_toolbar">

	<div class="cms_tool_text cms_popup_title"><?= $heading ?></div>

	<div class="cms_popup_toolbar_actions">
		<?php if (!empty($save_button)): ?>
			<div class="cms_tool_button cms_page_panel_targets_close"><?= $save_button ?></div>
		<?php endif ?>
		<div class="cms_tool_button cms_popup_cancel"><?= !empty($cancel_label) ? $cancel_label : 'Cancel' ?></div>
	</div>

</div>