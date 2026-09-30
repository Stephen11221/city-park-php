<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$user = null;
$parks = [];
$parksAvailable = true;
try {
    $user = currentUser();
    $parks = database()->query("SELECT park_name, location, description, opening_time, closing_time FROM parks WHERE status = 'open' ORDER BY park_name LIMIT 3")->fetchAll();
} catch (Throwable $exception) {
    $parksAvailable = false;
}
$accountLink = $user ? 'dashboard.php' : 'register.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Discover City Park green spaces, explore facilities, and plan your next visit. Create an account to request a booking and manage your reservations.">
<title>City Park · Make room for the outdoors</title>
<link rel="stylesheet" href="landing.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header wrap">
<a class="brand" href="index.php" aria-label="City Park home"><span class="brand-mark">CP</span>City Park</a>
<nav aria-label="Main navigation"><a href="#parks">Our parks</a><a href="#how-it-works">Plan a visit</a>
<?php if ($user): ?><a class="button small" href="dashboard.php">Dashboard →</a><?php else: ?><a href="login.php">Log in</a><a class="button small" href="register.php">Get started →</a><?php endif; ?>
</nav></header>
<main id="main">
<section class="hero wrap">
<div class="hero-copy"><span class="eyebrow">A LITTLE MORE GREEN. A LITTLE MORE LIFE.</span><h1>Make room for<br>the <em>outdoors.</em></h1><p>Space to play, a place to gather, and a moment to slow down. Find your next visit at City Park.</p><div class="actions"><a class="button" href="#parks">Explore our parks <span aria-hidden="true">↗</span></a><a class="text-link" href="<?= escape($accountLink) ?>"><?= $user ? 'Open your dashboard' : 'Create an account' ?> →</a></div><div class="hero-note"><span aria-hidden="true">✳</span> Your next good day starts outside.</div></div>
<div class="park-art" role="img" aria-label="Illustration of a green park with trees, a walking path, and a sunny sky">
<svg viewBox="0 0 540 580" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
<rect width="540" height="580" fill="#e4eddb"/><circle cx="411" cy="113" r="57" fill="#efc66c"/>
<path d="M0 320Q130 215 290 300T540 277V580H0Z" fill="#9ab78d"/>
<path d="M0 404Q160 308 300 365T540 335V580H0Z" fill="#4f855b"/>
<path d="M370 310C255 367 398 406 251 463S121 549 172 580H338C269 517 440 470 392 401S335 363 414 317Z" fill="#e9d8b3"/>
<g fill="#244e39"><path d="M35 306L104 99L175 306Z"/><path d="M111 344L181 152L249 344Z"/><path d="M421 359L477 184L534 359Z"/></g>
<g stroke="#715c3d" stroke-width="9"><path d="M104 252V393M181 291V401M477 309V420"/></g>
<g fill="#396443"><circle cx="328" cy="205" r="51"/><circle cx="300" cy="241" r="39"/><circle cx="357" cy="237" r="39"/></g><path d="M328 240V347" stroke="#715c3d" stroke-width="8"/>
<g stroke="#d2e0ab" stroke-width="3" stroke-linecap="round"><path d="M44 475l-6-15m6 15l9-12M442 495l-6-15m6 15l9-12M78 541l-6-15m6 15l9-12M363 549l-6-15m6 15l9-12"/></g>
</svg><div class="art-label"><span class="live-dot"></span> More space. More possibilities.</div></div>
</section>
<div class="benefit-strip"><div class="wrap benefits"><span>Find your green space</span><span aria-hidden="true">✳</span><span>Gather your people</span><span aria-hidden="true">✳</span><span>Plan a day to remember</span></div></div>
<section id="parks" class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">ROOM TO EXPLORE</span><h2>A place for your next plan.</h2></div><p>Discover open parks and find the setting for your next outdoor moment.</p></div>
<?php if ($parks): ?><div class="park-grid"><?php foreach ($parks as $park): ?><article class="park-card"><span class="pill">Open park</span><h3><?= escape($park['park_name']) ?></h3><p class="location"><?= escape($park['location']) ?></p><?php if ($park['description']): ?><p class="description"><?= escape($park['description']) ?></p><?php endif; ?><?php if ($park['opening_time'] && $park['closing_time']): ?><p class="hours">Open <?= escape(substr($park['opening_time'], 0, 5)) ?> – <?= escape(substr($park['closing_time'], 0, 5)) ?></p><?php endif; ?><a class="text-link" href="<?= $user ? 'facilities.php' : 'login.php' ?>"><?= $user ? 'Explore facilities' : 'Log in to explore facilities' ?> →</a></article><?php endforeach; ?></div>
<?php else: ?><div class="empty-parks"><span aria-hidden="true">✳</span><div><h3><?= $parksAvailable ? 'Your next outdoor adventure is on its way.' : 'Park listings are temporarily unavailable.' ?></h3><p><?= $parksAvailable ? 'Open parks will appear here as they become available. Create an account to get ready for your first booking.' : 'Please check back soon. You can still learn how to plan your visit below.' ?></p></div></div><?php endif; ?>
</section>
<section id="how-it-works" class="planning"><div class="wrap content-section"><span class="eyebrow">LESS PLANNING. MORE LIVING.</span><h2>Your visit, in three simple steps.</h2><div class="steps"><article><span class="step-number">01</span><h3>Find your space</h3><p>Explore parks and check the facilities, capacity, and availability for your visit.</p></article><article><span class="step-number">02</span><h3>Request a booking</h3><p>Create an account, choose a date and time, and send your facility booking request.</p></article><article><span class="step-number">03</span><h3>Get ready to go</h3><p>Check your account for booking approval and keep your visit details in one place.</p></article></div></div></section>
<section class="wrap"><div class="cta"><div><span class="eyebrow">STEP OUTSIDE</span><h2>A little fresh air<br>goes a long way.</h2><p>Start planning your next City Park visit.</p></div><a class="button light" href="<?= escape($accountLink) ?>"><?= $user ? 'Go to dashboard' : 'Create your account' ?> →</a></div></section>
</main>
<footer class="wrap"><a class="brand" href="index.php"><span class="brand-mark">CP</span>City Park</a><p>Space for people. Room for nature.</p><span>© <?= date('Y') ?> City Park</span></footer>
</body></html>
