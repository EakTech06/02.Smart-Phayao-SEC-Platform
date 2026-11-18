<?php
/** @var array $settings */
$grouped = [];
foreach ($settings as $s) {
    $g = $s['setting_group'] ?? ($s['group'] ?? 'general');
    $grouped[$g][] = $s;
}
?>
<h1 class="h4 mb-3">ตั้งค่าระบบ</h1>

<!-- Google Workspace (ค่าเชื่อมต่ออยู่ใน config/local.php ไม่เก็บในฐานข้อมูล) -->
<div class="card shadow-sm mb-3">
  <div class="card-body d-flex flex-wrap align-items-center gap-2">
    <div class="me-auto">
      <div class="fw-semibold"><i class="bi bi-google me-1"></i>Google Workspace (Shared Drive · Calendar · Gmail)</div>
      <div class="small text-muted">
        <?php if (Core\GoogleWorkspace::isConfigured()): ?>
          ตั้งค่าแล้ว: <span class="font-monospace"><?= e(preg_replace('#(/macros/s/.{6}).+(/exec)$#', '$1…$2', (string) config('google.apps_script_url'))) ?></span>
        <?php else: ?>
          ยังไม่ได้ตั้งค่า — ใส่ google.apps_script_url และ google.shared_secret ใน config/local.php (ดู tools/google-apps-script/README.md)
        <?php endif; ?>
      </div>
    </div>
    <form method="post" action="<?= e(url('/settings/google-test')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline-primary" <?= Core\GoogleWorkspace::isConfigured() ? '' : 'disabled' ?>><i class="bi bi-plug me-1"></i>ทดสอบการเชื่อมต่อ</button>
    </form>
  </div>
</div>

<form method="post" action="<?= e(url('/settings')) ?>">
  <?= csrf_field() ?>
  <?php foreach ($grouped as $group => $items): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">กลุ่ม: <?= e(strtoupper((string)$group)) ?></div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($items as $item): ?>
            <?php 
              $settingKey = $item['setting_key'] ?? ($item['key'] ?? '');
              $settingDesc = $item['description'] ?? $settingKey;
              $settingVal = $item['setting_value'] ?? ($item['value'] ?? '');
            ?>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small"><?= e($settingDesc) ?></label>
              <input type="text" name="settings[<?= e($settingKey) ?>]" value="<?= e($settingVal) ?>" class="form-control">
              <div class="form-text font-monospace" style="font-size: 0.75rem;">Key: <?= e($settingKey) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="d-flex justify-content-end mb-4">
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึกการตั้งค่า</button>
  </div>
</form>