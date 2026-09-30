<?php
declare(strict_types=1);
if (!isset($mode) || !in_array($mode, ['login', 'register'], true)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/auth.php';
$isRegister = $mode === 'register';
$title = $isRegister ? 'Create your account' : 'Welcome back';
$error = '';
$name = trim(postValue('full_name'));
$email = trim(postValue('email'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = postValue('password');
    if (!validCsrf()) {
        http_response_code(403);
        $error = 'Your form expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Enter a valid email address.';
    } elseif ($isRegister && ($name === '' || strlen($name) > 100)) {
        $error = 'Enter a name of 1 to 100 bytes.';
    } elseif (strlen($password) > 72 || strpos($password, "\0") !== false
        || ($isRegister && strlen($password) < 8) || $password === '') {
        $error = $isRegister ? 'Use a password between 8 and 72 bytes without null characters.' : 'Email or password is incorrect.';
    } elseif ($isRegister && $password !== postValue('password_confirmation')) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $db = database();
            if ($isRegister) {
                $statement = $db->prepare('INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)');
                $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $newUserId = (int) $db->lastInsertId();
                recordAuthenticationActivity($newUserId, 'Registered an account');
                signIn(['id' => $newUserId]);
                redirectTo(afterLoginDestination());
            } else {
                $statement = $db->prepare('SELECT id, password, status, role, employment_status FROM users WHERE email = ?');
                $statement->execute([$email]);
                $user = $statement->fetch();
                // Use a dummy hash to keep missing-account checks comparable in cost.
                $hash = $user ? $user['password'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
                if (password_verify($password, $hash) && $user && $user['status'] === 'active' && ($user['role'] !== 'staff' || $user['employment_status'] === 'employed')) {
                    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                        $update = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
                        $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                    }
                    recordAuthenticationActivity((int) $user['id'], 'Logged in');
                    signIn($user);
                    redirectTo(afterLoginDestination());
                }
                $error = 'Email or password is incorrect.';
            }
        } catch (PDOException $exception) {
            if ($isRegister && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                $error = 'An account with this email already exists. Please log in.';
            } else {
                http_response_code(503);
                $error = 'Account services are temporarily unavailable. Please try again later.';
                error_log('City Park Management: authentication database operation failed.');
            }
        } catch (RuntimeException $exception) {
            http_response_code(503);
            $error = 'Account services are temporarily unavailable. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= escape($title) ?> · City Park Management</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="auth-shell">
<header><span class="logo">&lt;?&gt;</span> City Park Management</header>
<section>
<span class="eyebrow">WELCOME TO CITY PARK</span>
<h1><?= escape($title) ?></h1>
<p><?= $isRegister ? 'Create an account to book facilities and manage your visits.' : 'Log in to access your City Park account.' ?></p>
<?php if (!$isRegister): ?><p class="hint">Staff can sign in here using the email and password provided by their administrator.</p><?php endif; ?>
<?php if ($error !== ''): ?>
<p class="error" role="alert"><?= escape($error) ?></p>
<?php endif; ?>
<form method="post" action="<?= $isRegister ? 'register.php' : 'login.php' ?>">
<input type="hidden" name="csrf" value="<?= escape(csrfToken()) ?>">
<?php if ($isRegister): ?>
<label for="full_name">Full name</label>
<input id="full_name" name="full_name" value="<?= escape($name) ?>" maxlength="100" autocomplete="name" required>
<?php endif; ?>
<label for="email">Email address</label>
<input id="email" name="email" type="email" value="<?= escape($email) ?>" maxlength="150" autocomplete="username" required>
<label for="password">Password</label>
<input id="password" name="password" type="password" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" <?= $isRegister ? 'minlength="8" aria-describedby="password-help"' : '' ?> required>
<?php if ($isRegister): ?>
<p id="password-help" class="hint">Use at least 8 characters (maximum 72 bytes).</p>
<label for="password_confirmation">Confirm password</label>
<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
<?php endif; ?>
<button type="submit"><?= $isRegister ? 'Create account' : 'Log in' ?> →</button>
</form>
<p class="switch"><?php if ($isRegister): ?>Already have an account? <a href="login.php">Log in</a><?php else: ?>New here? <a href="register.php">Create an account</a><?php endif; ?></p>
</section>
<footer><a href="index.php">← Back to City Park</a></footer>
</main>
</body>
</html>
