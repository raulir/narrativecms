<div class="register_container">
	<div class="register_content">
	
		<?php if($loggedin): ?>
		
			<div class="register_loggedin_text"><?= $loggedin_text ?></div>
		
		<?php else: ?>
	
			<div class="register_heading"><?= $heading ?></div>
			
			<form class="register_form" method="post">
	
				<?php if($show_username == 'yes'): ?>
					<div class="register_row">
						<label for="register_username" class="register_label"><?= $register_username ?></label>
						<input id="register_username" class="register_input" type="text" name="register_username" autocomplete="nope"
								value="<?= !empty($register_data_username) ? $register_data_username : '' ?>">
					</div>
				<?php endif ?>
				
				<div class="register_row">
					<label for="register_email" class="register_label"><?= $register_email ?></label>
					<input id="register_email" class="register_input" type="text" name="register_email" autocomplete="nope"
							value="<?= !empty($register_data_email) ? $register_data_email : '' ?>">
				</div>
				
				<div class="register_row">
					<label for="register_fullname" class="register_label"><?= $register_fullname ?></label>
					<input id="register_fullname" class="register_input" type="text" name="register_fullname" autocomplete="nope"
							value="<?= !empty($register_data_fullname) ? $register_data_fullname : '' ?>">
				</div>
				
				<?php if($show_password == 'yes'): ?>
					<div class="register_row">
						<label for="register_password" class="register_label"><?= $register_password ?></label>
						<input id="register_password" class="register_input" type="password" name="register_password" autocomplete="new-password">
					</div>
					<div class="register_row">
						<label for="register_password2" class="register_label"><?= $register_password2 ?></label>
						<input id="register_password2" class="register_input" type="password" name="register_password2" autocomplete="new-password">
					</div>
				<?php endif ?>
				
				<div class="register_row">
					<input type="hidden" name="do" value="register">
					<input type="submit" class="register_submit" value="<?= $register_submit ?>">
				</div>
	
			</form>
		
		<?php endif ?>

	</div>
</div>
