<div class="shop_productbuy_container">
	<div class="shop_productbuy_content">

		<input type="hidden" class="shop_productbuy_input" name="product_id" value="<?= $product_id ?>">
		
		<?php if(!empty($variations) || !empty($one_size)): ?>

			<?php if(!empty($product['price'])): ?>
				<div class="shop_productbuy_feature">
					<div class="cg_h3 shop_productbuy_feature_label"><?= $price_label ?></div>
					<div class="cg_h3 shop_productbuy_feature_text">£<?= $product['price'] ?></div>
				</div>
			<?php endif ?>
			
			<div class="shop_productbuy_dimensions">
				<?php _panel('shop/productdimensions', ['product_id' => $product_id, 'errors' => (!empty($errors) ? $errors : []), ]) ?>
			</div>

			<div class="cg_cta shop_productbuy_add"><?= $add_label ?></div>
		
		<?php else: ?>
			
			<?php if(empty($in_basket)): ?>
				<div class="shop_productbuy_error_heading"><?= $stock_error_heading ?></div>
				<div class="shop_productbuy_error_text"><?= $stock_error_text ?></div>
				<a class="cg_cta shop_productbuy_error_button" <?php _lh($stock_error_button_link) ?>><?= $stock_error_button_label ?></a>
			<?php else: ?>
				<div class="shop_productbuy_error_text"><?= $stock_error_last_in_basket ?></div>
			<?php endif ?>
			
		<?php endif ?>

		<?php if(!empty($success)): ?>
			<div class="shop_productbuy_added"><?= $added_message ?></div>
		<?php endif ?>	
		
		<div class="shop_productbuy_link_area">
			<a class="shop_productbuy_link" <?php _lh($link_link) ?>><?= $link_text ?></a>
		</div>

	</div>
</div>
