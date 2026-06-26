<?php if (!empty($loggedin) && empty($error)): ?>

	<?php if (!_is_position_ajax()): ?>
	<script type="text/javascript">window.location.href = "<?= $success_url ?>"</script>
	<?php endif ?>

<?php else: ?>

	<div class="auth_google_container">
		<div class="auth_google_content">

			<?php if (!empty($error)): ?>
				<div class="auth_google_error auth_google_error_active"><?= $error_message ?></div>
			<?php endif ?>

		</div>
	</div>

<?php endif ?>