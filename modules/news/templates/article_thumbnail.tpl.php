<?php if(!empty($cms_page_panel_id)): ?>
<a class="article_thumbnail_container" <?php _lh('article='.$cms_page_panel_id); ?>>
	<div class="article_thumbnail_content">
        
        <?php if(!empty($article_images)): ?>
        	<?php $tmp = array_values($article_images); $image = array_shift($tmp); ?>
            <div class="article_thumbnail_image iebg" <?php _ib($image['image'], 400); ?>></div>
        <?php endif; ?>
        
        <div class="article_thumbnail_heading"><?= $heading ?></div>
		<div class="article_thumbnail_label"><?= $lead_text ?></div>
		<div class="article_thumbnail_link"><?= $read_more ?></div>
		
	</div>
</a>
<?php endif ?>