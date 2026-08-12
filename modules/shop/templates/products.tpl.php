<div class="products_container"
		data-category_id="<?= (int)($category_id ?? 0) ?>"
		data-subcategory_id="<?= (int)($subcategory_id ?? 0) ?>"
		data-collection_id="<?= (int)($collection_id ?? 0) ?>">

	<div class="products_menu_area">
		<?php _panel('shop/products_menu', $menu_params ?? []) ?>
	</div>

	<div class="products_grid_area">
		<?php _panel('shop/products_grid', $grid_params ?? []) ?>
	</div>

</div>
