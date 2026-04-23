<?php
declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activeNav dashboard|settings|profile|orders|shipped|products|coupons|inventory|reviews|earnings|withdraw|transactions|kyc (shipping|delivery|returns still used on those pages for title only) */
/** @var array $seller */

if (!isset($pageTitle, $activeNav, $seller) || !is_array($seller)) {
    throw new RuntimeException('shell-top: set $pageTitle, $activeNav, $seller');
}

$initials = '';
$name = trim((string) ($seller['full_name'] ?? ''));
if ($name !== '') {
    $parts = preg_split('/\s+/', $name) ?: [];
    foreach ($parts as $p) {
        $initials .= strtoupper(substr($p, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
}
if ($initials === '') {
    $initials = 'S';
}

$sellerNotifications = [];
try {
    $pdo = db();
    $notifySt = $pdo->prepare(
        "SELECT o.id, o.order_ref, o.status, o.total_amount, o.created_at,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Guest') AS customer_name,
                GROUP_CONCAT(DISTINCT oi.name ORDER BY oi.id SEPARATOR ', ') AS items_text
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         INNER JOIN products p ON p.id = oi.product_id
         LEFT JOIN users u ON u.id = o.user_id
         WHERE p.seller_id = ?
         GROUP BY o.id, o.order_ref, o.status, o.total_amount, o.created_at, u.first_name, u.last_name
         ORDER BY o.id DESC
         LIMIT 6"
    );
    $notifySt->execute([(int) $seller['id']]);
    $sellerNotifications = $notifySt->fetchAll();
} catch (Throwable) {
    $sellerNotifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../../admin/partials/theme-head-script.php'; ?>
  <title><?= h($pageTitle) ?> - LUXE Seller</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../admin/css/admin.css">
  <link rel="stylesheet" href="css/seller.css">
</head>
<body class="admin-app admin-app--merchant">
  <div class="admin-layout" id="adminLayout">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="admin-sidebar__brand">
        <div class="admin-sidebar__logo" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
        <div>
          <div class="admin-sidebar__title">LUXE</div>
          <div class="admin-sidebar__subtitle">Seller panel</div>
        </div>
      </div>
      <div class="admin-sidebar__user">
        <div class="admin-sidebar__user-row">
          <div class="admin-sidebar__user-avatar" aria-hidden="true"><?= h($initials) ?></div>
          <div class="admin-sidebar__user-text">
            <div class="admin-sidebar__user-name"><?= h((string) $seller['full_name']) ?></div>
            <div class="admin-sidebar__user-email"><?= h((string) $seller['email']) ?></div>
          </div>
        </div>
        <a class="admin-sidebar__signout" href="logout.php">Sign Out</a>
      </div>
      <nav class="admin-nav">
        <div class="admin-nav__label">Seller Menu</div>
        <a class="admin-nav__link<?= $activeNav === 'dashboard' ? ' admin-nav__link--active' : '' ?>" href="index.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13 15.4c0-2.074 0-3.111.659-3.756S15.379 11 17.5 11s3.182 0 3.841.644C22 12.29 22 13.326 22 15.4v2.2c0 2.074 0 3.111-.659 3.756S19.621 22 17.5 22s-3.182 0-3.841-.644C13 20.71 13 19.674 13 17.6z" opacity=".5"></path><path fill="currentColor" d="M2 8.6c0 2.074 0 3.111.659 3.756S4.379 13 6.5 13s3.182 0 3.841-.644C11 11.71 11 10.674 11 8.6V6.4c0-2.074 0-3.111-.659-3.756S8.621 2 6.5 2s-3.182 0-3.841.644C2 3.29 2 4.326 2 6.4zm11-3.1c0-1.087 0-1.63.171-2.06a2.3 2.3 0 0 1 1.218-1.262C14.802 2 15.327 2 16.375 2h2.25c1.048 0 1.573 0 1.986.178c.551.236.99.69 1.218 1.262c.171.43.171.973.171 2.06s0 1.63-.171 2.06a2.3 2.3 0 0 1-1.218 1.262C20.198 9 19.673 9 18.625 9h-2.25c-1.048 0-1.573 0-1.986-.178a2.3 2.3 0 0 1-1.218-1.262C13 7.13 13 6.587 13 5.5"></path><path fill="currentColor" d="M2 18.5c0 1.087 0 1.63.171 2.06a2.3 2.3 0 0 0 1.218 1.262c.413.178.938.178 1.986.178h2.25c1.048 0 1.573 0 1.986-.178c.551-.236.99-.69 1.218-1.262c.171-.43.171-.973.171-2.06s0-1.63-.171-2.06a2.3 2.3 0 0 0-1.218-1.262C9.198 15 8.673 15 7.625 15h-2.25c-1.048 0-1.573 0-1.986.178c-.551.236-.99.69-1.218 1.262C2 16.87 2 17.413 2 18.5" opacity=".5"></path></svg>
          Dashboard
        </a>

        <a class="admin-nav__link<?= $activeNav === 'products' ? ' admin-nav__link--active' : '' ?>" href="products.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><g fill="none"><path fill="currentColor" fill-rule="evenodd" d="M17.192 6H6.808c-1.688 0-2.531 0-3.175.33A3 3 0 0 0 2.33 7.633C2 8.277 2 9.12 2 10.808c0 .429 0 .643.073.824a1 1 0 0 0 .3.404c.153.122.358.183.77.307L8.5 13.95v1.213c0 .765.46 1.471 1.187 1.767l.56.227a4.65 4.65 0 0 0 3.506 0l.56-.227a1.91 1.91 0 0 0 1.187-1.767V13.95l5.358-1.607c.41-.124.616-.185.768-.307a1 1 0 0 0 .3-.404c.074-.18.074-.395.074-.824c0-1.688 0-2.531-.33-3.175a3 3 0 0 0-1.303-1.303C19.723 6 18.88 6 17.192 6M13.6 13h-3.2c-.22 0-.4.182-.4.406v1.757c0 .166.1.315.251.377l.56.228c.764.31 1.614.31 2.377 0l.56-.228a.41.41 0 0 0 .252-.377v-1.757a.403.403 0 0 0-.4-.406" clip-rule="evenodd"></path><path fill="currentColor" d="m20.958 12.313l-.034.01L15.5 13.95v1.213c0 .765-.46 1.471-1.187 1.767l-.56.227a4.65 4.65 0 0 1-3.506 0l-.56-.227A1.91 1.91 0 0 1 8.5 15.163V13.95L3 12.3c0 3.675.035 7.388 1.318 8.528C5.636 22 7.758 22 12 22s6.364 0 7.682-1.172c1.283-1.14 1.317-4.853 1.318-8.528z" opacity=".5"></path><path stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M9.17 4a3.001 3.001 0 0 1 5.66 0" opacity=".5"></path></g></svg>
        Products
        </a>

        <a class="admin-nav__link<?= $activeNav === 'inventory' ? ' admin-nav__link--active' : '' ?>" href="inventory.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M8.422 20.618C10.178 21.54 11.056 22 12 22V12L2.638 7.073l-.04.067C2 8.154 2 9.417 2 11.942v.117c0 2.524 0 3.787.597 4.801c.598 1.015 1.674 1.58 3.825 2.709z"></path><path fill="currentColor" d="m17.577 4.432l-2-1.05C13.822 2.461 12.944 2 12 2c-.945 0-1.822.46-3.578 1.382l-2 1.05C4.318 5.536 3.242 6.1 2.638 7.072L12 12l9.362-4.927c-.606-.973-1.68-1.537-3.785-2.641" opacity=".7"></path><path fill="currentColor" d="m21.403 7.14l-.041-.067L12 12v10c.944 0 1.822-.46 3.578-1.382l2-1.05c2.151-1.129 3.227-1.693 3.825-2.708c.597-1.014.597-2.277.597-4.8v-.117c0-2.525 0-3.788-.597-4.802" opacity=".5"></path><path fill="currentColor" d="m6.323 4.484l.1-.052l1.493-.784l9.1 5.005l4.025-2.011q.205.232.362.498c.15.254.262.524.346.825L17.75 9.964V13a.75.75 0 0 1-1.5 0v-2.286l-3.5 1.75v9.44A3 3 0 0 1 12 22c-.248 0-.493-.032-.75-.096v-9.44l-8.998-4.5c.084-.3.196-.57.346-.824q.156-.266.362-.498l9.04 4.52l3.387-1.693z"></path></svg>
        Inventory
        </a>

        <a class="admin-nav__link<?= $activeNav === 'orders' ? ' admin-nav__link--active' : '' ?>" href="orders.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M4.083 11.894c.439-2.34.658-3.511 1.491-4.203C6.408 7 7.598 7 9.98 7h4.04c2.383 0 3.573 0 4.407.691c.833.692 1.052 1.862 1.491 4.203l.75 4c.617 3.292.926 4.938.026 6.022S18.12 23 14.771 23H9.23c-3.349 0-5.024 0-5.923-1.084c-.9-1.084-.591-2.73.026-6.022z" opacity=".5"></path><path fill="currentColor" d="M9.75 5.985a2.25 2.25 0 0 1 4.5 0v1c.566 0 1.062.002 1.5.015V5.985a3.75 3.75 0 1 0-7.5 0V7c.438-.013.934-.015 1.5-.015zm.128 9.765a2.251 2.251 0 0 0 4.245 0a.75.75 0 1 1 1.414.5a3.751 3.751 0 0 1-7.073 0a.75.75 0 0 1 1.414-.5"></path></svg>
         Orders
        </a>

        <a class="admin-nav__link<?= $activeNav === 'shipped' ? ' admin-nav__link--active' : '' ?>" href="shipped-products.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M14 20h-4c-3.771 0-5.657 0-6.828-1.172S2 15.771 2 12c0-.442.002-1.608.004-2H22c.002.392 0 1.558 0 2c0 .66 0 1.261-.006 1.812l-1.403-1.403a2.25 2.25 0 0 0-3.182 0l-2 2a2.25 2.25 0 0 0 1.341 3.827v1.738C15.964 20 15.056 20 14 20" opacity=".5"></path><path fill="currentColor" fill-rule="evenodd" d="M18.47 13.47a.75.75 0 0 1 1.06 0l2 2a.75.75 0 1 1-1.06 1.06l-.72-.72V20a.75.75 0 0 1-1.5 0v-4.19l-.72.72a.75.75 0 1 1-1.06-1.06z" clip-rule="evenodd"></path><path fill="currentColor" d="M12.5 15.25a.75.75 0 0 0 0 1.5H14a.75.75 0 0 0 0-1.5zm-6.5 0a.75.75 0 0 0 0 1.5h4a.75.75 0 0 0 0-1.5zM9.995 4h4.01c3.781 0 5.672 0 6.846 1.116c.846.803 1.083 1.96 1.149 3.884v1H2V9c.066-1.925.303-3.08 1.149-3.884C4.323 4 6.214 4 9.995 4"></path></svg>
        Shipped

        </a>
        
        <a class="admin-nav__link<?= $activeNav === 'coupons' ? ' admin-nav__link--active' : '' ?>" href="coupons.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M10.926 2.36a.75.75 0 0 1 .249 1.031a.65.65 0 0 0 .095.8l.098.098c.588.588.805 1.453.564 2.25a.75.75 0 1 1-1.435-.434a.76.76 0 0 0-.19-.756l-.098-.098a2.15 2.15 0 0 1-.314-2.642a.75.75 0 0 1 1.031-.249m9.048 4.687c-.138.053-.26.176-.506.421c-.246.246-.368.368-.422.507a.7.7 0 0 0 0 .503c.054.138.176.261.422.507c.245.245.368.368.506.421a.7.7 0 0 0 .504 0c.138-.053.26-.176.506-.421c.245-.246.368-.369.421-.507a.7.7 0 0 0 0-.503c-.053-.139-.175-.261-.42-.507c-.246-.245-.369-.368-.507-.421a.7.7 0 0 0-.504 0m1.434 5.513a1.01 1.01 0 0 0-1.078.17a2.51 2.51 0 0 1-2.924.296l-.212-.123a.75.75 0 0 1 .75-1.299l.212.123c.378.218.853.17 1.179-.12a2.51 2.51 0 0 1 2.674-.422l.291.128a.75.75 0 1 1-.6 1.374z"></path><path fill="currentColor" d="M13.561 4.396c.201-.2.302-.301.418-.338a.5.5 0 0 1 .302 0c.116.037.217.137.418.338c.2.202.301.302.338.418a.5.5 0 0 1 0 .302c-.037.117-.137.217-.338.418s-.302.302-.418.339a.5.5 0 0 1-.302 0c-.116-.037-.217-.138-.418-.339c-.201-.2-.302-.301-.338-.418a.5.5 0 0 1 0-.302c.036-.116.137-.216.338-.418m5.497 10.917a.536.536 0 1 1 .758.759a.536.536 0 0 1-.758-.759" opacity=".7"></path><path fill="currentColor" d="M6.927 3.94a.536.536 0 1 1 .758.76a.536.536 0 0 1-.758-.76m10.762.782a.75.75 0 0 1 .588.882l-.144.72a2.82 2.82 0 0 1-1.87 2.12a1.31 1.31 0 0 0-.875.99l-.144.72a.75.75 0 0 1-1.47-.295l.144-.72c.198-.99.912-1.8 1.87-2.119c.448-.15.782-.527.874-.99l.144-.72a.75.75 0 0 1 .883-.588" opacity=".5"></path><path fill="currentColor" d="M17.5 9.742a.536.536 0 1 1 .758.758a.536.536 0 0 1-.758-.758" opacity=".2"></path><path fill="currentColor" d="m4.012 15.762l1.69-5.069c.766-2.298 1.149-3.447 2.055-3.66c.906-.215 1.763.642 3.475 2.355l3.38 3.379c1.712 1.713 2.569 2.569 2.355 3.475s-1.363 1.29-3.661 2.055l-5.069 1.69c-2.765.922-4.148 1.383-4.878.653s-.269-2.113.653-4.878" opacity=".5"></path><path fill="currentColor" d="m8.8 7.504l.05-.245c-.392-.23-.739-.31-1.093-.227a1.2 1.2 0 0 0-.397.175l.696.144c-.478-.1-.641-.133-.696-.144l-.035.024l-.005.026a26 26 0 0 0-.138.73a51 51 0 0 0-.311 1.939c-.215 1.533-.415 3.492-.312 5.057c.062.948.26 2.123.435 3.04a51 51 0 0 0 .312 1.503l.021.093l.006.025l.002.009l.73-.17l-.73.17l.137.588l.765-.254l.664-.221l-.106-.46l-.006-.021l-.02-.088l-.072-.33a49 49 0 0 1-.23-1.125c-.173-.907-.355-2.007-.411-2.857c-.092-1.404.088-3.235.3-4.75a50 50 0 0 1 .434-2.582l.008-.037l.002-.01zm4.24 10.882l-1.424.475l-.092-.278l.712-.237l-.712.237l-.001-.003l-.002-.006l-.007-.022a10 10 0 0 1-.115-.37c-.074-.247-.172-.59-.27-.983c-.192-.77-.402-1.792-.402-2.644s.21-1.874.402-2.643a22 22 0 0 1 .385-1.354l.007-.021l.002-.007v-.001l.713.235l-.712-.236l.212-.637l1.186 1.187l-.004.014l-.082.267c-.069.23-.16.55-.252.916c-.187.75-.357 1.622-.357 2.28s.17 1.531.357 2.28a21 21 0 0 0 .356 1.253l.006.017l.001.004z"></path></svg>
        Coupons
        </a>
        
        <a class="admin-nav__link<?= $activeNav === 'reviews' ? ' admin-nav__link--active' : '' ?>" href="reviews.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="m13.629 20.472l-.542.916c-.483.816-1.69.816-2.174 0l-.542-.916c-.42-.71-.63-1.066-.968-1.262c-.338-.197-.763-.204-1.613-.219c-1.256-.021-2.043-.098-2.703-.372a5 5 0 0 1-2.706-2.706C2 14.995 2 13.83 2 11.5v-1c0-3.273 0-4.91.737-6.112a5 5 0 0 1 1.65-1.651C5.59 2 7.228 2 10.5 2h3c3.273 0 4.91 0 6.113.737a5 5 0 0 1 1.65 1.65C22 5.59 22 7.228 22 10.5v1c0 2.33 0 3.495-.38 4.413a5 5 0 0 1-2.707 2.706c-.66.274-1.447.35-2.703.372c-.85.015-1.275.022-1.613.219c-.338.196-.548.551-.968 1.262" opacity=".5"></path><path fill="currentColor" d="M10.99 14.308c-1.327-.978-3.49-2.84-3.49-4.593c0-2.677 2.475-3.677 4.5-1.609c2.025-2.068 4.5-1.068 4.5 1.609c0 1.752-2.163 3.615-3.49 4.593c-.454.335-.681.502-1.01.502s-.556-.167-1.01-.502"></path></svg>
         Reviews
        </a>
        
        <div class="admin-nav__label">Finance</div>
        <a class="admin-nav__link<?= $activeNav === 'earnings' ? ' admin-nav__link--active' : '' ?>" href="earnings.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M21 15.998v-6c0-2.828 0-4.242-.879-5.121C19.353 4.109 18.175 4.012 16 4H8c-2.175.012-3.353.109-4.121.877C3 5.756 3 7.17 3 9.998v6c0 2.829 0 4.243.879 5.122c.878.878 2.293.878 5.121.878h6c2.828 0 4.243 0 5.121-.878c.879-.88.879-2.293.879-5.122" opacity=".5"></path><path fill="currentColor" d="M8 3.5A1.5 1.5 0 0 1 9.5 2h5A1.5 1.5 0 0 1 16 3.5v1A1.5 1.5 0 0 1 14.5 6h-5A1.5 1.5 0 0 1 8 4.5z"></path><path fill="currentColor" fill-rule="evenodd" d="M6.25 10.5A.75.75 0 0 1 7 9.75h.5a.75.75 0 0 1 0 1.5H7a.75.75 0 0 1-.75-.75m3.5 0a.75.75 0 0 1 .75-.75H17a.75.75 0 0 1 0 1.5h-6.5a.75.75 0 0 1-.75-.75M6.25 14a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5H7a.75.75 0 0 1-.75-.75m3.5 0a.75.75 0 0 1 .75-.75H17a.75.75 0 0 1 0 1.5h-6.5a.75.75 0 0 1-.75-.75m-3.5 3.5a.75.75 0 0 1 .75-.75h.5a.75.75 0 0 1 0 1.5H7a.75.75 0 0 1-.75-.75m3.5 0a.75.75 0 0 1 .75-.75H17a.75.75 0 0 1 0 1.5h-6.5a.75.75 0 0 1-.75-.75" clip-rule="evenodd"></path></svg>
        Earnings
        </a>
        <a class="admin-nav__link<?= $activeNav === 'withdraw' ? ' admin-nav__link--active' : '' ?>" href="withdraw-requests.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 20.028V18H8v2.028c0 .277 0 .416.095.472s.224-.006.484-.13l1.242-.593c.088-.042.132-.063.179-.063s.091.02.179.063l1.242.593c.26.124.39.186.484.13c.095-.056.095-.195.095-.472" opacity=".5"></path><path fill="currentColor" d="M8 18h-.574c-1.084 0-1.462.006-1.753.068c-.513.11-.96.347-1.285.667c-.11.108-.164.161-.291.505s-.107.489-.066.78l.022.15c.11.653.31.998.616 1.244c.307.246.737.407 1.55.494c.837.09 1.946.092 3.536.092h4.43c1.59 0 2.7-.001 3.536-.092c.813-.087 1.243-.248 1.55-.494s.506-.591.616-1.243c.091-.548.11-1.241.113-2.171h-8v2.028c0 .277 0 .416-.095.472s-.224-.006-.484-.13l-1.242-.593c-.088-.042-.132-.063-.179-.063s-.091.02-.179.063l-1.242.593c-.26.124-.39.186-.484.13C8 20.444 8 20.305 8 20.028z"></path><path fill="currentColor" d="M4.727 2.733c.306-.308.734-.508 1.544-.618C7.105 2.002 8.209 2 9.793 2h4.414c1.584 0 2.688.002 3.522.115c.81.11 1.238.31 1.544.618c.305.308.504.74.613 1.557c.112.84.114 1.955.114 3.552V18H7.426c-1.084 0-1.462.006-1.753.068c-.513.11-.96.347-1.285.667c-.11.108-.164.161-.291.505A1.3 1.3 0 0 0 4 19.7V7.842c0-1.597.002-2.711.114-3.552c.109-.816.308-1.249.613-1.557" opacity=".5"></path><path fill="currentColor" d="M7.25 7A.75.75 0 0 1 8 6.25h8a.75.75 0 0 1 0 1.5H8A.75.75 0 0 1 7.25 7M8 9.75a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5z"></path></svg>
        Withdraw Requests
        </a>
        <a class="admin-nav__link<?= $activeNav === 'transactions' ? ' admin-nav__link--active' : '' ?>" href="transactions.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M2 12c0-4.714 0-7.071 1.464-8.536C4.93 2 7.286 2 12 2s7.071 0 8.535 1.464C22 4.93 22 7.286 22 12s0 7.071-1.465 8.535C19.072 22 16.714 22 12 22s-7.071 0-8.536-1.465C2 19.072 2 16.714 2 12" opacity=".5"></path><path fill="currentColor" d="M10.543 7.517a.75.75 0 1 0-1.086-1.034l-2.314 2.43l-.6-.63a.75.75 0 1 0-1.086 1.034l1.143 1.2a.75.75 0 0 0 1.086 0zM13 8.25a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5zm-2.457 6.267a.75.75 0 1 0-1.086-1.034l-2.314 2.43l-.6-.63a.75.75 0 1 0-1.086 1.034l1.143 1.2a.75.75 0 0 0 1.086 0zM13 15.25a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5z"></path></svg>
        Transactions
        </a>
        <div class="admin-nav__label">Account</div>
        <a class="admin-nav__link<?= $activeNav === 'settings' ? ' admin-nav__link--active' : '' ?>" href="settings.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M14.279 2.152C13.909 2 13.439 2 12.5 2s-1.408 0-1.779.152a2 2 0 0 0-1.09 1.083c-.094.223-.13.484-.145.863a1.62 1.62 0 0 1-.796 1.353a1.64 1.64 0 0 1-1.579.008c-.338-.178-.583-.276-.825-.308a2.03 2.03 0 0 0-1.49.396c-.318.242-.553.646-1.022 1.453c-.47.807-.704 1.21-.757 1.605c-.07.526.074 1.058.4 1.479c.148.192.357.353.68.555c.477.297.783.803.783 1.361s-.306 1.064-.782 1.36c-.324.203-.533.364-.682.556a2 2 0 0 0-.399 1.479c.053.394.287.798.757 1.605s.704 1.21 1.022 1.453c.424.323.96.465 1.49.396c.242-.032.487-.13.825-.308a1.64 1.64 0 0 1 1.58.008c.486.28.774.795.795 1.353c.015.38.051.64.145.863c.204.49.596.88 1.09 1.083c.37.152.84.152 1.779.152s1.409 0 1.779-.152a2 2 0 0 0 1.09-1.083c.094-.223.13-.483.145-.863c.02-.558.309-1.074.796-1.353a1.64 1.64 0 0 1 1.579-.008c.338.178.583.276.825.308c.53.07 1.066-.073 1.49-.396c.318-.242.553-.646 1.022-1.453c.47-.807.704-1.21.757-1.605a2 2 0 0 0-.4-1.479c-.148-.192-.357-.353-.68-.555c-.477-.297-.783-.803-.783-1.361s.306-1.064.782-1.36c.324-.203.533-.364.682-.556a2 2 0 0 0 .399-1.479c-.053-.394-.287-.798-.757-1.605s-.704-1.21-1.022-1.453a2.03 2.03 0 0 0-1.49-.396c-.242.032-.487.13-.825.308a1.64 1.64 0 0 1-1.58-.008a1.62 1.62 0 0 1-.795-1.353c-.015-.38-.051-.64-.145-.863a2 2 0 0 0-1.09-1.083" clip-rule="evenodd" opacity=".5"></path><path fill="currentColor" d="M15.523 12c0 1.657-1.354 3-3.023 3s-3.023-1.343-3.023-3S10.83 9 12.5 9s3.023 1.343 3.023 3"></path></svg>
        Settings
        </a>
        <a class="admin-nav__link<?= $activeNav === 'profile' ? ' admin-nav__link--active' : '' ?>" href="profile.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><circle cx="10" cy="6.75" r="4" fill="currentColor"></circle><ellipse cx="10" cy="17.75" fill="currentColor" opacity=".5" rx="7" ry="4"></ellipse><path fill="currentColor" fill-rule="evenodd" d="M18.357 2.364a.75.75 0 0 1 1.029-.257L19 2.75l.386-.643h.001l.002.002l.004.002l.01.006l.113.076c.07.049.166.12.277.212c.222.185.512.462.802.838c.582.758 1.155 1.914 1.155 3.507s-.573 2.75-1.155 3.507c-.29.376-.58.653-.802.838a4 4 0 0 1-.363.27l-.028.018l-.01.006l-.003.002l-.002.001s-.001.001-.387-.642l.386.643a.75.75 0 0 1-.776-1.283l.005-.004l.041-.027q.06-.042.177-.136c.152-.128.362-.326.573-.6c.417-.542.844-1.386.844-2.593s-.427-2.05-.844-2.593a3.8 3.8 0 0 0-.573-.6a3 3 0 0 0-.218-.163l-.005-.003a.75.75 0 0 1-.253-1.027" clip-rule="evenodd"></path><path fill="currentColor" fill-rule="evenodd" d="M16.33 4.415a.75.75 0 0 1 1.006-.336L17 4.75l.336-.67h.001l.002.001l.004.002l.008.004l.022.012a2 2 0 0 1 .233.153c.136.102.31.254.48.467c.349.436.664 1.099.664 2.031s-.316 1.595-.664 2.031a2.7 2.7 0 0 1-.654.586l-.06.034l-.02.012l-.01.004l-.003.002l-.002.001l-.33-.657l.329.658a.75.75 0 0 1-.685-1.335l.003-.001l.052-.036c.052-.04.13-.106.209-.205c.15-.189.335-.526.335-1.094s-.184-.905-.335-1.094a1.2 1.2 0 0 0-.261-.24l-.003-.002a.75.75 0 0 1-.322-1" clip-rule="evenodd"></path></svg>
        Profile
        </a>
        <a class="admin-nav__link<?= $activeNav === 'kyc' ? ' admin-nav__link--active' : '' ?>" href="kyc-details.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 20.028V18H8v2.028c0 .277 0 .416.095.472s.224-.006.484-.13l1.242-.593c.088-.042.132-.063.179-.063s.091.02.179.063l1.242.593c.26.124.39.186.484.13c.095-.056.095-.195.095-.472" opacity=".5"></path><path fill="currentColor" d="M8 18h-.574c-1.084 0-1.462.006-1.753.068c-.513.11-.96.347-1.285.667c-.11.108-.164.161-.291.505s-.107.489-.066.78l.022.15c.11.653.31.998.616 1.244c.307.246.737.407 1.55.494c.837.09 1.946.092 3.536.092h4.43c1.59 0 2.7-.001 3.536-.092c.813-.087 1.243-.248 1.55-.494s.506-.591.616-1.243c.091-.548.11-1.241.113-2.171h-8v2.028c0 .277 0 .416-.095.472s-.224-.006-.484-.13l-1.242-.593c-.088-.042-.132-.063-.179-.063s-.091.02-.179.063l-1.242.593c-.26.124-.39.186-.484.13C8 20.444 8 20.305 8 20.028z"></path><path fill="currentColor" d="M4.727 2.733c.306-.308.734-.508 1.544-.618C7.105 2.002 8.209 2 9.793 2h4.414c1.584 0 2.688.002 3.522.115c.81.11 1.238.31 1.544.618c.305.308.504.74.613 1.557c.112.84.114 1.955.114 3.552V18H7.426c-1.084 0-1.462.006-1.753.068c-.513.11-.96.347-1.285.667c-.11.108-.164.161-.291.505A1.3 1.3 0 0 0 4 19.7V7.842c0-1.597.002-2.711.114-3.552c.109-.816.308-1.249.613-1.557" opacity=".5"></path><path fill="currentColor" d="M7.25 7A.75.75 0 0 1 8 6.25h8a.75.75 0 0 1 0 1.5H8A.75.75 0 0 1 7.25 7M8 9.75a.75.75 0 0 0 0 1.5h5a.75.75 0 0 0 0-1.5z"></path></svg>
        KYC &amp; Bank
        </a>
        <a class="admin-nav__link" href="logout.php">
        <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M6.25 19a.75.75 0 0 0 1.32.488l6-7a.75.75 0 0 0 0-.976l-6-7A.75.75 0 0 0 6.25 5z" opacity=".5"></path><path fill="currentColor" fill-rule="evenodd" d="M10.512 19.57a.75.75 0 0 1-.081-1.058L16.012 12l-5.581-6.512a.75.75 0 1 1 1.139-.976l6 7a.75.75 0 0 1 0 .976l-6 7a.75.75 0 0 1-1.058.082" clip-rule="evenodd"></path></svg>
        Sign Out
        </a>
      </nav>
    </aside>
    <div class="admin-sidebar-overlay" id="adminSidebarOverlay" aria-hidden="true"></div>
    <div class="admin-main-wrap">
      <header class="admin-topbar">
        <div class="admin-topbar__left">
          <button type="button" class="admin-menu-btn" id="adminMenuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="adminSidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          </button>
          <div class="seller-top-meta">Manage orders and products in your categories</div>
        </div>
        <div class="admin-topbar__actions">
          <div class="admin-notify-wrap">
            <button type="button" class="admin-icon-btn admin-icon-btn--notify admin-notify-wrap__btn" id="sellerNotifyBtn" title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
            <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M18.75 9v.704c0 .845.24 1.671.692 2.374l1.108 1.723c1.011 1.574.239 3.713-1.52 4.21a25.8 25.8 0 0 1-14.06 0c-1.759-.497-2.531-2.636-1.52-4.21l1.108-1.723a4.4 4.4 0 0 0 .693-2.374V9c0-3.866 3.022-7 6.749-7s6.75 3.134 6.75 7" opacity=".5"></path><path fill="currentColor" d="M12.75 6a.75.75 0 0 0-1.5 0v4a.75.75 0 0 0 1.5 0zM7.243 18.545a5.002 5.002 0 0 0 9.513 0c-3.145.59-6.367.59-9.513 0"></path></svg>
            </button>
            <div class="admin-notify-dropdown" role="region" aria-label="Order notifications">
              <div class="admin-notify-dropdown__inner">
                <div class="admin-notify-dropdown__head">Order notifications</div>
                <ul class="admin-notify-list">
                  <?php foreach ($sellerNotifications as $n): ?>
                    <?php
                    $status = strtolower((string) ($n['status'] ?? 'processing'));
                    $dotClass = 'admin-notify-row__dot';
                    if ($status === 'processing') {
                        $dotClass .= ' admin-notify-row__dot--warn';
                    } elseif ($status === 'delivered' || $status === 'shipped') {
                        $dotClass .= ' admin-notify-row__dot--muted';
                    }
                    $meta = ucfirst($status) . ' · Rs ' . number_format((int) $n['total_amount']) . ' · ' . (new DateTimeImmutable((string) $n['created_at']))->format('M j, g:i A');
                    ?>
                    <li>
                      <a class="admin-notify-row" href="orders.php">
                        <span class="<?= h($dotClass) ?>" aria-hidden="true"></span>
                        <span class="admin-notify-row__body">
                          <span class="admin-notify-row__title"><?= h((string) $n['order_ref']) ?> · <?= h((string) $n['customer_name']) ?></span>
                          <span class="admin-notify-row__meta"><?= h($meta) ?></span>
                        </span>
                      </a>
                    </li>
                  <?php endforeach; ?>
                  <?php if ($sellerNotifications === []): ?>
                    <li>
                      <span class="admin-notify-row">
                        <span class="admin-notify-row__dot admin-notify-row__dot--muted" aria-hidden="true"></span>
                        <span class="admin-notify-row__body">
                          <span class="admin-notify-row__title">No orders yet</span>
                          <span class="admin-notify-row__meta">New order notifications yahan dikhenge.</span>
                        </span>
                      </span>
                    </li>
                  <?php endif; ?>
                </ul>
                <a class="admin-notify-dropdown__foot" href="orders.php">View all orders</a>
              </div>
            </div>
          </div>
          <button type="button" class="admin-icon-btn" id="adminThemeBtn" title="Dark mode" aria-label="Switch to dark mode" aria-pressed="false">
          <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M22 12c0 5.523-4.477 10-10 10a10 10 0 0 1-3.321-.564A9 9 0 0 1 8 18a8.97 8.97 0 0 1 2.138-5.824A6.5 6.5 0 0 0 15.5 15a6.5 6.5 0 0 0 5.567-3.143c.24-.396.933-.32.933.143" clip-rule="evenodd" opacity=".5"></path><path fill="currentColor" d="M2 12c0 4.359 2.789 8.066 6.679 9.435A9 9 0 0 1 8 18c0-2.221.805-4.254 2.138-5.824A6.47 6.47 0 0 1 9 8.5a6.5 6.5 0 0 1 3.143-5.567C12.54 2.693 12.463 2 12 2C6.477 2 2 6.477 2 12"></path></svg>
          </button>
          <div class="admin-user-wrap">
            <button type="button" class="admin-user-pill admin-user-wrap__btn" id="sellerUserMenuBtn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
              <span class="admin-user-pill__avatar" aria-hidden="true"><?= h($initials) ?></span>
              <span class="admin-user-pill__meta">
                <span class="admin-user-pill__name"><?= h((string) $seller['full_name']) ?></span>
                <span class="admin-user-pill__role">Seller</span>
              </span>
            </button>
            <div class="admin-user-dropdown" role="region" aria-label="Seller account options">
              <div class="admin-user-dropdown__inner">
                <div class="admin-user-dropdown__head">
                  <div class="admin-user-dropdown__name"><?= h((string) $seller['full_name']) ?></div>
                  <div class="admin-user-dropdown__email"><?= h((string) $seller['email']) ?></div>
                </div>
                <ul class="admin-user-dropdown__list" role="list">
                  <li>
                    <a class="admin-user-dropdown__item" href="profile.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24"><circle cx="10" cy="6.75" r="4" fill="currentColor"></circle><ellipse cx="10" cy="17.75" fill="currentColor" opacity=".5" rx="7" ry="4"></ellipse><path fill="currentColor" fill-rule="evenodd" d="M18.357 2.364a.75.75 0 0 1 1.029-.257L19 2.75l.386-.643h.001l.002.002l.004.002l.01.006l.113.076c.07.049.166.12.277.212c.222.185.512.462.802.838c.582.758 1.155 1.914 1.155 3.507s-.573 2.75-1.155 3.507c-.29.376-.58.653-.802.838a4 4 0 0 1-.363.27l-.028.018l-.01.006l-.003.002l-.002.001s-.001.001-.387-.642l.386.643a.75.75 0 0 1-.776-1.283l.005-.004l.041-.027q.06-.042.177-.136c.152-.128.362-.326.573-.6c.417-.542.844-1.386.844-2.593s-.427-2.05-.844-2.593a3.8 3.8 0 0 0-.573-.6a3 3 0 0 0-.218-.163l-.005-.003a.75.75 0 0 1-.253-1.027" clip-rule="evenodd"></path><path fill="currentColor" fill-rule="evenodd" d="M16.33 4.415a.75.75 0 0 1 1.006-.336L17 4.75l.336-.67h.001l.002.001l.004.002l.008.004l.022.012a2 2 0 0 1 .233.153c.136.102.31.254.48.467c.349.436.664 1.099.664 2.031s-.316 1.595-.664 2.031a2.7 2.7 0 0 1-.654.586l-.06.034l-.02.012l-.01.004l-.003.002l-.002.001l-.33-.657l.329.658a.75.75 0 0 1-.685-1.335l.003-.001l.052-.036c.052-.04.13-.106.209-.205c.15-.189.335-.526.335-1.094s-.184-.905-.335-1.094a1.2 1.2 0 0 0-.261-.24l-.003-.002a.75.75 0 0 1-.322-1" clip-rule="evenodd"></path></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Profile</span>
                        <span class="admin-user-dropdown__hint">Seller dashboard</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item" href="settings.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M14.279 2.152C13.909 2 13.439 2 12.5 2s-1.408 0-1.779.152a2 2 0 0 0-1.09 1.083c-.094.223-.13.484-.145.863a1.62 1.62 0 0 1-.796 1.353a1.64 1.64 0 0 1-1.579.008c-.338-.178-.583-.276-.825-.308a2.03 2.03 0 0 0-1.49.396c-.318.242-.553.646-1.022 1.453c-.47.807-.704 1.21-.757 1.605c-.07.526.074 1.058.4 1.479c.148.192.357.353.68.555c.477.297.783.803.783 1.361s-.306 1.064-.782 1.36c-.324.203-.533.364-.682.556a2 2 0 0 0-.399 1.479c.053.394.287.798.757 1.605s.704 1.21 1.022 1.453c.424.323.96.465 1.49.396c.242-.032.487-.13.825-.308a1.64 1.64 0 0 1 1.58.008c.486.28.774.795.795 1.353c.015.38.051.64.145.863c.204.49.596.88 1.09 1.083c.37.152.84.152 1.779.152s1.409 0 1.779-.152a2 2 0 0 0 1.09-1.083c.094-.223.13-.483.145-.863c.02-.558.309-1.074.796-1.353a1.64 1.64 0 0 1 1.579-.008c.338.178.583.276.825.308c.53.07 1.066-.073 1.49-.396c.318-.242.553-.646 1.022-1.453c.47-.807.704-1.21.757-1.605a2 2 0 0 0-.4-1.479c-.148-.192-.357-.353-.68-.555c-.477-.297-.783-.803-.783-1.361s.306-1.064.782-1.36c.324-.203.533-.364.682-.556a2 2 0 0 0 .399-1.479c-.053-.394-.287-.798-.757-1.605s-.704-1.21-1.022-1.453a2.03 2.03 0 0 0-1.49-.396c-.242.032-.487.13-.825.308a1.64 1.64 0 0 1-1.58-.008a1.62 1.62 0 0 1-.795-1.353c-.015-.38-.051-.64-.145-.863a2 2 0 0 0-1.09-1.083" clip-rule="evenodd" opacity=".5"></path><path fill="currentColor" d="M15.523 12c0 1.657-1.354 3-3.023 3s-3.023-1.343-3.023-3S10.83 9 12.5 9s3.023 1.343 3.023 3"></path></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Settings</span>
                        <span class="admin-user-dropdown__hint">Password &amp; shortcuts</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item" href="settings.php#seller-delete-zone">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                      <svg width="14" height="14" viewBox="0 0 10 11" ><path opacity="0.5" d="M9.15334 4.11869C9.15334 4.15609 8.86022 7.86351 8.69279 9.4238C8.58795 10.3813 7.97067 10.9621 7.04475 10.9786C6.33333 10.9946 5.63689 11 4.95167 11C4.22421 11 3.51278 10.9946 2.82222 10.9786C1.92733 10.9572 1.30951 10.3648 1.21002 9.4238C1.03778 7.85801 0.750005 4.15609 0.744656 4.11869C0.739307 4.00594 0.775681 3.8987 0.849497 3.8118C0.922244 3.7315 1.02709 3.68311 1.13728 3.68311H8.76607C8.87573 3.68311 8.97522 3.7315 9.05385 3.8118C9.12713 3.8987 9.16404 4.00594 9.15334 4.11869Z" fill="currentColor"></path><path d="M9.9 2.18727C9.9 1.96123 9.72188 1.78414 9.50791 1.78414H7.90427C7.57798 1.78414 7.29448 1.55205 7.22174 1.22481L7.13187 0.823871C7.00617 0.339338 6.57236 0 6.0856 0H3.81493C3.32282 0 2.89329 0.339338 2.76278 0.85027L2.6788 1.22536C2.60552 1.55205 2.32202 1.78414 1.99626 1.78414H0.392619C0.178123 1.78414 0 1.96123 0 2.18727V2.39627C0 2.61681 0.178123 2.7994 0.392619 2.7994H9.50791C9.72188 2.7994 9.9 2.61681 9.9 2.39627V2.18727Z" fill="currentColor"></path></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Delete account</span>
                        <span class="admin-user-dropdown__hint">Settings · confirmation flow</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item admin-user-dropdown__item--logout" href="logout.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M6.25 19a.75.75 0 0 0 1.32.488l6-7a.75.75 0 0 0 0-.976l-6-7A.75.75 0 0 0 6.25 5z" opacity=".5"></path><path fill="currentColor" fill-rule="evenodd" d="M10.512 19.57a.75.75 0 0 1-.081-1.058L16.012 12l-5.581-6.512a.75.75 0 1 1 1.139-.976l6 7a.75.75 0 0 1 0 .976l-6 7a.75.75 0 0 1-1.058.082" clip-rule="evenodd"></path></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Sign Out</span>
                        <span class="admin-user-dropdown__hint">End session</span>
                      </span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </header>
      <main class="admin-content">
