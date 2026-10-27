<?php /** @var bool $forced */ /** @var int $minLength */ ?>
<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-xl-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h1 class="h5 mb-3"><i class="bi bi-shield-lock me-1"></i>เปลี่ยนรหัสผ่าน</h1>
        <?php if ($forced): ?>
          <div class="alert alert-warning small">ต้องเปลี่ยนรหัสผ่านก่อนเริ่มใช้งานระบบ</div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/password/change')) ?>" novalidate>
          <?= csrf_field() ?>
          <?php foreach ([
              'current_password' => ['รหัสผ่านปัจจุบัน', 'current-password'],
              'new_password' => ['รหัสผ่านใหม่', 'new-password'],
              'new_password_confirmation' => ['ยืนยันรหัสผ่านใหม่', 'new-password'],
          ] as $field => [$label, $autocomplete]): ?>
            <div class="mb-3">
              <label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label>
              <div class="input-group">
                <input type="password" id="<?= e($field) ?>" name="<?= e($field) ?>" autocomplete="<?= e($autocomplete) ?>" required
                       class="form-control <?= error_for($field) ? 'is-invalid' : '' ?>">
                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1" data-target="<?= e($field) ?>">
                  <i class="bi bi-eye"></i>
                </button>
                <?php if ($msg = error_for($field)): ?>
                  <div class="invalid-feedback"><?= e($msg) ?></div>
                <?php endif; ?>
              </div>
              <?php if ($field !== 'current_password' && isset(errors()[$field])): ?>
                <?php foreach (errors()[$field] as $msg): ?>
                  <div class="text-danger small mt-1"><?= e($msg) ?></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <ul class="small text-muted ps-3 mb-3">
            <li>อย่างน้อย <?= e($minLength) ?> ตัวอักษร มีพิมพ์ใหญ่ พิมพ์เล็ก ตัวเลข และอักขระพิเศษ</li>
            <li>ห้ามใช้เลขประจำตัวประชาชน และห้ามซ้ำกับรหัสผ่านเดิม</li>
          </ul>
          <button type="submit" class="btn btn-primary w-100">บันทึกรหัสผ่านใหม่</button>
        </form>
      </div>
    </div>
  </div>
</div>