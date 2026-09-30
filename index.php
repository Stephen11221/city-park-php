<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/includes/public.php';
$user = null;
$parks = [];
$facilities = [];
$parksAvailable = true;
try {
    $user = currentUser();
    $parks = database()->query("SELECT park_name, location, description, opening_time, closing_time, image_path FROM parks WHERE status = 'open' ORDER BY park_name LIMIT 3")->fetchAll();
    $facilities = database()->query("SELECT f.facility_name, f.description, f.capacity, f.price, f.image_path, p.park_name FROM facilities f JOIN parks p ON p.id = f.park_id WHERE f.status = 'available' AND p.status = 'open' ORDER BY f.facility_name LIMIT 3")->fetchAll();
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
<?php publicHeader($user, 'home'); ?>
<main id="main">
<section class="hero wrap">
<div class="hero-copy"><span class="eyebrow">A LITTLE MORE GREEN. A LITTLE MORE LIFE.</span><h1>Make room for<br>the <em>outdoors.</em></h1><p>Space to play, a place to gather, and a moment to slow down. Find your next visit at City Park.</p><div class="actions"><a class="button" href="#parks">Explore our parks <span aria-hidden="true">↗</span></a><a class="text-link" href="<?= escape($accountLink) ?>"><?= $user ? 'Open your dashboard' : 'Create an account' ?> →</a></div><div class="hero-note"><span aria-hidden="true">✳</span> Your next good day starts outside.</div></div>
<div class="park-art">
<img class="hero-photo" src="assets/images/lakeside-park.jpg" alt="People relaxing on a green lawn beside a lake" width="1200" height="800" fetchpriority="high">
<div class="art-label"><span class="live-dot"></span> More space. More possibilities.</div></div>
</section>
<div class="benefit-strip"><div class="wrap benefits"><span>Find your green space</span><span aria-hidden="true">✳</span><span>Gather your people</span><span aria-hidden="true">✳</span><span>Plan a day to remember</span></div></div>
<section id="parks" class="wrap content-section"><div class="section-heading"><div><span class="eyebrow">ROOM TO EXPLORE</span><h2>A place for your next plan.</h2></div><p>Discover open parks and find the setting for your next outdoor moment.</p></div>
<?php if ($parks): ?><div class="park-grid"><?php foreach ($parks as $park): ?><article class="park-card"><?php if ($photo = localPhoto($park['image_path'] ?? null)): ?><img class="card-photo" src="<?= escape($photo) ?>" alt="<?= escape($park['park_name']) ?> — representative photograph" width="1200" height="800" loading="lazy" decoding="async"><?php endif; ?><span class="pill">Open park</span><h3><?= escape($park['park_name']) ?></h3><p class="location"><?= escape($park['location']) ?></p><?php if ($park['description']): ?><p class="description"><?= escape($park['description']) ?></p><?php endif; ?><?php if ($park['opening_time'] && $park['closing_time']): ?><p class="hours">Open <?= escape(substr($park['opening_time'], 0, 5)) ?> – <?= escape(substr($park['closing_time'], 0, 5)) ?></p><?php endif; ?><a class="text-link" href="<?= $user ? 'facilities.php' : 'login.php' ?>"><?= $user ? 'Explore facilities' : 'Log in to explore facilities' ?> →</a></article><?php endforeach; ?></div>
<?php else: ?><div class="empty-parks"><span aria-hidden="true">✳</span><div><h3><?= $parksAvailable ? 'Your next outdoor adventure is on its way.' : 'Park listings are temporarily unavailable.' ?></h3><p><?= $parksAvailable ? 'Open parks will appear here as they become available. Create an account to get ready for your first booking.' : 'Please check back soon. You can still learn how to plan your visit below.' ?></p></div></div><?php endif; ?>
</section>
<?php if ($facilities): ?>
<section class="wrap content-section facility-section"><div class="section-heading"><div><span class="eyebrow">SPACE FOR EVERY OCCASION</span><h2>Find your kind of day out.</h2></div><p>Explore facilities, compare group sizes, and plan your next gathering.</p></div>
<div class="park-grid"><?php foreach ($facilities as $facility): ?><article class="park-card"><?php if ($photo = localPhoto($facility['image_path'] ?? null)): ?><img class="card-photo" src="<?= escape($photo) ?>" alt="<?= escape($facility['facility_name']) ?> — representative photograph" width="1200" height="800" loading="lazy" decoding="async"><?php endif; ?><span class="pill">Available facility</span><h3><?= escape($facility['facility_name']) ?></h3><p class="location"><?= escape($facility['park_name']) ?></p><p>Up to <?= (int) $facility['capacity'] ?> people · <?= number_format((float) $facility['price'], 2) ?> per booking</p><a class="text-link" href="<?= $user ? 'facilities.php' : 'login.php' ?>"><?= $user ? 'Explore and book' : 'Log in to book' ?> →</a></article><?php endforeach; ?></div></section>
<?php endif; ?>
<section id="how-it-works" class="planning"><div class="wrap content-section"><span class="eyebrow">LESS PLANNING. MORE LIVING.</span><h2>Your visit, in three simple steps.</h2><div class="steps"><article><span class="step-number">01</span><h3>Find your space</h3><p>Explore parks and check the facilities, capacity, and availability for your visit.</p></article><article><span class="step-number">02</span><h3>Request a booking</h3><p>Create an account, choose a date and time, and send your facility booking request.</p></article><article><span class="step-number">03</span><h3>Get ready to go</h3><p>Check your account for booking approval and keep your visit details in one place.</p></article></div></div></section>
<section class="wrap"><div class="cta"><div><span class="eyebrow">STEP OUTSIDE</span><h2>A little fresh air<br>goes a long way.</h2><p>Start planning your next City Park visit.</p></div><a class="button light" href="<?= escape($accountLink) ?>"><?= $user ? 'Go to dashboard' : 'Create your account' ?> →</a></div></section>
</main>
<footer class="wrap"><a class="brand" href="index.php"><span class="brand-mark">CP</span>City Park</a><p>Space for people. Room for nature.</p><span>© <?= date('Y') ?> City Park · <a href="assets/images/CREDITS.md">Photo credits</a></span></footer>
</body></html>
