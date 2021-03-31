<div class="localisation_regionselector_container">

	<div class="localisation_regionselector_content">

		<div class="localisation_regionselector_regions">
			<?php foreach($regions as $region): ?>
				<div class="localisation_regionselector_region 
						<?= $region['region_id'] == $active_region ? ' localisation_regionselector_region_active ' : '' ?>" 
						data-region_id="<?= $region['region_id'] ?>"
						 <?php $region['region_id'] == $active_region ? _ib($arrow, 12) : false ?>>
					<?= $region['label'] ?>
				</div>
			<?php endforeach ?>
		</div>

	</div>

</div>