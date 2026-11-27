<?php /** @var string $content */ $title = $title ?? ''; ?>
<!doctype html>
<html lang="th" data-bs-theme="light" data-palette="cyber" data-sidebar="full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title) ?> | <?= e(app_name()) ?></title>
  <script src="<?= e(asset('js/theme-init.js')) ?>"></script>
  <?php require __DIR__ . '/head_assets.php'; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>" media="print">
</head>
<body>
<div class="app-bg" aria-hidden="true"></div>
<div class="app-shell">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="app-main">
    <?php require __DIR__ . '/header.php'; ?>
    <main class="main-content" id="main-content">
      <?php require __DIR__ . '/alerts.php'; ?>
      <?= $content ?>
    </main>
    <?php require __DIR__ . '/footer.php'; ?>
  </div>
</div>
<?php require __DIR__ . '/bottom_nav.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
