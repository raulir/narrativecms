<?php if(empty($loggedin)): ?>

	<div class="register_container" <?php if($redirect_link['target'] != '_none'): ?> data-success="<?= _l($redirect_link) ?>" <?php endif ?>>
		<div class="register_content">
		
			<?php if($loggedin): ?>
			
				<div class="register_loggedin_text"><?= $loggedin_text ?></div>
			
			<?php else: ?>
		
				<div class="register_heading"><?= $heading ?></div>
				
				<form class="register_form" method="post">
		
					<?php if(!empty($show_email)): ?>
						<div class="register_row">
							<label for="register_email" class="register_label">
								<?= $register_email ?>
								<?php if($show_email == 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_email" class="register_input register_email <?= $show_email == 2 ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_email" autocomplete="nope" value="<?= $register_data_email ?? '' ?>" 
									data-label="<?= $register_email ?>" data-field_id="email">
							<?php if(!empty($email_info)): ?>
								<div class="register_help">i<div class="register_help_text"><?= $email_info ?></div></div>
							<?php endif ?>
						</div>
					<?php endif ?>
	
					<?php if(!empty($show_fullname)): ?>
						<div class="register_row">
							<label for="register_first_name" class="register_label">
								<?= $register_first_name ?>
								<?php if($show_fullname == 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_first_name" class="register_input register_first_name 
											<?= $show_fullname == 2 ? 'register_field_mandatory' : '' ?>"
									type="text" name="register_first_name" autocomplete="nope" value="<?= $register_data_first_name ?? '' ?>" 
									data-label="<?= $register_first_name ?>" data-field_id="first_name">
							<?php if(!empty($fullname_info)): ?>
								<div class="register_help">i<div class="register_help_text"><?= $fullname_info ?></div></div>
							<?php endif ?>
						</div>
						<div class="register_row">
							<label for="register_last_name" class="register_label">
								<?= $register_last_name ?>
								<?php if($show_fullname == 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_last_name" class="register_input register_last_name 
											<?= $show_fullname == 2 ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_last_name" autocomplete="nope" value="<?= $register_data_last_name ?? '' ?>" 
									data-label="<?= $register_last_name ?>" data-field_id="last_name">
						</div>
					<?php endif ?>
	
					<?php if(!empty($show_username)): ?>
						<div class="register_row">
							<label for="register_username" class="register_label">
								<?= $register_username ?>
								<?php if($show_username == 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_username" class="register_input register_username 
											<?= $show_username == 2 ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_username" autocomplete="nope" value="<?= $register_data_username ?? '' ?>" 
									data-label="<?= $register_username ?>" data-field_id="username">
							<?php if(!empty($username_info)): ?>
								<div class="register_help">i<div class="register_help_text"><?= $username_info ?></div></div>
							<?php endif ?>
						</div>
					<?php endif ?>
					
					<?php if(!empty($show_password)): ?>
						<div class="register_row">
							<label for="register_password" class="register_label">
								<?= $register_password ?>
								<div class="register_mandatory">*</div>
							</label>
							<input id="register_password" class="register_input register_password 
											<?= $show_password == 1 ? 'register_field_mandatory' : '' ?>"
									type="password" name="register_password" autocomplete="new-password" 
									data-label="<?= $register_password ?>" data-field_id="password">
							<?php if(!empty($password_info)): ?>
								<div class="register_help">i<div class="register_help_text"><?= $password_info ?></div></div>
							<?php endif ?>
						</div>
						<div class="register_row">
							<label for="register_password2" class="register_label">
								<?= $register_password2 ?>
								<div class="register_mandatory">*</div>
							</label>
							<input id="register_password2" class="register_input register_password2 
											<?= $show_password == 1 ? 'register_field_mandatory' : '' ?>" 
									type="password" name="register_password2" autocomplete="new-password" 
									data-label="<?= $register_password2 ?>" data-field_id="password2">
						</div>
					<?php endif ?>
					
					<?php foreach($fields as $field): ?>
						<div class="register_row">
							<label for="register_field_<?= $field['name'] ?>" class="register_label">
								<?= $field['label'] ?>
								<?php if($field['mandatory'] == 'yes'): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_field_<?= $field['name'] ?>" class="register_input register_field_<?= $field['name'] ?> 
									<?= $field['mandatory'] == 'yes' ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_field_<?= $field['name'] ?>" autocomplete="nope" 
									value="<?= $register_data_field[$field['name']] ?? '' ?>" 
									data-label="<?= $field['label'] ?>" data-field_id="<?= $field['name'] ?>">
							<?php if(!empty($field['info'])): ?>
								<div class="register_help">i<div class="register_help_text"><?= $field['info']?></div></div>
							<?php endif ?>
						</div>
					<?php endforeach ?>
	
					<?php if(!empty($text)): ?>
						<div class="register_row register_text_row">
							<div class="register_text"><?= $text ?></div>
						</div>
					<?php endif ?>
			
					<div class="register_row register_submit_row">
						<div class="register_submit"><?= $register_submit ?></div>
					</div>
		
				</form>
				
				<div class="register_errors">
				
					<div class="register_error register_error_mandatory">
						<?= str_replace('{{fieldname}}', '<div class="register_fieldname" data-glue="'.$message_mandatory_glue.'"></div>', 
								$message_mandatory) ?>
					</div>
					
					<div class="register_error register_error_mandatories">
						<?= str_replace('{{fieldname}}', '<div class="register_fieldname"></div>', $message_mandatories) ?>
					</div>
		
					<div class="register_error register_error_emailexists"><?= $message_emailexists ?></div>
					<div class="register_error register_error_usernameexists"><?= $message_usernameexists ?></div>
					<div class="register_error register_error_usernamelength"><?= $message_usernamelength ?></div>
					<div class="register_error register_error_bademail"><?= $message_bademail ?></div>
					<div class="register_error register_error_password_mismatch"><?= $message_password_mismatch ?></div>
				
				</div>
				
			<?php endif ?>
	
		</div>
	</div>
	
<?php else: ?>

	<script type="text/javascript">window.location.href = "<?= _l($redirect_link) ?>"</script>

<?php endif ?>
