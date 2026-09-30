<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth.php';
require_once __DIR__ . '/images.php';
function publicUser(): ?array
{
    try { return currentUser(); } catch (Throwable $exception) { return null; }
}
function publicHeader(?array $user, string $active = ''): void
{
    ?>
<header class="site-header wrap"><a class="brand" href="index.php"><span class="brand-mark">CP</span>City Park</a>
<nav aria-label="Main navigation"><a href="index.php#parks">Our parks</a><a href="menu.php" <?= $active === 'menu' ? 'aria-current="page"' : '' ?>>Menu</a><a href="tables.php" <?= $active === 'tables' ? 'aria-current="page"' : '' ?>>Book a table</a>
<?php if ($user): ?><a class="button small" href="dashboard.php">Dashboard →</a><?php else: ?><a href="login.php">Log in</a><a class="button small" href="register.php">Get started →</a><?php endif; ?></nav></header>
<?php
}
function publicStart(string $title, ?array $user, string $active): void
{
    ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= escape($title) ?> · City Park</title><link rel="stylesheet" href="landing.css"></head><body><a class="skip" href="#main">Skip to content</a><?php publicHeader($user, $active); ?><main id="main" class="wrap catalogue"><?php
}
function publicEnd(): void
{
    ?></main><footer class="wrap"><a class="brand" href="index.php"><span class="brand-mark">CP</span>City Park</a><p>Space for people. Room for nature.</p><span>© <?= date('Y') ?> City Park</span></footer></body></html><?php
}
