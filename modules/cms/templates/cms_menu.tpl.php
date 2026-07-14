<div class="cms_menu_container">
	<div class="cms_menu_content">
		<div class="cms_menu_items">
			<?php foreach($menu_items as $menu_item): ?>
		
				<div class="cms_menu_item <?= !empty($children[$menu_item['id']]) ? 'cms_menu_parent ' : '' ?>" <?php _ib('cms/cms_triangle.png', 14) ?>
					<?php if(!empty($children[$menu_item['id']]) && isset($menu_item['ctrl'])): ?> data-cms_ctrl="<?= $menu_item['ctrl'] ?>" <?php endif ?>>
		
					<a class="cms_menu_link" <?php (!empty($menu_item['url']) ? _lh($menu_item['url']) : ''); ?> 
						<?php if(isset($menu_item['ctrl']) && !empty($menu_item['url'])): ?> data-cms_ctrl="<?= $menu_item['ctrl'] ?>" <?php endif ?>>
						
						<?= $menu_item['name'] ?>
					
					</a>
					
					<?php if(!empty($children[$menu_item['id']])): ?>
						<div class="cms_menu_children cms_menu_children_l2">
							<?php foreach($children[$menu_item['id']] as $child): ?>

								<?php if (!empty($children[$child['id']])): ?>

									<div class="cms_menu_child cms_menu_child_parent">
										<a class="cms_menu_link cms_menu_child_label" <?php if (!empty($child['url'])): ?><?php _lh($child['url']); ?><?php else: ?>href="javascript:void(0)"<?php endif ?>>
											<?= $child['name'] ?>
										</a>
										<div class="cms_menu_children cms_menu_children_l3">
											<?php foreach($children[$child['id']] as $grandchild): ?>
												<a class="cms_menu_child cms_menu_link" <?php (!empty($grandchild['url']) ? _lh($grandchild['url']) : ''); ?>>
													<?= $grandchild['name'] ?>
												</a>
											<?php endforeach ?>
										</div>
									</div>

								<?php else: ?>

									<a class="cms_menu_child cms_menu_link" <?php (!empty($child['url']) ? _lh($child['url']) : ''); ?>>
										<?= $child['name'] ?>
									</a>

								<?php endif ?>
							
							<?php endforeach ?>
						</div>
					<?php endif ?>
		
				</div>
				
			<?php endforeach ?>
		</div>
	</div>
</div>
