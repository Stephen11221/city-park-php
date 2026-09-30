<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = requireUser();
$definitions = entities();
$counts = [];
$recent = [];
$failure = false;
try {
    foreach ($definitions as $entity => $definition) {
        if (!canView($entity, $user)) { continue; }
        [$scope, $params] = scopeFor($entity, $user);
        $table = $definition['table'] ?? $entity;
        $counts[$entity] = (int) query("SELECT COUNT(*) FROM `{$table}` t WHERE {$scope}", $params)->fetchColumn();
    }
    [$scope, $params] = scopeFor('bookings', $user);
    $recent = query("SELECT t.id, t.booking_date, t.start_time, t.status, f.facility_name, u.full_name FROM bookings t JOIN facilities f ON f.id = t.facility_id JOIN users u ON u.id = t.user_id WHERE {$scope} ORDER BY t.id DESC LIMIT 5", $params)->fetchAll();
} catch (Throwable $exception) {
    http_response_code(503);
    $failure = true;
}
layoutStart('Dashboard', $user);
?>
<div class="page-heading"><div><span class="eyebrow">YOUR CITY, YOUR GREEN SPACES</span><h1>Hello, <?= escape($user['full_name']) ?>.</h1><p><?= $user['role'] === 'customer' ? 'Plan your next park visit and keep track of your bookings.' : 'A clear view of your parks, people, and daily operations.' ?></p></div><a class="button" href="bookings.php?action=create">+ New booking</a></div>
<?php if ($failure): ?><div class="error-box" role="alert">Dashboard data is temporarily unavailable. Please try again.</div><?php endif; ?>
<div class="stat-grid"><?php foreach ($counts as $entity => $count): ?><a class="stat-card" href="<?= escape($entity) ?>.php"><span><?= escape($definitions[$entity]['title']) ?></span><strong><?= number_format($count) ?></strong><small>View <?= escape(strtolower($definitions[$entity]['title'])) ?> →</small></a><?php endforeach; ?></div>
<section class="records"><div class="detail-heading"><div><h2>Recent bookings</h2><p><?= $user['role'] === 'customer' ? 'Your latest facility reservations.' : 'The latest reservations across your parks.' ?></p></div><a href="bookings.php">View all →</a></div>
<?php if (!$recent): ?><div class="empty"><h2>No bookings yet</h2><p>Explore your facilities and create the first booking.</p><a href="facilities.php">Explore facilities →</a></div><?php else: ?>
<div class="table-scroll" role="region" aria-label="Recent bookings" tabindex="0"><table><thead><tr><th scope="col">Booking</th><th scope="col">Facility</th><th scope="col">Customer</th><th scope="col">Date</th><th scope="col">Status</th></tr></thead><tbody><?php foreach ($recent as $booking): ?><tr><td><a href="bookings.php?action=view&amp;id=<?= (int) $booking['id'] ?>">#<?= (int) $booking['id'] ?></a></td><td><?= escape($booking['facility_name']) ?></td><td><?= escape($booking['full_name']) ?></td><td><?= escape($booking['booking_date'] . ' · ' . substr($booking['start_time'], 0, 5)) ?></td><td><span class="tag <?= escape((string) $booking['status']) ?>"><?= escape(readable((string) $booking['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="connection-panel"><div><h2>Connected to your park database</h2><p><?= escape(databaseConfig()['name']) ?> · <?= escape(readable((string) $user['role'])) ?> access</p></div><span class="tag active">Connected</span></section>
<?php layoutEnd(); ?>
