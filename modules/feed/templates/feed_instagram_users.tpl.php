<div class="cms_toolbar">
	
	<div class="admin_tool_text"><?php print($title); ?></div>
	
	<div class="admin_tool_button admin_right feed_instagram_user_button">Add user</div>
	
</div>

<div class="cms_admin_area">

	<?php if(!count($users)): ?>

		No authorised Instagram users found

	<?php else: ?>

		<div class="feed_instagram_table">
			
			<?php foreach($users as $user): ?>
				<div class="feed_instagram_row">
					<div class="feed_instagram_cell feed_instagram_picture" <?php _ib($user['profile_picture']); ?>></div>
					<div class="feed_instagram_cell feed_instagram_full_name"><?php print($user['full_name']); ?></div>
					<div class="feed_instagram_cell feed_instagram_username"><?php print($user['username']); ?></div>
					<div class="feed_instagram_cell feed_instagram_buttons">
						<!-- div class="cms_input_button feed_instagram_test_button" data-cms_page_panel_id="<?php print($user['cms_page_panel_id']); ?>">Test</div -->
						<div class="cms_input_button feed_instagram_remove_button" data-cms_page_panel_id="<?php print($user['cms_page_panel_id']); ?>">Remove</div>
					</div>
				</div>
				<div class="feed_instagram_spacer"></div>
			<?php endforeach ?>
		
		</div>

	<?php endif ?>

</div>

<div class="feed_instagram_popup_overlay">

	<div class="feed_instagram_popup_cell">
		<div class="feed_instagram_popup_container">
		
			<div class="cms_toolbar feed_instagram_popup_toolbar">
		
				<div class="admin_tool_text admin_left feed_instagram_popup_title">Authorising feed use</div>

				<div class="admin_tool_button admin_right feed_instagram_popup_cancel">Cancel</div>

			</div>
		
			<div class="feed_instagram_popup_confirmation">
				You will be redirected to Instagram to authorise this website to use your content.<br>
				<br>
				If you are currently logged in to the Instagram website but want to authorise a different user, please log out from Instagram before continuing.<br>
				<br>
				By clicking "OK" below, I agree that my Instagram basic user data and public posts are used on this website.<br>
				<br>
				<a class="feed_instagram_privacy" href="http://cms.bytecrackers.com/instagram/" target="_blank">Privacy policy</a>
			</div>
		
			<div class="admin_tool_button feed_instagram_popup_ok">OK</div>
				
			</div>

		</div>
	</div>

</div>
