<?php if (!empty($loggedin) && empty($error)): ?>

	<?php if (!_is_position_ajax()): ?>
	<script type="text/javascript">window.location.href = "<?= $success_url ?>"</script>
	<?php endif ?>

<?php else: ?>

	<div class="auth_google_container">
		<div class="auth_google_content">

			<div class="auth_google_heading"><?= $heading ?></div>

			<?php if (!empty($error)): ?>
				<div class="auth_google_error_heading"><?= $error_heading ?></div>
				<div class="auth_google_error"><?= $error_message ?></div>
			<?php endif ?>

			<?php if (($auth_intent ?? 'login') === 'register'): ?>
				<div class="auth_google_register_area">
					<div class="auth_google_register_heading"><?= $register_text ?></div>
					<a class="auth_google_register_cta" <?php _lh($register_link) ?>><?= $register_cta ?></a>
				</div>
				<div class="auth_google_login_area">
					<div class="auth_google_login_heading"><?= $login_text ?></div>
					<a class="auth_google_login_cta" <?php _lh($login_link) ?>><?= $login_cta ?></a>
				</div>
			<?php else: ?>
				<div class="auth_google_login_area">
					<div class="auth_google_login_heading"><?= $login_text ?></div>
					<a class="auth_google_login_cta" <?php _lh($login_link) ?>><?= $login_cta ?></a>
				</div>
				<div class="auth_google_register_area">
					<div class="auth_google_register_heading"><?= $register_text ?></div>
					<a class="auth_google_register_cta" <?php _lh($register_link) ?>><?= $register_cta ?></a>
				</div>
			<?php endif ?>

		</div>
	</div>

<?php endif ?>
