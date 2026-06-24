<div class="cms_access_denied_container">
	<div class="cms_access_denied_overlay"></div>
	<div class="cms_access_denied_content">
		<div class="cms_access_denied_close" <?php _ib('cms/cms_close.png', 16) ?>></div>
		<div class="cms_access_denied_text"><?php print(!empty($text) ? $text : 'System error: access denied'); ?></div>
		<a class="cms_access_denied_home" href="<?php print(!empty($login_url) ? $login_url : _user_login_url(false)); ?>"><?php print(!empty($login_text) ? $login_text : _user_login_text(false)); ?></a>
	</div>
</div>