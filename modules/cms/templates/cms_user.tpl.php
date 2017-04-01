
<?php if(empty($_SESSION['cms_user'])): ?>

	<div class="admin_container login_container">
		<div class="cms_content login_content">
	
			<form method="post" style="display: inline; ">
				<input type="hidden" name="do" value="admin_login">
				<div class="login_label">Username</div>
				<input class="login_input" type="text" name="username">
				<div class="login_label">Password</div>
				<input class="login_input" type="password" name="password">
				<input class="login_button" type="submit" value="Log In">
			</form>

			<div style="clear: both; "><!--  --></div>

		</div>
	</div>

<?php else: ?>

	<div class="cms_user_container">
		<div class="cms_user_content">
			<div class="cms_user_user"><div class="cms_user_username" <?php _ib('/modules/cms/img/cms_user.png'); ?>><?= !empty($_SESSION['cms_user']['name']) ? $_SESSION['cms_user']['name'] : $_SESSION['cms_user']['username'] ?></div></div>
			<div class="cms_user_button">log out</div>
		</div>
	</div>

	<form class="cms_user_form" method="post" style="display: hidden; ">
		<input type="hidden" name="do" value="admin_logout">
	</form>

<?php endif ?>
