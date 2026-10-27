<?php
/** @var array $settings */
$grouped = [];
foreach ($settings as $s) {
    $g = $s['setting_group'] ?? ($s['group'] ?? 'general');
    $grouped[$g][] = $s;
}
?>
<h1 class="h4 mb-3">ตั้งค่าระบบ</h1>

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