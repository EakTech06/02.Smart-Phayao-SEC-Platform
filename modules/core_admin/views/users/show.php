<?php

use Modules\CoreAdmin\Services\UserService;



/** @var array $user */
/** @var bool $canManage */
/** @var string|null $tempPassword */
$id = (int) $user['id'];
$isActive = $user['status'] === 'active';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
  <a href="<?= e(url('/users')) ?>" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i> กลับ</a>
      <?php if (can('core.permissions.assign_all')): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url("/users/{$id}/permissions")) ?>"><i class="bi bi-shield-check me-1"></i>จัดการสิทธิ์</a>
      <?php endif; ?>
  <?php if ($canManage): ?>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-primary" href="<?= e(url("/users/{$id}/edit")) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</a>

      <form method="post" action="<?= e(url("/users/{$id}/reset-password")) ?>" data-confirm="รีเซ็ตรหัสผ่านและปลดล็อกบัญชีนี้?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-key me-1"></i>รีเซ็ตรหัสผ่าน</button>
      </form>

      <form method="post" action="<?= e(url("/users/{$id}/status")) ?>"
            data-confirm="<?= $isActive ? 'ระงับบัญชีนี้และบังคับออกจากระบบ?' : 'เปิดใช้งานบัญชีนี้?' ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
          <i class="bi bi-<?= $isActive ? 'pause-circle' : 'play-circle' ?> me-1"></i><?= $isActive ? 'ระงับ' : 'เปิดใช้งาน' ?>
        </button>
      </form>

      <?php if (can('core.users.delete_all')): ?>
        <form method="post" action="<?= e(url("/users/{$id}")) ?>" data-confirm="ลบบัญชี <?= e($user['username']) ?> ใช่หรือไม่?">
          <?= csrf_field() ?>
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>ลบ</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($tempPassword !== null): ?>
  <div class="alert alert-warning">
    <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>รหัสผ่านชั่วคราว (แสดงครั้งเดียว)</div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <code class="fs-5 user-select-all" id="temp-password"><?= e($tempPassword) ?></code>
      <button type="button" class="btn btn-sm btn-outline-dark" data-copy="#temp-password"><i class="bi bi-clipboard me-1"></i>คัดลอก</button>
    </div>
    <div class="small mt-2">แจ้งผู้ใช้ผ่านช่องทางที่ปลอดภัย ระบบจะบังคับให้เปลี่ยนรหัสผ่านเมื่อเข้าใช้ครั้งแรก</div>
  </div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <h1 class="h5 mb-3">
      <?= e(trim(($user['title_name'] ?? '') . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
      <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?> align-middle ms-1">
        <?= e(UserService::STATUSES[$user['status']] ?? $user['status']) ?>
      </span>
    </h1>
    <dl class="row mb-0">
      <dt class="col-sm-4 col-lg-3">รหัสบุคลากร</dt><dd class="col-sm-8 col-lg-9"><?= e($user['employee_code'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ชื่อผู้ใช้</dt><dd class="col-sm-8 col-lg-9"><?= e($user['username']) ?></dd>
      <dt class="col-sm-4 col-lg-3">อีเมล</dt><dd class="col-sm-8 col-lg-9"><?= e($user['email'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ตำแหน่ง</dt><dd class="col-sm-8 col-lg-9"><?= e($user['position'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ประเภทบุคลากร</dt>
      <dd class="col-sm-8 col-lg-9"><?= e(UserService::EMPLOYMENT_TYPES[$user['employment_type'] ?? ''] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ฝ่าย/งาน</dt><dd class="col-sm-8 col-lg-9"><?= e($user['department_name'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">บทบาท</dt><dd class="col-sm-8 col-lg-9"><?= e($user['role_names'] ?? '-') ?></dd>
      <dt class="col-sm-4 col-lg-3">ต้องเปลี่ยนรหัสผ่าน</dt>
      <dd class="col-sm-8 col-lg-9"><?= (int) $user['must_change_password'] === 1 ? 'ใช่' : 'ไม่' ?></dd>
      <dt class="col-sm-4 col-lg-3">เข้าใช้ล่าสุด</dt><dd class="col-sm-8 col-lg-9"><?= e($user['last_login_at'] ?? '-') ?></dd>
    </dl>
  </div>
</div>