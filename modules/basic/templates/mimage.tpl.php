<div class="cms_container mimage_container mimage_responsiveness_<?= $responsiveness ?? 'all' ?>" 
		<?= !empty($colour) ? ('style="background-color: '.$colour.';"') : '' ?>>
		
	<div class="mimage_content">
	
		<div class="mimage_image" <?php !empty($optimise) ? _ib($image, ['width' => 2000, 'pb' => 1]) : _ib($image, ['pb' => 1]) ?>>
			<?php foreach($links as $link): ?>

				<?php $area = json_decode($link['area'], true) ?>
				
				<?php if (empty($area)) $area = ['width' => 1, 'height' => 1] ?>
				
				<?php $sx = 100/$area['width'] ?>
				<?php $sy = 100/$area['height'] ?>
				
				<?php for($x = 0; $x < $area['width']; $x++): ?>
					<?php for($y = 0; $y < $area['height']; $y++): ?>
					
						<?php if(!empty($area['values']) && $area['values'][$x + ($y * $area['width'])] == 1): ?>
							<a class="mimage_link" style="left: <?= $x * $sx  ?>%; top: <?= $y * $sy ?>%; width: <?= $sx ?>%; height: <?= $sy ?>%; "
									<?php _lh($link['link']) ?>></a>
						<?php endif ?>

					<?php endfor ?>
				<?php endfor ?>
			<?php endforeach ?>
		</div>
		
	</div>
	
</div>