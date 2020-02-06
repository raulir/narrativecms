<div class="login_container">
	<div class="login_content">
	
		<div class="login_heading"><?= $heading ?></div>
		
		<?php if(empty($loggedin)): ?>
	
			<form class="login_form" method="post" autocomplete="off">
		
				<div class="login_row">
					<label for="login_login_username" class="login_label"><?= $username_label ?></label>
					<input id="login_login_username" class="login_input" type="text" name="login">
				</div>
				<div class="login_row">
					<label for="login_login_password" class="login_label"><?= $password_label ?></label>
					<input id="login_login_password" class="login_input" type="password" name="password" autocomplete="new-password">
				</div>
				<div class="login_row">
					<input type="hidden" name="do" value="login">
					<input type="submit" class="login_submit login_login" value="<?= $button_label ?>">
				</div>
		
			</form>
			
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
