<div class="feature_block_container">

	<div class="feature_block_content">

		<div class="feature_block_left"></div>
		
		<?php if(!empty($text)): ?>
			<div class="feature_block_text <?php print(!empty($extra_class) ? $extra_class : ''); ?>">
				<?php print($text); ?>
			</div>
		<?php endif ?>

		<div style="clear: both; "></div>

		<?php if(!empty($facts)): ?>
			<div class="feature_block_facts <?php print(!empty($text) ? 'feature_block_facts_shifted' : ''); ?>">
				<?php foreach($facts as $fact): ?>
					<div class="feature_block_fact">
						<div class="feature_block_fact_label">
							<?php print($fact['label']); ?>
						</div>
						<div class="feature_block_fact_text">
							<?php print($fact['text']); ?>
						</div>
					</div>
				<?php endforeach ?>
			</div>
		<?php endif ?>

		<?php if(!empty($video_id) && !empty($video_site)): ?>
			<div class="feature_block_video">
			
				<?php if ($video_site == 'youtube'): ?>
					<iframe title="YouTube video player" height="415" width="690" 
							src="http://www.youtube.com/embed/<?php print($video_id); ?>?wmode=transparent" 
							frameborder="0" allowfullscreen wmode="opaque"></iframe>
				<?php elseif ($video_site == 'dailymotion'): ?>
					<iframe title="DailyMotion video player" height="415" width="690"
							src="//www.dailymotion.com/embed/video/<?php print($video_id); ?>?wmode=transparent" 
							frameborder="0" allowfullscreen wmode="opaque"></iframe>
				<?php endif ?>

			</div>
			<div class="feature_block_image_text"><?php print($image_text); ?></div>
			<div class="feature_block_image_copyright"><?php print(!empty($image_copyright) ? '&copy;'.$image_copyright : ''); ?></div>
		<?php elseif(!empty($image)): ?>
			<div class="feature_block_image" <?php _ib($image, array('width' => 690,)); ?>></div>
			<div class="feature_block_image_text"><?php print($image_text); ?></div>
			<div class="feature_block_image_copyright"><?php print(!empty($image_copyright) ? '&copy;'.$image_copyright : ''); ?></div>
		<?php elseif(!empty($quote)): ?>
			<div class="feature_block_quote">&ldquo;<?php print($quote); ?>&rdquo;</div>
		<?php endif ?>
		
	</div>

</div>
