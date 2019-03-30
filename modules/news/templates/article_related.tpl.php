<div class="article_related_container">

	<div class="article_related_content">

        <?php if(!empty($heading)): ?>
			<div class="article_related_heading"><?= $heading ?></div>
        <?php endif ?>
		
		<?php foreach($articles as $article): ?>
		
			<?php _panel('news/article_thumbnail', $article); ?>
		
		<?php endforeach ?>
		
		<div style="clear: both; "></div>
		
	</div>

</div>