<?php
/** @var array $user */
?>
<h1 class="h4 mb-3">โปรไฟล์ของฉัน</h1>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">ข้อมูลส่วนตัว</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">ชื่อผู้ใช้</dt><dd class="col-sm-8"><?= e($user['username']) ?></dd>
          <dt class="col-sm-4">ชื่อ-นามสกุล</dt><dd class="col-sm-8"><?= e(trim(($user['title_name'] ?? '') . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></dd>
          <dt class="col-sm-4">อีเมล</dt><dd class="col-sm-8"><?= e($user['email'] ?? '-') ?></dd>
          <dt class="col-sm-4">ฝ่าย/งาน</dt><dd class="col-sm-8"><?= e($user['department_name'] ?? '-') ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">เปลี่ยนรหัสผ่าน</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/profile/password')) ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="current_password">รหัสผ่านปัจจุบัน</label>
            <input type="password" name="current_password" id="current_password" class="form-control <?= error_for('current_password') ? 'is-invalid' : '' ?>" required>
            <?php if ($msg = error_for('current_password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="new_password">รหัสผ่านใหม่</label>
            <input type="password" name="new_password" id="new_password" class="form-control <?= error_for('new_password') ? 'is-invalid' : '' ?>" required>
            <?php if ($msg = error_for('new_password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif; ?>
            <div class="form-text">ต้องมีตัวพิมพ์เล็ก พิมพ์ใหญ่ ตัวเลข และอักขระพิเศษ อย่างน้อย 8 ตัว</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="new_password_confirmation">ยืนยันรหัสผ่านใหม่</label>
            <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i>เปลี่ยนรหัสผ่าน</button>
        </form>
      </div>
    </div>
  </div>
</div>