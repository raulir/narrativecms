<div class="shop_basket_container" data-cms_page_panel_id="<?= $cms_page_panel_id ?>">
	<div class="shop_basket_content">
		
		<?php if(count($items)): ?>
		
			<div class="shop_basket_area">
		
				<div class="shop_basket_heading"><?= $heading ?></div>
		
				<div class="shop_basket_count"><?= count($items).' '.(count($items) == 1 ? $items_label_singular : $items_label) ?></div>
				
				<?php foreach($items as $item): ?>
					<div class="shop_basket_item">
	
						<div class="shop_basket_image" <?php _ib($item['image'], 350) ?>></div>
						<div class="shop_basket_item_right">
							<a class="shop_basket_item_heading" <?php _lh('cg/product='.$item['product_id']) ?>><?= $item['heading'] ?></a>
							<div class="shop_basket_item_description"><?= $item['description'] ?></div>
							<div class="shop_basket_elements">
								<div class="shop_basket_element">
									<div class="shop_basket_element_label"><?= $price_label ?></div>
									<div class="cg_h4 shop_basket_element_text shop_basket_element_price"><?= $item['price'] ?></div>
								</div>
								<?php foreach($item['dimensions'] as $dim): ?>
									<div class="shop_basket_element">
										<div class="shop_basket_element_label"><?= $dim['label'] ?></div>
										<div class="shop_basket_element_text"><?= $dim['value'] ?></div>
									</div>
								<?php endforeach ?>
							</div>
							<div class="shop_basket_item_remove" data-item_id="<?= $item['item_id'] ?>"><?= $remove_label ?></div>
						</div>
		
					</div>
				<?php endforeach ?>
			
				<div class="shop_basket_checkout"><?= $proceed_label ?></div>
			
			</div>
			
		<?php else: ?>
			<div class="shop_basket_message"><?= $empty_message ?></div>
			<a class="shop_basket_empty_button" <?php _lh($empty_link) ?>><?= $empty_button ?></a>
		<?php endif ?>

	</div>
</div>
