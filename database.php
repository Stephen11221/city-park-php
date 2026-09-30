<?php
declare(strict_types=1);

function databaseConfig(): array
{
    $localPath = __DIR__ . '/database.local.php';
    $local = is_file($localPath) ? require $localPath : [];
    if (!is_array($local)) {
        throw new RuntimeException('Invalid database configuration.');
    }
    $setting = static function (string $key, string $default = '') use ($local): string {
        $environment = getenv($key);
        return $environment !== false ? $environment : (string) ($local[$key] ?? $default);
    };

    $host = $setting('DB_HOST', '127.0.0.1');
    $port = $setting('DB_PORT', '3306');
    $name = $setting('DB_DATABASE');
    $user = $setting('DB_USERNAME');
    $password = $setting('DB_PASSWORD');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Set DB_DATABASE and DB_USERNAME in database.local.php or the environment.');
    }
    if (preg_match('/[;\x00-\x1f]/', $host . $name)
        || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('Invalid database host, name, or port.');
    }

    return compact('host', 'port', 'name', 'user', 'password');
}

function database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $config = databaseConfig();
    $host = $config['host'];
    $port = $config['port'];
    $name = $config['name'];
    $user = $config['user'];
    $password = $config['password'];
    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
    return $connection;
}
