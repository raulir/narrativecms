<div class="feed_dashboard_item_container" <?php print(empty($block['show']) ? ' style="opacity: 0.3; " ' : ''); ?>>
	
	<?php if (in_array($block['source'], array('twitter', 'instagram', ))): ?>
		<div class="feed_dashboard_item_icon" <?php _ib('feed/feed_icon_'.$block['source'].'.png', 20) ?>></div>
	<?php elseif(!empty($block['icon'])): ?>
		<div class="feed_dashboard_item_icon" <?php _ib($block['icon'], 34); ?>></div>
	<?php endif ?>

	<div class="admin_text feed_dashboard_item_title">
		<?php print(str_replace('/20', '/', $block['date'])); ?> <?php print(!empty($block['username']) ? ' - ' . $block['username'] : ''); ?>
	</div>
	
	<div class="feed_dashboard_item_image_container" <?php _ib('cms/cms_opacity.png', 40) ?>>
		<?php if(!empty($block['image'])): ?>
			<div class="feed_dashboard_item_image" <?php _ib($block['image'], 95) ?>></div>
		<?php endif ?>
	</div>
	
	<div class="feed_dashboard_item_content">
		<?php if($block['source'] == 'article' || $block['source'] == 'project'): ?>
			<div class="feed_dashboard_item_heading"><?php print($block['heading']); ?></div>
		<?php endif ?>
		<div class="feed_dashboard_item_text"><?php print($block['text']); ?></div>
	</div>
	
</div>