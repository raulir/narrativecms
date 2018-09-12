<?php if(!empty($message)): ?>
	<div class="login_message"><?= !empty($$message) ? $$message : 'Message "'.$message.'" occured' ?></div>
<?php endif ?>

<?php if(empty($_SESSION['user_id'])): ?>

	<div class="login_container">
		<div class="login_content">
		
			<div class="login_heading"><?= $heading ?></div>
		
			<form class="login_form" method="post" autocomplete="off">
		
				<div class="login_row">
					<label for="login_login_email" class="login_label"><?= $email_label ?></label>
					<input id="login_login_email" class="login_input" type="text" name="login">
				</div>
				<div class="login_row">
					<label for="login_login_password" class="login_label"><?= $password_label ?></label>
					<input id="login_login_password" class="login_input" type="password" name="password" autocomplete="new-password">
				</div>
				<div class="login_row">
					<input type="hidden" name="do" value="login">
					<input class="login_email" type="text" name="email">
					<input type="submit" class="login_submit login_login" value="<?= $button_label ?>">
				</div>
		
			</form>
			
			<div class="login_forgotten_text">
				<?= $forgotten_text ?>
			</div>
	
			<div class="login_extra_text">
				<a class="login_extra_link" <?php _lh($link_url) ?>><?= $link_text ?></a> <?= $extra_text ?>
			</div>
	
			<div class="login_register_heading <?= !empty($show_register) ? ' login_register_form_active ' : '' ?>"><?= $register_heading ?></div>
			
			<form class="login_register_form <?= !empty($show_register) ? ' login_register_form_active ' : '' ?>" method="post">
	
				<div class="login_row">
					<label for="login_register_username" class="login_label"><?= $register_username ?></label>
					<input id="login_register_username" class="login_input" type="text" name="register_username" autocomplete="nope" value="<?= !empty($register_data_username) ? $register_data_username : '' ?>">
				</div>
				<div class="login_row">
					<label for="login_register_fullname" class="login_label"><?= $register_fullname ?></label>
					<input id="login_register_fullname" class="login_input" type="text" name="register_fullname" autocomplete="nope" value="<?= !empty($register_data_fullname) ? $register_data_fullname : '' ?>">
				</div>
				<div class="login_row">
					<label for="login_register_email" class="login_label"><?= $register_email ?></label>
					<input id="login_register_email" class="login_input" type="text" name="register_email" autocomplete="nope" value="<?= !empty($register_data_email) ? $register_data_email : '' ?>">
				</div>
				<div class="login_row">
					<label for="login_register_password" class="login_label"><?= $register_password ?></label>
					<input id="login_register_password" class="login_input" type="password" name="register_password" autocomplete="new-password">
				</div>
				<div class="login_row">
					<label for="login_register_password2" class="login_label"><?= $register_password2 ?></label>
					<input id="login_register_password2" class="login_input" type="password" name="register_password2" autocomplete="new-password">
				</div>
				<div class="login_row">
					<input type="hidden" name="do" value="register">
					<input class="login_email" type="text" name="email">
					<input type="submit" class="login_submit login_register" value="<?= $register_submit ?>">
				</div>
	
			</form>
	
		</div>
	</div>

<?php endif ?>
