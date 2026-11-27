<?php
/** @var array $settings */
use Modules\CoreAdmin\Controllers\SettingController;

$grouped = [];
$values = array_column($settings, 'setting_value', 'setting_key');
$oldInput = Core\Session::getFlash('old', []);
$oldSettings = is_array($oldInput['settings'] ?? null) ? $oldInput['settings'] : [];
// ที่แสดงผลของแต่ละช่องชื่อระบบ
$brandingHints = [
    'app.name' => 'แท็บเบราว์เซอร์ · หน้าเข้าสู่ระบบ · อีเมลแจ้งเตือน · แอปยืนยันตัวตน 2 ขั้นตอน · ท้ายกระดาษรายงาน',
    'app.brand_main' => 'โลโก้เมนูด้านข้าง · แถบบนหน้าแรก · หัวข้อหน้าแรกสาธารณะ',
    'app.brand_sub' => 'บรรทัดเล็กใต้ชื่อหลัก (เว้นว่างได้)',
    'app.short_name' => 'แถบบนเมื่อเปิดบนมือถือ (พื้นที่จำกัด ควรสั้น)',
    'app.tagline' => 'ข้อความต่อท้ายชื่อหน่วยงานในหน้าแรกสาธารณะ (เว้นว่างได้)',
];
foreach ($settings as $s) {
    if (isset(SettingController::BRANDING[$s['setting_key']])) {
        continue; // แสดงในการ์ด "ชื่อระบบ" ด้านบน
    }
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
  <div class="card shadow-sm mb-3" id="branding">
    <div class="card-header fw-semibold"><i class="bi bi-type me-1"></i>ชื่อระบบ</div>
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 p-3 mb-3 rounded-3 border bg-body-tertiary" aria-label="ตัวอย่างโลโก้เมนูด้านข้าง (ค่าที่บันทึกแล้ว)">
        <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
        <span class="brand-text"><strong><?= e(app_name('brand_main')) ?></strong><small><?= e(app_name('brand_sub')) ?></small></span>
        <span class="ms-auto small text-body-secondary">ตัวอย่างที่แสดงอยู่ตอนนี้</span>
      </div>
      <div class="row g-3">
        <?php foreach (SettingController::BRANDING as $key => [$label, $max, $required]): ?>
          <?php $field = 'settings.' . $key; $err = error_for($field); $id = 'set-' . str_replace('.', '-', $key); ?>
          <div class="col-12 <?= $key === 'app.name' ? 'col-md-8' : 'col-md-4' ?>">
            <label class="form-label fw-semibold small" for="<?= e($id) ?>"><?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?></label>
            <input type="text" id="<?= e($id) ?>" name="settings[<?= e($key) ?>]" maxlength="<?= (int) $max ?>" <?= $required ? 'required' : '' ?>
                   value="<?= e(is_scalar($oldSettings[$key] ?? null) ? (string) $oldSettings[$key] : ($values[$key] ?? app_name(substr($key, 4)))) ?>"
                   class="form-control<?= $err ? ' is-invalid' : '' ?>" aria-describedby="<?= e($id) ?>-hint">
            <?php if ($err): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            <div class="form-text" id="<?= e($id) ?>-hint"><?= e($brandingHints[$key]) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

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