<?php if (!$allowed): ?>
<div class="orders_admin_container"><div class="orders_admin_empty">No access</div></div>
<?php else: ?>
<div class="orders_admin_container" data-view="<?= $view ?>">

	<div class="cms_toolbar orders_admin_toolbar">

		<div class="cms_list_list_tick cms_list_toolbar_tick">[ ]</div>
		<div class="cms_tool_text">Shop orders</div>

		<div class="cms_toolbar_buttons">
			<?php // float right: first in DOM is rightmost ?>
			<div class="cms_tool_button cms_right orders_admin_search_button">Search</div>
			<input class="admin_tool_select cms_right orders_admin_q" type="text" placeholder="Order number or email">
			<?php foreach (['carts' => 'Carts', 'archive' => 'Archive', 'orders' => 'Orders'] as $key => $label): ?>
				<div class="cms_tool_button cms_right orders_admin_view<?= $key === $view ? ' cms_tool_button_active' : '' ?>" data-view="<?= $key ?>"><?= $label ?></div>
			<?php endforeach ?>
		</div>

	</div>

	<div class="orders_admin_table">
		<?php // Column headings in the row font (bold); a hidden tick keeps them lined up with the cells ?>
		<div class="orders_admin_head">
			<div class="cms_list_list_tick orders_admin_head_tick">[ ]</div>
			<div class="orders_admin_head_cells">
				<div class="orders_admin_cell">Order</div>
				<div class="orders_admin_cell">Date</div>
				<div class="orders_admin_cell">Customer</div>
				<div class="orders_admin_cell">Items</div>
				<div class="orders_admin_cell">Total</div>
				<div class="orders_admin_cell">Payment</div>
				<div class="orders_admin_cell">Status</div>
			</div>
		</div>
		<div class="cms_list_list_container orders_admin_rows"><?php _panel('shop/orders_admin_rows', ['view' => $view]) ?></div>
	</div>

</div>
<?php endif ?>
