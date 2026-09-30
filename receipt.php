<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/cashier.php';
$user = requireCashier();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$sale = query('SELECT * FROM sales WHERE id = ?', [$id])->fetch();
if (!$sale) { abortPage(404, 'Receipt not found.', $user); }
$lines = query('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$id])->fetchAll();
layoutStart('Receipt ' . receiptNumber($id), $user, 'sales');
?>
<div class="page-heading no-print"><h1><?= $sale['status'] === 'refunded' ? 'Refunded receipt' : 'Sale recorded' ?></h1><div class="row-actions"><button type="button" data-print>Print receipt</button><a class="button secondary" href="cashier.php">New sale</a><a href="sales.php">All sales</a></div></div>
<section class="receipt"><div class="receipt-heading"><h2>City Park</h2><p>Sales receipt · <?= escape(receiptNumber($id)) ?></p><strong><?= escape(strtoupper($sale['status'])) ?></strong></div>
<dl><div><dt>Date</dt><dd><?= escape($sale['paid_at']) ?></dd></div><div><dt>Cashier</dt><dd><?= escape($sale['cashier_name']) ?></dd></div><div><dt>Table</dt><dd><?= escape($sale['table_name']) ?></dd></div><div><dt>Waiter</dt><dd><?= escape($sale['waiter_name'] ?? '—') ?></dd></div></dl>
<table><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody><?php foreach ($lines as $line): ?><tr><td><?= escape($line['item_name']) ?></td><td><?= (int) $line['quantity'] ?></td><td><?= number_format((float) $line['unit_price'], 2) ?></td><td><?= number_format((float) $line['line_total'], 2) ?></td></tr><?php endforeach; ?></tbody></table>
<dl><div><dt>Subtotal</dt><dd><?= number_format((float) $sale['subtotal'], 2) ?></dd></div><div><dt>Tax (<?= number_format($sale['tax_rate_bps'] / 100, 2) ?>%)</dt><dd><?= number_format((float) $sale['tax_amount'], 2) ?></dd></div><div><dt>Total · <?= escape($sale['currency']) ?></dt><dd><?= number_format((float) $sale['total'], 2) ?></dd></div><div><dt>Method</dt><dd><?= escape(readable($sale['payment_method'])) ?></dd></div><div><dt>Received</dt><dd><?= number_format((float) $sale['tendered'], 2) ?></dd></div><div><dt>Change</dt><dd><?= number_format((float) $sale['change_amount'], 2) ?></dd></div><?php if ($sale['payment_reference']): ?><div><dt>Reference</dt><dd><?= escape($sale['payment_reference']) ?></dd></div><?php endif; ?></dl>
<?php if ($sale['status'] === 'refunded'): ?><p>Full refund recorded: <?= escape($sale['refunded_at']) ?><br><?= escape($sale['refund_reason']) ?></p><?php endif; ?><p class="receipt-footer">Thank you for visiting City Park.</p></section>
<script src="cashier.js" defer></script><?php layoutEnd(); ?>
