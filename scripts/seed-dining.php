<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/migrate-dining.php';
migrateDining();
$db = database();
$created = 0;
function diningSeed(string $table, string $key, array $values): void
{
    global $db, $created;
    $statement = $db->prepare("SELECT id FROM `{$table}` WHERE demo_key = ?");
    $statement->execute([$key]);
    if ($statement->fetchColumn()) { return; }
    $values['demo_key'] = $key;
    $columns = '`' . implode('`, `', array_keys($values)) . '`';
    $marks = implode(', ', array_fill(0, count($values), '?'));
    $statement = $db->prepare("INSERT INTO `{$table}` ({$columns}) VALUES ({$marks})");
    $statement->execute(array_values($values));
    $created++;
}
try {
    $db->beginTransaction();
    foreach ([
        ['Grilled chicken plate (Demo)', 'meals', 'Grilled chicken with vegetables and potato wedges.', '850.00', 'grilled-chicken.jpg'],
        ['Fresh orange juice (Demo)', 'drinks', 'A glass of refreshing orange juice.', '250.00', 'orange-juice.jpg'],
        ['Chocolate cake slice (Demo)', 'desserts', 'A slice of rich chocolate cake for a sweet finish.', '350.00', 'chocolate-cake.jpg'],
        ['Vegetable samosas (Demo)', 'snacks', 'Crisp pastry filled with seasoned vegetables. Two pieces.', '200.00', null],
        ['Garden salad (Demo)', 'meals', 'A seasonal mix of fresh greens and vegetables.', '450.00', null],
        ['Bottled water (Demo)', 'drinks', 'Still drinking water, 500 ml.', '100.00', null],
    ] as $i => [$name, $category, $description, $price, $photo]) {
        diningSeed('menu_items', 'demo-menu-' . $i, ['item_name' => $name, 'category' => $category, 'description' => $description, 'price' => $price, 'image_path' => $photo ? 'assets/images/' . $photo : null, 'status' => 'available']);
    }
    $parks = $db->query("SELECT id FROM parks WHERE demo_key IS NOT NULL ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
    if (!$parks) { throw new RuntimeException('Run scripts/seed-demo-data.php first to add sample parks.'); }
    foreach ([2, 4, 6, 4, 8, 6] as $i => $seats) {
        diningSeed('facilities', 'demo-table-' . $i, ['park_id' => $parks[$i % count($parks)], 'facility_name' => 'Garden Table ' . ($i + 1) . ' (Demo)', 'kind' => 'table', 'capacity' => $seats, 'price' => $seats > 4 ? '500.00' : '250.00', 'image_path' => 'assets/images/picnic-lawn.jpg', 'description' => 'An individual outdoor table for up to ' . $seats . ' guests. Demo table with a representative photograph.', 'status' => 'available']);
    }
    $db->commit();
    echo $created . " dining demo records created. Existing records preserved.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) { $db->rollBack(); }
    fwrite(STDERR, "Dining demo failed: " . $exception->getMessage() . "\n");
    exit(1);
}
