<?php foreach ($orders as $order): ?>
	<div class="cms_list_sortable_item cms_list_list_item orders_admin_row" data-order_id="<?= (int)$order['id'] ?>">
		<div class="cms_list_list_tick">[ ]</div>
		<a class="orders_admin_link" <?php _lh('admin/orders/'.(int)$order['id'].'/') ?>>
			<div class="orders_admin_cell"><?php if ($order['payment_note'] !== ''): ?><div class="orders_admin_warn">!</div><?php endif ?>#<?= (int)$order['id'] ?></div>
			<div class="orders_admin_cell"><?= $order['date'] ?></div>
			<div class="orders_admin_cell"><?= $order['email'] !== '' ? $order['email'] : $order['name'] ?></div>
			<div class="orders_admin_cell"><?= (int)$order['items'] ?></div>
			<div class="orders_admin_cell"><?= $order['total'] ?></div>
			<div class="orders_admin_cell"><?= $order['payment_label'] ?></div>
			<div class="orders_admin_cell"><?= $order['status_label'] ?></div>
		</a>
	</div>
<?php endforeach ?>
<?php if (!$orders && $first_page): ?>
	<div class="cms_list_list_message orders_admin_empty">Nothing to show</div>
<?php endif ?>
