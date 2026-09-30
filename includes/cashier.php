<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
function requireCashier(): array
{
    $user = requireUser();
    if (!in_array($user['role'], ['admin', 'cashier'], true)) { abortPage(403, 'Cashier or administrator access is required.', $user); }
    return $user;
}
function cashierCurrency(): string
{
    $currency = getenv('POS_CURRENCY') ?: 'KES';
    if (!preg_match('/^[A-Z]{3}$/D', $currency)) { throw new RuntimeException('Invalid POS currency.'); }
    return $currency;
}
function cashierTaxBps(): int
{
    $rate = getenv('POS_TAX_BPS') ?: '0';
    if (!ctype_digit($rate) || (int) $rate > 10000) { throw new RuntimeException('Invalid POS tax rate.'); }
    return (int) $rate;
}
function cents(string $value): int
{
    if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $match)) { throw new DomainException('Enter a valid non-negative money amount with at most two decimals.'); }
    return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
}
function decimalMoney(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}
function receiptNumber(int $id): string { return 'CP-' . str_pad((string) $id, 8, '0', STR_PAD_LEFT); }
function cashierAudit(array $user, string $action, string $details): void
{
    query('INSERT INTO cashier_audit (actor_id, actor_name, action, details) VALUES (?, ?, ?, ?)', [$user['id'], $user['full_name'], $action, $details]);
    recordActivity((int) $user['id'], 'Cashier: ' . $action . ' · ' . substr($details, 0, 150));
}
function assignWaiter(array $user, int $tableId, int $waiterId): void
{
    $table = query("SELECT id, facility_name FROM facilities WHERE id = ? AND kind = 'table' FOR UPDATE", [$tableId])->fetch();
    if (!$table) { throw new DomainException('Select a valid table.'); }
    $previous = query('SELECT waiter_id FROM table_assignments WHERE facility_id = ?', [$tableId])->fetchColumn();
    if (!$waiterId) {
        query('DELETE FROM table_assignments WHERE facility_id = ?', [$tableId]);
    } else {
        $waiter = query("SELECT id FROM users WHERE id = ? AND role = 'staff' AND status = 'active' AND employment_status = 'employed' FOR UPDATE", [$waiterId])->fetchColumn();
        if (!$waiter) { throw new DomainException('Select an active staff member as the waiter.'); }
        query('INSERT INTO table_assignments (facility_id, waiter_id, assigned_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE waiter_id = VALUES(waiter_id), assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP', [$tableId, $waiterId, $user['id']]);
    }
    cashierAudit($user, 'Table assignment', $table['facility_name'] . ' (#' . $tableId . '): waiter #' . ($previous ?: 'none') . ' → #' . ($waiterId ?: 'none'));
}
function checkoutSale(array $user): int
{
    $key = postValue('request_key');
    if (!preg_match('/^[a-f0-9]{64}$/D', $key)) { throw new DomainException('Checkout expired. Reload the cashier page.'); }
    $existing = query('SELECT id, cashier_id FROM sales WHERE request_key = ?', [$key])->fetch();
    if ($existing) {
        if ((int) $existing['cashier_id'] !== (int) $user['id']) { throw new DomainException('Checkout belongs to another cashier.'); }
        return (int) $existing['id'];
    }
    if (!isset($_SESSION['checkout_keys'][$key])) { throw new DomainException('Checkout expired. Reload the cashier page.'); }
    $quantities = $_POST['qty'] ?? [];
    if (!is_array($quantities) || count($quantities) > 10000) { throw new DomainException('Select up to 200 menu items.'); }
    $selected = [];
    foreach ($quantities as $id => $quantity) {
        if (!is_string($quantity) || !ctype_digit((string) $id) || !ctype_digit($quantity) || (int) $quantity > 99) { throw new DomainException('Item quantities must be whole numbers from 0 to 99.'); }
        if ((int) $quantity > 0) { $selected[(int) $id] = (int) $quantity; }
    }
    if (count($selected) > 200) { throw new DomainException('Select up to 200 different menu items per sale.'); }
    if (!$selected) { throw new DomainException('Add at least one meal or drink to the sale.'); }
    ksort($selected);
    $tableValue = postValue('table_id');
    if ($tableValue !== '' && (!ctype_digit($tableValue) || (int) $tableValue < 1)) { throw new DomainException('Select a valid table or takeaway.'); }
    $table = null; $waiter = null;
    if ($tableValue !== '') {
        $table = query("SELECT f.id, f.facility_name FROM facilities f JOIN parks p ON p.id = f.park_id WHERE f.id = ? AND f.kind = 'table' AND f.status = 'available' AND p.status = 'open' FOR UPDATE", [$tableValue])->fetch();
        if (!$table) { throw new DomainException('This table is unavailable. Choose another table.'); }
        $waiter = query("SELECT u.id, u.full_name FROM table_assignments a JOIN users u ON u.id = a.waiter_id WHERE a.facility_id = ? AND u.status = 'active' AND u.employment_status = 'employed' AND u.role = 'staff'", [$table['id']])->fetch();
        if (!$waiter) { throw new DomainException('Assign an active waiter to this table before checkout.'); }
    }
    $lines = []; $subtotal = 0;
    foreach ($selected as $id => $quantity) {
        $item = query('SELECT id, item_name, price, status FROM menu_items WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$item || $item['status'] !== 'available') { throw new DomainException('A selected menu item is no longer available. Refresh the menu.'); }
        $unit = cents($item['price']); $line = $unit * $quantity; $subtotal += $line;
        $lines[] = [$id, $item['item_name'], $quantity, decimalMoney($unit), decimalMoney($line)];
    }
    $bps = cashierTaxBps(); $tax = intdiv($subtotal * $bps + 5000, 10000); $total = $subtotal + $tax;
    if ($total <= 0 || $total > 999999999999) { throw new DomainException('Sale total is outside the allowed range.'); }
    $method = postValue('payment_method');
    if (!in_array($method, ['cash', 'mpesa', 'card', 'bank'], true)) { throw new DomainException('Select a payment method.'); }
    $reference = trim(postValue('payment_reference'));
    if (strlen($reference) > 100 || ($method !== 'cash' && $reference === '')) { throw new DomainException('Enter a payment reference, up to 100 characters, for non-cash payments.'); }
    $tendered = $method === 'cash' ? cents(trim(postValue('tendered'))) : $total;
    if ($tendered < $total || $tendered > 999999999999) { throw new DomainException('Cash received must cover the total and be within the allowed range.'); }
    query('INSERT INTO sales (request_key, cashier_id, cashier_name, facility_id, table_name, waiter_id, waiter_name, currency, subtotal, tax_rate_bps, tax_amount, total, payment_method, payment_reference, tendered, change_amount, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [$key, $user['id'], $user['full_name'], $table['id'] ?? null, $table['facility_name'] ?? 'Takeaway', $waiter['id'] ?? null, $waiter['full_name'] ?? null, cashierCurrency(), decimalMoney($subtotal), $bps, decimalMoney($tax), decimalMoney($total), $method, $reference ?: null, decimalMoney($tendered), decimalMoney($tendered - $total)]);
    $saleId = (int) database()->lastInsertId();
    foreach ($lines as $line) { query('INSERT INTO sale_items (sale_id, menu_item_id, item_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)', array_merge([$saleId], $line)); }
    cashierAudit($user, 'Sale paid', receiptNumber($saleId) . ' · ' . cashierCurrency() . ' ' . decimalMoney($total) . ' · ' . $method);
    return $saleId;
}
