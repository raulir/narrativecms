<div class="basic_vimeo_container basic_vimeo_scheme_<?= $scheme ?> basic_vimeo_buttons_<?= (int)!empty($subsfile) ?>" 
		data-autoplay="<?= $autoplay ?? '' ?>" data-volume="0.8" 
		<?= !empty($subsfile) ? (' data-subsfile="'.$subsfile.'"') : '' ?> data-next_subs="0">
	<div class="basic_vimeo_content">

		<div class="basic_vimeo_area" <?php _ib($image, 300) ?>>
	
			<script src="https://player.vimeo.com/api/player.js" defer></script>
		
			<iframe class="basic_vimeo_iframe" src="https://player.vimeo.com/video/<?= $vimeo_id ?>?background=1" 
					width="640" height="360" frameborder="0" 
					allow="autoplay; fullscreen" allowfullscreen></iframe>
			
			<div class="basic_vimeo_toolbar">
			
				<div class="basic_vimeo_play">
					<div class="basic_vimeo_play_button" <?php _ib($play_button, 30) ?>></div>
					<div class="basic_vimeo_pause_button" <?php _ib($pause_button, 30) ?>></div>
				</div>
				
				<div class="basic_vimeo_current">00:00/00:00</div>
				
				<div class="basic_vimeo_progress">
					<div class="basic_vimeo_progress_bar">
						<div class="basic_vimeo_progress_current"></div>
					</div>
					<div class="basic_vimeo_progress_search"></div>
				</div>
			
				<div class="basic_vimeo_sound basic_vimeo_sound_is_off">
					<div class="basic_vimeo_sound_off" <?php _ib($sound_off, 30) ?>></div>
					<div class="basic_vimeo_sound_on" <?php _ib($sound_on, 30) ?>></div>
				</div>
				
				<div class="basic_vimeo_volume">
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.1"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.2"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.3"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.4"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.5"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.6"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.7"></div>
					<div class="basic_vimeo_volume_button basic_vimeo_volume_button_active" data-volume="0.8"></div>
					<div class="basic_vimeo_volume_button" data-volume="0.9"></div>
					<div class="basic_vimeo_volume_button" data-volume="1.0"></div>
				</div>

				<?php if(!empty($subsfile)): ?>
					<div class="basic_vimeo_subtitles_button" <?php _ib($subtitles_button, 30) ?>></div>
				<?php endif ?>
			
			</div>
			
			<div class="basic_vimeo_subtitles"></div>
			
			<?php if(empty($autoplay)): ?>
				<div class="basic_vimeo_start" <?php _ib($play_large, 400) ?>></div>
			<?php endif ?>
			
		</div>
	
	</div>
</div>