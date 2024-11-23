<?php if(empty($_COOKIE['basic_popup_hide_'.$hash])): ?>
	
	<style>
		.basic_popup_<?= $popup_id ?> .basic_popup_content {
			width: <?= $width ?>rem;
		}
		@media only screen and (max-width: <?= $GLOBALS['config']['rem_m_px'] ?>px) {
			.basic_popup_<?= $popup_id ?> .basic_popup_content {
				width: <?= 40 + $width/10 ?>rem;
			}
		}
		
	</style>
	
	<div class="basic_popup_container basic_popup_<?= $popup_id ?>" data-popup_hash="<?= $hash ?>" data-popup_days="<?= $days ?>">
		<div class="basic_popup_overlay basic_popup_cancel"></div>
		<div class="basic_popup_content" <?= !empty($colour) ? (' style="background-color: '.$colour.'; " ') : '' ?>>
		
			<!-- here comes the content -->
		
			<?php if(!empty($image)): ?>
				<div class="basic_popup_image" <?php _ib($image, ['width' => 1200, 'pb' => 1, ]) ?>>
	
					<?php foreach($links as $link): ?>
		
						<?php $area = json_decode($link['area'], true) ?>
						
						<?php if (empty($area)) $area = ['width' => 1, 'height' => 1] ?>
						
						<?php $sx = 100/$area['width'] ?>
						<?php $sy = 100/$area['height'] ?>
						
						<?php for($x = 0; $x < $area['width']; $x++): ?>
							<?php for($y = 0; $y < $area['height']; $y++): ?>
							
								<?php if(!empty($area['values']) && $area['values'][$x + ($y * $area['width'])] == 1): ?>
									<a class="basic_popup_link <?= ($link['link']['target'] == '_none') ? 'basic_popup_cancel' : '' ?>" 
											style="left: <?= $x * $sx  ?>%; top: <?= $y * $sy ?>%; width: <?= $sx ?>%; height: <?= $sy ?>%; "
											<?php _lh($link['link']) ?>></a>
								<?php endif ?>
		
							<?php endfor ?>
						<?php endfor ?>
					<?php endforeach ?>
				
				</div>
			<?php endif ?>
		
		</div>
	</div>

<?php endif ?>