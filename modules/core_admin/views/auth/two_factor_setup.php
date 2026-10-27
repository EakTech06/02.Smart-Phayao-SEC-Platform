<?php /** @var string $secret */ /** @var string $uri */ ?>
<h1 class="h5 text-center mb-3"><i class="bi bi-shield-check me-1"></i>ตั้งค่าการยืนยันตัวตน 2 ขั้นตอน</h1>
<div class="alert alert-info small">บัญชีนี้มีสิทธิ์สูงจึงต้องเปิดใช้ 2FA ก่อนเข้าใช้งาน</div>
<ol class="small ps-3">
  <li>ติดตั้งแอป Google Authenticator หรือ Microsoft Authenticator</li>
  <li>สแกน QR Code ด้านล่าง (หรือกรอกรหัสลับด้วยตนเอง)</li>
  <li>กรอกรหัส 6 หลักที่แอปแสดง</li>
</ol>
<div class="d-flex justify-content-center my-3"><div id="qrcode" class="p-2 bg-white border rounded" data-uri="<?= e($uri) ?>"></div></div>
<p class="text-center small mb-1">รหัสลับ (กรอกด้วยตนเอง)</p>
<p class="text-center"><code class="fs-6 user-select-all"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
<form method="post" action="<?= e(url('/2fa/setup')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required
         class="form-control form-control-lg text-center fs-3 mb-2 <?= error_for('code') ? 'is-invalid' : '' ?>" placeholder="000000">
  <?php if ($msg = error_for('code')): ?><div class="invalid-feedback d-block mb-2"><?= e($msg) ?></div><?php endif; ?>
  <button type="submit" class="btn btn-primary btn-lg w-100">ยืนยันและเปิดใช้งาน</button>
</form>