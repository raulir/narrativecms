<div class="header_container">
	<div class="header_content">
	
		<?php if($loggedin): ?>
	
			<div class="header_loggedin">
				<a class="header_loggedin_link" <?php _lh($user_link) ?>>
				
					<div class="header_loggedin_image" <?php _ib($user_image, 30) ?>></div>
					
					<div class="header_loggedin_label"><?= $user_name ?></div>
				
				</a>
			</div>
	
		<?php else: ?>
	
			<div class="header_loggedout">
				<a class="header_loggedout_link" <?php _lh($login_link) ?>>
				
					<div class="header_loggedout_image" <?php _ib($icon, 30) ?>></div>
					
					<div class="header_loggedout_label"><?= $login_text ?></div>
				
				</a>
			</div>
		
		<?php endif ?>
	
	</div>
</div>