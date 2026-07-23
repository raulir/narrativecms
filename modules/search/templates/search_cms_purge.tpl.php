<div class="cms_input search_cms_purge" data-cms_input_height="1">

	<label><?= !empty($label) ? $label : 'Search purge' ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="search_cms_purge_status"></div>

	<div class="cms_tool_button search_cms_purge_button">Purge</div>

</div>
