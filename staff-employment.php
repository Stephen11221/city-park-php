<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user = requireUser();
if ($user['role'] !== 'admin') { abortPage(403, 'Only administrators can manage employment.', $user); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
if (!in_array($action, ['fire', 'rehire'], true)) { abortPage(404, 'Action not found.', $user); }
$staff = query("SELECT id, full_name, employment_status FROM users WHERE id = ? AND role = 'staff'", [$id])->fetch();
if (!$staff) { abortPage(404, 'Staff member not found.', $user); }
$error = '';
$reason = trim(postValue('reason'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) { abortPage(403, 'Your form expired. Reload and try again.', $user); }
    if ($action === 'fire' && ($reason === '' || strlen($reason) > 2000)) {
        http_response_code(422); $error = 'Enter a dismissal reason of 1 to 2000 bytes.';
    } else {
        $db = database();
        try {
            $db->beginTransaction();
            $staff = query("SELECT id, full_name, employment_status FROM users WHERE id = ? AND role = 'staff' FOR UPDATE", [$id])->fetch();
            if (!$staff) { throw new DomainException('Staff member no longer exists.'); }
            if (($action === 'fire' && $staff['employment_status'] !== 'employed') || ($action === 'rehire' && $staff['employment_status'] !== 'dismissed')) {
                throw new DomainException('Employment status has already changed. Return to the staff page.');
            }
            if ($action === 'fire') {
                query("UPDATE users SET employment_status = 'dismissed', status = 'inactive', terminated_on = ?, termination_reason = ? WHERE id = ?", [date('Y-m-d'), $reason, $id]);
            } else {
                query("UPDATE users SET employment_status = 'employed', status = 'active', hired_on = ?, terminated_on = NULL, termination_reason = NULL WHERE id = ?", [date('Y-m-d'), $id]);
            }
            recordActivity((int) $user['id'], ($action === 'fire' ? 'Dismissed' : 'Rehired') . ' staff member #' . $id);
            $db->commit();
            $_SESSION['flash'] = $action === 'fire' ? 'Staff member dismissed. Login access is disabled; payment history is preserved.' : 'Staff member rehired and login access restored.';
            redirectTo('staff.php?action=view&id=' . $id);
        } catch (Throwable $exception) {
            if ($db->inTransaction()) { $db->rollBack(); }
            http_response_code($exception instanceof DomainException ? 422 : 503);
            $error = $exception instanceof DomainException ? $exception->getMessage() : 'Unable to update employment. Please try again.';
        }
    }
}
layoutStart($action === 'fire' ? 'Dismiss staff' : 'Rehire staff', $user, 'staff');
?>
<section><h1><?= $action === 'fire' ? 'Dismiss' : 'Rehire' ?> <?= escape($staff['full_name']) ?>?</h1><p><?= $action === 'fire' ? 'This takes effect today and disables staff login, including existing sessions. The account, bookings, and payment history remain on record.' : 'This restores staff access today and starts a new hire date. Existing pay records and configured rates are preserved.' ?></p>
<?php if ($error): ?><p class="error-box" role="alert"><?= escape($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>">
<?php if ($action === 'fire'): ?><label for="reason">Dismissal reason</label><textarea id="reason" name="reason" rows="4" maxlength="2000" required><?= escape($reason) ?></textarea><?php endif; ?>
<div class="form-actions"><button class="<?= $action === 'fire' ? 'danger' : '' ?>" type="submit">Confirm <?= $action === 'fire' ? 'dismissal' : 'rehire' ?></button><a href="staff.php?action=view&amp;id=<?= $id ?>">Cancel</a></div></form></section>
<?php layoutEnd(); ?>
