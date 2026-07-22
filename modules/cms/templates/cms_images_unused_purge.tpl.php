<div class="cms_input cms_images_unused_purge" data-cms_input_height="2" data-cms_input_width="1">

	<label><?= htmlspecialchars($label ?? 'Images older than months', ENT_QUOTES, 'UTF-8') ?></label>

	<?php if (!empty($help)) _panel('cms/cms_help', ['help' => $help, ]); ?>

	<div class="cms_images_unused_purge_controls">

		<input type="number"
			class="cms_images_unused_purge_months"
			name="min_months"
			value="<?= (int)($min_months ?? 3) ?>"
			min="0"
			step="1"
			title="Months">

		<select class="cms_images_unused_purge_category" name="category" title="Category">
			<option value="">All categories</option>
			<?php foreach (($categories ?? []) as $cat_key => $cat_label): ?>
				<option value="<?= htmlspecialchars((string)$cat_key, ENT_QUOTES, 'UTF-8') ?>"
					<?= (string)($category ?? '') === (string)$cat_key ? ' selected' : '' ?>>
					<?= htmlspecialchars((string)$cat_label, ENT_QUOTES, 'UTF-8') ?>
				</option>
			<?php endforeach ?>
		</select>

		<div class="cms_tool_button cms_images_unused_purge_test_button">Test</div>
		<div class="cms_tool_button cms_images_unused_purge_button">Purge</div>

	</div>

	<div class="cms_images_unused_purge_status"></div>

</div>
