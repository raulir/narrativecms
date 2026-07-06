<div class="cms_translate_string_overlay"></div>

<div class="cms_translate_string_container" data-cms_page_panel_id="<?= (int)$cms_page_panel_id ?>"
		data-field_name="<?= htmlspecialchars($field_name, ENT_QUOTES) ?>"
		data-field_type="<?= htmlspecialchars($field_type, ENT_QUOTES) ?>"
		data-default_language="<?= htmlspecialchars($default_language, ENT_QUOTES) ?>">

	<div class="cms_translate_string_toolbar cms_toolbar">

		<div class="cms_tool_text cms_translate_string_title">Translations</div>

		<div class="cms_translate_string_toolbar_buttons">
			<div class="cms_translate_string_save cms_tool_button">
				<div class="cms_tool_button_inner" <?php _ib('cms/cms_save.png', 30) ?>></div>
			</div>
			<div class="cms_translate_string_close cms_tool_button">
				<div class="cms_tool_button_inner" <?php _ib('cms/cms_cancel.png', 30) ?>></div>
			</div>
		</div>

	</div>

	<div class="cms_translate_string_content">

		<div class="cms_translate_string_grid_header cms_grid_header">
			<div class="cms_grid_field cms_grid_field_left" style="width: 20%;">
				<div class="cms_grid_field_inner">Language</div>
			</div>
			<div class="cms_grid_field cms_grid_field_left" style="width: 80%;">
				<div class="cms_grid_field_inner">Translation</div>
			</div>
		</div>

		<div class="cms_translate_string_grid_fixed">

			<div class="cms_translate_string_row cms_grid_row" data-language_id="default" data-row_type="default">
				<div class="cms_grid_field cms_grid_field_left" style="width: 20%;">
					<div class="cms_grid_field_inner cms_translate_string_label">default</div>
				</div>
				<div class="cms_grid_field cms_grid_field_left" style="width: 80%;">
					<div class="cms_grid_field_inner">
						<div class="cms_translate_string_readonly"><?= htmlspecialchars($definition_default, ENT_QUOTES) ?></div>
					</div>
				</div>
			</div>

			<div class="cms_translate_string_row cms_grid_row" data-language_id="<?= htmlspecialchars($default_language, ENT_QUOTES) ?>" data-row_type="main">
				<div class="cms_grid_field cms_grid_field_left" style="width: 20%;">
					<div class="cms_grid_field_inner cms_translate_string_label"><?= htmlspecialchars($default_language, ENT_QUOTES) ?> (main)</div>
				</div>
				<div class="cms_grid_field cms_grid_field_left" style="width: 80%;">
					<div class="cms_grid_field_inner">
						<?php _panel('cms/cms_translate_string_input', [
								'field_type' => $field_type,
								'value' => $main_value,
								'language_id' => $default_language,
						]) ?>
					</div>
				</div>
			</div>

		</div>

		<div class="cms_translate_string_grid_scroll">

			<?php foreach ($other_rows as $row): ?>
				<div class="cms_translate_string_row cms_grid_row" data-language_id="<?= htmlspecialchars($row['language_id'], ENT_QUOTES) ?>" data-row_type="language">
					<div class="cms_grid_field cms_grid_field_left" style="width: 20%;">
						<div class="cms_grid_field_inner cms_translate_string_label"><?= htmlspecialchars($row['language_id'], ENT_QUOTES) ?></div>
					</div>
					<div class="cms_grid_field cms_grid_field_left" style="width: 80%;">
						<div class="cms_grid_field_inner">
							<?php _panel('cms/cms_translate_string_input', [
									'field_type' => $field_type,
									'value' => $row['value'],
									'language_id' => $row['language_id'],
							]) ?>
						</div>
					</div>
				</div>
			<?php endforeach ?>

		</div>

	</div>

</div>