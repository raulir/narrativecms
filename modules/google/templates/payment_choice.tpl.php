<div class="payment_choice_container"
		data-product_id="<?= $product_id ?>"
		data-currency_id="<?= $currency_id ?>"
		data-play_product_id="<?= htmlspecialchars($play_product_id, ENT_QUOTES, 'UTF-8') ?>"
		data-user_id="<?= $user_id ?>"
		data-choice_offer="<?= $choice_offer ?>"
		data-opening="<?= $choice_opening ?>"
		data-unavailable="<?= $choice_unavailable ?>"
		data-no_play_product="<?= $choice_no_play_product ?>"
		data-error_start="<?= $choice_error_start ?>"
		data-error_stripe="<?= $choice_error_stripe ?>"
		data-error_save="<?= $choice_error_save ?>"
		data-error_checkout="<?= $choice_error_checkout ?>"
		data-error_open="<?= $choice_error_open ?>"
		data-error_play="<?= $choice_error_play ?>"
		data-error_confirm="<?= $choice_error_confirm ?>"
		data-pending="<?= $choice_pending ?>">

	<div class="payment_choice_heading"><?= $choice_heading ?></div>
	<?php if ($plan_title !== ''): ?>
		<div class="payment_choice_plan"><?= $plan_title ?></div>
	<?php endif ?>

	<div class="payment_choice_options" role="radiogroup">
		<div class="payment_choice_option" data-way="play" role="radio" aria-checked="false" tabindex="0">
			<div class="payment_choice_option_mark"></div>
			<div class="payment_choice_option_copy">
				<div class="payment_choice_option_label"><?= $choice_play ?></div>
				<div class="payment_choice_option_note"><?= $choice_play_note ?></div>
				<div class="payment_choice_option_reason"></div>
			</div>
		</div>
		<div class="payment_choice_option" data-way="stripe" role="radio" aria-checked="false" tabindex="0">
			<div class="payment_choice_option_mark"></div>
			<div class="payment_choice_option_copy">
				<div class="payment_choice_option_label"><?= $choice_stripe ?></div>
				<div class="payment_choice_option_note"><?= $choice_stripe_note ?></div>
				<div class="payment_choice_option_reason"></div>
			</div>
		</div>
	</div>

	<div class="payment_choice_message"></div>

	<div class="payment_choice_buttons">
		<button type="button" class="payment_choice_back"><?= $choice_back ?></button>
		<button type="button" class="payment_choice_continue"><?= $choice_continue ?></button>
	</div>

</div>
