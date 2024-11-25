<div class="searchajax_container">
	<div class="searchajax_content">

		<?php if(empty($result) || !empty($error_message)): ?>
		
			<div class="searchajax_message"><?= $error_message ?? $no_result ?></div>
		
		<?php else: ?>
		
			<?php foreach($result as $item): ?>

					<a class="searchajax_item <?= empty($item['image']) ? ' searchajax_item_noimage ' : '' ?>" <?php _lh($item['slug']) ?>>
		
						<!-- div class="searchajax_item_image" <?php _ib($item['image'], ['width' => 100, 
								'css' => 'background-color:'.$item['data']['colour'], ]) ?>></div -->
						<div class="searchajax_item_title <?= $item['score'] > 1 ? 'searchajax_item_bold' : '' ?>">
							<?= $item['heading'] ?>
						</div>
						<!-- div class="searchajax_item_text"><?= $item['text'] ?></div -->
		
					</a>

			<?php endforeach ?>
		
		<?php endif ?>
		
	</div>
</div>