<div class="searchajax_container">
	<div class="searchajax_content">

		<?php if(!empty($error_message) || (empty($result) && empty($result_products) && empty($result_other))): ?>
		
			<div class="searchajax_message"><?= !empty($error_message) ? $error_message : $no_result ?></div>
		
		<?php else: ?>
		
			<?php
				$list = !empty($result) ? $result : array_merge($result_products ?? [], $result_other ?? []);
			?>
			<?php foreach($list as $item): ?>

					<a class="searchajax_item<?= empty($item['image']) ? ' searchajax_item_noimage' : '' ?>" <?php _lh($item['slug']) ?>>
		
						<div class="searchajax_item_title<?= !empty($item['score']) && $item['score'] > 1 ? ' searchajax_item_bold' : '' ?>">
							<?= !empty($item['list_heading']) ? $item['list_heading'] : $item['heading'] ?>
						</div>
		
					</a>

			<?php endforeach ?>
		
		<?php endif ?>
		
	</div>
</div>
