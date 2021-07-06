<div class="cms_user_login_container">
	<div class="cms_user_login_content">

		<form method="post" style="display: inline; ">
			<div class="cms_user_login_box">
			
				<div class="cms_user_login_heading" 
					<?php _ib(!empty($GLOBALS['config']['favicon']) ? $GLOBALS['config']['favicon'] : 'cms/cms_icon_black.png', 32) ?>>
					<?= trim(str_replace('#page#', '', $GLOBALS['config']['site_title']), ' '.$GLOBALS['config']['site_title_delimiter']) ?>
				</div>

				<input type="hidden" name="do" value="cms_user_login">
				<div class="cms_user_login_label">Username</div>
				<input class="cms_user_login_input" type="text" name="username">
				<div class="cms_user_login_label">Password</div>
				<input class="cms_user_login_input" type="password" name="password">
				<input class="cms_user_login_button" type="submit" value="Log In">
			
			</div>
		</form>

	</div>
</div>

<div class="cms_user_login_copyright">
	<a class="cms_user_login_cms" <?php _lh('https://www.bytecrackers.com/') ?> <?php _ib('cms/cms_icon.png', 15) ?>>BC CMS</a> &nbsp; - &nbsp; 
	<a <?php _lh('https://github.com/raulir/bccms/') ?>>&copy; 2012-2021 Rauli Rajande</a>
</div>
