<div class="pricing_container"
		data-layout="<?= $layout ?>"
		data-interval="month"
		data-currency_id="<?= $active_currency_id ?>"
		data-cms_page_panel_id="<?= $cms_page_panel_id ?>"
		data-checkout_provider="<?= $checkout_provider ?>"
		data-checkout_missing="<?= $checkout_missing ? '1' : '0' ?>"
		data-user_logged_in="<?= $user_logged_in ? '1' : '0' ?>"
		data-login_url="<?= $login_url ?>"
		data-auto_checkout="<?= $auto_checkout ? '1' : '0' ?>"
		data-auto_product_id="<?= $auto_product_id ?>"
		data-auto_currency_id="<?= $auto_currency_id ?>"
		data-checkout_missing_message="<?= $checkout_missing_message ?>"
		data-checkout_error_message="<?= $checkout_error_message ?>">

	<div class="pricing_content">

		<?php if ($error): ?>

			<div class="pricing_error"><?= $error ?></div>

		<?php else: ?>

			<?php if ($heading): ?>
				<div class="pricing_heading"><?= $heading ?></div>
			<?php endif ?>

			<?php if ($show_interval_toggle && $has_paid_cards): ?>
				<div class="pricing_toggle" role="group">
					<div class="pricing_toggle_option pricing_toggle_active"
							data-interval="month"><?= $monthly_label ?></div>
					<div class="pricing_toggle_option"
							data-interval="year">
						<?= $yearly_label ?>
						<?php if ($yearly_badge): ?>
							<span class="pricing_toggle_badge"><?= $yearly_badge ?></span>
						<?php endif ?>
					</div>
				</div>
			<?php endif ?>

			<div class="pricing_plans">
				<div class="pricing_cards">
					<?php foreach ($cards as $card): ?>
						<?php include __DIR__.'/pricing/card.tpl.php'; ?>
					<?php endforeach ?>
				</div>
			</div>

			<?php if ($show_currency_switcher): ?>
				<div class="pricing_currency_wrap">
					<?php _panel('shop/currency_selector', [
							'currency_ids' => $currency_ids,
							'default' => $active_currency_id,
							'add_empty' => 0,
					]) ?>
				</div>
			<?php endif ?>

			<?php if (!$cards): ?>
				<div class="pricing_empty">No plans in this category yet.</div>
			<?php endif ?>

		<?php endif ?>

	</div>

</div>
