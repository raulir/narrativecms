<div class="product_container" data-product_id="<?= (int)$cms_page_panel_id ?>" data-variants='<?= json_encode(array_values($variants ?? []),JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
	<div class="product_content">

		<div class="product_images">
			<?php if (!empty($images)): ?>
				<?php foreach ($images as $image): ?>
					<div class="product_image<?= !empty($image['variant_active']) ? ' product_image_variant_active' : '' ?><?php if (!empty($image['ids'])): ?> product_image_variant<?php foreach ($image['ids'] as $id): ?> product_image_product_<?= $id ?><?php endforeach ?><?php else: ?> product_image_novariant<?php endif ?>">
						<div class="product_image_image" <?php _ib($image['image'], 1200) ?>></div>
					</div>
				<?php endforeach ?>
			<?php endif ?>
		</div>

		<div class="product_copy">

			<div class="product_heading"><?= $heading ?></div>
			<div class="product_price" data-price_prefix="<?= $price_prefix ?>"><?= $price_display ?></div>

			<div class="product_dims">
				<?php foreach (($dims ?? []) as $dimension): ?>
					<div class="product_dim">
						<div class="product_dim_heading"><?= $dimension['name'] ?></div>
						<?php foreach ($dimension['values'] as $value): ?>
							<div class="product_dim_value<?= $value['active'] ? ' product_dim_active' : '' ?><?= $value['available'] ? '' : ' product_dim_value_unavailable' ?>"
									data-dim_id="<?= $dimension['dim_id'] ?>"
									data-value_id="<?= $value['id'] ?>">
								<?= $value['label'] ?>
							</div>
						<?php endforeach ?>
					</div>
				<?php endforeach ?>
			</div>

			<div class="product_buy<?= $buy_class !== '' ? ' '.$buy_class : '' ?>"
					data-product_item_id="<?= $buy_item_id ?>">
				<?= $buy_label ?>
			</div>
			<div class="product_added"><?= $added_message ?></div>
			<div class="product_add_error"></div>

		</div>

	</div>
</div>
