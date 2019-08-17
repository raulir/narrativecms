<div class="cms_toolbar">

	<div class="cms_tool_text">Admin users</div>

</div>

<div>

	<?php if (!empty($GLOBALS['config']['admin_username'])): ?>
		<div class="cms_user_settings_superuser">
			Superuser with username "<?= $GLOBALS['config']['admin_username'] ?>" is set in "<?= $config_file ?>"
		</div>
	<?php endif ?>
	
	<?php foreach($users as $user): ?>
	
		<div class="cms_user_settings_user">
		
			<input class="cms_user_settings_cms_user_id" type="hidden" name="cms_user_id" value="<?= $user['cms_user_id'] ?>">
			<input class="cms_user_settings_sort" type="hidden" name="sort" value="<?= $user['sort'] ?>">
	
			<div class="cms_user_settings_user_header">
			
				<?= (!empty($user['username']) ? $user['username'] : 'Add new user').(!empty($user['name']) ? ' ('.$user['name'].')' : '') ?>
			
				<div class="cms_user_settings_user_save cms_user_settings_button">Save</div>
			
				<?php if (!empty($user['username'])): ?>
					<div class="cms_user_settings_user_delete cms_user_settings_button">Delete</div>
				<?php endif ?>
			
			</div>
		
			<div class="cms_user_settings_user_content">
			
				<div class="cms_user_settings_line">
					username:
					<input class="cms_user_settings_input cms_user_settings_username" type="text" name="username" value="<?= $user['username'] ?>">
				</div>
				<div class="cms_user_settings_line">
					password:
					<input class="cms_user_settings_input cms_user_settings_password" type="text" name="password" value="" <?= !empty($user['password']) ? 'placeholder="ONLY FILL IN TO CHANGE"' : '' ?>>
				</div>
				<div class="cms_user_settings_line">
					real name:
					<input class="cms_user_settings_input cms_user_settings_name" type="text" name="name" value="<?= $user['name'] ?>">
				</div>
				<div class="cms_user_settings_line">
					email:
					<input class="cms_user_settings_input cms_user_settings_email" type="text" name="email" value="<?= $user['email'] ?>">
				</div>
				
				<div class="cms_user_settings_user_rights">
					<div class="cms_user_settings_user_rights_heading">rights:</div>
					<div class="cms_user_settings_user_rights_content cms_user_settings_user_rights_content_<?= $user['username'] ?>">
						<?php foreach($user['rights'] as $right): ?>
							<?php if(!empty($right)): ?>
								<div class="cms_user_settings_user_access_item" 
										data-target="<?= $user['username'] ?>" data-value="<?= $right ?>" data-text="<?= $right_names[$right] ?>">
									
									<div class="cms_user_settings_user_access_item_x">x</div>
									<?= $right_names[$right] ?>
								
								</div>
							<?php endif ?>
						<?php endforeach ?>
					</div>
					<div class="cms_user_settings_user_rights_bottom">
						<div class="cms_user_settings_rights_add cms_user_settings_button" data-target="<?= $user['username'] ?>">Add</div>
						<select class="cms_user_settings_user_rights_select cms_user_settings_user_rights_select_<?= $user['username'] ?>">
							<?php foreach($right_names as $right_value => $right_name): ?>
								
								<?php if(!in_array($right_value, $user['rights'])): ?>
									<option value="<?= $right_value ?>"><?= $right_name ?></option>
								<?php endif ?>
							
							<?php endforeach ?>
						</select>
					</div>
				</div>
				
			</div>
	
		</div>
	
	<?php endforeach ?>

</div>
