<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/database.php';
function migrateDining(): void
{
    $db = database();
    $check = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facilities' AND COLUMN_NAME = 'kind'");
    if (!(int) $check->fetchColumn()) {
        $db->exec("ALTER TABLE facilities ADD COLUMN kind ENUM('facility', 'table') NOT NULL DEFAULT 'facility'");
    }
    $db->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(150) NOT NULL,
        category ENUM('meals', 'snacks', 'drinks', 'desserts') NOT NULL DEFAULT 'meals',
        description TEXT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        image_path VARCHAR(255) NULL,
        status ENUM('available', 'unavailable') NOT NULL DEFAULT 'available',
        demo_key VARCHAR(80) NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    migrateDining();
    echo "Menu and bookable tables are ready.\n";
}
