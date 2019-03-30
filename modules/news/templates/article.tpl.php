<div class="article_container">

	<div class="article_content">

		<div class="article_heading">
            <?php if(!empty($title_text)): ?>
			<div class="article_heading_title"><?php print($title_text); ?></div>
            <?php endif ?>
			<h1><?php print($heading); ?></h1>
            <?php if(!empty($title_arrow)): ?>
                <div class="article_arrow iebg" <?php _ib($title_arrow) ?>></div>
            <?php endif ?>
		</div>
        <div style="clear: both; "></div>

        <?php if($images_position == 'after'):?>

        <div class="article_right <?php if($images_position == 'after'):?> mobile_before <?php endif; ?>">

            <?php if(!empty($text)): ?><div class="article_text"><?php print($text); ?></div><?php endif ?>

            <?php if(!empty($read_more) && !empty($read_more_text)): ?>
                <div class="article_link"><a <?php _lh($read_more); ?>><?php print($read_more_text); ?></a></div>
            <?php endif ?>

        </div>

        <?php endif; ?>

		<div class="article_left">

            <?php if( ! empty($article_images)): ?>
                <?php foreach($article_images as $i): ?>
	                <div class="article_image">
	                	<img class="article_image_image" src="<?php _i($i['image'], array('width' => 600, )); ?>">
	                   <?php if(!empty($i['caption'])): ?>
	                        <div class="article_image_caption"><?php print($i['caption']); ?></div>
	                    <?php endif; ?>
 	                </div>
                <?php endforeach; ?>
            <?php endif ?>
		
		</div>

        <?php if($images_position == 'before'):?>

            <div class="article_right <?php if($images_position == 'after'):?> mobile_before <?php endif; ?>">

                <?php if(!empty($text)): ?><div class="article_text"><?php print($text); ?></div><?php endif ?>

                <?php if(!empty($read_more) && !empty($read_more_text)): ?>
                    <div class="article_link"><a <?php _lh($read_more); ?>><?php print($read_more_text); ?></a></div>
                <?php endif ?>

            </div>

        <?php endif ?>
	


		<div style="clear: both; "></div>

	</div>
	
	<?php if(!empty($features)): ?>
		<?php foreach($features as $feature): ?>
			<?php _panel('feature_block', array_merge($feature, array('extra_class' => 'article_text', ))); ?>
		<?php endforeach ?>
	<?php endif ?>

</div>