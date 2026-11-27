<?php /** @var int $status */ /** @var string $message */ /** @var string|null $trace */ ?>
<!doctype html>
<html lang="th" data-bs-theme="light" data-palette="cyber">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($status) ?> | <?= e(app_name()) ?></title>
  <script src="<?= e(asset('js/theme-init.js')) ?>"></script>
  <?php require __DIR__ . '/head_assets.php'; ?>
</head>
<body>
<div class="app-bg" aria-hidden="true"></div>
<div class="container py-5 text-center">
  <h1 class="display-3 fw-bold text-gradient"><?= e($status) ?></h1>
  <p class="lead"><?= e($message) ?></p>
  <a href="<?= e(url('/')) ?>" class="btn btn-primary">กลับหน้าหลัก</a>
  <?php if (!empty($trace)): ?>
    <pre class="text-start small bg-dark text-light p-3 mt-4 rounded overflow-auto"><?= e($trace) ?></pre>
  <?php endif; ?>
</div>
</body>
</html>
