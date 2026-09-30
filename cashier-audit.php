<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/cashier.php';
$user = requireCashier();
$q = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 150) : '';
$where = $q !== '' ? 'WHERE actor_name LIKE ? OR action LIKE ? OR details LIKE ?' : '';
$params = $q !== '' ? array_fill(0, 3, '%' . $q . '%') : [];
$total = (int) query("SELECT COUNT(*) FROM cashier_audit {$where}", $params)->fetchColumn();
$pages = max(1, (int) ceil($total / 30));
$page = min($pages, max(1, (int) (is_scalar($_GET['page'] ?? null) ? $_GET['page'] : 1))); $offset = ($page - 1) * 30;
$logs = query("SELECT * FROM cashier_audit {$where} ORDER BY id DESC LIMIT 30 OFFSET {$offset}", $params)->fetchAll();
layoutStart('Cashier audit', $user, 'cashier-audit');
?>
<div class="page-heading"><div><h1>Cashier audit</h1><p>Read-only history of sales, refunds, and waiter assignments, including who made each change.</p></div></div>
<section><form class="filters"><div><label for="q">Search audit</label><input id="q" name="q" value="<?= escape($q) ?>" placeholder="Cashier, receipt number, or action"></div><button>Search</button></form><div class="table-scroll"><table><thead><tr><th>Date</th><th>Actor</th><th>Action</th><th>Details</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= escape($log['created_at']) ?></td><td><?= escape($log['actor_name']) ?></td><td><?= escape($log['action']) ?></td><td><?= escape($log['details']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$logs): ?><p>No matching audit records.</p><?php endif; ?><div class="pagination"><span><?= $total ?> records · page <?= $page ?> of <?= $pages ?></span><div><?php if ($page > 1): ?><a href="?<?= escape(http_build_query(['q' => $q, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?><?php if ($page < $pages): ?><a href="?<?= escape(http_build_query(['q' => $q, 'page' => $page + 1])) ?>">Next</a><?php endif; ?></div></div></section>
<?php layoutEnd(); ?>
