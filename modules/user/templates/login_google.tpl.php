<script src="https://accounts.google.com/gsi/client" async defer></script>

<div id="g_id_onload"
	data-client_id="<?= $google_client_id ?>"
	data-login_uri="<?= $_SERVER["REQUEST_SCHEME"] ?>://<?= $_SERVER['HTTP_HOST'] ?><?php _l($google_auth_page, true) ?>"
	data-auto_prompt="false">
</div>
<div class="g_id_signin"
	data-type="standard"
	data-size="large"
	data-theme="outline"
	data-text="sign_in_with"
	data-shape="rectangular"
	data-logo_alignment="left">
</div>