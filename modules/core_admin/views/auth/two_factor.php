<div class="text-center mb-4">
  <i class="bi bi-shield-lock text-primary display-5"></i>
  <h1 class="h4 mt-2">ยืนยันตัวตน 2 ขั้นตอน</h1>
  <p class="text-muted small">กรอกรหัส 6 หลักจากแอป Authenticator หรือรหัสกู้คืน (XXXX-XXXX)</p>
</div>
<form method="post" action="<?= e(url('/2fa')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3">
    <input type="text" name="code" inputmode="text" autocomplete="one-time-code" maxlength="20" required autofocus
           class="form-control form-control-lg text-center fs-3 <?= error_for('code') ? 'is-invalid' : '' ?>" placeholder="000000">
    <?php if ($msg = error_for('code')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary btn-lg w-100">ยืนยัน</button>
</form>
<div class="text-center mt-3"><a href="<?= e(url('/login')) ?>" class="small">กลับไปหน้าเข้าสู่ระบบ</a></div>