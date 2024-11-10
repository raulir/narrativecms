<div class="download_container">
	<div class="download_content">
	
		<div class="download_hero"><?php _panel('lewisham/hero', [
				'heading' => $heading, 
				'cta_arrow' => $arrow_down, 
				'subtitle' => $subtitle, 
				'colour' => $colour, 
				'image' => (!empty($large_image) ? $large_image : $image), 
		]) ?></div>
		
		<?php if(!empty($url)): ?>
			<div class="download_issu"><?php _panel('basic/iframe', ['address' => $url, ]) ?></div>
		<?php endif ?>
		
		<div class="download_bottom <?= !empty($file) ? 'download_file' : '' ?>">
		
			<?php if (!empty($file)): ?>
				<a class="download_download" <?php _lf($file) ?>><?= $cta ?></a>
			<?php endif ?>
			
			<a class="download_previous" <?php _ib($arrow_left, 60) ?> <?php _lh('download/download='.$siblings['last_id']) ?>><?= $previous_label ?></a>
			<a class="download_next" <?php _ib($arrow_right, 60) ?> <?php _lh('download/download='.$siblings['next_id']) ?>><?= $next_label ?></a>
		
		</div>
	
	</div>
</div>