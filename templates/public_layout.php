<?php /** @var string $content */ $title = $title ?? ''; ?>
<!doctype html>
<html lang="th" data-bs-theme="light" data-palette="cyber" data-sidebar="full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | <?= e(app_name()) ?></title>
  <script src="<?= e(asset('js/theme-init.js')) ?>"></script>
  <?php require __DIR__ . '/head_assets.php'; ?>
</head>
<body>
<div class="app-bg" aria-hidden="true"></div>
<!-- หน้าสาธารณะ (ผู้ใช้งานทั่วไป ไม่ต้องเข้าสู่ระบบ): ไม่มีเมนูด้านข้าง/การแจ้งเตือน -->
<header class="app-topbar public-topbar">
  <a href="<?= e(url('/')) ?>" class="d-flex align-items-center gap-2 text-decoration-none me-auto min-w-0">
    <?= brand_mark() ?>
    <span class="brand-text d-none d-sm-block"><strong class="text-body-emphasis"><?= e(app_name('brand_main')) ?></strong><small><?= e(app_name('brand_sub')) ?></small></span>
  </a>
  <div class="d-flex align-items-center gap-2">
    <div class="dropdown">
      <button class="topbar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="เลือกชุดสี" title="ชุดสี (Color Palette)">
        <i class="bi bi-palette2 text-gradient"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end palette-menu" role="menu" aria-label="ชุดสี">
        <div class="small text-body-secondary px-2 pb-1">ชุดสี (Color Palette)</div>
        <?php foreach (['cyber' => 'Cyber Blue', 'neon' => 'Neon Purple', 'emerald' => 'Emerald', 'sunset' => 'Sunset', 'phayao' => 'Phayao Lake'] as $key => $label): ?>
          <button type="button" class="palette-option" role="menuitemradio" aria-checked="false" data-palette-option="<?= e($key) ?>">
            <span class="palette-swatch swatch-<?= e($key) ?>"></span><span><?= e($label) ?></span><i class="bi bi-check-lg check"></i>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="topbar-btn theme-toggle" type="button" data-theme-toggle aria-label="สลับโหมดมืด/สว่าง" title="สลับโหมดมืด/สว่าง">
      <i class="bi bi-moon-stars theme-icon-light"></i><i class="bi bi-sun theme-icon-dark"></i>
    </button>
    <a href="<?= e(url('/login')) ?>" class="btn btn-primary px-3"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</a>
  </div>
</header>
<main class="main-content public-content" id="main-content">
  <?php require __DIR__ . '/alerts.php'; ?>
  <?= $content ?>
</main>
<footer class="text-center text-body-secondary small main-footer">
  &copy; <?= e(date('Y') + 543) ?> <?= e(Core\Setting::get('org.name', config('app.org'))) ?>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
</body>
</html>
