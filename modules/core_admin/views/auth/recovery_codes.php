<?php /** @var list<string> $codes */ ?>
<h1 class="h5 text-center mb-3"><i class="bi bi-key me-1"></i>รหัสกู้คืน (แสดงครั้งเดียว)</h1>
<div class="alert alert-warning small">
  เก็บรหัสเหล่านี้ไว้ในที่ปลอดภัย ใช้แทนรหัส 6 หลักได้รหัสละ 1 ครั้งเมื่อไม่มีโทรศัพท์
</div>
<div class="row row-cols-2 g-2 recovery-codes mb-3" id="recovery-codes">
  <?php foreach ($codes as $code): ?>
    <div class="col"><code class="d-block text-center border rounded py-2"><?= e($code) ?></code></div>
  <?php endforeach; ?>
</div>
<button type="button" class="btn btn-outline-secondary w-100 mb-2" id="copy-codes"><i class="bi bi-clipboard me-1"></i>คัดลอกทั้งหมด</button>
<a href="<?= e(url('/dashboard')) ?>" class="btn btn-primary w-100">บันทึกแล้ว ดำเนินการต่อ</a>