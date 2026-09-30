<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/public.php';
$user = publicUser();
$categories = ['meals' => 'Meals', 'snacks' => 'Snacks', 'drinks' => 'Drinks', 'desserts' => 'Desserts'];
$category = is_string($_GET['category'] ?? null) && isset($categories[$_GET['category']]) ? $_GET['category'] : '';
$q = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 150) : '';
$items = [];
$error = false;
try {
    $sql = "SELECT item_name, category, description, price, image_path, demo_key FROM menu_items WHERE status = 'available'";
    $params = [];
    if ($category !== '') { $sql .= ' AND category = ?'; $params[] = $category; }
    if ($q !== '') { $sql .= ' AND (item_name LIKE ? OR description LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; }
    $sql .= ' ORDER BY category, item_name';
    $statement = database()->prepare($sql);
    $statement->execute($params);
    $items = $statement->fetchAll();
} catch (Throwable $exception) { $error = true; http_response_code(503); }
publicStart('Food & drinks', $user, 'menu');
?>
<div class="catalogue-heading"><span class="eyebrow">GOOD FOOD. GOOD COMPANY.</span><h1>Fresh air.<br><em>Fresh flavours.</em></h1><p>Browse our food and drinks, then find a table for your next visit.</p><a class="button" href="tables.php">Find a table →</a></div>
<form class="catalogue-filters" method="get"><div><label for="q">Search the menu</label><input id="q" name="q" value="<?= escape($q) ?>" placeholder="Find a dish or drink"></div><div><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option><?php foreach ($categories as $key => $label): ?><option value="<?= $key ?>" <?= $category === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><button class="button" type="submit">Show menu</button><a href="menu.php">Reset</a></form>
<?php if ($error): ?><p class="catalogue-error" role="alert">The menu is temporarily unavailable. Please try again later.</p><?php elseif (!$items): ?><div class="empty-parks"><div><h3>No matching menu items.</h3><p>Try another category or search.</p></div></div><?php else: ?><div class="park-grid">
<?php foreach ($items as $item): ?><article class="park-card"><?php if ($photo = localPhoto($item['image_path'])): ?><img class="card-photo" src="<?= escape($photo) ?>" alt="<?= escape($item['item_name']) ?> — representative photo" width="1200" height="800" loading="lazy"><?php endif; ?><span class="pill"><?= escape($categories[$item['category']]) ?><?= $item['demo_key'] ? ' · Demo' : '' ?></span><h3><?= escape($item['item_name']) ?></h3><p><?= escape((string) $item['description']) ?></p><p class="menu-price"><?= number_format((float) $item['price'], 2) ?></p></article><?php endforeach; ?></div><?php endif; ?>
<p class="catalogue-note">Browse the menu here and order with staff during your visit. A table reservation does not include food or drinks.</p>
<?php publicEnd(); ?>
