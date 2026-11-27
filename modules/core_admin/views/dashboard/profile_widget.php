<?php
/** @var array $user */
/** @var string $name */
$avatarFile = $user['avatar'] ?? $avatar ?? null;
$initials = mb_substr($user['first_name'] ?? $user['name'] ?? 'U', 0, 1);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'อรุณสวัสดิ์' : ($hour < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
?>
<div class="card hero-card h-100 rise-in">
  <div class="card-body d-flex align-items-center gap-3 p-4">
    <span class="avatar-ring flex-shrink-0">
      <?php if (!empty($avatarFile)): ?>
        <img src="<?= e(url('/uploads/avatars/' . rawurlencode((string) $avatarFile))) ?>" alt="รูปโปรไฟล์" class="avatar" style="width: 72px; height: 72px;">
      <?php else: ?>
        <span class="avatar fs-3" style="width: 72px; height: 72px;"><?= e($initials) ?></span>
      <?php endif; ?>
    </span>
    <div class="min-w-0">
      <div class="small text-body-secondary"><?= e($greeting) ?> · <?= e(thai_date(date('Y-m-d'))) ?></div>
      <h2 class="h4 mb-1 text-truncate"><?= e($name) ?></h2>
      <div class="text-body-secondary small text-truncate"><?= e($user['position'] ?? 'ผู้ใช้งานระบบ') ?></div>
    </div>
  </div>
</div>
