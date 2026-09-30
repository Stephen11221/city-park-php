<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/database.php';
function migrateDemoData(): void
{
    $db = database();
    $check = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    foreach (['users', 'parks', 'facilities', 'bookings', 'payments', 'maintenance', 'activity_logs'] as $table) {
        $check->execute([$table, 'demo_key']);
        if (!(int) $check->fetchColumn()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN demo_key VARCHAR(80) NULL, ADD UNIQUE KEY `{$table}_demo_key_unique` (demo_key)");
        }
        if (in_array($table, ['parks', 'facilities'], true)) {
            $check->execute([$table, 'image_path']);
            if (!(int) $check->fetchColumn()) {
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN image_path VARCHAR(255) NULL");
            }
        }
    }
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    migrateDemoData();
    echo "Image paths and stable demo identifiers are ready.\n";
}
