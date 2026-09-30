<?php
declare(strict_types=1);

function validateFields(array $fields, bool $editing): array
{
    $values = [];
    $errors = [];
    foreach ($fields as $key => $field) {
        $raw = postValue($key);
        $value = $field['type'] === 'password' ? $raw : trim($raw);
        if ($field['type'] === 'password' && $editing && $value === '') { continue; }
        if ($value === '') {
            if ($field['required']) { $errors[] = $field['label'] . ' is required.'; }
            $values[$key] = null;
            continue;
        }
        switch ($field['type']) {
            case 'select':
                if (!in_array($value, $field['options'], true)) { $errors[] = 'Select a valid ' . strtolower($field['label']) . '.'; }
                break;
            case 'reference':
            case 'integer':
                if (!ctype_digit($value) || (float) $value > 2147483647 || (int) $value < ($field['min'] ?? 1)) {
                    $errors[] = $field['label'] . ' must be a valid whole number.';
                } elseif ($field['type'] === 'reference' && !query("SELECT id FROM `{$field['table']}` WHERE id = ?", [$value])->fetchColumn()) {
                    $errors[] = $field['label'] . ' no longer exists.';
                }
                break;
            case 'money':
                if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $value)) { $errors[] = $field['label'] . ' must be between 0 and 99999999.99 with at most two decimal places.'; }
                break;
            case 'date':
            case 'time':
            case 'datetime-local':
                $format = $field['type'] === 'date' ? 'Y-m-d' : ($field['type'] === 'time' ? 'H:i' : 'Y-m-d\TH:i');
                $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
                if (!$parsed || $parsed->format($format) !== $value || ($field['type'] !== 'time' && (int) substr($value, 0, 4) < 1000)) {
                    $errors[] = 'Enter a valid ' . strtolower($field['label']) . '.';
                } elseif ($field['type'] === 'datetime-local') {
                    $value = $parsed->format('Y-m-d H:i:s');
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email address.'; }
                // Fall through to the length check.
            case 'text':
            case 'textarea':
                if (strlen($value) > ($field['max'] ?? 60000)) { $errors[] = $field['label'] . ' is too long.'; }
                break;
            case 'password':
                if (strlen($value) < 8 || strlen($value) > 72 || strpos($value, "\0") !== false) {
                    $errors[] = 'Use a password between 8 and 72 bytes without null characters.';
                } else {
                    $value = password_hash($value, PASSWORD_DEFAULT);
                }
                break;
        }
        $values[$key] = $value;
    }
    return [$values, $errors];
}

function validateRecord(string $entity, array &$values, int $id, array $user): array
{
    $errors = [];
    if ($entity === 'users' && $id === (int) $user['id'] && ($values['role'] !== 'admin' || $values['status'] !== 'active')) {
        $errors[] = 'Keep your own administrator account active with the admin role.';
    }
    if ($entity === 'parks' && $values['opening_time'] !== null && $values['closing_time'] !== null
        && $values['closing_time'] <= $values['opening_time']) {
        $errors[] = 'Closing time must be after opening time. Overnight hours are not supported.';
    }
    if ($entity === 'bookings') {
        // Serializes overlapping booking checks for the same facility.
        $facility = query('SELECT f.*, p.status AS park_status, p.opening_time, p.closing_time FROM facilities f JOIN parks p ON p.id = f.park_id WHERE f.id = ? FOR UPDATE', [$values['facility_id']])->fetch();
        if (!$facility) { return ['Select an existing facility.']; }
        if ($user['role'] === 'customer') {
            $values['user_id'] = $user['id'];
            $values['status'] = 'pending';
            $values['total_amount'] = $facility['price'];
            if ($values['booking_date'] < date('Y-m-d')) { $errors[] = 'Choose today or a future booking date.'; }
        }
        if ($values['end_time'] <= $values['start_time']) { $errors[] = 'End time must be after start time.'; }
        if ((int) $values['number_of_people'] > (int) $facility['capacity']) { $errors[] = 'The group size exceeds this facility’s capacity (' . $facility['capacity'] . ').'; }
        if (in_array($values['status'], ['pending', 'approved'], true)) {
            if ($facility['status'] !== 'available' || $facility['park_status'] !== 'open') { $errors[] = 'This facility or its park is not currently available.'; }
            if (($facility['opening_time'] !== null && $values['start_time'] < substr($facility['opening_time'], 0, 5))
                || ($facility['closing_time'] !== null && $values['end_time'] > substr($facility['closing_time'], 0, 5))) {
                $errors[] = 'Choose times within the park’s opening hours.';
            }
            $clash = query("SELECT id FROM bookings WHERE facility_id = ? AND booking_date = ? AND status IN ('pending', 'approved') AND start_time < ? AND end_time > ? AND id <> ? LIMIT 1 FOR UPDATE", [$values['facility_id'], $values['booking_date'], $values['end_time'], $values['start_time'], $id])->fetchColumn();
            if ($clash) { $errors[] = 'Another booking already occupies this facility during the selected time.'; }
        }
        if (!query("SELECT id FROM users WHERE id = ? AND status = 'active'", [$values['user_id']])->fetchColumn()) { $errors[] = 'Choose an active user for this booking.'; }
    }
    if ($entity === 'payments') {
        if ($values['payment_status'] === 'paid' && $values['paid_at'] === null) { $errors[] = 'Enter the payment date and time for a paid payment.'; }
    }
    if ($entity === 'maintenance') {
        if ($values['completed_date'] !== null && $values['completed_date'] < $values['reported_date']) { $errors[] = 'Completion date cannot be before the report date.'; }
        if ($values['status'] === 'completed' && $values['completed_date'] === null) { $errors[] = 'Enter a completion date for completed maintenance.'; }
        if ($values['assigned_to'] !== null && !query("SELECT id FROM users WHERE id = ? AND role IN ('admin', 'staff') AND status = 'active'", [$values['assigned_to']])->fetchColumn()) {
            $errors[] = 'Assign maintenance to an active staff member or administrator.';
        }
    }
    return $errors;
}

function assertDeletable(string $entity, int $id, array $user): void
{
    if ($entity === 'users' && $id === (int) $user['id']) { throw new DomainException('You cannot delete your own account.'); }
    // Avoid cascading removal of related operational and financial records.
    $references = [
        'users' => [['bookings', 'user_id']],
        'parks' => [['facilities', 'park_id']],
        'facilities' => [['bookings', 'facility_id'], ['maintenance', 'facility_id']],
        'bookings' => [['payments', 'booking_id']],
    ];
    foreach ($references[$entity] ?? [] as [$table, $column]) {
        if (query("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1 FOR UPDATE", [$id])->fetchColumn()) {
            throw new DomainException('This record has related ' . $table . '. Remove or reassign those records first.');
        }
    }
}
