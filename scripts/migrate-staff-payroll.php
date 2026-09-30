<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/database.php';
function migrateStaffPayroll(): void
{
    $db = database();
    foreach ([
        'job_title' => 'VARCHAR(100) NULL',
        'hired_on' => 'DATE NULL',
        'terminated_on' => 'DATE NULL',
        'termination_reason' => 'TEXT NULL',
        'employment_status' => "ENUM('employed','dismissed') NOT NULL DEFAULT 'employed'",
        'daily_rate' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
        'monthly_rate' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    ] as $column => $type) {
        $check = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $check->execute([$column]);
        if (!(int) $check->fetchColumn()) { $db->exec("ALTER TABLE users ADD COLUMN `{$column}` {$type}"); }
    }
    $schema = file_get_contents(dirname(__DIR__) . '/database/staff-payroll.sql');
    foreach (explode(';', $schema) as $statement) { if (trim($statement) !== '') { $db->exec($statement); } }
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { migrateStaffPayroll(); echo "Staff employment, pay allocations, and suppliers are ready.\n"; }
