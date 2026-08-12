<?php
	$product_count = is_array($products ?? null) ? count($products) : 0;
	$is_empty = $product_count < 1;
	$empty_message = $empty_filter_message ?? 'No products to show with this filter';
?>
<div class="products_grid_container<?= $is_empty ? ' products_grid_empty' : '' ?>"
		data-category_id="<?= (int)($category_id ?? 0) ?>"
		data-subcategory_id="<?= (int)($subcategory_id ?? 0) ?>"
		data-collection_id="<?= (int)($collection_id ?? 0) ?>">
	<div class="products_grid_content">

		<?php if ($is_empty): ?>

			<div class="products_grid_empty_message"><?= $empty_message ?></div>

		<?php else: ?>

			<?php foreach($products as $product): ?>
				<div class="products_grid_product"><?php _panel('shop/product_thumb', [
					'cms_page_panel_id' => $product['cms_page_panel_id'],
				]) ?></div>
			<?php endforeach ?>

		<?php endif ?>

	</div>
</div>
