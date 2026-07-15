<?php if(empty($loggedin)): ?>

	<div class="register_container" data-cms_page_panel_id="<?= $cms_page_panel_id ?>" data-success="<?= $success_url ?>"
			data-progress_message="<?= htmlspecialchars($progress_message ?? 'One moment...', ENT_QUOTES, 'UTF-8') ?>">
		<div class="register_content">
		
				<div class="register_heading"><?= $heading ?></div>

				<?php if (!empty($alternatives) && is_array($alternatives)): ?>
					<div class="register_alternatives">
						<?php foreach($alternatives as $item): ?>
							<a class="register_alternative" <?php _lh($item['link'] ?? []) ?> <?php _ib($item['icon'] ?? '', ['width' => 24,
									'css' => ('background-color: '.($item['background'] ?? '').'; color: '.($item['colour'] ?? '').'; border-color: '.($item['colour'] ?? '').';')])
									?>><?= $item['label'] ?? '' ?></a>
						<?php endforeach ?>
					</div>
					<div class="register_alternatives_separator"><?= $separator_label ?></div>
				<?php endif ?>
				
				<form class="register_form" method="post">
		
					<?php if ((int)$show_email > 0): ?>
						<div class="register_row">
							<label for="register_email" class="register_label">
								<?= $register_email ?>
								<?php if ((int)$show_email === 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_email" class="register_input register_email <?= (int)$show_email === 2 ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_email" autocomplete="nope" value="<?= $register_data_email ?? '' ?>" 
									data-label="<?= $register_email ?>" data-field_id="email">
							<?php if(!empty($email_info)): ?>
								<div class="register_help">?<div class="register_help_text"><?= $email_info ?></div></div>
							<?php endif ?>
						</div>
					<?php endif ?>

					<?php if ((int)$show_username > 0): ?>
						<div class="register_row">
							<label for="register_username" class="register_label">
								<?= $register_username ?>
								<?php if ((int)$show_username === 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_username" class="register_input register_username 
											<?= (int)$show_username === 2 ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_username" autocomplete="nope" value="<?= $register_data_username ?? '' ?>" 
									data-label="<?= $register_username ?>" data-field_id="username">
							<?php if(!empty($username_info)): ?>
								<div class="register_help">?<div class="register_help_text"><?= $username_info ?></div></div>
							<?php endif ?>
						</div>
					<?php endif ?>
	
					<?php if ((int)$show_fullname > 0): ?>
						<div class="register_row">
							<label for="register_first_name" class="register_label">
								<?= $register_first_name ?>
								<?php if ((int)$show_fullname === 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_first_name" class="register_input register_first_name 
											<?= (int)$show_fullname === 2 ? 'register_field_mandatory' : '' ?>"
									type="text" name="register_first_name" autocomplete="nope" value="<?= $register_data_first_name ?? '' ?>" 
									data-label="<?= $register_first_name ?>" data-field_id="first_name">
							<?php if(!empty($names_info)): ?>
								<div class="register_help">?<div class="register_help_text"><?= $names_info ?></div></div>
							<?php endif ?>
						</div>
						<div class="register_row">
							<label for="register_last_name" class="register_label">
								<?= $register_last_name ?>
								<?php if ((int)$show_fullname === 2): ?>
									<div class="register_mandatory">*</div>
								<?php endif ?>
							</label>
							<input id="register_last_name" class="register_input register_last_name 
											<?= (int)$show_fullname === 2 ? 'register_field_mandatory' : '' ?>" 
									type="text" name="register_last_name" autocomplete="nope" value="<?= $register_data_last_name ?? '' ?>" 
									data-label="<?= $register_last_name ?>" data-field_id="last_name">
						</div>
					<?php endif ?>
					
					<?php if ((int)$show_password > 0): ?>
						<?php $password_min_length = (int)$password_min_length ?>
						<div class="register_row">
							<label for="register_password" class="register_label">
								<?= $register_password ?>
								<div class="register_mandatory">*</div>
							</label>
							<input id="register_password" class="register_input register_password 
											<?= (int)$show_password === 1 ? 'register_field_mandatory' : '' ?>"
									type="password" name="register_password" autocomplete="new-password" 
									data-label="<?= $register_password ?>" data-field_id="password"
									data-min_length="<?= $password_min_length ?>">
							<?php if(!empty($password_info)): ?>
								<div class="register_help">?<div class="register_help_text"><?= str_replace('{{min_length}}', (string)$password_min_length, $password_info) ?></div></div>
							<?php endif ?>
						</div>
						<div class="register_row">
							<label for="register_password2" class="register_label">
								<?= $register_password2 ?>
								<div class="register_mandatory">*</div>
							</label>
							<input id="register_password2" class="register_input register_password2 
											<?= (int)$show_password === 1 ? 'register_field_mandatory' : '' ?>" 
									type="password" name="register_password2" autocomplete="new-password" 
									data-label="<?= $register_password2 ?>" data-field_id="password2">
						</div>
					<?php endif ?>
					
					<?php foreach(($fields ?? []) as $field): ?>
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
								<div class="register_help">?<div class="register_help_text"><?= $field['info']?></div></div>
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
					<div class="register_error register_error_password_length"><?= str_replace('{{min_length}}', (string)(int)($password_min_length ?? 8), $message_password_length) ?></div>
					<div class="register_error register_error_loggedin"><?= $message_loggedin ?></div>
				
				</div>
				
				<div class="register_success"><?= $message_success ?></div>

				<?php if ($show_login !== 'no'): ?>
					<div class="register_login_area">
						<div class="register_login_heading"><?= $login_text ?></div>
						<a class="register_login_cta" <?php _lh($login_link) ?>><?= $login_cta ?></a>
					</div>
				<?php endif ?>
	
		</div>
	</div>
	
<?php else: ?>

	<?php if (!_is_position_ajax()): ?>
	<script type="text/javascript">window.location.href = "<?= $success_url ?>"</script>
	<?php endif ?>

<?php endif ?>
