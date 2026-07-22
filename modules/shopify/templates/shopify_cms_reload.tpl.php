<div class="cms_input shopify_cms_reload" data-cms_input_height="2">

	<label><?= !empty($label) ? htmlspecialchars($label, ENT_QUOTES, 'UTF-8') : 'Reload Shopify data' ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="shopify_cms_reload_status"></div>

	<div class="cms_tool_button shopify_cms_reload_button">Clear</div>

</div>
