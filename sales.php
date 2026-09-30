<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/cashier.php';
$user = requireCashier();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['role'] !== 'admin') { abortPage(403, 'Only admins can record refunds.', $user); }
    if (!validCsrf()) { abortPage(403, 'Your form expired.', $user); }
    $id = filter_var(postValue('sale_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $reason = trim(postValue('reason'));
    $db = database();
    try {
        if ($reason === '' || strlen($reason) > 500 || postValue('confirm_refund') !== '1') { throw new DomainException('Enter a reason and confirm that the full refund has been made.'); }
        $db->beginTransaction();
        $sale = query('SELECT id, status, total, currency FROM sales WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$sale || $sale['status'] !== 'paid') { throw new DomainException('This sale is missing or already refunded.'); }
        query("UPDATE sales SET status = 'refunded', refunded_at = NOW(), refunded_by = ?, refund_reason = ? WHERE id = ?", [$user['id'], $reason, $id]);
        cashierAudit($user, 'Full refund recorded', receiptNumber($id) . ' · ' . $sale['currency'] . ' ' . $sale['total'] . ' · ' . $reason);
        $db->commit();
        redirectTo('receipt.php?id=' . $id);
    } catch (Throwable $exception) {
        if ($db->inTransaction()) { $db->rollBack(); }
        http_response_code($exception instanceof DomainException ? 422 : 503);
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Unable to record refund.';
    }
}
$page = max(1, (int) (is_scalar($_GET['page'] ?? null) ? $_GET['page'] : 1));
$count = (int) query('SELECT COUNT(*) FROM sales')->fetchColumn(); $pages = max(1, (int) ceil($count / 25)); $page = min($page, $pages); $offset = ($page - 1) * 25;
$sales = query("SELECT * FROM sales ORDER BY id DESC LIMIT 25 OFFSET {$offset}")->fetchAll();
layoutStart('Sales & receipts', $user, 'sales');
?>
<div class="page-heading"><div><h1>Sales &amp; receipts</h1><p>Saved receipts keep the original menu prices, table, waiter, and cashier details.</p></div><a class="button" href="cashier.php">New sale</a></div>
<?php if ($error): ?><p class="error-box" role="alert"><?= escape($error) ?></p><?php endif; ?>
<section><div class="table-scroll"><table><thead><tr><th>Receipt</th><th>Date</th><th>Cashier</th><th>Table / waiter</th><th>Total</th><th>Method</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($sales as $sale): ?><tr><td><?= escape(receiptNumber((int) $sale['id'])) ?></td><td><?= escape($sale['paid_at']) ?></td><td><?= escape($sale['cashier_name']) ?></td><td><?= escape($sale['table_name']) ?><br><?= escape($sale['waiter_name'] ?? '') ?></td><td><?= escape($sale['currency']) ?> <?= number_format((float) $sale['total'], 2) ?></td><td><?= escape(readable($sale['payment_method'])) ?></td><td><?= escape(readable($sale['status'])) ?></td><td><a href="receipt.php?id=<?= (int) $sale['id'] ?>">View / print</a><?php if ($user['role'] === 'admin' && $sale['status'] === 'paid'): ?><details><summary>Record full refund</summary><form method="post"><input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>"><input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>"><label>Reason<input name="reason" maxlength="500" required></label><label class="photo-remove"><input type="checkbox" name="confirm_refund" value="1" required> Full refund has been made</label><button class="danger" type="submit">Record refund</button></form></details><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$sales): ?><p>No sales yet. Create a sale from the cashier dashboard.</p><?php endif; ?><div class="pagination"><span>Page <?= $page ?> of <?= $pages ?></span><div><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">Previous</a><?php endif; ?><?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Next</a><?php endif; ?></div></div></section>
<?php layoutEnd(); ?>
