<div class="cart_container<?= $cart_visible ? ' cart_visible' : '' ?><?= $cart['number'] ? '' : ' cart_empty' ?>"
		data-cart_quantity="<?= $cart['number'] ?>"
		data-checkout_panel="<?= $checkout_panel ?>"
		data-checkout_missing="<?= $checkout_provider_missing ? '1' : '0' ?>"
		data-checkout_ready="<?= !empty($checkout_ready) ? '1' : '0' ?>"
		data-add_error="<?= $add_error ?>"
		data-checkout_error="<?= $checkout_error ?>"
		data-checkout_missing_error="<?= $checkout_missing_error ?>">

	<div class="cart_content">
	<div class="cart_area">

		<?php $cart_number_html = '<div class="cart_quantity">'.$cart['number'].'</div>' ?>
		<div class="cart_label cart_label_desktop"><?= str_replace('{{number}}', $cart_number_html, $cart_label) ?></div>
		<div class="cart_label cart_label_mobile"><?= str_replace('{{number}}', $cart_number_html, $cart_label_mobile) ?></div>

<?php if (!$cart['number']): ?>

		<div class="cart_popup">
			<div class="cart_popup_box">
				<div class="cart_close"></div>
				<div class="cart_popup_empty"><?= $empty_label ?></div>
				<div class="cart_popup_checkout_row">
					<div class="cart_popup_checkout"><?= $checkout_label ?></div>
					<div class="cart_popup_cancel"><?= $cancel_label ?></div>
				</div>
				<div class="cart_popup_message"></div>
			</div>
		</div>

<?php elseif ($cart_details): ?>

		<div class="cart_popup">
			<div class="cart_popup_box">
			<div class="cart_popup_heading"><?= $popup_heading ?></div>
			<div class="cart_data">
				<div class="cart_close"></div>
				<div class="cart_popup_items">
					<?php foreach($cart['items'] as $item): ?>
						<div class="cart_popup_item">
							<div class="cart_popup_item_image" <?php
								if (strpos($item['image'], 'http') === 0 || strpos($item['image'], '/') === 0){
									print('style="background-image:url('.$item['image'].')"');
								} else {
									_ib($item['image'], 300);
								}
							?>></div>
							<div class="cart_popup_item_heading"><?= $item['heading'] ?></div>
							<div class="cart_popup_item_number"><?= $item['number'] ?></div>
							<div class="cart_popup_item_text"><?php if ($item['dims'] !== ''): ?><div class="cart_popup_item_dims"><?= $item['dims'] ?></div><?php endif ?><?php if ($item['attributes']): ?><div class="cart_popup_item_customisation"><?= $customisation_label ?><?php foreach ($item['attributes'] as $attribute): ?><br><?= $attribute['key'] ?>: <?= $attribute['value'] ?><?php endforeach ?></div><?php elseif ($item['description'] !== ''): ?><div class="cart_popup_item_customisation"><?= nl2br($item['description']) ?></div><?php endif ?></div>
							<div class="cart_popup_item_amount"><?= $item['amount'] ?></div>
							<div class="cart_popup_item_delete" data-line_id="<?= $item['line_id'] ?>"><?= $delete_label ?></div>
							<?php if ($item['notice'] !== ''): ?>
								<div class="cart_popup_item_notice"><?= $item['notice'] ?></div>
							<?php endif ?>
							<?php if ($item['warning'] !== ''): ?>
								<div class="cart_popup_item_warning"><?= $item['warning'] ?></div>
							<?php endif ?>
						</div>
					<?php endforeach ?>
				</div>
				<div class="cart_popup_total">
					<div class="cart_popup_total_label"><?= $total_label ?></div>
					<div class="cart_popup_total_amount"><?= $cart['total'] ?></div>
				</div>
				<?php if ($note !== ''): ?>
					<div class="cart_popup_note"><?= $note ?></div>
				<?php endif ?>
			</div>
			<div class="cart_popup_checkout_row">
				<div class="cart_popup_checkout"><?= $checkout_label ?></div>
				<div class="cart_popup_cancel"><?= $cancel_label ?></div>
			</div>
			<div class="cart_popup_message"></div>
			</div>
		</div>

<?php endif ?>

	</div>
	</div>

</div>
