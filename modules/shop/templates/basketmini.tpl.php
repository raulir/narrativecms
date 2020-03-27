<div class="basketmini_container basketmini_container_hidden basketmini_state_closed" data-cms_page_panel_id="<?= $cms_page_panel_id ?>">
	<div class="basketmini_content">

		<div class="basketmini_area">

			<?php if(empty($items)): ?>
				<div class="basketmini_empty">
					<div class="basketmini_heading"><?= $empty_label ?></div>
					<a class="basketmini_empty_cta" <?php _lh($cta_link) ?>><?= $empty_cta_text ?></a>
				</div>
			<?php else: ?>
				<div class="basketmini_closed">
					<div class="basketmini_heading">
						<?= $closed_heading ?>
					</div>
					<div class="basketmini_closed_text">
						<?= str_replace('{{number}}', count($items), count($items) == 1 ? $closed_text_singular : $closed_text_plural) ?>
					</div>
					<a class="basketmini_closed_button"><?= $closed_button_text ?></a>
				</div>
				<div class="basketmini_open">
				
					<div class="basketmini_open_close"></div>
					<div class="basketmini_heading"><?= $open_heading ?></div>
					
					<div class="basketmini_items">
						<?php foreach($items as $item): ?>
							<div class="basketmini_item" data-order_line_id="<?= $item['cms_page_panel_id'] ?>">
								
								<div class="basketmini_item_heading"><?= $item['text'] ?></div>
								<?php if($item['dimension']): ?>
									<div class="basketmini_item_dimension"><?= $shop_options['dimension_prefix'] ?><?= $item['dimension'] ?><?= $shop_options['dimension_suffix'] ?></div>
								<?php endif ?>
								<div class="basketmini_item_price"><?= $shop_options['price_prefix'] ?><?= $item['price'] ?><?= $shop_options['price_suffix'] ?></div>
								
								<div class="basketmini_item_delete"></div>
								
							</div>
						<?php endforeach ?>
					</div>
					
					<div class="basketmini_total_price">
						<div class="basketmini_total_price_label"><?= $price_label ?></div>
						<div class="basketmini_total_price_amount"><?= $shop_options['price_prefix'] ?><?= $price_total ?><?= $shop_options['price_suffix'] ?></div>
					</div>
					
					<?php if(!empty($dimension_total)): ?>
						<div class="basketmini_total_dimension">
							<div class="basketmini_total_dimension_label"><?= $dimension_label ?></div>
							<div class="basketmini_total_dimension_amount"><?= $shop_options['dimension_prefix'] ?><?= $dimension_total ?><?= $shop_options['dimension_suffix'] ?></div>
						</div>
					<?php endif ?>
					
					<a class="basketmini_open_cta" <?php _lh($cta_link) ?>><?= $cta_text ?></a>
					<a class="basketmini_checkout" <?php _lh($checkout_link) ?>><?= $checkout_text ?></a>
				
				</div>
			<?php endif ?>

		</div>
	
		<div class="basketmini_modal">
			<div class="basketmini_modal_table">
				<div class="basketmini_modal_cell">
					<div class="basketmini_modal_text"><?= $modal_text ?></div>
					<div class="basketmini_modal_buttons">
						<div class="basketmini_modal_yes"><?= $modal_yes ?></div>
						<div class="basketmini_modal_cancel"><?= $modal_cancel ?></div>
					</div>
				</div>
			</div>
		</div>	
		
	</div>
</div>
