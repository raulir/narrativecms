<?php
// One pricing card — parent owns foreach. Model prepares all $card keys.
?>
<div class="pricing_card<?= $card['featured'] ? ' pricing_card_featured' : '' ?><?= $card['always_visible'] ? ' pricing_card_free' : ' pricing_card_paid' ?><?= $card['visible'] ? '' : ' pricing_card_hidden' ?>"
		data-always="<?= $card['always_visible'] ? '1' : '0' ?>"
		data-interval="<?= $card['interval'] ?>"
		data-currency_id="<?= $card['currency_id'] ?>"
		data-product_id="<?= $card['product_id'] ?>"
		data-subcategory_id="<?= $card['subcategory_id'] ?>">

	<div class="pricing_card_title"><?= $card['title'] ?></div>

	<div class="pricing_card_price_block">
		<div class="pricing_card_price"><?= $card['price_fmt'] ?></div>
		<div class="pricing_card_period"><?= $card['period_label'] ?></div>
	</div>

	<div class="pricing_card_features"><?= $card['features_html'] ?></div>

	<?php if ($card['cta_plan'] === 'free'): ?>
		<?php if ($card['cta_use_lh']): ?>
			<a class="pricing_cta pricing_cta_free" <?php _lh($card['cta_link']) ?>>
				<?= $card['cta_text'] ?>
			</a>
		<?php else: ?>
			<a class="pricing_cta pricing_cta_free" href="<?= $card['cta_href'] ?>">
				<?= $card['cta_text'] ?>
			</a>
		<?php endif ?>
	<?php else: ?>
		<div class="pricing_cta <?= $card['cta_class'] ?>"
				data-plan="<?= $card['cta_plan'] ?>"
				data-product_id="<?= $card['product_id'] ?>"
				data-interval="<?= $card['interval'] ?>"
				data-currency_id="<?= $card['currency_id'] ?>">
			<?= $card['cta_text'] ?>
		</div>
	<?php endif ?>

</div>
