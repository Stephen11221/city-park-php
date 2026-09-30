<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('city_park_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postValue(string $key): string
{
    return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validCsrf(): bool
{
    return postValue('csrf') !== '' && hash_equals(csrfToken(), postValue('csrf'));
}

function signIn(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'booking_intent' => $_SESSION['booking_intent'] ?? null,
        'user_id' => (int) $user['id'],
        'csrf' => bin2hex(random_bytes(32)),
    ];
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $statement = database()->prepare('SELECT id, full_name, email, role, status FROM users WHERE id = ? AND status = \'active\'');
    $statement->execute([$_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function redirectTo(string $page): void
{
    header('Location: ' . $page, true, 303);
    exit;
}

function recordActivity(?int $userId, string $action): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip !== null && !filter_var($ip, FILTER_VALIDATE_IP)) { $ip = null; }
    $statement = database()->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)');
    $statement->execute([$userId, $action, $ip]);
}

function recordAuthenticationActivity(?int $userId, string $action): void
{
    try {
        recordActivity($userId, $action);
    } catch (Throwable $exception) {
        error_log('City Park: unable to record authentication activity.');
    }
}

function afterLoginDestination(): string
{
    $intent = $_SESSION['booking_intent'] ?? null;
    unset($_SESSION['booking_intent']);
    if (is_array($intent) && $intent) {
        $intent = array_intersect_key($intent, array_flip(['facility_id', 'booking_date', 'start_time', 'end_time', 'number_of_people']));
        return 'bookings.php?' . http_build_query(array_merge(['action' => 'create'], $intent));
    }
    return 'dashboard.php';
}
