<div class="cms_access_denied_container">
	<div class="cms_access_denied_overlay"></div>
	<div class="cms_access_denied_content">
		<div class="cms_access_denied_close" <?php _ib('cms/cms_close.png', 16) ?>></div>
		<div class="cms_access_denied_text"><?= !empty($text) ? $text : 'System error: access denied' ?></div>
		<a class="cms_access_denied_home" href="<?= $login_url ?>"><?= $login_text ?></a>
	</div>
</div>