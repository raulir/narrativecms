<div class="verify_email_container">
	<div class="verify_email_content">

		<?php if (!empty($verified)): ?>

			<script type="text/javascript">window.location.href = "<?= $success_url ?>"</script>

		<?php elseif (!empty($error)): ?>

			<div class="verify_email_error verify_email_error_<?= $error ?>"><?= $error_message ?? $error ?></div>

		<?php endif ?>

	</div>
</div>