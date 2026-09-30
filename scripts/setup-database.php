<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/database.php';
try {
    $config = databaseConfig();
    $server = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4',
        $config['user'], $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $identifier = '`' . str_replace('`', '``', $config['name']) . '`';
    $server->exec('CREATE DATABASE IF NOT EXISTS ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Schema file unavailable.');
    }
    // This schema contains plain CREATE TABLE statements without stored routines.
    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') {
            database()->exec($statement);
        }
    }
    require_once __DIR__ . '/migrate-demo-data.php';
    migrateDemoData();
    require_once __DIR__ . '/migrate-dining.php';
    migrateDining();
    echo "City park database and tables are ready.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Setup failed. Check database configuration and CREATE privileges.\n");
    exit(1);
}
