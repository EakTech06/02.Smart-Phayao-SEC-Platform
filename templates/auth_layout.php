<?php /** @var string $content */ $title = $title ?? ''; ?>
<!doctype html>
<html lang="th" data-bs-theme="light" data-palette="cyber">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | <?= e(config('app.name')) ?></title>
  <script src="<?= e(asset('js/theme-init.js')) ?>"></script>
  <?php require __DIR__ . '/head_assets.php'; ?>
</head>
<body>
<div class="app-bg" aria-hidden="true"></div>
<div class="container auth-wrapper d-flex flex-column align-items-center justify-content-center py-4">
  <div class="d-flex align-items-center gap-2 mb-3">
    <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
    <span class="brand-text"><strong><?= e(config('app.name')) ?></strong></span>
  </div>
  <div class="card auth-card w-100 position-relative">
    <div class="card-body p-4 p-md-5">
      <?php require __DIR__ . '/alerts.php'; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
