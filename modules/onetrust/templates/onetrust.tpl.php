<style>
	.onetrust_container {background-color:<?= $background_colour ?>;color:<?= $text_colour ?>;border-color:<?= $text_colour ?>;}
</style>
<div class="onetrust_container" data-settings_label="<?= $cookie_settings_label ?>">
	<script src="https://cdn.cookielaw.org/scripttemplates/otSDKStub.js" charset="UTF-8" data-domain-script="<?= $onetrust_id ?>"></script>
	<script type="text/javascript">function OptanonWrapper(){}</script>
	<button id="ot-sdk-btn" class="ot-sdk-show-settings"><?= $cookie_settings_label ?></button>
	<?php if($cookie_notice_url['target'] != '_none'): ?>
		<a class="onetrust_link" <?php _lh($cookie_notice_url) ?>><?= $cookie_notice_label ?></a>
	<?php endif ?>
</div>