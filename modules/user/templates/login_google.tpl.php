<?php
	$login_uri = rtrim((string)($GLOBALS['config']['base_site'] ?? $GLOBALS['config']['base_host'] ?? ''), '/')._l($google_auth_page, false);
?>
<div class="login_google_container"
		data-progress_message="<?= $progress_message ?>"
		data-login_uri="<?= $login_uri ?>">
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
