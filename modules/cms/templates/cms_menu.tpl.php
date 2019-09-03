<div class="cms_menu_container">
	<div class="cms_menu_content">
		<div class="cms_menu_items">
			<?php foreach($menu_items as $menu_item): ?>
		
				<div class="cms_menu_item <?= !empty($children[$menu_item['id']]) ? 'cms_menu_parent ' : '' ?>" <?php _ib('cms/cms_triangle.png', 14) ?>>
		
					<a class="cms_menu_link" <?php (!empty($menu_item['url']) ? _lh($menu_item['url']) : ''); ?>>
						<?php print($menu_item['name']); ?>
					</a>
					
					<?php if(!empty($children[$menu_item['id']])): ?>
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
	</div>
</div>
