<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);

$pdo = db();
$user = auth_user($pdo);
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>Page Not Found - LUXE</title>
  <meta name="description" content="The page you requested does not exist." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  <style>
    .not-found-wrap {
      min-height: 62vh;
      display: grid;
      place-items: center;
      padding: 28px 0 12px;
    }
    .not-found-card {
      width: min(780px, 100%);
      border: 1px solid var(--border);
      border-radius: 20px;
      background:
        radial-gradient(circle at top right, rgba(139, 92, 246, 0.2), transparent 44%),
        radial-gradient(circle at bottom left, rgba(236, 72, 153, 0.18), transparent 50%),
        var(--card);
      padding: clamp(22px, 4vw, 34px);
      text-align: center;
      box-shadow: var(--shadow);
    }
    .not-found-code {
      font-size: clamp(2.4rem, 7vw, 4.8rem);
      line-height: 1;
      font-weight: 800;
      letter-spacing: 0.03em;
      color: var(--white);
      margin-bottom: 12px;
      text-shadow: 0 10px 34px rgba(139, 92, 246, 0.35);
    }
    .not-found-title {
      margin: 0 0 8px;
      font-size: clamp(1.2rem, 2.6vw, 1.75rem);
      color: var(--white);
    }
    .not-found-text {
      margin: 0 auto;
      max-width: 58ch;
      color: var(--text-muted);
      line-height: 1.7;
    }
    .not-found-actions {
      margin-top: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .not-found-btn {
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 10px 16px;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--white);
      background: rgba(255, 255, 255, 0.04);
      transition: transform var(--transition), border-color var(--transition), background var(--transition);
      cursor: pointer;
    }
    .not-found-btn:hover {
      transform: translateY(-1px);
      border-color: var(--border-hover);
      background: rgba(139, 92, 246, 0.12);
    }
    .not-found-btn--primary {
      background: var(--gradient);
      border-color: transparent;
    }
  </style>
</head>
<body class="not-found-page">
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <?php
  $header = [
      'user' => $user,
      'cart_count' => $cartNavCount,
      'top_text' => 'New arrivals every week',
      'top_highlight' => 'Free shipping above Rs 999',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container">
      <section class="not-found-wrap" aria-labelledby="notFoundTitle">
        <div class="not-found-card">
          <p class="not-found-code">404</p>
          <h1 class="not-found-title" id="notFoundTitle">Page Not Found</h1>
          <p class="not-found-text">The URL you entered is invalid or the page has moved. Please go back to the previous page or continue to the homepage.</p>
          <div class="not-found-actions">
            <button type="button" class="not-found-btn" id="notFoundBackBtn">Go Back</button>
            <a href="index.php" class="not-found-btn not-found-btn--primary">Go to Home</a>
          </div>
        </div>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/includes/user_footer.php'; ?>
  <script src="script/luxe.js?v=7"></script>
  <script>
    (function () {
      var backBtn = document.getElementById('notFoundBackBtn');
      if (!backBtn) return;
      backBtn.addEventListener('click', function () {
        if (window.history.length > 1) {
          window.history.back();
          return;
        }
        window.location.href = 'index.php';
      });
    })();
  </script>
</body>
</html>
