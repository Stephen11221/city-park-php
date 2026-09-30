<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/validation.php';
if (!isset($entity) || !isset(entities()[$entity])) { http_response_code(404); exit; }
$user = requireUser();
if (!canView($entity, $user)) { abortPage(403, 'You do not have access to this page.', $user); }
$definition = entities()[$entity];
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'list';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$allowed = ['list', 'view', 'create', 'edit', 'delete', 'cancel'];
if (!in_array($action, $allowed, true)) { abortPage(404, 'Page not found.', $user); }
if (($action === 'create' && !canCreate($entity, $user))
    || (in_array($action, ['edit', 'delete'], true) && !canManage($entity, $user))
    || ($action === 'cancel' && !($entity === 'bookings' && $user['role'] === 'customer'))) {
    abortPage(403, 'You do not have permission to make this change.', $user);
}
[$scope, $scopeParams] = scopeFor($entity, $user);
[$select, $joins] = entitySelect($entity, $definition);
$record = null;
$errors = [];
$fields = $definition['fields'];
if ($entity === 'bookings' && $user['role'] === 'customer') {
    unset($fields['user_id'], $fields['total_amount'], $fields['status']);
}
try {
    if (in_array($action, ['view', 'edit', 'delete', 'cancel'], true)) {
        $record = query("SELECT {$select} FROM `{$entity}` t {$joins} WHERE t.id = ? AND {$scope}", array_merge([$id], $scopeParams))->fetch();
        if (!$record) { abortPage(404, 'Record not found.', $user); }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validCsrf()) { abortPage(403, 'Your form expired. Reload the page and try again.', $user); }
        if (!in_array($action, ['create', 'edit', 'delete', 'cancel'], true)) { abortPage(403, 'This page does not accept changes.', $user); }
        $db = database();
        $db->beginTransaction();
        if ($action === 'delete') {
            query("SELECT id FROM `{$entity}` WHERE id = ? FOR UPDATE", [$id]);
            assertDeletable($entity, $id, $user);
            query("DELETE FROM `{$entity}` WHERE id = ?", [$id]);
            recordActivity((int) $user['id'], 'Deleted ' . $definition['singular'] . ' #' . $id);
        } elseif ($action === 'cancel') {
            $changed = query("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status IN ('pending', 'approved')", [$id, $user['id']])->rowCount();
            if (!$changed) { throw new DomainException('Only pending or approved bookings can be cancelled.'); }
            recordActivity((int) $user['id'], 'Cancelled booking #' . $id);
        } else {
            [$values, $errors] = validateFields($fields, $action === 'edit');
            if (!$errors) { $errors = validateRecord($entity, $values, $id, $user); }
            if (!$errors) {
                $keys = array_keys($values);
                if ($action === 'create') {
                    $columns = '`' . implode('`, `', $keys) . '`';
                    $placeholders = implode(', ', array_fill(0, count($keys), '?'));
                    query("INSERT INTO `{$entity}` ({$columns}) VALUES ({$placeholders})", array_values($values));
                    $id = (int) $db->lastInsertId();
                } else {
                    $assignments = implode(', ', array_map(static function ($key) { return '`' . $key . '` = ?'; }, $keys));
                    query("UPDATE `{$entity}` SET {$assignments} WHERE id = ?", array_merge(array_values($values), [$id]));
                }
                recordActivity((int) $user['id'], ($action === 'create' ? 'Created ' : 'Updated ') . $definition['singular'] . ' #' . $id);
            }
        }
        if ($errors) {
            $db->rollBack();
            http_response_code(422);
        } else {
            $db->commit();
            $_SESSION['flash'] = ucfirst($definition['singular']) . ($action === 'delete' ? ' deleted.' : ($action === 'cancel' ? ' cancelled.' : ' saved.'));
            redirectTo($entity . '.php' . (in_array($action, ['create', 'edit'], true) ? '?action=view&id=' . $id : ''));
        }
    }
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    if ($exception instanceof DomainException) {
        http_response_code(422);
        $errors[] = $exception->getMessage();
    } elseif ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
        http_response_code(422);
        $errors[] = 'This email or transaction reference is already in use.';
    } else {
        http_response_code(503);
        $errors[] = 'Unable to complete this request. Check the database connection and try again.';
        error_log('City Park: record operation failed.');
    }
}

