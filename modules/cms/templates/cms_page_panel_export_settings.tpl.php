<?php
	$heading = 'Export page panel';
	$save_button = '';
	include __DIR__.'/cms_popup_toolbar.tpl.php';
?>
<div class="cms_page_panel_export_settings_container" data-export_id="<?= (int)$export_id ?>">

	<div class="cms_page_panel_export_settings_content">

		<div class="cms_page_panel_export_settings_heading">Export settings:</div>

		<div class="cms_page_panel_export_settings_row cms_page_panel_export_toggle_row">
			<div class="cms_page_panel_export_col_check">
				<span class="cms_page_panel_export_fake_check">[v]</span>
				<input type="checkbox" class="cms_page_panel_export_opt cms_page_panel_export_opt_hidden" name="include_database" value="1" checked>
			</div>
			<div class="cms_page_panel_export_col_label cms_page_panel_export_settings_text">database data (incl. translations)</div>
			<div class="cms_page_panel_export_col_opts"></div>
			<div class="cms_page_panel_export_col_size cms_page_panel_export_database_size">—</div>
		</div>

		<div class="cms_page_panel_export_settings_row cms_page_panel_export_toggle_row">
			<div class="cms_page_panel_export_col_check">
				<span class="cms_page_panel_export_fake_check">[v]</span>
				<input type="checkbox" class="cms_page_panel_export_opt cms_page_panel_export_opt_hidden" name="include_files" value="1" checked>
			</div>
			<div class="cms_page_panel_export_col_label cms_page_panel_export_settings_text">files (images, videos, uploads)</div>
			<div class="cms_page_panel_export_col_opts"></div>
			<div class="cms_page_panel_export_col_size cms_page_panel_export_files_size">—</div>
		</div>

		<div class="cms_page_panel_export_settings_row cms_page_panel_export_optim_images_row cms_page_panel_export_toggle_row">
			<div class="cms_page_panel_export_col_check">
				<span class="cms_page_panel_export_fake_check">[ ]</span>
				<input type="checkbox" class="cms_page_panel_export_opt cms_page_panel_export_opt_hidden" name="optimised_images" value="1">
			</div>
			<div class="cms_page_panel_export_col_label cms_page_panel_export_col_label_sub cms_page_panel_export_settings_text">optimise images</div>
			<div class="cms_page_panel_export_col_opts">
				<span class="cms_page_panel_export_settings_cutoff_label">cutoff px</span>
				<input type="text" class="cms_page_panel_export_image_cutoff" name="image_cutoff_px" value="<?= (int)$image_cutoff_px ?>">
			</div>
			<div class="cms_page_panel_export_col_size cms_page_panel_export_optim_images_affected">none</div>
		</div>

		<div class="cms_page_panel_export_settings_row cms_page_panel_export_optim_videos_row cms_page_panel_export_toggle_row">
			<div class="cms_page_panel_export_col_check">
				<span class="cms_page_panel_export_fake_check">[ ]</span>
				<input type="checkbox" class="cms_page_panel_export_opt cms_page_panel_export_opt_hidden" name="optimised_videos" value="1">
			</div>
			<div class="cms_page_panel_export_col_label cms_page_panel_export_col_label_sub cms_page_panel_export_settings_text">optimise videos</div>
			<div class="cms_page_panel_export_col_opts">
				<span class="cms_page_panel_export_settings_cutoff_label">compress to</span>
				<select class="cms_page_panel_export_video_quality" name="video_quality">
					<option value="hd"<?= ($video_quality === 'hd' ? ' selected' : '') ?>>hd</option>
					<option value="ld"<?= ($video_quality === 'ld' ? ' selected' : '') ?>>ld</option>
				</select>
			</div>
			<div class="cms_page_panel_export_col_size cms_page_panel_export_optim_videos_affected">none</div>
		</div>

		<div class="cms_page_panel_export_settings_row cms_page_panel_export_toggle_row">
			<div class="cms_page_panel_export_col_check">
				<span class="cms_page_panel_export_fake_check">[ ]</span>
				<input type="checkbox" class="cms_page_panel_export_opt cms_page_panel_export_opt_hidden" name="include_panel_files" value="1">
			</div>
			<div class="cms_page_panel_export_col_label cms_page_panel_export_settings_text">panel source files (php, definitions, js, scss)</div>
			<div class="cms_page_panel_export_col_opts"></div>
			<div class="cms_page_panel_export_col_size cms_page_panel_export_panel_files_size">—</div>
		</div>

		<div class="cms_page_panel_export_settings_total">
			Total estimate: <span class="cms_page_panel_export_total_size">—</span>
		</div>

		<div class="cms_page_panel_export_buttons">
			<div class="cms_tool_button cms_page_panel_export_run">Export</div>
		</div>

		<?php if (!empty($last_export['filename'])): ?>
		<div class="cms_page_panel_export_settings_last_divider"></div>
		<div class="cms_page_panel_export_settings_last">
			<span class="cms_page_panel_export_settings_last_label">Exported last at <?= $last_export['exported_at'] ?></span>
			<a class="cms_tool_button cms_page_panel_export_download" <?php _lh('/admin/export/'.$last_export['filename']); ?>>Download</a>
		</div>
		<?php endif ?>

	</div>

	<div class="cms_page_panel_export_overlay">
		<div class="cms_page_panel_export_overlay_label">Exporting...</div>
	</div>

</div>