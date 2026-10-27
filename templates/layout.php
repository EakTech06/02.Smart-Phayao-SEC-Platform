<?php /** @var string $content */ $title = $title ?? ''; ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($title) ?> | <?= e(config('app.name')) ?></title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-body-tertiary">
<?php require __DIR__ . '/header.php'; ?>
<div class="container-fluid">
  <div class="row">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="col-lg-9 col-xl-10 ms-auto px-3 px-md-4 py-4 main-content">
      <?php require __DIR__ . '/alerts.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<?php require __DIR__ . '/bottom_nav.php'; ?>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>