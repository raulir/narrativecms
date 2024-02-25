<div class="carousel_basic_container carousel_basic_count_<?= count($items) ?> <?= !empty($style_id) ? 'carousel_basic_style_'.$style_id : '' ?>">
	<div class="carousel_basic_content">
	
		<?php if(!empty($heading)): ?>
			<div class="carousel_basic_heading"><?= $heading ?></div>
		<?php endif ?>
	
		<div class="carousel_basic_items carousel_basic_carousel_hidden" data-animation_speed="<?= $speed?>" 
				data-delay="<?= $delay ?>" data-cycle="<?= $cycle ?>">

			<?php foreach($items as $item): ?>
			
				<div class="carousel_basic_item <?= !empty($item['scheme']) ? ' carousel_basic_item_scheme_'.$item['scheme'] : '' ?>
						<?= !empty($item['id']) ? ' carousel_basic_item_id_'.$item['id'].' ' : '' ?>" 
						<?= !empty($item['id']) ? ' data-id="'.$item['id'].'" ' : '' ?> 
						<?= !empty($item['scheme']) ? ' data-scheme="'.$item['scheme'].'" ' : '' ?>>

					<div class="carousel_basic_item_image" <?php _ib($item['image'], ['width' => 1100, 'output' => 'jpg']) ?>></div>
					<div class="carousel_basic_item_mobile_image" <?php _ib(!empty($item['mobile_image']) ? $item['mobile_image'] : $item['image'], 700) ?>>
					</div>
					
					<?php if(!empty($item['video'])): ?>
						
						<div class="carousel_basic_item_video">
							<video class="carousel_basic_item_video_video" playsinline reload="auto" muted>
								<source src="<?= $GLOBALS['config']['upload_url'] . $item['video'] ?>" type="video/mp4">
								<!-- <?php _lfs($item['video']) ?> -->
			            	</video>
		            	</div>
						
					<?php endif ?>
					
					<?php if(!empty($item['overlay'])): ?>
						<div class="carousel_basic_item_overlay" <?php _ib($item['overlay'], 1400) ?>></div>
						<div class="carousel_basic_item_mobile_overlay" 
								<?php _ib(!empty($item['mobile_overlay']) ? $item['mobile_overlay'] : $item['overlay'], 600) ?>></div>
					<?php endif ?>
							
					<?php if(!empty($item['heading']) || !empty($item['text']) || !empty($item['subheading']) || !empty($item['cta_text'])): ?>
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
					<?php endif ?>

				</div>
			
			<?php endforeach ?>

		</div>
	
		<div class="carousel_basic_arrow carousel_basic_arrow_left" <?php !empty($arrow_left) ? _ib($arrow_left, 30) : '' ?>></div>
		<div class="carousel_basic_arrow carousel_basic_arrow_right" <?php !empty($arrow_right) ? _ib($arrow_right, 30) : '' ?>></div>

	</div>
</div>
