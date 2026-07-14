<div class="register_google_container">
	<div class="register_google_content">

		<div class="register_google_heading"><?= $heading ?></div>
		<div class="register_google_intro"><?= $intro_text ?></div>

		<script src="https://accounts.google.com/gsi/client" async defer></script>

		<div id="g_id_onload"
			data-client_id="<?= $google_client_id ?>"
			data-login_uri="<?= $_SERVER["REQUEST_SCHEME"] ?>://<?= $_SERVER['HTTP_HOST'] ?><?php _l($google_auth_page, true) ?>"
			data-auto_prompt="false">
		</div>

		<div class="register_google_button">
			<div class="g_id_signin"
				data-type="standard"
				data-size="large"
				data-theme="outline"
				data-text="signup_with"
				data-shape="rectangular"
				data-logo_alignment="left">
			</div>
		</div>

	</div>
</div>
