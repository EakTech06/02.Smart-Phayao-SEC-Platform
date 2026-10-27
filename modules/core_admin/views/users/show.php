<?php /** @var array $user */ ?>
<a href="<?= e(url('/users')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<div class="card shadow-sm">
  <div class="card-body">
    <h1 class="h5 mb-3"><?= e(trim(($user['title_name'] ?? '') . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h1>
    <dl class="row mb-0">
      <dt class="col-sm-4 col-lg-3">รหัสบุคลากร</dt><dd class="col-sm-8 col-lg-9"><?= e($user['employee_code'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ชื่อผู้ใช้</dt><dd class="col-sm-8 col-lg-9"><?= e($user['username']) ?></dd>
      <dt class="col-sm-4 col-lg-3">อีเมล</dt><dd class="col-sm-8 col-lg-9"><?= e($user['email'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ตำแหน่ง</dt><dd class="col-sm-8 col-lg-9"><?= e($user['position'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ฝ่าย/งาน</dt><dd class="col-sm-8 col-lg-9"><?= e($user['department_name'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">บทบาท</dt><dd class="col-sm-8 col-lg-9"><?= e($user['role_names'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">เข้าใช้ล่าสุด</dt><dd class="col-sm-8 col-lg-9"><?= e($user['last_login_at'] ?? '-') ?></dd>
    </dl>
  </div>
</div>