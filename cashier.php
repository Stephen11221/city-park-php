<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/cashier.php';
$user = requireCashier();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) { abortPage(403, 'Your form expired. Reload and try again.', $user); }
    $db = database();
    try {
        $db->beginTransaction();
        if (postValue('action') === 'assign') {
            $tableId = filter_var(postValue('table_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $waiterId = postValue('waiter_id') === '' ? 0 : filter_var(postValue('waiter_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$tableId || $waiterId === false) { throw new DomainException('Choose a table and an active waiter.'); }
            assignWaiter($user, (int) $tableId, (int) $waiterId);
            $db->commit();
            $_SESSION['flash'] = 'Table assignment saved.';
            redirectTo('cashier.php#tables');
        } elseif (postValue('action') === 'checkout') {
            $saleId = checkoutSale($user);
            $db->commit();
            redirectTo('receipt.php?id=' . $saleId);
        } else { throw new DomainException('Unknown cashier action.'); }
    } catch (Throwable $exception) {
        if ($db->inTransaction()) { $db->rollBack(); }
        // A concurrent retry may have committed the same request key first.
        if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
            $existing = query('SELECT id FROM sales WHERE request_key = ? AND cashier_id = ?', [postValue('request_key'), $user['id']])->fetchColumn();
            if ($existing) { redirectTo('receipt.php?id=' . $existing); }
        }
        http_response_code($exception instanceof DomainException ? 422 : 503);
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Unable to save this transaction. Please try again.';
    }
}
$currency = cashierCurrency(); $taxBps = cashierTaxBps();
$items = query('SELECT * FROM menu_items ORDER BY category, item_name')->fetchAll();
$tables = query("SELECT f.id, f.facility_name, f.capacity, f.status, p.park_name, p.status AS park_status, a.waiter_id, u.full_name AS waiter_name, u.status AS waiter_status, u.employment_status FROM facilities f JOIN parks p ON p.id = f.park_id LEFT JOIN table_assignments a ON a.facility_id = f.id LEFT JOIN users u ON u.id = a.waiter_id WHERE f.kind = 'table' ORDER BY p.park_name, f.facility_name")->fetchAll();
$waiters = query("SELECT id, full_name FROM users WHERE role = 'staff' AND status = 'active' AND employment_status = 'employed' ORDER BY full_name")->fetchAll();
$key = postValue('request_key');
if (!isset($_SESSION['checkout_keys'][$key])) { $key = bin2hex(random_bytes(32)); $_SESSION['checkout_keys'][$key] = time(); }
if (count($_SESSION['checkout_keys']) > 50) { array_shift($_SESSION['checkout_keys']); }
layoutStart('Cashier dashboard', $user, 'cashier');
?>
<div class="page-heading"><div><span class="eyebrow">CITY PARK POINT OF SALE</span><h1>Cashier dashboard</h1><p>Select meals, choose a table, and record payment. <?= escape($currency) ?> · <?= number_format($taxBps / 100, 2) ?>% added tax.</p></div><a class="button secondary" href="sales.php">Sales &amp; receipts →</a></div>
<?php if ($error): ?><div class="error-box" role="alert"><?= escape($error) ?></div><?php endif; ?>
<form method="post" id="pos-form" data-currency="<?= escape($currency) ?>" data-tax-bps="<?= $taxBps ?>">
<input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>"><input type="hidden" name="action" value="checkout"><input type="hidden" name="request_key" value="<?= escape($key) ?>">
<div class="pos-grid"><section><div class="detail-heading"><h2>Food &amp; drinks</h2><input id="menu-search" type="search" placeholder="Search meals or category" aria-label="Search menu"></div><div class="pos-menu">
<?php foreach ($items as $item): $available = $item['status'] === 'available'; $qty = is_array($_POST['qty'] ?? null) && is_string($_POST['qty'][$item['id']] ?? null) ? $_POST['qty'][$item['id']] : '0'; ?>
<article class="pos-item" data-menu-text="<?= escape(strtolower($item['item_name'] . ' ' . $item['category'])) ?>"><?php if ($photo = localPhoto($item['image_path'])): ?><img src="<?= escape($photo) ?>" alt="<?= escape($item['item_name']) ?>" width="240" height="150" loading="lazy"><?php endif; ?><span class="eyebrow"><?= escape(readable($item['category'])) ?></span><h3><?= escape($item['item_name']) ?></h3><p><?= escape($currency) ?> <?= number_format((float) $item['price'], 2) ?><?= $available ? '' : ' · Unavailable' ?></p><label for="qty-<?= (int) $item['id'] ?>">Quantity</label><input id="qty-<?= (int) $item['id'] ?>" type="number" name="qty[<?= (int) $item['id'] ?>]" min="0" max="99" step="1" value="<?= escape($qty) ?>" data-unit-cents="<?= cents($item['price']) ?>" data-name="<?= escape($item['item_name']) ?>" <?= $available ? '' : 'disabled' ?>></article>
<?php endforeach; ?>
</div><?php if (!$items): ?><p>Add menu items before making a sale.</p><?php endif; ?></section>
<section class="pos-bill"><h2>Current sale</h2><div id="order-summary" aria-live="polite"><p>Select quantities from the menu.</p></div><dl><div><dt>Subtotal</dt><dd id="pos-subtotal">0.00</dd></div><div><dt>Tax</dt><dd id="pos-tax">0.00</dd></div><div><dt>Total (<?= escape($currency) ?>)</dt><dd id="pos-total">0.00</dd></div><div><dt>Cash change</dt><dd id="pos-change">0.00</dd></div></dl>
<label for="sale-table">Table</label><select id="sale-table" name="table_id"><option value="">Takeaway / no table</option><?php foreach ($tables as $table): ?><option value="<?= (int) $table['id'] ?>" <?= postValue('table_id') === (string) $table['id'] ? 'selected' : '' ?> <?= $table['status'] !== 'available' || $table['park_status'] !== 'open' ? 'disabled' : '' ?>><?= escape($table['facility_name']) ?> · <?= escape($table['waiter_name'] ?? 'No waiter assigned') ?></option><?php endforeach; ?></select>
<label for="payment-method">Payment method</label><select id="payment-method" name="payment_method"><?php foreach (['cash', 'mpesa', 'card', 'bank'] as $method): ?><option value="<?= $method ?>" <?= postValue('payment_method') === $method ? 'selected' : '' ?>><?= escape(readable($method)) ?></option><?php endforeach; ?></select>
<label for="tendered">Cash received</label><input id="tendered" name="tendered" type="number" min="0" step="0.01" value="<?= escape(postValue('tendered')) ?>"><label for="payment-reference">Payment reference</label><input id="payment-reference" name="payment_reference" maxlength="100" value="<?= escape(postValue('payment_reference')) ?>"><p class="hint">Non-cash payments require a reference. Confirm funds were received before recording payment; this page does not charge cards or initiate M-Pesa transfers.</p><button type="submit" id="complete-sale">Record payment &amp; receipt</button></section></div></form>
<section id="tables"><div class="detail-heading"><div><h2>Tables &amp; waiters</h2><p>Assign a staff member to serve each table. Assignments do not create or cancel reservations.</p></div><a href="tables.php">Check reservations →</a></div><div class="table-scroll"><table><thead><tr><th>Table</th><th>Park</th><th>Seats</th><th>Status</th><th>Assigned waiter</th><th>Allocate waiter</th></tr></thead><tbody><?php foreach ($tables as $table): ?><tr><td><?= escape($table['facility_name']) ?></td><td><?= escape($table['park_name']) ?></td><td><?= (int) $table['capacity'] ?></td><td><?= escape(readable($table['status'])) ?></td><td><?= escape($table['waiter_name'] ?? 'Unassigned') ?><?= $table['waiter_name'] && ($table['waiter_status'] !== 'active' || $table['employment_status'] !== 'employed') ? ' (inactive — reassign)' : '' ?></td><td><form method="post" class="assignment-form"><input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>"><input type="hidden" name="action" value="assign"><input type="hidden" name="table_id" value="<?= (int) $table['id'] ?>"><select name="waiter_id" aria-label="Waiter for <?= escape($table['facility_name']) ?>"><option value="">Unassigned</option><?php foreach ($waiters as $waiter): ?><option value="<?= (int) $waiter['id'] ?>" <?= (int) $table['waiter_id'] === (int) $waiter['id'] ? 'selected' : '' ?>><?= escape($waiter['full_name']) ?></option><?php endforeach; ?></select><button type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<script src="cashier.js" defer></script>
<?php layoutEnd(); ?>
