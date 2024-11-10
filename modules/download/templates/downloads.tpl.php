<div class="downloads_container">
	<div class="downloads_content">

		<?php foreach($downloads as $download): ?>

			<a class="downloads_download <?= !empty($download['image']) ? ' downloads_download_image ' : '' ?>" 
					<?php 
						if (empty($download['url'])): 
							_lf($download['file']); 
						else:
							_lh('download/download='.$download['cms_page_panel_id']);
						endif
					?> <?php _ib($download['image'], ['width' => 550, 'css' => ('background-color:'.$download['colour']), ]) ?>>

				<div class="downloads_download_heading"><?= $download['heading'] ?></div>
				<div class="downloads_cta"><?= $cta ?></div>
				
			</a>
		
		<?php endforeach ?>

	</div>
</div>
