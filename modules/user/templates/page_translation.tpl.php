<div class="page_translation_container"
		data-cms_page_panel_id="<?= (int)($cms_page_panel_id ?? 0) ?>"
		data-cms_language="<?= htmlspecialchars((string)($cms_language ?? ''), ENT_QUOTES, 'UTF-8') ?>"
		data-default_language="<?= htmlspecialchars((string)($default_language ?? ''), ENT_QUOTES, 'UTF-8') ?>"
		data-ai_available="<?= !empty($ai_available) ? 1 : 0 ?>"
		data-ai_ask_confirmation="<?= isset($ai_ask_confirmation) ? (!empty($ai_ask_confirmation) ? 1 : 0) : 1 ?>"
		data-ai_only_missing="<?= !empty($ai_only_missing) ? 1 : 0 ?>">

	<div class="page_translation_toolbar">

		<div class="page_translation_breadcrumb">
			<?php if (!empty($breadcrumb) && is_array($breadcrumb)): ?>
				<?php foreach ($breadcrumb as $i => $element): ?>
					<?php
						$bc_url = !empty($element['url']) ? $element['url'] : '';
						$bc_tag = $bc_url !== '' ? 'a' : 'span';
					?>
					<<?= $bc_tag ?> class="page_translation_bc_item<?= $bc_url === '' ? ' page_translation_bc_disabled' : '' ?>"
							<?php if ($bc_url !== ''){ _lh($bc_url); } ?>>
						<?= htmlspecialchars((string)($element['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
					</<?= $bc_tag ?>>
					<?php if ($i < count($breadcrumb) - 1): ?>
						<span class="page_translation_bc_gt">&nbsp; &gt; &nbsp;</span>
					<?php endif ?>
				<?php endforeach ?>
			<?php endif ?>
		</div>

		<div class="page_translation_toolbar_actions">
			<?php // AI always visible when there are fields (emptied UI values count as missing) ?>
			<?php if (!empty($rows)): ?>
				<div class="page_translation_btn page_translation_ai" title="Fill AI suggestions (API cost)">AI</div>
			<?php endif ?>
			<?php if (empty($error) && !empty($rows)): ?>
				<div class="page_translation_btn page_translation_save">Save</div>
			<?php endif ?>
			<div class="page_translation_btn page_translation_cancel">Close</div>
		</div>

	</div>

	<?php if (!empty($error)): ?>

		<div class="page_translation_message"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>

	<?php elseif (empty($languages) || count($languages) < 2): ?>

		<div class="page_translation_message">
			Configure more than one language in CMS → Languages to translate panel fields.
		</div>

	<?php elseif (empty($rows)): ?>

		<div class="page_translation_message">
			This panel has no fields with translate: 1.
		</div>

	<?php else: ?>

		<div class="page_translation_content">

		<div class="page_translation_grid_header">
			<div class="page_translation_col page_translation_col_label">Input</div>
			<div class="page_translation_col page_translation_col_default">Default</div>
			<div class="page_translation_col page_translation_col_copy"></div>
			<div class="page_translation_col page_translation_col_base"><?= htmlspecialchars((string)($default_language ?? 'base'), ENT_QUOTES, 'UTF-8') ?> (base)</div>
			<div class="page_translation_col page_translation_col_edit"><?= htmlspecialchars((string)($cms_language ?? ''), ENT_QUOTES, 'UTF-8') ?> (edit)</div>
			<div class="page_translation_col page_translation_col_copy"></div>
			<div class="page_translation_col page_translation_col_ai">AI</div>
		</div>

		<div class="page_translation_grid_body">

			<?php foreach ($rows as $row): ?>
				<?php
					$default_text = (string)($row['definition_default'] ?? '');
					$default_copy_visible = (empty($row['readonly']) && trim($default_text) !== '');
					$orig_type = strtolower((string)($row['field_type'] ?? ''));
					$edit_rows = ($orig_type === 'textarea') ? 5 : 2;
					$is_colour = ($orig_type === 'colour' || $orig_type === 'color');
				?>
				<div class="page_translation_row"
						data-field_name="<?= htmlspecialchars((string)$row['field_name'], ENT_QUOTES, 'UTF-8') ?>"
						data-field_type="<?= htmlspecialchars((string)($row['field_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

					<div class="page_translation_col page_translation_col_label">
						<div class="page_translation_label"><?= htmlspecialchars((string)$row['label'], ENT_QUOTES, 'UTF-8') ?></div>
					</div>

					<div class="page_translation_col page_translation_col_default">
						<div class="page_translation_readonly page_translation_default_text"><?= htmlspecialchars($default_text, ENT_QUOTES, 'UTF-8') ?></div>
					</div>

					<div class="page_translation_col page_translation_col_copy">
						<?php if (empty($row['readonly'])): ?>
							<div class="page_translation_copy_btn page_translation_default_use"
									title="Copy default into edit"
									<?php if (!$default_copy_visible): ?>style="display:none;"<?php endif ?>>→</div>
						<?php endif ?>
					</div>

					<div class="page_translation_col page_translation_col_base">
						<div class="page_translation_readonly page_translation_base_text"><?= htmlspecialchars((string)($row['base_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
					</div>

					<div class="page_translation_col page_translation_col_edit">
						<?php if (!empty($row['readonly'])): ?>
							<div class="page_translation_readonly"><?= htmlspecialchars((string)($row['selected_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
						<?php elseif ($is_colour): ?>
							<input type="text" class="page_translation_input page_translation_colour_input"
									value="<?= htmlspecialchars((string)($row['selected_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
						<?php else: ?>
							<textarea class="page_translation_input page_translation_textarea"
									rows="<?= (int)$edit_rows ?>"><?= htmlspecialchars((string)($row['selected_value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
						<?php endif ?>
					</div>

					<div class="page_translation_col page_translation_col_copy">
						<?php if (empty($row['readonly'])): ?>
							<div class="page_translation_copy_btn page_translation_ai_use" title="Copy AI into edit" style="display:none;">←</div>
						<?php endif ?>
					</div>

					<div class="page_translation_col page_translation_col_ai">
						<div class="page_translation_ai_suggestion"
								data-field_name="<?= htmlspecialchars((string)$row['field_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
					</div>

				</div>
			<?php endforeach ?>

		</div>

		</div><!-- .page_translation_content -->

	<?php endif ?>

</div>
