<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/migrate-demo-data.php';
$db = database();
$lock = 'city-park-demo-' . substr(hash('sha256', databaseConfig()['name']), 0, 30);
$acquire = $db->prepare('SELECT GET_LOCK(?, 10)');
$acquire->execute([$lock]);
if ((int) $acquire->fetchColumn() !== 1) { fwrite(STDERR, "Another seed is running. Try again later.\n"); exit(1); }
$created = [];
function seedRecord(string $table, string $key, array $values): int
{
    global $db, $created;
    $find = $db->prepare("SELECT id FROM `{$table}` WHERE demo_key = ?");
    $find->execute([$key]);
    $existing = $find->fetchColumn();
    if ($existing) { return (int) $existing; }
    $values['demo_key'] = $key;
    $columns = '`' . implode('`, `', array_keys($values)) . '`';
    $marks = implode(', ', array_fill(0, count($values), '?'));
    $insert = $db->prepare("INSERT INTO `{$table}` ({$columns}) VALUES ({$marks})");
    $insert->execute(array_values($values));
    $created[$table] = ($created[$table] ?? 0) + 1;
    return (int) $db->lastInsertId();
}
try {
    migrateDemoData();
    $db->beginTransaction();
    $staff = seedRecord('users', 'demo-staff', ['full_name' => 'Demo · Alex Green', 'email' => 'alex.staff@citypark.example', 'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'role' => 'staff', 'status' => 'active']);
    $customers = [];
    foreach (['Jamie Brooks', 'Sam Rivers', 'Taylor Woods'] as $i => $name) {
        $customers[] = seedRecord('users', 'demo-customer-' . $i, ['full_name' => 'Demo · ' . $name, 'email' => 'customer' . ($i + 1) . '@citypark.example', 'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), 'role' => 'customer', 'status' => 'active']);
    }
    $parkSpecs = [
        ['Lakeside Gardens (Demo)', 'Lake District · Sample location', 'A peaceful lakeside setting with open lawns, picnic spaces, and room for relaxed outdoor gatherings. Demo listing with a representative stock photograph.', 'lakeside-park.jpg'],
        ['Greenwood Family Park (Demo)', 'Greenwood Quarter · Sample location', 'A leafy neighbourhood park for family days out, playground visits, and time together in the fresh air. Demo listing with a representative stock photograph.', 'playground.jpg'],
        ['Riverside Commons (Demo)', 'Riverside District · Sample location', 'Enjoy generous green space, casual games, and picnic tables by the water. Demo listing with a representative stock photograph.', 'picnic-lawn.jpg'],
    ];
    $parks = [];
    foreach ($parkSpecs as $i => [$name, $location, $description, $image]) {
        $parks[] = seedRecord('parks', 'demo-park-' . $i, ['park_name' => $name, 'location' => $location, 'description' => $description, 'opening_time' => '07:00', 'closing_time' => '19:00', 'status' => 'open', 'image_path' => 'assets/images/' . $image]);
    }
    $facilitySpecs = [
        [0, 'Lakeside Picnic Area (Demo)', 30, '1500.00', 'picnic-gathering.jpg', 'A reservable picnic area for small groups and outdoor lunches.'],
        [0, 'Waterfront Lawn (Demo)', 80, '5000.00', 'lakeside-park.jpg', 'An open lawn for daytime community gatherings and group activities.'],
        [1, 'Family Playground (Demo)', 20, '1000.00', 'playground.jpg', 'A play area for supervised family visits and small children’s groups.'],
        [1, 'Outdoor Basketball Court (Demo)', 12, '1200.00', 'basketball-court.jpg', 'An outdoor court for practice sessions and friendly games.'],
        [2, 'Riverside Picnic Tables (Demo)', 24, '1000.00', 'picnic-lawn.jpg', 'Picnic tables in a quiet outdoor setting for a relaxed group visit.'],
        [2, 'Community Gathering Lawn (Demo)', 60, '3500.00', 'picnic-gathering.jpg', 'Flexible outdoor space for clubs, group meetups, and picnics.'],
    ];
    $facilities = [];
    foreach ($facilitySpecs as $i => [$park, $name, $capacity, $price, $image, $description]) {
        $facilities[] = seedRecord('facilities', 'demo-facility-' . $i, ['park_id' => $parks[$park], 'facility_name' => $name, 'description' => $description . ' Sample facility; photograph is illustrative.', 'capacity' => $capacity, 'price' => $price, 'status' => 'available', 'image_path' => 'assets/images/' . $image]);
    }
    $statuses = ['approved', 'pending', 'completed', 'cancelled', 'approved', 'pending'];
    foreach ($facilities as $i => $facility) {
        $date = (new DateTimeImmutable('today'))->modify(($i === 2 ? '-3' : '+' . ($i + 3)) . ' days')->format('Y-m-d');
        $booking = seedRecord('bookings', 'demo-booking-' . $i, ['user_id' => $customers[$i % 3], 'facility_id' => $facility, 'booking_date' => $date, 'start_time' => '10:00', 'end_time' => '12:00', 'number_of_people' => min(10, $facilitySpecs[$i][2]), 'total_amount' => $facilitySpecs[$i][3], 'status' => $statuses[$i]]);
        $paymentStatus = ['paid', 'pending', 'paid', 'refunded', 'paid', 'pending'][$i];
        seedRecord('payments', 'demo-payment-' . $i, ['booking_id' => $booking, 'transaction_reference' => 'DEMO-PAYMENT-' . ($i + 1), 'amount' => $facilitySpecs[$i][3], 'payment_method' => ['mpesa', 'cash', 'card'][$i % 3], 'payment_status' => $paymentStatus, 'paid_at' => $paymentStatus === 'pending' ? null : (new DateTimeImmutable($i === 2 ? '-3 days' : 'now'))->format('Y-m-d H:i:s')]);
        seedRecord('activity_logs', 'demo-log-' . $i, ['user_id' => $staff, 'action' => 'Demo seed: sample booking #' . $booking . ' and test payment record created', 'ip_address' => null]);
    }
    foreach (['Inspect picnic tables', 'Check playground fittings', 'Clean court surface'] as $i => $title) {
        seedRecord('maintenance', 'demo-maintenance-' . $i, ['facility_id' => $facilities[$i === 0 ? 0 : $i + 1], 'title' => $title . ' (Demo)', 'description' => 'Sample maintenance task for testing the management workflow.', 'reported_by' => $staff, 'assigned_to' => $staff, 'priority' => ['low', 'medium', 'high'][$i], 'status' => ['reported', 'in_progress', 'completed'][$i], 'reported_date' => date('Y-m-d', strtotime('-2 days')), 'completed_date' => $i === 2 ? date('Y-m-d') : null]);
    }
    $db->commit();
    echo $created ? 'Created demo records: ' . json_encode($created) . "\n" : "Demo data already exists; no records changed.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) { $db->rollBack(); }
    fwrite(STDERR, "Demo seed failed; row changes rolled back. " . $exception->getMessage() . "\n");
    exit(1);
} finally {
    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lock]);
}
