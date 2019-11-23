<div class="audioplayer_container" data-cms_page_panel_id="<?= $cms_page_panel_id ?>">
	<div class="audioplayer_content">

		<div class="audioplayer_copy">
			<div class="audioplayer_subheading"><?= $subheading ?></div>
			<div class="audioplayer_heading"><?= $heading ?></div>
		</div>
		
		<div class="audioplayer_audio">
			<audio class="audioplayer_audio_audio">
				<source src="<?= $GLOBALS['config']['upload_url'] . $track ?>" type="audio/mpeg">
			</audio>
		</div>
		
		<div class="audioplayer_progress">
			<div class="audioplayer_bar"></div>
			<div class="audioplayer_current">0.0</div>
			<div class="audioplayer_seek">0.0</div>
		</div>
		
		<div class="audioplayer_control">
			<div class="audioplayer_play" <?php _ib($icon_play, 75) ?>></div>
			<div class="audioplayer_pause" <?php _ib($icon_pause, 75) ?>></div>
		</div>

	</div>
</div>