<div class="cms_input cms_rebuild_routes" data-cms_input_height="1" data-cms_input_width="1">

	<label><?= htmlspecialchars($label ?? 'Public URL routes', ENT_QUOTES, 'UTF-8') ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="cms_rebuild_routes_controls">
		<div class="cms_tool_button cms_rebuild_routes_button">Rebuild routes</div>
	</div>

	<div class="cms_rebuild_routes_status"></div>

</div>
