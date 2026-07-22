<?php if (!empty($html_cache)): ?>
<?= $html_cache ?>
<?php else: ?>
<div class="searchajax_container">
	<div class="searchajax_content">

		<?php if(empty($result) || !empty($error_message)): ?>
		
			<div class="searchajax_message"><?= !empty($error_message) ? $error_message : $no_result ?></div>
		
		<?php else: ?>
		
			<?php foreach($result as $item): ?>

					<a class="searchajax_item<?= empty($item['image']) ? ' searchajax_item_noimage' : '' ?>" <?php _lh($item['slug']) ?>>
		
						<div class="searchajax_item_title<?= !empty($item['score']) && $item['score'] > 1 ? ' searchajax_item_bold' : '' ?>">
							<?= $item['heading'] ?>
						</div>
		
					</a>

			<?php endforeach ?>
		
		<?php endif ?>
		
	</div>
</div>
<?php endif ?>
