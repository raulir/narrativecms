<div class="register_container">
	<div class="register_content">
	
		<?php if($loggedin): ?>
		
			<div class="register_loggedin_text"><?= $loggedin_text ?></div>
		
		<?php else: ?>
	
			<div class="register_heading"><?= $heading ?></div>
			
			<form class="register_form" method="post">
	
				<?php if($config['show_username']): ?>
					<div class="register_row">
						<label for="register_username" class="register_label"><?= $register_username ?></label>
						<input id="register_username" class="register_input register_username" type="text" name="register_username" autocomplete="nope"
								value="<?= !empty($register_data_username) ? $register_data_username : '' ?>">
					</div>
				<?php endif ?>
				
				<div class="register_row">
					<label for="register_email" class="register_label"><?= $register_email ?></label>
					<input id="register_email" class="register_input register_email" type="text" name="register_email" autocomplete="nope"
							value="<?= !empty($register_data_email) ? $register_data_email : '' ?>">
				</div>
				
				<div class="register_row">
					<label for="register_first_name" class="register_label"><?= $register_first_name ?></label>
					<input id="register_first_name" class="register_input register_first_name" type="text" name="register_first_name" autocomplete="nope"
							value="<?= !empty($register_data_first_name) ? $register_data_first_name : '' ?>">
				</div>
				
				<div class="register_row">
					<label for="register_last_name" class="register_label"><?= $register_last_name ?></label>
					<input id="register_last_name" class="register_input register_last_name" type="text" name="register_last_name" autocomplete="nope"
							value="<?= !empty($register_data_last_name) ? $register_data_last_name : '' ?>">
				</div>
				
				<div class="register_row">
					<label for="register_phone" class="register_label"><?= $register_phone ?></label>
					<input id="register_phone" class="register_input register_phone" type="text" name="register_phone" autocomplete="nope"
							value="<?= !empty($register_data_phone) ? $register_data_phone : '' ?>">
				</div>

				<?php if($show_password == 'yes'): ?>
					<div class="register_row">
						<label for="register_password" class="register_label"><?= $register_password ?></label>
						<input id="register_password" class="register_input register_password" type="password" name="register_password" autocomplete="new-password">
					</div>
					<div class="register_row">
						<label for="register_password2" class="register_label"><?= $register_password2 ?></label>
						<input id="register_password2" class="register_input register_password2" type="password" name="register_password2" autocomplete="new-password">
					</div>
				<?php endif ?>
				
				<div class="register_row">
					<div class="register_submit"><?= $register_submit ?></div>
				</div>
	
			</form>
			
			<div class="register_error register_error_password_missing"><?= $message_password_missing ?></div>
			<div class="register_error register_error_emailexists"><?= $message_emailexists ?></div>
			<div class="register_error register_error_usernameexists"><?= $message_usernameexists ?></div>
			<div class="register_error register_error_usernamelength"><?= $message_usernamelength ?></div>
			<div class="register_error register_error_bademail"><?= $message_bademail ?></div>
			<div class="register_error register_error_password_mismatch"><?= $message_password_mismatch ?></div>
		
		<?php endif ?>

	</div>
</div>
