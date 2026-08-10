<div class="menu_container">

	<div class="menu_content">
	
		<?php foreach($links as $link): ?>
		
			<?php if(!empty($link['cms_scroll_to'])): ?>
		
				<a class="menu_item menu_<?= $link['align'] ?> <?= empty($link['mobile_hidden']) ? '' : 'menu_item_mobile_hidden' ?> cms_scroll_to" data-cms_scroll_to="<?= $link['hash'] ?>">
					<?= $link['text'] ?>
				</a>
				
			<?php else: ?>
				
				<a class="menu_item menu_<?= $link['align'] ?> <?= empty($link['mobile_hidden']) ? '' : 'menu_item_mobile_hidden' ?><?= !empty($link['is_current']) ? ' menu_item_active' : '' ?>"
						<?php
						// Always pass CMS link array so single_page_mode gets data-_pl (including "current" page)
						_lh(
							!empty($link['link']) ? $link['link'] : '',
							!empty($link['hash']) ? ['anchor' => $link['hash']] : []
						);
						?>>
					<?= $link['text'] ?>
				</a>

			<?php endif ?>
		
		<?php endforeach ?>
	
	</div>

</div>
