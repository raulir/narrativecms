<?php
// Plain-text body of the order emails ($mail: notify | confirm | dispatch | refund). Data from shop_model::send_order_emails,
// send_dispatch_email and send_refund_email; labels are the shop/order_email settings. A site module may replace this
// template (//shop_order_email). notify and refund go to the shop (admin link); confirm and dispatch to the buyer (terms line).
?>
<?php if ($mail === 'refund'): ?>
<?php if ($refund_intro !== ''): ?>
<?= $refund_intro ?>


<?php endif ?>
<?= $refunded_label ?>: <?= $refunded ?> / <?= $paid_admin ?>


<?php endif ?>
<?php if ($mail === 'notify' && $payment_note !== ''): ?>
<?= $check_label ?>: <?= $payment_note ?>


<?php endif ?>
<?php if ($mail === 'confirm' && $confirm_intro !== ''): ?>
<?= $confirm_intro ?>


<?php endif ?>
<?php if ($mail === 'dispatch'): ?>
<?php if ($dispatch_intro !== ''): ?>
<?= $dispatch_intro ?>


<?php endif ?>
<?php if ($tracking_number !== ''): ?>
<?= $tracking_label ?>: <?= $tracking_number ?>


<?php endif ?>
<?php endif ?>
<?= $order_label ?> <?= $order_number ?>


<?php foreach ($lines as $line): ?>
<?php if ($line['type'] === 'item'): ?>
<?= $line['quantity'] ?> x <?= $line['name'] ?><?= $line['dims'] !== '' ? ' ('.$line['dims'].')' : '' ?>  <?= $line['amount'] ?>

<?php else: ?>
<?= $delivery_label ?>: <?= $line['name'] ?>  <?= $line['amount'] ?>

<?php endif ?>
<?php endforeach ?>

<?= $total_label ?>: <?= $total_paid ?>


<?= $buyer_label ?>: <?= $buyer ?>

<?= $address_label ?>: <?= $address ?>

<?php if ($mail === 'notify' || $mail === 'refund'): ?>

<?= $payment_label ?>: <?= $payment ?>

<?= $admin_label ?>: <?= $admin_url ?>

<?php elseif ($terms_line !== ''): ?>

<?= str_replace('{{site_url}}', $site_url, $terms_line) ?>

<?php endif ?>