layoutStart($definition['title'], $user, $entity);
?>
<div class="page-heading"><div><span class="eyebrow">CITY PARK MANAGEMENT</span><h1><?= escape($definition['title']) ?></h1><p><?= escape($definition['description']) ?></p></div>
<?php if ($action === 'list' && canCreate($entity, $user)): ?><a class="button" href="<?= escape($entity) ?>.php?action=create">+ Add <?= escape($definition['singular']) ?></a><?php elseif ($action !== 'list'): ?><a class="button secondary" href="<?= escape($entity) ?>.php">Back to <?= escape(strtolower($definition['title'])) ?></a><?php endif; ?></div>
<?php if ($errors): ?><div class="error-box" role="alert"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php
try {
if ($action === 'list') {
    $q = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 150) : '';
    $filter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    $statusField = isset($fields['status']) ? 'status' : (isset($fields['payment_status']) ? 'payment_status' : null);
    $where = $scope;
    $params = $scopeParams;
    if ($q !== '') {
        $search = ['CAST(t.id AS CHAR) LIKE ?'];
        $params[] = '%' . $q . '%';
        foreach ($definition['fields'] as $key => $field) {
            if (in_array($field['type'], ['text', 'email', 'textarea'], true)) {
                $search[] = "t.`{$key}` LIKE ?"; $params[] = '%' . $q . '%';
            } elseif ($field['type'] === 'reference') {
                $search[] = "r_{$key}.`{$field['column']}` LIKE ?"; $params[] = '%' . $q . '%';
            }
        }
        $where .= ' AND (' . implode(' OR ', $search) . ')';
    }
    if ($filter !== '' && $statusField !== null && in_array($filter, $fields[$statusField]['options'], true)) {
        $where .= " AND t.`{$statusField}` = ?"; $params[] = $filter;
    }
    $total = (int) query("SELECT COUNT(*) FROM `{$entity}` t {$joins} WHERE {$where}", $params)->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = min($pages, max(1, (int) (is_scalar($_GET['page'] ?? null) ? $_GET['page'] : 1)));
    $offset = ($page - 1) * 20;
    $rows = query("SELECT {$select} FROM `{$entity}` t {$joins} WHERE {$where} ORDER BY t.id DESC LIMIT 20 OFFSET {$offset}", $params)->fetchAll();
    ?>
<section class="records"><form class="filters" method="get"><div><label for="q">Search <?= escape(strtolower($definition['title'])) ?></label><input id="q" name="q" value="<?= escape($q) ?>" placeholder="Search by name, details, or ID"></div>
<?php if ($statusField): ?><div><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach ($fields[$statusField]['options'] as $option): ?><option value="<?= escape($option) ?>" <?= $filter === $option ? 'selected' : '' ?>><?= escape(readable($option)) ?></option><?php endforeach; ?></select></div><?php endif; ?>
<button type="submit">Search</button><a href="<?= escape($entity) ?>.php">Reset</a></form>
<div class="table-meta"><?= $total ?> <?= $total === 1 ? 'record' : 'records' ?></div>
<?php if (!$rows): ?><div class="empty"><h2>No <?= escape(strtolower($definition['title'])) ?> found</h2><p><?= $q !== '' || $filter !== '' ? 'Try a different search or clear your filters.' : 'Records will appear here when they are added.' ?></p><?php if (canCreate($entity, $user)): ?><a href="<?= escape($entity) ?>.php?action=create">Add your first <?= escape($definition['singular']) ?> →</a><?php endif; ?></div>
<?php else: ?><div class="table-scroll" role="region" aria-label="<?= escape($definition['title']) ?> records" tabindex="0"><table><thead><tr><th scope="col">ID</th><?php foreach ($definition['columns'] as $key): ?><th scope="col"><?= escape($fields[$key]['label'] ?? $definition['fields'][$key]['label']) ?></th><?php endforeach; ?><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td>#<?= (int) $row['id'] ?></td><?php foreach ($definition['columns'] as $key): $field = $definition['fields'][$key]; ?><td><?php if ($field['type'] === 'select'): ?><span class="tag <?= escape((string) $row[$key]) ?>"><?= escape(displayValue($row, $key, $field)) ?></span><?php else: ?><?= escape(displayValue($row, $key, $field)) ?><?php endif; ?></td><?php endforeach; ?><td class="row-actions"><a href="<?= escape($entity) ?>.php?action=view&amp;id=<?= (int) $row['id'] ?>">View</a><?php if (canManage($entity, $user)): ?><a href="<?= escape($entity) ?>.php?action=edit&amp;id=<?= (int) $row['id'] ?>">Edit</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<div class="pagination"><span>Page <?= $page ?> of <?= $pages ?></span><div><?php if ($page > 1): ?><a href="?<?= escape(http_build_query(['q' => $q, 'status' => $filter, 'page' => $page - 1])) ?>">← Previous</a><?php endif; ?><?php if ($page < $pages): ?><a href="?<?= escape(http_build_query(['q' => $q, 'status' => $filter, 'page' => $page + 1])) ?>">Next →</a><?php endif; ?></div></div></section>
<?php
} elseif (in_array($action, ['create', 'edit'], true)) {
    ?>
<section><h2><?= $action === 'create' ? 'Add ' : 'Edit ' ?><?= escape($definition['singular']) ?></h2>
<?php if ($entity === 'bookings' && $user['role'] === 'customer'): ?><p>The facility price is charged once per booking. Your request starts as pending.</p><?php endif; ?>
<form method="post" class="record-form"><input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>"><div class="form-grid">
<?php foreach ($fields as $key => $field):
    $value = $_SERVER['REQUEST_METHOD'] === 'POST' ? postValue($key) : ($record[$key] ?? $field['default'] ?? '');
    if ($field['type'] === 'password') { $value = ''; }
    if ($field['type'] === 'time') { $value = substr((string) $value, 0, 5); }
    if ($field['type'] === 'datetime-local') { $value = substr(str_replace(' ', 'T', (string) $value), 0, 16); }
    $required = $field['required'] && !($key === 'password' && $action === 'edit');
?><div class="form-field <?= $field['type'] === 'textarea' ? 'wide' : '' ?>"><label for="<?= escape($key) ?>"><?= escape($field['label']) ?><?= $required ? ' *' : ' (optional)' ?></label>
<?php if ($field['type'] === 'select' || $field['type'] === 'reference'): ?>
<select id="<?= escape($key) ?>" name="<?= escape($key) ?>" <?= $required ? 'required' : '' ?>>
<option value="">Select <?= escape(strtolower($field['label'])) ?></option>
<?php
    $options = [];
    if ($field['type'] === 'select') {
        foreach ($field['options'] as $option) { $options[$option] = readable($option); }
    } else {
        if ($field['table'] === 'bookings') {
            $options = query('SELECT b.id, CONCAT("Booking #", b.id, " · ", u.full_name, " · ", f.facility_name, " · ", b.booking_date) AS label FROM bookings b JOIN users u ON u.id = b.user_id JOIN facilities f ON f.id = b.facility_id ORDER BY b.id DESC')->fetchAll(PDO::FETCH_KEY_PAIR);
        } elseif ($key === 'assigned_to') {
            $options = query("SELECT id, full_name FROM users WHERE role IN ('admin','staff') AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_KEY_PAIR);
        } elseif ($field['table'] === 'facilities') {
            $options = query('SELECT f.id, CONCAT(f.facility_name, " · ", p.park_name, " · capacity ", f.capacity, " · price ", f.price) FROM facilities f JOIN parks p ON p.id = f.park_id ORDER BY f.facility_name')->fetchAll(PDO::FETCH_KEY_PAIR);
        } else {
            $options = query("SELECT id, `{$field['column']}` FROM `{$field['table']}` ORDER BY `{$field['column']}`")->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
    foreach ($options as $option => $label): ?>
<option value="<?= escape((string) $option) ?>" <?= (string) $option === (string) $value ? 'selected' : '' ?>><?= escape((string) $label) ?><?= $field['type'] === 'reference' ? ' (#' . (int) $option . ')' : '' ?></option>
<?php endforeach; ?></select>
<?php if ($field['type'] === 'reference' && !$options): ?><small>No records available. Add a <?= escape(strtolower($field['label'])) ?> first.</small><?php endif; ?>
<?php elseif ($field['type'] === 'textarea'): ?><textarea id="<?= escape($key) ?>" name="<?= escape($key) ?>" rows="4"><?= escape((string) $value) ?></textarea>
<?php else: $type = in_array($field['type'], ['integer', 'money'], true) ? 'number' : $field['type']; ?>
<input id="<?= escape($key) ?>" name="<?= escape($key) ?>" type="<?= escape($type) ?>" value="<?= escape((string) $value) ?>" <?= $required ? 'required' : '' ?> <?= isset($field['max']) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?> <?= $type === 'number' ? 'min="' . ($field['min'] ?? 0) . '" step="' . ($field['type'] === 'money' ? '0.01' : '1') . '"' : '' ?> <?= $type === 'password' ? 'autocomplete="new-password" minlength="8"' : '' ?>>
<?php if ($key === 'password'): ?><small><?= $action === 'edit' ? 'Leave blank to keep the current password.' : 'Use 8–72 bytes. Passwords are stored as hashes.' ?></small><?php endif; ?>
<?php endif; ?></div><?php endforeach; ?></div><div class="form-actions"><button type="submit">Save <?= escape($definition['singular']) ?></button><a href="<?= escape($entity) ?>.php">Cancel</a></div></form></section>
<?php
} elseif ($record) {
    ?>
<section><div class="detail-heading"><h2><?= escape(ucfirst($definition['singular'])) ?> #<?= $id ?></h2><?php if ($action === 'view' && canManage($entity, $user)): ?><div class="row-actions"><a class="button" href="?action=edit&amp;id=<?= $id ?>">Edit</a><a class="button danger-outline" href="?action=delete&amp;id=<?= $id ?>">Delete</a></div><?php endif; ?></div>
<?php if ($action === 'delete' || $action === 'cancel'): ?><p><?= $action === 'delete' ? 'Delete this record permanently? Records with linked bookings, facilities, maintenance, or payments must be resolved first.' : 'Cancel this booking? Any existing payment will remain recorded; cancellation does not issue a refund.' ?></p><form method="post"><input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>"><div class="form-actions"><button class="danger" type="submit">Confirm <?= $action === 'delete' ? 'delete' : 'cancellation' ?></button><a href="?action=view&amp;id=<?= $id ?>">Keep record</a></div></form><?php endif; ?>
<dl class="record-details"><?php foreach ($definition['fields'] as $key => $field): if ($key === 'password') { continue; } ?><div><dt><?= escape($field['label']) ?></dt><dd><?= nl2br(escape(displayValue($record, $key, $field))) ?></dd></div><?php endforeach; ?><?php if (!isset($definition['fields']['created_at'])): ?><div><dt>Created at</dt><dd><?= escape((string) $record['created_at']) ?></dd></div><?php endif; ?></dl>
<?php if ($action === 'view' && $entity === 'bookings' && $user['role'] === 'customer' && in_array($record['status'], ['pending', 'approved'], true)): ?><a class="button danger-outline" href="?action=cancel&amp;id=<?= $id ?>">Cancel booking</a><?php endif; ?>
</section>
<?php
}
} catch (Throwable $exception) {
    http_response_code(503);
    echo '<div class="error-box" role="alert">Unable to load records. Please try again later.</div>';
    error_log('City Park: unable to render record page.');
}
layoutEnd();
