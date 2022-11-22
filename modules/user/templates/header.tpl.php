<div class="user_header_container">
	<div class="user_header_content">
	
		<?php if($loggedin): ?>
	
			<div class="user_header_loggedin">
				<a class="user_header_loggedin_link" <?php _lh($user_link ?? '') ?>>
				
					<?php if(empty($hide_icons)): ?>
						<div class="user_header_loggedin_image" <?php _ib($user_image, 30) ?>></div>
					<?php endif ?>
					
					<?php if(empty($hide_labels)): ?>
						<div class="user_header_loggedin_label"><?= $user_name ?></div>
					<?php endif ?>
				
				</a>
				<div class="user_header_dropdown">
					<a class="user_header_dropdown_item user_header_settings" <?php _lh($settings_link) ?>><?= $settings_text ?></a>
					<div class="user_header_dropdown_item user_header_logout">
						<?php _panel('user/logout', ['label' => $logout_text, 'link' => $logout_link]) ?>
					</div>
				</div>
			</div>
	
		<?php else: ?>
	
			<div class="user_header_loggedout">
				<a class="user_header_loggedout_link" <?php _lh($login_link) ?>>
				
					<?php if(empty($hide_icons)): ?>
						<div class="user_header_loggedout_image" <?php _ib($icon, 30) ?>></div>
					<?php endif ?>
					
					<?php if(empty($hide_labels)): ?>
						<div class="user_header_loggedout_label"><?= $login_text ?></div>
					<?php endif ?>
								
				</a>
			</div>
		
		<?php endif ?>
	
	</div>
</div>