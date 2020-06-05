<div class="login_container" <?php if($redirect_link['target'] != '_none'): ?> data-success="<?= _l($redirect_link) ?>" <?php endif ?>>
	<div class="login_content">
	
		<div class="login_heading"><?= $heading ?></div>
		
		<?php if(empty($loggedin)): ?>
	
			<form class="login_form" method="post" autocomplete="off">
		
				<div class="login_row">
					<label for="login_login_username" class="login_label"><?= $username_label ?></label>
					<input id="login_login_username" class="login_input login_input_username" type="text" name="login" placeholder="<?= $username_label ?>">
				</div>
				<div class="login_row">
					<label for="login_login_password" class="login_label"><?= $password_label ?></label>
					<input id="login_login_password" class="login_input login_input_password" type="password" name="password" placeholder="<?= $password_label ?>">
				</div>
				<div class="login_row login_submit_row">
					<div class="login_submit login_login"><?= $button_label ?></div>
				</div>
		
			</form>
			
			<div class="login_error login_error_bad_username"><?= $message_bad_username ?></div>
			<div class="login_error login_error_bad_password"><?= $message_bad_password ?></div>
			<div class="login_error login_error_missing_credential"><?= $message_missing_credential ?></div>
			
			<?php if(!empty($message)): ?>
				<div class="login_message"><?= $message ?></div>
			<?php endif ?>
			
			<div class="login_forgot_area login_forgot_area_<?= $show_forgot ?>">
	
				<div class="login_forgot_heading"><?= $forgot_text ?></div>
		
				<a class="login_forgot_cta" <?php _lh($forgot_link) ?>><?= $forgot_cta ?></a>
			
			</div>
			
			<div class="login_register_area login_register_area_<?= $show_register ?>">
	
				<div class="login_register_heading"><?= $register_text ?></div>
		
				<a class="login_register_cta" <?php _lh($register_link) ?>><?= $register_cta ?></a>
			
			</div>

		<?php else: ?>
		
			<div class="login_loggedin_text"><?= $loggedin_text ?></div>

		<?php endif ?>

	</div>
</div>
