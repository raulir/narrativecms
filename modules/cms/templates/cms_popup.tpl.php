<div class="cms_popup_container cms_popup_<?= $name ?>">

	<div class="cms_popup_cell">
		<div class="cms_popup_content">
		
			<div class="cms_toolbar cms_popup_toolbar">
		
				<div class="cms_tool_text cms_popup_title"><?= $heading ?></div>

				<div class="cms_popup_toolbar_actions">
					<?php if (!empty($name) && $name === 'targets'): ?>
						<div class="cms_tool_button cms_page_panel_targets_close">Save</div>
					<?php endif ?>
					<div class="cms_tool_button cms_popup_cancel">Cancel</div>
				</div>

			</div>
		
			<div class="cms_popup_area">
				<!-- here comes the content -->
			</div>

		</div>
	</div>

</div>
