<?php /** @var int $status */ /** @var string $message */ /** @var string|null $trace */ ?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($status) ?> | Smart Phayao SEC</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5 text-center">
  <h1 class="display-3 fw-bold text-primary"><?= e($status) ?></h1>
  <p class="lead"><?= e($message) ?></p>
  <a href="<?= e(url('/')) ?>" class="btn btn-primary">กลับหน้าหลัก</a>
  <?php if (!empty($trace)): ?>
    <pre class="text-start small bg-dark text-light p-3 mt-4 rounded overflow-auto"><?= e($trace) ?></pre>
  <?php endif; ?>
</div>
</body>
</html>