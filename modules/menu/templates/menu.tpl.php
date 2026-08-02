<div class="menu_container">

	<div class="menu_content">
	
		<?php foreach($links as $link): ?>
		
			<?php if(!empty($link['cms_scroll_to'])): ?>
		
				<a class="menu_item menu_<?= $link['align'] ?> <?= empty($link['mobile_hidden']) ? '' : 'menu_item_mobile_hidden' ?> cms_scroll_to" data-cms_scroll_to="<?= $link['hash'] ?>">
					<?= $link['text'] ?>
				</a>
				
			<?php else: ?>
				
				<a class="menu_item menu_<?= $link['align'] ?> <?= empty($link['mobile_hidden']) ? '' : 'menu_item_mobile_hidden' ?>" <?php _lh($link['href']); ?>>
					<?= $link['text'] ?>
				</a>

			<?php endif ?>
		
		<?php endforeach ?>
	
	</div>

</div>
