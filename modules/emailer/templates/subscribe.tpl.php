<?php if (!empty($confirm_result)): ?>
<div class="subscribe_container">
	<div class="subscribe_content">
		<div class="subscribe_confirm"><?= $confirm_success_message ?? 'Email confirmed' ?></div>
	</div>
</div>
<?php else: ?>
<div class="subscribe_container" data-cms_page_panel_id="<?= (int)($cms_page_panel_id ?? 0) ?>">
	<div class="subscribe_content">

		<?php if (!empty($heading)): ?>
			<div class="subscribe_heading"><?= $heading ?></div>
		<?php endif ?>

		<?php if (!empty($text)): ?>
			<div class="subscribe_text"><?= $text ?></div>
		<?php endif ?>

		<div class="subscribe_form">
			<input class="subscribe_email" type="text" name="email" autocomplete="email"
					placeholder="<?= $email_placeholder ?? '' ?>"
					title="<?= $email_label ?? 'Email' ?>">
			<div class="subscribe_submit"><?= $submit_text ?? 'Subscribe' ?></div>
			<div class="subscribe_error"><?= $error_message ?? '' ?></div>
			<div class="subscribe_message">
				<div class="subscribe_message_sending"><?= $sending_message ?? '' ?></div>
				<div class="subscribe_message_text"><?= $success_message ?? '' ?></div>
			</div>
		</div>

	</div>
</div>
<?php endif ?>
