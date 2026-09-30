<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/cashier.php';
$user = requireCashier();
$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$error = '';
foreach ([$from, $to] as $date) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) { $error = 'Choose valid report dates.'; }
}
if (!$error && ($from > $to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366)) { $error = 'Choose a date range of up to 366 days, with the end after the start.'; }
$currency = cashierCurrency(); $rows = []; $summary = ['sales' => 0, 'refunds' => 0, 'expenses' => 0, 'payroll' => 0]; $methods = [];
if (!$error) {
    $until = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
    $rows = query("SELECT paid_at AS occurred_at, 'sales' AS category, CONCAT('CP-', LPAD(id, 8, '0')) AS reference, payment_method, total AS amount, currency FROM sales WHERE paid_at >= ? AND paid_at < ?
        UNION ALL SELECT refunded_at, 'refunds', CONCAT('CP-', LPAD(id, 8, '0')), payment_method, total, currency FROM sales WHERE status = 'refunded' AND refunded_at >= ? AND refunded_at < ?
        UNION ALL SELECT expense_date, 'expenses', CONCAT('Expense #', id, ': ', title), payment_method, amount, ? FROM expenses WHERE expense_date >= ? AND expense_date < ?
        UNION ALL SELECT paid_on, 'payroll', CONCAT('Staff pay #', id), 'payroll', amount, ? FROM staff_payments WHERE status = 'paid' AND paid_on >= ? AND paid_on < ? ORDER BY occurred_at DESC", [$from, $until, $from, $until, $currency, $from, $until, $currency, $from, $until])->fetchAll();
    // Keep currencies separate rather than adding unlike amounts.
    foreach ($rows as $row) {
        if ($row['currency'] !== $currency) { continue; }
        $amount = cents($row['amount']); $summary[$row['category']] += $amount;
        $method = $row['payment_method'];
        $methods[$method] = ($methods[$method] ?? 0) + ($row['category'] === 'sales' ? $amount : -$amount);
    }
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="city-park-cash-report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Type', 'Reference', 'Method', 'Currency', 'Money in', 'Money out'], ',', '"', '');
        foreach ($rows as $row) {
            $reference = preg_match('/^[=+@\-\t\r\n]/', $row['reference']) ? "'" . $row['reference'] : $row['reference'];
            fputcsv($out, [$row['occurred_at'], $row['category'], $reference, $row['payment_method'], $row['currency'], $row['category'] === 'sales' ? $row['amount'] : '0.00', $row['category'] === 'sales' ? '0.00' : $row['amount']], ',', '"', '');
        }
        fclose($out); exit;
    }
} else { http_response_code(422); }
$net = $summary['sales'] - $summary['refunds'] - $summary['expenses'] - $summary['payroll'];
layoutStart('Accounting', $user, 'accounting');
?>
<div class="page-heading"><div><h1>Accounting</h1><p>Sales receipts, recorded refunds, operating expenses, and paid staff allocations.</p></div><?php if ($user['role'] === 'admin'): ?><a class="button" href="expenses.php?action=create">Record expense</a><?php endif; ?></div>
<section><form class="filters" method="get"><div><label for="from">From</label><input id="from" name="from" type="date" value="<?= escape($from) ?>" required></div><div><label for="to">To</label><input id="to" name="to" type="date" value="<?= escape($to) ?>" required></div><button>Update report</button><button class="secondary" name="export" value="csv">Export CSV</button></form><p>Summary currency: <?= escape($currency) ?>. This is a cash movement report, not a profit statement. Booking reservation payments are tracked separately under Payments and are not included here.</p></section>
<?php if ($error): ?><p class="error-box" role="alert"><?= escape($error) ?></p><?php endif; ?>
<div class="stat-grid"><?php foreach (['sales' => 'Sales received', 'refunds' => 'Refunds recorded', 'expenses' => 'Operating expenses', 'payroll' => 'Staff pay'] as $key => $label): ?><div class="stat-card"><span><?= $label ?></span><strong><?= number_format($summary[$key] / 100, 2) ?></strong><small><?= escape($currency) ?></small></div><?php endforeach; ?><div class="stat-card"><span>Net movement</span><strong><?= number_format($net / 100, 2) ?></strong><small><?= escape($currency) ?></small></div></div>
<section><h2>Net movement by payment method</h2><dl><?php foreach ($methods as $method => $amount): ?><div><dt><?= escape(readable($method)) ?></dt><dd><?= escape($currency) ?> <?= number_format($amount / 100, 2) ?></dd></div><?php endforeach; ?></dl><p>Staff pay is grouped separately because payroll records do not store a payment method.</p></section>
<section><h2>Transaction ledger</h2><div class="table-scroll"><table><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Method</th><th>Currency</th><th>Money in</th><th>Money out</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= escape($row['occurred_at']) ?></td><td><?= escape(readable($row['category'])) ?></td><td><?= escape($row['reference']) ?></td><td><?= escape(readable($row['payment_method'])) ?></td><td><?= escape($row['currency']) ?></td><td><?= $row['category'] === 'sales' ? number_format((float) $row['amount'], 2) : '—' ?></td><td><?= $row['category'] !== 'sales' ? number_format((float) $row['amount'], 2) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$rows): ?><p>No transactions in this period.</p><?php endif; ?></section>
<?php layoutEnd(); ?>
