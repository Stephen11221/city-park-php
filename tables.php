<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/public.php';
$user = publicUser();
$get = static function (string $key, string $default): string { return is_string($_GET[$key] ?? null) ? $_GET[$key] : $default; };
$date = $get('booking_date', date('Y-m-d', strtotime('+1 day')));
$start = $get('start_time', '10:00');
$end = $get('end_time', '12:00');
$people = $get('number_of_people', '2');
$errors = [];
foreach ([[$date, 'Y-m-d', 'date'], [$start, 'H:i', 'start time'], [$end, 'H:i', 'end time']] as [$value, $format, $label]) {
    $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
    if (!$parsed || $parsed->format($format) !== $value) { $errors[] = 'Enter a valid ' . $label . '.'; }
}
if (!$errors && $date < date('Y-m-d')) { $errors[] = 'Choose today or a future date.'; }
if ($end <= $start) { $errors[] = 'End time must be after start time.'; }
if (!ctype_digit($people) || (int) $people < 1 || (float) $people > 2147483647) { $errors[] = 'Enter a valid number of guests.'; }
$tables = [];
if (!$errors) {
    try {
        $statement = database()->prepare("SELECT f.*, p.park_name, p.location FROM facilities f JOIN parks p ON p.id = f.park_id
            WHERE f.kind = 'table' AND f.status = 'available' AND p.status = 'open' AND f.capacity >= ?
            AND (p.opening_time IS NULL OR p.opening_time <= ?) AND (p.closing_time IS NULL OR p.closing_time >= ?)
            AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.facility_id = f.id AND b.booking_date = ? AND b.status IN ('pending','approved') AND b.start_time < ? AND b.end_time > ?)
            ORDER BY f.capacity, f.facility_name");
        $statement->execute([$people, $start, $end, $date, $end, $start]);
        $tables = $statement->fetchAll();
    } catch (Throwable $exception) { http_response_code(503); $errors[] = 'Table availability is temporarily unavailable. Please try again later.'; }
} else { http_response_code(422); }
publicStart('Book a table', $user, 'tables');
?>
<div class="catalogue-heading"><span class="eyebrow">PULL UP A CHAIR</span><h1>A table for<br><em>your people.</em></h1><p>Choose your visit time and group size to find a table. Reservations are confirmed after staff approval.</p></div>
<form class="catalogue-filters" method="get"><div><label for="booking_date">Date</label><input id="booking_date" type="date" name="booking_date" min="<?= date('Y-m-d') ?>" value="<?= escape($date) ?>" required></div><div><label for="start_time">From</label><input id="start_time" type="time" name="start_time" value="<?= escape($start) ?>" required></div><div><label for="end_time">Until</label><input id="end_time" type="time" name="end_time" value="<?= escape($end) ?>" required></div><div><label for="number_of_people">Guests</label><input id="number_of_people" type="number" name="number_of_people" value="<?= escape($people) ?>" min="1" required></div><button class="button" type="submit">Find a table</button></form>
<?php if ($errors): ?><div class="catalogue-error" role="alert"><?php foreach ($errors as $error): ?><p><?= escape($error) ?></p><?php endforeach; ?></div><?php elseif (!$tables): ?><div class="empty-parks"><div><h3>No tables match your visit.</h3><p>Try a different date, time, or group size.</p></div></div><?php else: ?><p><?= count($tables) ?> tables available for your selected time.</p><div class="park-grid">
<?php foreach ($tables as $table): $link = 'bookings.php?' . http_build_query(['action' => 'create', 'facility_id' => $table['id'], 'booking_date' => $date, 'start_time' => $start, 'end_time' => $end, 'number_of_people' => $people]); ?><article class="park-card"><?php if ($photo = localPhoto($table['image_path'])): ?><img class="card-photo" src="<?= escape($photo) ?>" alt="<?= escape($table['facility_name']) ?> — representative photo" width="1200" height="800" loading="lazy"><?php endif; ?><span class="pill">Seats <?= (int) $table['capacity'] ?></span><h3><?= escape($table['facility_name']) ?></h3><p class="location"><?= escape($table['park_name']) ?></p><p><?= escape((string) $table['description']) ?></p><p class="menu-price"><?= (float) $table['price'] === 0.0 ? 'No reservation fee' : number_format((float) $table['price'], 2) . ' reservation fee' ?></p><a class="text-link" href="<?= escape($link) ?>">Book this table →</a></article><?php endforeach; ?></div><?php endif; ?>
<p class="catalogue-note">Availability is checked again when you submit. Pending and approved bookings reserve the selected table. Food and drinks are ordered separately.</p>
<?php publicEnd(); ?>
