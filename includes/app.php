<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth.php';
require_once __DIR__ . '/entities.php';
require_once __DIR__ . '/images.php';

function query(string $sql, array $params = []): PDOStatement
{
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function requireUser(): array
{
    try {
        $user = currentUser();
    } catch (Throwable $exception) {
        http_response_code(503);
        exit('Account services are unavailable. Please try again later.');
    }
    if (!$user) {
        redirectTo('login.php');
    }
    return $user;
}

function canView(string $entity, array $user): bool
{
    if ($entity === 'users' || $entity === 'activity_logs') {
        return $user['role'] === 'admin';
    }
    if ($entity === 'maintenance') {
        return in_array($user['role'], ['admin', 'staff'], true);
    }
    return in_array($user['role'], ['admin', 'staff', 'customer'], true);
}

function canManage(string $entity, array $user): bool
{
    return $entity !== 'activity_logs' && ($user['role'] === 'admin'
        || ($user['role'] === 'staff' && in_array($entity, ['parks', 'facilities', 'bookings', 'payments', 'maintenance'], true)));
}

function canCreate(string $entity, array $user): bool
{
    return canManage($entity, $user) || ($entity === 'bookings' && $user['role'] === 'customer');
}

function scopeFor(string $entity, array $user): array
{
    if ($user['role'] === 'customer' && $entity === 'bookings') {
        return ['t.user_id = ?', [$user['id']]];
    }
    if ($user['role'] === 'customer' && $entity === 'payments') {
        return ['t.booking_id IN (SELECT id FROM bookings WHERE user_id = ?)', [$user['id']]];
    }
    return ['1=1', []];
}

function readable(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function layoutStart(string $title, array $user, string $active = ''): void
{
    $definitions = entities();
    ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= escape($title) ?> · City Park</title><link rel="stylesheet" href="style.css"></head>
<body class="management"><div class="app-shell">
<aside class="sidebar"><a class="brand" href="dashboard.php"><span class="logo">CP</span><span>City Park<small>Management</small></span></a>
<nav aria-label="Main navigation"><a href="index.php">Public homepage</a><a href="dashboard.php" <?= $active === '' ? 'aria-current="page"' : '' ?>>Dashboard</a>
<?php foreach ($definitions as $key => $definition): if (!canView($key, $user)) { continue; } ?>
<a href="<?= escape($key) ?>.php" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= escape($definition['title']) ?></a>
<?php endforeach; ?></nav><div class="account"><strong><?= escape($user['full_name']) ?></strong><small><?= escape(readable((string) $user['role'])) ?></small>
<form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>"><button class="secondary" type="submit">Log out</button></form></div></aside>
<main class="workspace"><div class="topbar"><span>City Park Management</span><span><?= escape(date('D, d M Y')) ?></span></div>
<?php if (isset($_SESSION['flash'])): ?><p class="notice" role="status"><?= escape((string) $_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif;
}

function layoutEnd(): void
{
    echo '</main></div></body></html>';
}

function abortPage(int $status, string $message, array $user): void
{
    http_response_code($status);
    layoutStart($status === 403 ? 'Access denied' : 'Record unavailable', $user);
    echo '<section><h1>' . escape($message) . '</h1><p><a href="dashboard.php">Return to dashboard</a></p></section>';
    layoutEnd();
    exit;
}

function entitySelect(string $entity, array $definition): array
{
    $select = 't.*';
    $joins = '';
    foreach ($definition['fields'] as $key => $field) {
        if ($field['type'] !== 'reference') { continue; }
        $alias = 'r_' . $key;
        $select .= ", {$alias}.`{$field['column']}` AS `{$key}__label`";
        $joins .= " LEFT JOIN `{$field['table']}` {$alias} ON {$alias}.id = t.`{$key}`";
    }
    // Never load password hashes for the list or detail views.
    if ($entity === 'users') {
        $select = 't.id, t.full_name, t.email, t.phone, t.role, t.status, t.created_at, t.updated_at';
    }
    return [$select, $joins];
}

function displayValue(array $record, string $key, array $field): string
{
    $value = $record[$key] ?? null;
    if ($value === null || $value === '') { return '—'; }
    if ($field['type'] === 'reference') {
        return ($record[$key . '__label'] ?? 'Deleted record') . ' (#' . $value . ')';
    }
    if ($field['type'] === 'select') { return readable((string) $value); }
    if ($field['type'] === 'money') { return number_format((float) $value, 2); }
    return (string) $value;
}
