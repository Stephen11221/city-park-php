<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the logout button to sign out.');
}
if (!validCsrf()) {
    http_response_code(403);
    exit('Your form expired. Go back, refresh the page, and try again.');
}
recordAuthenticationActivity(isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null, 'Logged out');
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $params['path'],
    'secure' => $params['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
redirectTo('login.php');
