<div class="cart_container<?= !empty($cart_visible) ? ' cart_visible' : '' ?><?= empty($cart['number']) ? ' cart_empty' : '' ?>"
		data-cart_quantity="<?= (int)($cart['number'] ?? 0) ?>"
		data-cart_source="<?= htmlspecialchars($cart_source ?? '', ENT_QUOTES, 'UTF-8') ?>"
		data-checkout_panel="<?= htmlspecialchars($checkout_panel ?? '', ENT_QUOTES, 'UTF-8') ?>"
		data-checkout_missing="<?= !empty($checkout_provider_missing) ? '1' : '0' ?>"
		data-mixed_source_error="<?= htmlspecialchars($mixed_source_error ?? '', ENT_QUOTES, 'UTF-8') ?>"
		data-stock_error="<?= htmlspecialchars($stock_error ?? '', ENT_QUOTES, 'UTF-8') ?>"
		data-missing_variant_error="<?= htmlspecialchars($missing_variant_error ?? '', ENT_QUOTES, 'UTF-8') ?>">

	<div class="cart_content">
	<div class="cart_area">

		<div class="cart_label cart_label_desktop"><?= $cart['number_text'] ?></div>
		<div class="cart_label cart_label_mobile"><?= $cart['number_text_mobile'] ?></div>

<?php if (empty($cart_details)): ?>

		<div class="cart_popup">
			<div class="cart_close"></div>
			<div class="cart_popup_empty"><?= $empty_label ?></div>
		</div>

<?php elseif (empty($cart['number'])): ?>

		<div class="cart_popup">
			<div class="cart_close"></div>
			<div class="cart_popup_empty"><?= $empty_label ?></div>
		</div>

<?php else: ?>

		<div class="cart_popup">
			<div class="cart_popup_heading"><?= $popup_heading ?></div>
			<div class="cart_data">
				<div class="cart_close"></div>
				<div class="cart_popup_items">
					<?php foreach($cart['items'] as $item): ?>
						<div class="cart_popup_item">
							<div class="cart_popup_item_image" <?php
								if (!empty($item['image']) && (strpos($item['image'], 'http') === 0 || strpos($item['image'], '/') === 0)){
									print('style="background-image:url('.htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8').')"');
								} else {
									_ib($item['image'] ?? '');
								}
							?>></div>
							<div class="cart_popup_item_heading"><?= $item['heading'] ?></div>
							<div class="cart_popup_item_number"><?= $item['number'] ?></div>
							<div class="cart_popup_item_text"><?= $item['text'] ?></div>
							<div class="cart_popup_item_amount"><?= $item['amount'] ?></div>
							<div class="cart_popup_item_delete" data-item_id="<?= $item['line_id'] ?>"><?= $delete_label ?></div>
						</div>
					<?php endforeach ?>
				</div>
				<div class="cart_popup_total">
					<div class="cart_popup_total_label"><?= $total_label ?></div>
					<div class="cart_popup_total_amount"><?= $cart['total'] ?></div>
				</div>
				<?php if (!empty($note)): ?>
					<div class="cart_popup_note"><?= $note ?></div>
				<?php endif ?>
			</div>
			<div class="cart_popup_checkout_row">
				<div class="cart_popup_checkout"><?= $checkout_label ?></div>
				<?php if (!empty($checkout_provider_missing)): ?>
					<div class="cart_checkout_error">Select shop checkout provider!</div>
				<?php endif ?>
			</div>
		</div>

<?php endif ?>

	</div>
	</div>

</div>
