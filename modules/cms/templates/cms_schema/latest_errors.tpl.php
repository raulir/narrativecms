<?php // Latest fix errors (session), with a dismiss button. Expects: $latest_fix_errors (not empty) ?>
<div class="cms_schema_latest_errors">
	<div class="cms_schema_latest_errors_title">Latest fix errors</div>
	<div class="cms_schema_latest_errors_dismiss cms_small_button">dismiss</div>
	<div class="cms_schema_latest_errors_items">
		<?php foreach ($latest_fix_errors as $fix_error): ?>
			<div class="cms_schema_item_row cms_schema_latest_errors_row">
				<div class="cms_schema_latest_errors_module">
					<?= $fix_error['module'] ?? '' ?>
				</div>
				<div class="cms_schema_location">
					<?= $fix_error['key'] ?? '' ?>
				</div>
				<div class="cms_schema_description cms_schema_latest_errors_message">
					<?= $fix_error['message'] ?? '' ?>
					<?php if (!empty($fix_error['sql'])): ?>
						<div class="cms_schema_latest_errors_sql"><?= $fix_error['sql'] ?></div>
					<?php endif ?>
				</div>
			</div>
		<?php endforeach ?>
	</div>
</div>
