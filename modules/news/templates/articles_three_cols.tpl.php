
<div class="articles_three_cols_container">
	
	<div class="articles_three_cols_top">
		
	</div>
		
	<div class="articles_three_cols_content">
		
		<?php foreach($articles as $article): ?>
		
			<?php _panel('news/article_thumbnail', $article); ?>

		<?php endforeach ?>
		
		<div style="clear: both; "></div>
		
	</div>
	
	<?php if($load_more): ?>
		<div class="articles_three_cols_bottom_container">
			<div class="articles_three_cols_bottom" data-increment="<?php print($limit_increment); ?>"
					data-types="<?php print(implode(',', $types)); ?>">
				<?php print($bottom_button_text); ?>
			</div>
		</div>
	<?php endif ?>

</div>
