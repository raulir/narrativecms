<?php if (empty($order['cms_page_panel_id'])): ?>
<div class="order_admin_container"><div class="order_admin_missing">Order not found</div></div>
<?php else: ?>
<div class="order_admin_container" data-order_id="<?= (int)$order['cms_page_panel_id'] ?>">

	<div class="cms_toolbar order_admin_toolbar">
		<a class="cms_tool_button order_admin_back" <?php _lh($back_url) ?>><div class="order_admin_back_arrow" <?php _ib('cms/cms_paging_previous.png', 30) ?>></div></a>
		<div class="cms_tool_text order_admin_heading"><?= $heading ?></div>
	</div>

	<div class="order_admin_content">

		<?php if ($order['payment_note'] !== ''): ?>
			<div class="order_admin_note_warn"><?= $order['payment_note'] ?></div>
		<?php endif ?>

		<div class="order_admin_column order_admin_column_left">

			<div class="order_admin_card">
				<div class="order_admin_card_heading">Items</div>
				<?php foreach ($lines as $line): ?>
					<div class="order_admin_line">
						<div class="order_admin_thumb" <?php if ($line['image'] !== ''): _ib($line['image'], 200); endif ?>></div>
						<div class="order_admin_line_copy">
							<div class="order_admin_line_name"><?= $line['name'] ?></div>
							<?php if ($line['dims'] !== ''): ?><div class="order_admin_line_dims"><?= $line['dims'] ?></div><?php endif ?>
						</div>
						<div class="order_admin_line_math"><?= (int)$line['quantity'] ?> × <?= $line['unit_price'] ?></div>
						<div class="order_admin_line_total"><?= $line['total'] ?></div>
						<?php if ($line['returnable']): ?>
							<div class="order_admin_line_stock"><div class="order_admin_button order_admin_return" data-line_id="<?= (int)$line['line_id'] ?>">Return to stock</div></div>
						<?php elseif ($line['returned'] !== ''): ?>
							<div class="order_admin_line_stock order_admin_muted">Returned to stock <?= $line['returned'] ?></div>
						<?php endif ?>
					</div>
				<?php endforeach ?>
				<?php if (!$lines): ?>
					<div class="order_admin_text order_admin_muted">No items</div>
				<?php endif ?>
			</div>

			<div class="order_admin_card">
				<div class="order_admin_card_heading">Summary</div>
				<div class="order_admin_sum_row"><div class="order_admin_sum_label">Subtotal</div><div class="order_admin_sum_value"><?= (int)$item_qty ?> <?= (int)$item_qty === 1 ? 'item' : 'items' ?></div><div class="order_admin_sum_amount"><?= $subtotal ?></div></div>
				<div class="order_admin_sum_row"><div class="order_admin_sum_label">Delivery</div><div class="order_admin_sum_value"><?= $delivery_name ?></div><div class="order_admin_sum_amount"><?= $delivery_total ?></div></div>
				<div class="order_admin_sum_row order_admin_sum_total"><div class="order_admin_sum_label">Total</div><div class="order_admin_sum_value"></div><div class="order_admin_sum_amount"><?= $total ?></div></div>
				<?php if ($paid !== ''): ?>
					<div class="order_admin_sum_row"><div class="order_admin_sum_label">Paid</div><div class="order_admin_sum_value"><?= $order['payment_provider'] ?> <?= $order['payment_ref'] ?></div><div class="order_admin_sum_amount"><?= $paid ?></div></div>
				<?php endif ?>
				<?php if ($refunded !== ''): ?>
					<div class="order_admin_sum_row"><div class="order_admin_sum_label">Refunded</div><div class="order_admin_sum_value"></div><div class="order_admin_sum_amount"><?= $refunded ?></div></div>
				<?php endif ?>
			</div>

			<div class="order_admin_card">
				<div class="order_admin_card_heading">History</div>
				<textarea class="order_admin_comment" placeholder="Leave a comment"></textarea>
				<div class="order_admin_button order_admin_post">Post</div>
				<div class="order_admin_log">
					<?php foreach ($history as $row): ?>
						<div class="order_admin_log_row<?= $row['comment'] ? ' order_admin_log_comment' : '' ?>">
							<div class="order_admin_log_time"><?= $row['time'] ?></div>
							<div class="order_admin_log_line"><?= nl2br($row['line']) ?><?php if ($row['user_name'] !== ''): ?> <div class="order_admin_log_user"><?= $row['user_name'] ?></div><?php endif ?></div>
						</div>
					<?php endforeach ?>
				</div>
			</div>

		</div><div class="order_admin_column order_admin_column_right">

			<div class="order_admin_card">
				<div class="order_admin_card_heading">Customer</div>
				<?php if ($order['buyer_name'] === '' && $order['buyer_email'] === ''): ?>
					<div class="order_admin_text order_admin_muted">Not known yet</div>
				<?php endif ?>
				<div class="order_admin_text"><?= $order['buyer_name'] ?></div>
				<div class="order_admin_text"><?= $order['buyer_email'] ?></div>
				<?php if ((int)($order['user_id'] ?? 0) > 0): ?>
					<a class="order_admin_text order_admin_link" <?php _lh('admin/cms_page_panel/'.(int)$order['user_id'].'/') ?>>Site user</a>
				<?php endif ?>
			</div>

			<div class="order_admin_card">
				<div class="order_admin_card_heading">Delivery</div>
				<?php if ($delivery_name !== ''): ?>
					<div class="order_admin_text order_admin_delivery_method"><?= $delivery_name ?></div>
				<?php endif ?>
				<?php foreach (['shipping_name', 'shipping_address1', 'shipping_address2', 'shipping_city', 'shipping_postcode', 'shipping_county', 'shipping_country', 'shipping_phone'] as $field): ?>
					<?php if ($order[$field] !== ''): ?>
						<div class="order_admin_text"><?= $order[$field] ?></div>
					<?php endif ?>
				<?php endforeach ?>
				<?php if ($order['tracking_number'] !== ''): ?>
					<div class="order_admin_text order_admin_tracking_line">Tracking number: <?= $order['tracking_number'] ?></div>
				<?php endif ?>
				<?php if ($order['shipping_address1'] === '' && $delivery_name === ''): ?>
					<div class="order_admin_text order_admin_muted">Not known yet</div>
				<?php endif ?>
			</div>

			<div class="order_admin_card">
				<div class="order_admin_card_heading">Status</div>
				<div class="order_admin_status_row">
					<div class="order_admin_status_label">Payment</div>
					<?php if ($payment_editable): ?>
						<select class="admin_tool_select order_admin_payment_status">
							<?php foreach ($payment_options as $key => $label): ?>
								<option value="<?= $key ?>"<?php if ($payment_status === $key): ?> selected="selected"<?php endif ?>><?= $label ?></option>
							<?php endforeach ?>
						</select>
					<?php else: ?>
						<?php // A draft or abandoned cart: payment only comes from the payment provider ?>
						<div class="order_admin_status_fixed"><?= $payment_label ?></div>
					<?php endif ?>
				</div>
				<div class="order_admin_status_row">
					<div class="order_admin_status_label">Order</div>
					<?php if ($finished): ?>
						<div class="order_admin_status_fixed"><?= $status_label ?></div>
					<?php else: ?>
						<select class="admin_tool_select order_admin_status">
							<?php foreach ($status_options as $key => $label): ?>
								<option value="<?= $key ?>"<?php if ($status === $key): ?> selected="selected"<?php endif ?>><?= $label ?></option>
							<?php endforeach ?>
						</select>
					<?php endif ?>
				</div>
				<?php if ($refund_reminder): ?>
					<?php // Shown by the JS while Cancelled is chosen on a paid order ?>
					<div class="order_admin_status_row order_admin_cancel_note">
						<div class="order_admin_status_label"></div>
						<div class="order_admin_cancel_text">Cancelling does not refund the buyer: make the refund in Stripe; it will show here.</div>
					</div>
				<?php endif ?>
				<?php // Shown by the JS while the Order select is Fulfilled and the order is not Fulfilled yet ?>
				<div class="order_admin_dispatch" data-status="<?= $status ?>">
					<div class="order_admin_status_row">
						<div class="order_admin_status_label"></div>
						<?php // CMS tick ([ ] / [v], as in the lists): the admin CSS shows no native checkboxes ?>
						<div class="order_admin_email_buyer" data-on="<?= $dispatch_sent === '' ? 1 : 0 ?>"><div class="order_admin_email_tick"><?= $dispatch_sent === '' ? '[v]' : '[ ]' ?></div> Email the buyer</div>
					</div>
					<?php if ($dispatch_sent !== ''): ?>
						<div class="order_admin_status_row">
							<div class="order_admin_status_label"></div>
							<div class="order_admin_status_note">Sent <?= $dispatch_sent ?></div>
						</div>
					<?php endif ?>
					<div class="order_admin_status_row">
						<label class="order_admin_status_label" for="order_admin_tracking">Tracking number</label>
						<input class="admin_tool_select order_admin_tracking" id="order_admin_tracking" type="text" value="<?= $order['tracking_number'] ?>">
					</div>
				</div>
				<div class="order_admin_button order_admin_save_status">Save</div>
				<div class="order_admin_text order_admin_muted order_admin_status_help"><?= $finished ? 'Finished: the stock is posted and the order status cannot change.' : 'Finished posts the stock and cannot be undone.' ?></div>
			</div>

		</div>

	</div>

</div>
<?php endif ?>
