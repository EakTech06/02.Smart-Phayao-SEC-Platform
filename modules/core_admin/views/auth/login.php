<div class="text-center mb-4">
  <?= brand_mark('brand-mark-lg') ?>
  <h1 class="h4 mt-3 mb-1">เข้าสู่ระบบ</h1>
  <div class="fw-semibold"><?= e(app_name()) ?></div>
  <div class="text-body-secondary small"><?= e(Core\Setting::get('org.name', config('app.org'))) ?></div>
</div>
<form method="post" action="<?= e(url('/login')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="login">ชื่อผู้ใช้หรืออีเมล</label>
    <input type="text" id="login" name="login" value="<?= e(old('login')) ?>" autocomplete="username" required autofocus
           class="form-control form-control-lg <?= error_for('login') ? 'is-invalid' : '' ?>">
    <?php if ($msg = error_for('login')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif; ?>
  </div>
  <div class="mb-4">
    <label class="form-label" for="password">รหัสผ่าน</label>
    <div class="input-group">
      <input type="password" id="password" name="password" autocomplete="current-password" required
             class="form-control form-control-lg <?= error_for('password') ? 'is-invalid' : '' ?>">
      <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1" data-target="password">
        <i class="bi bi-eye"></i>
      </button>
      <?php if ($msg = error_for('password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif; ?>
    </div>
  </div>
  <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ</button>
</form>