<div class="cms_input shopify_cms_sync" data-cms_input_height="2">

	<label><?= !empty($label) ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : 'Sync products' ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="shopify_cms_sync_status"></div>

	<div class="cms_tool_button shopify_cms_sync_button">Sync</div>

</div>
