<?php
declare(strict_types=1);
function validatePayroll(array &$values, int $id): array
{
    $errors = [];
    // All allocations for a staff member lock the same user row before checking conflicts.
    $staff = query("SELECT id, employment_status, hired_on, terminated_on, daily_rate, monthly_rate FROM users WHERE id = ? AND role = 'staff' FOR UPDATE", [$values['staff_id']])->fetch();
    if (!$staff) { return ['Choose an existing staff member.']; }
    $old = $id ? query('SELECT * FROM staff_payments WHERE id = ? FOR UPDATE', [$id])->fetch() : null;
    if ($old && $old['status'] === 'paid') { return ['Paid allocations are locked to preserve payment history.']; }
    if ($values['pay_basis'] === 'monthly' && substr($values['period_start'], 8, 2) !== '01') { $errors[] = 'For monthly pay, select the first day of the month.'; }
    $unchangedPeriod = $old && (int) $old['staff_id'] === (int) $values['staff_id'] && $old['pay_basis'] === $values['pay_basis'] && $old['period_start'] === $values['period_start'];
    if ($unchangedPeriod) {
        $values['amount'] = $old['amount'];
    } else {
        $values['amount'] = $staff[$values['pay_basis'] === 'daily' ? 'daily_rate' : 'monthly_rate'];
        if ((float) $values['amount'] <= 0) { $errors[] = 'Set a positive ' . $values['pay_basis'] . ' pay rate on the staff profile first.'; }
        $period = $values['pay_basis'] === 'monthly' ? substr($values['period_start'], 0, 7) : $values['period_start'];
        $hire = $staff['hired_on'] ? ($values['pay_basis'] === 'monthly' ? substr($staff['hired_on'], 0, 7) : $staff['hired_on']) : null;
        $end = $staff['terminated_on'] ? ($values['pay_basis'] === 'monthly' ? substr($staff['terminated_on'], 0, 7) : $staff['terminated_on']) : null;
        if ($hire && $period < $hire) { $errors[] = 'The pay period cannot precede the hire date.'; }
        if ($staff['employment_status'] === 'dismissed' && (!$end || $period > $end)) { $errors[] = 'The pay period cannot be after dismissal.'; }
    }
    if ($values['status'] === 'paid') {
        if (!$values['paid_on']) { $errors[] = 'Enter the date paid.'; }
        elseif ($values['paid_on'] > date('Y-m-d')) { $errors[] = 'The date paid cannot be in the future.'; }
    } elseif ($values['paid_on'] !== null) { $errors[] = 'Only a paid allocation can have a date paid.'; }
    if ($values['status'] !== 'cancelled') {
        $monthStart = substr($values['period_start'], 0, 7) . '-01';
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('+1 month')->format('Y-m-d');
        $overlap = query("SELECT id FROM staff_payments WHERE staff_id = ? AND id <> ? AND status <> 'cancelled' AND period_start >= ? AND period_start < ? AND pay_basis <> ? LIMIT 1 FOR UPDATE", [$values['staff_id'], $id, $monthStart, $monthEnd, $values['pay_basis']])->fetchColumn();
        if ($overlap) { $errors[] = 'Daily and monthly pay cannot both be allocated for the same staff member in the same month.'; }
    }
    return $errors;
}
