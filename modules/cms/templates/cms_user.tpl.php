<div class="cms_user_container">
	<div class="cms_user_content">
		<div class="cms_user_user">
			<div class="cms_user_username" <?php _ib('cms/cms_user.png', 13); ?>>
				<?= !empty($_SESSION['cms_user']['name']) ? $_SESSION['cms_user']['name'] : $_SESSION['cms_user']['username'] ?>
			</div>
		</div>
		<div class="cms_user_button" data-cms_ctrl="q">log out</div>
	</div>
</div>

<form class="cms_user_form" method="post" style="display: hidden; ">
	<input type="hidden" name="do" value="admin_logout">
</form>
