<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/database.php';
function migrateCashier(): void
{
    $db = database();
    $role = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (strpos($role['Type'], "'cashier'") === false) {
        $db->exec("ALTER TABLE users MODIFY role ENUM('admin','staff','customer','cashier') DEFAULT 'customer'");
    }
    $schema = file_get_contents(dirname(__DIR__) . '/database/cashier.sql');
    if ($schema === false) { throw new RuntimeException('Cashier schema unavailable.'); }
    foreach (explode(';', $schema) as $statement) { if (trim($statement) !== '') { $db->exec($statement); } }
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { migrateCashier(); echo "Cashier, receipts, assignments, audit, and expenses are ready.\n"; }
