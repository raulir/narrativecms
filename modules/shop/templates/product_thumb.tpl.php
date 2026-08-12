<?php if (!empty($product['error'])): ?>

<div class="product_thumb_container product_thumb_error">
	<div class="product_thumb_heading"><?= $product['heading'] ?? ($unavailable_label ?? 'unavailable') ?></div>
</div>

<?php elseif (empty($product['show'])): ?>

<div class="product_thumb_container product_thumb_unavailable">
	<div class="product_thumb_heading"><?= $unavailable_label ?? 'unavailable' ?>: <?= $product['heading'] ?? '' ?></div>
</div>

<?php else: ?>

<div class="product_thumb_container">
	<a class="product_thumb_content" <?php _lh('shop/product='.$product['cms_page_panel_id']) ?>>

		<div class="product_thumb_image">
			<?php if (!empty($product['image'])): ?>
				<div class="product_thumb_image_image" <?php _ib($product['image'], ['width' => 700, ]) ?>></div>
			<?php endif ?>
		</div>

		<div class="product_thumb_copy">
			<div class="product_thumb_heading"><?= $product['heading'] ?></div>
			<div class="product_thumb_price"><?=
				!empty($product['available'])
					? ($product['price'] ?? '')
					: ($sold_out_label ?? 'sold out')
			?></div>
		</div>

	</a>
</div>

<?php endif ?>
