<div class="cms_translation_container"
		data-cms_page_panel_id="<?= (int)($cms_page_panel_id ?? 0) ?>"
		data-cms_language="<?= htmlspecialchars($cms_language ?? '', ENT_QUOTES) ?>"
		data-default_language="<?= htmlspecialchars($default_language ?? '', ENT_QUOTES) ?>"
		data-ai_available="<?= !empty($ai_available) ? 1 : 0 ?>"
		data-ai_ask_confirmation="<?= isset($ai_ask_confirmation) ? (!empty($ai_ask_confirmation) ? 1 : 0) : 1 ?>"
		data-ai_only_missing="<?= !empty($ai_only_missing) ? 1 : 0 ?>">

	<div class="cms_toolbar cms_translation_toolbar">

		<div class="cms_page_panel_toolbar_breadcrumb">
			<?php if (!empty($breadcrumb) && is_array($breadcrumb)): ?>
				<?php foreach ($breadcrumb as $i => $element): ?>
					<<?= !empty($element['url']) ? 'a' : 'div' ?> class="cms_tool_text cms_page_panel_toolbar_text"
							<?php if (!empty($element['url'])){ _lh($element['url']); } ?>>
						<?= htmlspecialchars($element['text'] ?? '', ENT_QUOTES) ?>
					</<?= !empty($element['url']) ? 'a' : 'div' ?>>
					<?php if ($i < count($breadcrumb) - 1): ?>
						<div class="cms_tool_text cms_page_panel_toolbar_gt">&nbsp; &gt; &nbsp;</div>
					<?php endif ?>
				<?php endforeach ?>
			<?php endif ?>
		</div>

		<div class="cms_toolbar_buttons">

			<?php if (!empty($GLOBALS['language']['languages'])): ?>
				<?php _panel('cms/cms_language_select') ?>
			<?php endif ?>

			<?php if (!empty($ai_available) && !empty($rows)): ?>
				<div class="cms_tool_button cms_right cms_translation_ai" title="Fill AI suggestions (API cost)">
					AI
				</div>
			<?php endif ?>

			<?php if (empty($error) && !empty($rows)): ?>
				<div class="cms_tool_button cms_right cms_translation_save" data-cms_ctrl="s">
					<div class="cms_tool_button_inner" <?php _ib('cms/cms_save.png', 30) ?>></div>
				</div>
			<?php endif ?>

		</div>

	</div>

	<?php if (!empty($error)): ?>

		<div class="cms_translation_message"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>

	<?php elseif (empty($languages) || count($languages) < 2): ?>

		<div class="cms_translation_message">
			Configure more than one language in
			<a <?php _lh('admin/panel_settings/cms__cms_languages/') ?>>CMS → Languages</a>
			to translate panel fields.
		</div>

	<?php elseif (empty($rows)): ?>

		<div class="cms_translation_message">
			This panel has no fields with <code>translate: 1</code>.
		</div>

	<?php else: ?>

		<div class="cms_translation_grid_header cms_grid_header">
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_label">
				<div class="cms_grid_field_inner">Input</div>
			</div>
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_default">
				<div class="cms_grid_field_inner">Default</div>
			</div>
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_copy">
				<div class="cms_grid_field_inner"></div>
			</div>
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_base">
				<div class="cms_grid_field_inner"><?= htmlspecialchars($default_language ?? 'base', ENT_QUOTES) ?> (base)</div>
			</div>
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_selected">
				<div class="cms_grid_field_inner"><?= htmlspecialchars($cms_language ?? '', ENT_QUOTES) ?> (edit)</div>
			</div>
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_copy">
				<div class="cms_grid_field_inner"></div>
			</div>
			<div class="cms_grid_field cms_grid_field_left cms_translation_col_ai">
				<div class="cms_grid_field_inner">AI</div>
			</div>
		</div>

		<div class="cms_translation_grid_body">

			<?php foreach ($rows as $row): ?>
				<?php
					$default_text = (string)($row['definition_default'] ?? '');
					$default_copy_visible = (empty($row['readonly']) && trim($default_text) !== '');
				?>
				<div class="cms_translation_row cms_grid_row"
						data-field_name="<?= htmlspecialchars($row['field_name'], ENT_QUOTES) ?>"
						data-field_type="<?= htmlspecialchars($row['field_type'], ENT_QUOTES) ?>">
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_label">
						<div class="cms_grid_field_inner cms_translation_label">
							<?= htmlspecialchars($row['label'], ENT_QUOTES) ?>
						</div>
					</div>
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_default">
						<div class="cms_grid_field_inner">
							<div class="cms_translation_readonly cms_translation_default_text"><?= htmlspecialchars($default_text, ENT_QUOTES) ?></div>
						</div>
					</div>
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_copy">
						<div class="cms_grid_field_inner cms_translation_copy_cell">
							<?php if (empty($row['readonly'])): ?>
								<div class="cms_translation_copy_btn cms_translation_default_use"
										title="Copy default into edit"
										<?php if (!$default_copy_visible): ?>style="display:none;"<?php endif ?>>→</div>
							<?php endif ?>
						</div>
					</div>
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_base">
						<div class="cms_grid_field_inner">
							<div class="cms_translation_readonly cms_translation_base_text"><?= htmlspecialchars($row['base_value'] ?? '', ENT_QUOTES) ?></div>
						</div>
					</div>
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_selected">
						<div class="cms_grid_field_inner">
							<?php if (!empty($row['readonly'])): ?>
								<div class="cms_translation_readonly"><?= htmlspecialchars($row['selected_value'] ?? '', ENT_QUOTES) ?></div>
							<?php else: ?>
								<?php
									// Grid uses textareas for all free text; keep colour control for colour fields
									$orig_type = strtolower((string)($row['field_type'] ?? ''));
									$edit_type = ($orig_type === 'colour' || $orig_type === 'color')
											? 'colour'
											: 'textarea';
									// Definition textareas: 5 lines; other types forced to textarea: 2 lines
									$edit_rows = ($orig_type === 'textarea') ? 5 : 2;
								?>
								<?php _panel('cms/cms_translate_string_input', [
										'field_type' => $edit_type,
										'value' => $row['selected_value'] ?? '',
										'language_id' => $cms_language,
										'rows' => $edit_rows,
								]) ?>
							<?php endif ?>
						</div>
					</div>
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_copy">
						<div class="cms_grid_field_inner cms_translation_copy_cell">
							<?php if (empty($row['readonly'])): ?>
								<div class="cms_translation_copy_btn cms_translation_ai_use" title="Copy AI into edit" style="display:none;">←</div>
							<?php endif ?>
						</div>
					</div>
					<div class="cms_grid_field cms_grid_field_left cms_translation_col_ai">
						<div class="cms_grid_field_inner cms_translation_ai_cell">
							<div class="cms_translation_ai_suggestion" data-field_name="<?= htmlspecialchars($row['field_name'], ENT_QUOTES) ?>"></div>
						</div>
					</div>
				</div>
			<?php endforeach ?>

		</div>

	<?php endif ?>

</div>
