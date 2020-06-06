<div class="reminder_container">
	<div class="reminder_content">
	
		<div class="reminder_heading"><?= $heading ?></div>
		
		<?php if(empty($loggedin)): ?>
		
			<?php if(empty($timeout)): ?>
		
				<form class="reminder_form" method="post" autocomplete="off">
			
					<div class="reminder_row">
						<label for="reminder_reminder_username" class="reminder_label"><?= $username_label ?></label>
						<input id="reminder_reminder_username" class="reminder_input reminder_input_username" type="text">
					</div>
					
					<?php if(!empty($success)): ?>
						<div class="reminder_row">
							<label for="reminder_reminder_password" class="reminder_label"><?= $password_label ?></label>
							<input id="reminder_reminder_password" class="reminder_input reminder_input_password" type="password">
						</div>
						<div class="reminder_row">
							<label for="reminder_reminder_password2" class="reminder_label"><?= $password2_label ?></label>
							<input id="reminder_reminder_password2" class="reminder_input reminder_input_password2" type="password">
						</div>
					<?php endif ?>
					
					<?php if(empty($success)): ?>
						<div class="reminder_row reminder_submit_row">
							<div class="reminder_submit"><?= $button_label ?></div>
						</div>
					<?php else: ?>
						<div class="reminder_row reminder_submit_row">
							<div class="reminder_save" data-token="<?= $token ?>"><?= $save_label ?></div>
						</div>
					<?php endif ?>
			
				</form>
				
				<div class="reminder_error reminder_error_bad_username"><?= $message_bad_username ?></div>
				<div class="reminder_error reminder_error_bad_save"><?= $message_bad_save ?></div>
				<div class="reminder_error reminder_error_passwords_mismatch"><?= $message_passwords_mismatch ?></div>
				
				<div class="reminder_success"><?= $message_success ?></div>
				<div class="reminder_save_success">
					<?= $message_save_success ?>
					<div class="reminder_login_area">
						<a class="reminder_login_cta" <?php _lh($login_link) ?>><?= $login_cta ?></a>
					</div>
				</div>
				
			<?php else: ?>
			
				<div class="reminder_timeout">
					<?= $message_timeout ?>
					<div class="reminder_timeout_area">
						<a class="reminder_timeout_cta" <?php _lh($timeout_link) ?>><?= $timeout_cta ?></a>
					</div>
				</div>
			
			<?php endif ?>

		<?php else: ?>
		
			<div class="reminder_loggedin_text"><?= $loggedin_text ?></div>

		<?php endif ?>

	</div>
</div>
