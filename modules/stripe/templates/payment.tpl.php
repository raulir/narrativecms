<div class="payment_container" data-timestamp="<?= $timestamp ?>" data-user_user_id="<?= $user_user_id ?? '0' ?>">
		
	<div class="payment_content">
	
		<div class="payment_area">
			<div class="payment_inner">
				
				<div class="payment_heading"><?= $heading ?> <?= $plan_title ?? '' ?></div>
				<div class="payment_text"><?= $text ?></div>
				
				<div class="payment_logos" <?php _ib($powered_by, 300) ?>></div>
				
				<div class="payment_focus_area">
				
					<div class="payment_overview">
						<?php /* 
							<div class="payment_overview_label"><?= $period_end_label ?></div><div class="payment_overview_value"><?= $period_end ?></div>
						<div class="payment_overview_label"><?= $payment_extra_label ?></div><div class="payment_overview_value"><?= $extra_payment ?></div>
						*/ ?>
						<div class="payment_overview_label"><?= $payment_new_label ?></div><div class="payment_overview_value"><?= $plan_payment ?></div>
					</div>
	
					<div class="payment_cta_area payment_message_active">
						<div class="payment_button_pay"><?= $pay_label ?></div>
						<div class="payment_button_cancel"><?= $cancel_label ?></div>
					</div>
					
					<div class="payment_success_message" <?php _ib($success, 30) ?>>
						<div class="payment_message_inner"><?= $payment_success_message ?></div>
						<a class="payment_message_cta" <?php _lh($success_cta_link) ?>><?= $success_cta_label ?></a>
					</div>
					<div class="payment_failure_message" <?php _ib($failure, 30) ?>>
						<div class="payment_message_inner"><?= $payment_failure_message ?></div>
						<a class="payment_message_cta" <?php _lh($failure_cta_link) ?>><?= $failure_cta_label ?></a>
					</div>
					<div class="payment_pending_message">
						<div class="payment_pending_icon" <?php _ib($pending, 30) ?>></div>
						<div class="payment_message_inner"><?= $payment_pending_message ?></div>
					</div>
				
				</div>

			</div>
		</div>
		
		<!-- div>
		<?php // _print_r($params_start) ?>
		</div -->
		
	</div>
	
</div>

<div class="payment_popup_container">

	<div class="payment_popup_close"><?= $popup_close_label ?></div>
	<div class="payment_popup_content">
	
	</div>

</div>
