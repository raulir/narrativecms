<div class="downloads_container">
	<div class="downloads_content">
	
		<?php foreach($downloads as $download): ?>

			<a class="downloads_download" <?php _lf($download['file']) ?> <?php _ib($download['image'], 550) ?>>
				<div class="downloads_download_inner">

					<div class="downloads_download_heading"><?= $download['heading'] ?></div>
					<div class="downloads_cta"><?= $cta ?></div>
				
				</div>
			</a>
		
		<?php endforeach ?>
	
	</div>
</div>
