<div class="verify_email_container">
	<div class="verify_email_content">

		<?php if (!empty($verified)): ?>

			<div class="verify_email_success"><?= $message_success ?></div>
			<a class="verify_email_continue" <?php _lh($success_url) ?>><?= $continue_text ?></a>

		<?php elseif (!empty($error)): ?>

			<div class="verify_email_error verify_email_error_<?= $error ?>"><?= $error_message ?? $error ?></div>

		<?php endif ?>

	</div>
</div>