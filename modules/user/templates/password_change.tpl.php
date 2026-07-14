<div class="password_change_container">
	<div class="password_change_content">

		<?php if (!empty($loggedin)): ?>

			<div class="password_change_heading"><?= $heading ?></div>

			<form class="password_change_form" method="post" autocomplete="off">

				<div class="password_change_row">
					<label for="password_change_password" class="password_change_label"><?= $password_label ?></label>
					<input id="password_change_password" class="password_change_input password_change_input_password"
							type="password" autocomplete="new-password" name="password">
				</div>

				<div class="password_change_row">
					<label for="password_change_password2" class="password_change_label"><?= $password2_label ?></label>
					<input id="password_change_password2" class="password_change_input password_change_input_password2"
							type="password" autocomplete="new-password" name="password2">
				</div>

				<div class="password_change_row password_change_submit_row">
					<div class="password_change_save"><?= $save_label ?></div>
				</div>

			</form>

			<div class="password_change_error password_change_error_passwords_mismatch"><?= $message_passwords_mismatch ?></div>
			<div class="password_change_error password_change_error_bad_save"><?= $message_bad_save ?></div>

			<div class="password_change_overlay">
				<div class="password_change_popup">
					<div class="password_change_popup_message"><?= $message_success ?></div>
				</div>
			</div>

		<?php endif ?>

	</div>
</div>
