<div class="cms_menu_container">
	<?php foreach($menu_items as $menu_item): ?>

		<div class="cms_menu_item <?php print(!empty($children[$menu_item['id']]) ? ' cms_menu_parent ' : ''); ?>">

			<a class="cms_menu_link" <?php (!empty($menu_item['url']) ? _lh($menu_item['url']) : ''); ?>>
				<?php print($menu_item['name']); ?>
			</a>
			
			<?php if(!empty($children[$menu_item['id']])): ?>
				<div class="cms_menu_triangle" <?php _ib('/modules/cms/img/cms_triangle.png'); ?>></div>
				<div class="cms_menu_children">
					<?php foreach($children[$menu_item['id']] as $child): ?>
						
						<a class="cms_menu_child cms_menu_link" <?php (!empty($child['url']) ? _lh($child['url']) : ''); ?>>
							<?php print($child['name']); ?>
						</a>
					
					<?php endforeach ?>
				</div>
			<?php endif ?>

		</div>
		
	<?php endforeach ?>
</div>
