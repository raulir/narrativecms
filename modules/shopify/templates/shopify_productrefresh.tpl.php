<?php if (!empty($show_refresh)): ?>
	<div class="cms_tool_button cms_right shopify_productrefresh"
			data-product_id="<?= (int)($cms_page_panel_id ?? $product_id ?? 0) ?>">Refresh</div>
<?php endif ?>
