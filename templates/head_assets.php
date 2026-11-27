  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Prompt:wght@500;600&family=Sarabun:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
<?php if (($faviconUrl = app_logo_url()) !== null): ?>
  <link rel="icon" href="<?= e($faviconUrl) ?>">
<?php endif; ?>
