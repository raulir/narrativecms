<?php
	$show_auto = !empty($show_settings_card);
	$show_currency = $show_currency_switcher && $manage_view === 'basic';
	$show_payment_area = ($manage_view === 'premium') && !empty($show_payment);
	$plans_class = 'manage_action_plans';
?>
<div class="pricing_container pricing_manage"
		data-layout="<?= $layout ?>"
		data-cards_server="1"
		data-interval="month"
		data-currency_id="<?= $active_currency_id ?>"
		data-cms_page_panel_id="<?= $cms_page_panel_id ?>"
		data-checkout_provider="<?= $checkout_provider ?>"
		data-checkout_missing="<?= $checkout_missing ? '1' : '0' ?>"
		data-user_logged_in="<?= $user_logged_in ? '1' : '0' ?>"
		data-login_url="<?= $login_url ?>"
		data-manage_view="<?= $manage_view ?>"
		data-auto_checkout="0"
		data-auto_product_id="0"
		data-auto_currency_id="0"
		data-checkout_missing_message="<?= $checkout_missing_message ?>"
		data-checkout_error_message="<?= $checkout_error_message ?>"
		data-manage_updating="<?= $manage_updating_label ?>">

	<div class="pricing_content">

		<?php if ($heading): ?>
			<div class="pricing_heading"><?= $heading ?></div>
		<?php endif ?>

		<?php if ($manage_view === 'login'): ?>

			<div class="pricing_manage_message">
				<?= $manage_login_message ?>
			</div>
			<?php if ($login_url): ?>
				<a class="pricing_cta pricing_cta_free" href="<?= $login_url ?>">Log in</a>
			<?php endif ?>

		<?php elseif ($error): ?>

			<div class="pricing_error"><?= $error ?></div>

		<?php else: ?>

			<div class="manage_status">
				<?php if ($status_plan_title): ?>
					<div class="manage_status_plan"><?= $status_plan_title ?></div>
				<?php endif ?>
				<?php if ($status_period_line || $show_auto): ?>
					<div class="manage_status_period"><?= $status_period_line ?></div>
				<?php endif ?>

				<?php if ($show_auto): ?>
					<div class="manage_auto_row">
						<span class="manage_auto_label"><?= $manage_auto_extension_label ?>:</span>
						<span class="manage_auto_toggle" role="group">
							<span class="manage_auto_option<?= $auto_renew_on ? ' manage_auto_option_active' : '' ?>"
									data-auto_renew="1"><?= $manage_on_label ?></span>
							<span class="manage_auto_option<?= !$auto_renew_on ? ' manage_auto_option_active' : '' ?>"
									data-auto_renew="0"><?= $manage_off_label ?></span>
						</span>
					</div>
				<?php endif ?>
			</div>

			<div class="manage_action_area">

				<?php if ($cards): ?>
					<div class="pricing_plans <?= $plans_class ?>">
						<div class="pricing_cards">
							<?php foreach ($cards as $card): ?>
								<?php include __DIR__.'/pricing/card.tpl.php'; ?>
							<?php endforeach ?>
						</div>
					</div>
				<?php endif ?>

				<?php if ($show_currency): ?>
					<div class="pricing_currency_wrap">
						<?php _panel('shop/currency_selector', [
								'currency_ids' => $currency_ids,
								'default' => $active_currency_id,
								'add_empty' => 0,
						]) ?>
					</div>
				<?php endif ?>

				<?php if ($manage_view === 'basic' && !$cards): ?>
					<div class="pricing_empty">No paid plans in this category yet.</div>
				<?php endif ?>

			</div>

			<?php if ($show_payment_area): ?>
				<div class="manage_payment_area">
					<div class="manage_payment_heading"><?= $manage_payment_heading ?></div>
					<div class="manage_payment_cta"><?= $manage_payment_cta ?></div>
				</div>
			<?php endif ?>

		<?php endif ?>

	</div>

</div>
