<?php
	$login_uri = ($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.($_SERVER['HTTP_HOST'] ?? '')._l($google_auth_page, false);
?>
<div class="login_google_container"
		data-progress_message="<?= htmlspecialchars($progress_message ?? 'One moment...', ENT_QUOTES, 'UTF-8') ?>"
		data-login_uri="<?= htmlspecialchars($login_uri, ENT_QUOTES, 'UTF-8') ?>">
	<div class="login_google_content">

		<div class="login_google_heading"><?= $heading ?></div>
		<div class="login_google_intro"><?= $intro_text ?></div>

		<script src="https://accounts.google.com/gsi/client" async defer></script>

		<div id="g_id_onload"
			data-client_id="<?= $google_client_id ?>"
			data-callback="user_google_credential_response"
			data-auto_prompt="false">
		</div>

		<div class="login_google_button">
			<div class="g_id_signin"
				data-type="standard"
				data-size="large"
				data-theme="outline"
				data-text="sign_in_with"
				data-shape="rectangular"
				data-logo_alignment="left">
			</div>
		</div>

	</div>
</div>
