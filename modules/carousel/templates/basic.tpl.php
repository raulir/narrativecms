<div class="carousel_basic_container">
	<div class="carousel_basic_content">
	
		<div class="carousel_basic_items carousel_basic_carousel_hidden" data-animation_speed="<?= $speed?>" data-delay="<?= $delay ?>" data-cycle="<?= $cycle ?>">

			<?php foreach($items as $item): ?>
			
				<div class="carousel_basic_item carousel_basic_item_scheme_<?= $item['colour_scheme'] ?>">

					<div class="carousel_basic_item_image" <?php _ib($item['image'], 1400) ?>></div>
					<div class="carousel_basic_item_mobile_image" <?php _ib(!empty($item['mobile_image']) ? $item['mobile_image'] : $item['image'], 900) ?>>
					</div>
					
					<?php if(!empty($item['video'])): ?>
						
						<div class="carousel_basic_item_video">
							<video class="carousel_basic_item_video_video" playsinline reload="auto" height="auto" width="auto" muted>
			             		<source <?php _lfs($item['video']) ?> type="video/mp4">
			            	</video>
		            	</div>
						
					<?php endif ?>
					
					<div class="carousel_basic_item_overlay" <?php _ib($item['overlay'], 1400) ?>></div>
					<div class="carousel_basic_item_mobile_overlay" 
							<?php _ib(!empty($item['mobile_overlay']) ? $item['mobile_overlay'] : $item['overlay'], 600) ?>></div>
							
					<div class="carousel_basic_item_copy">
						<div class="carousel_basic_item_copy_cell">
						
							<?php if(!empty($item['heading'])): ?>
								<div class="carousel_basic_item_heading"><?= $item['heading'] ?></div>
							<?php endif ?>
							
							<?php if(!empty($item['text'])): ?>
								<div class="carousel_basic_item_text"><?= $item['text'] ?></div>
							<?php endif ?>
							
							<?php if(!empty($item['subheading'])): ?>
								<div class="carousel_basic_item_subheading"><?= $item['subheading'] ?></div>
							<?php endif ?>
							
							<?php if(!empty($item['cta_link']['url'])): ?>
								<a <?php _lh($item['cta_link']) ?> class="carousel_basic_item_cta"><?= $item['cta_text'] ?></a>
							<?php endif ?>

						</div>
					</div>

				</div>
			
			<?php endforeach ?>

		</div>
	
		<div class="carousel_basic_arrow_left"></div>
		<div class="carousel_basic_arrow_right"></div>

	</div>
</div>
