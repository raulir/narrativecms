<div class="basketmini_container">
	<div class="basketmini_content">
		<div class="basketmini_closed">
			<div class="basketmini_closed_heading"><?= $closed_heading ?></div>
			<a class="basketmini_closed_cta" <?php _lh($cta_link) ?>><?= $cta_text ?></div>
		</div>
		<div class="basketmini_open">
			<div class="basketmini_open_heading"><?= $open_heading ?></div>
			<?php if(!empty($items)): ?>
				<div class="basketmini_items_heading"><?= $items_heading ?></div>
				<?php foreach($items as $item): ?>
					<div class="basketmini_item">
						
						<div class="basketmini_item_heading"><?= $item['text'] ?></div>
						<?php if($item['dimension']): ?>
							<div class="basketmini_item_dimension"><?= $dimension_prefix ?><?= $item['dimension'] ?><?= $dimension_suffix ?></div>
						<?php endif ?>
						<div class="basketmini_item_price"><?= $price_prefix ?><?= $item['price'] ?><?= $price_suffix ?></div>
						
					</div>
				<?php endforeach ?>
			<?php endif ?>
			<?php if(!empty($dimension_total)): ?>
				<div class="basketmini_total_dimension">
					<div class="basketmini_total_dimension_label"><?= $dimension_label ?></div>
					<div class="basketmini_total_dimension_amount"><?= $dimension_prefix ?><?= $dimension_total ?><?= $dimension_suffix ?></div>
				</div>
			<?php endif ?>
			<div class="basketmini_total_price">
				<div class="basketmini_total_price_label"><?= $price_label ?></div>
				<div class="basketmini_total_price_amount"><?= $price_prefix ?><?= $price_total ?><?= $price_suffix ?></div>
			</div>
			<a class="basketmini_open_cta" <?php _lh($cta_link) ?>><?= $cta_text ?></div>
			<a class="basketmini_checkout" <?php _lh($checkout_link) ?>><?= $checkout_text ?></div>
		</div>
	</div>
</div>