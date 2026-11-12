<?php

use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\UserService;

/** @var array $employee */
/** @var string|null $nationalId */
/** @var bool $canUpdate */
/** @var bool $canDelete */
/** @var bool $canReveal */
/** @var bool $canSync */
/** @var bool $canLink */
/** @var array $roles */
/** @var array $unlinkedUsers */
/** @var string $defaultUsername */

$id = (int) $employee['id'];
$name = PersonnelService::displayName($employee);
$statusClass = ['active' => 'success', 'inactive' => 'secondary', 'retired' => 'info', 'resigned' => 'dark'];
$thaiDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '-';
    }
    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    [$y, $m, $d] = array_map('intval', explode('-', substr($date, 0, 10)));

    return $d . ' ' . $months[$m] . ' ' . ($y + 543);
};
$row = static function (string $label, ?string $value): string {
    return '<dt class="col-sm-4 col-lg-3 text-muted fw-normal">' . e($label) . '</dt>'
        . '<dd class="col-sm-8 col-lg-9">' . e($value !== null && $value !== '' ? $value : '-') . '</dd>';
};
$accountErrors = array_filter([error_for('account'), error_for('username'), error_for('roles'), error_for('user_id')]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
  <a href="<?= e(url('/personnel')) ?>" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i> กลับ</a>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canUpdate): ?>
      <a class="btn btn-sm btn-primary" href="<?= e(url("/personnel/{$id}/edit")) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</a>
    <?php endif; ?>
    <?php if ($canDelete): ?>
      <form method="post" action="<?= e(url("/personnel/{$id}")) ?>"
            data-confirm="ลบข้อมูลบุคลากร <?= e($name) ?>?<?= $employee['user_id'] !== null ? ' (บัญชีผู้ใช้จะถูกระงับ)' : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>ลบ</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body d-flex flex-wrap align-items-center gap-3">
    <?php if (!empty($employee['avatar'])): ?>
      <img src="<?= e(url('uploads/avatars/' . basename((string) $employee['avatar']))) ?>" alt="" class="rounded-circle shadow-sm" width="72" height="72" style="object-fit:cover">
    <?php else: ?>
      <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-3" style="width:72px;height:72px">
        <?= e(mb_substr((string) $employee['first_name'], 0, 1)) ?>
      </div>
    <?php endif; ?>
    <div class="min-w-0">
      <h1 class="h5 mb-1"><?= e($name) ?>
        <?php if (!empty($employee['nickname'])): ?><span class="text-muted fw-normal">(<?= e($employee['nickname']) ?>)</span><?php endif; ?>
      </h1>
      <div class="text-muted small"><?= e($employee['position'] ?? '-') ?> · <?= e($employee['department_name'] ?? '-') ?></div>
      <span class="badge text-bg-<?= e($statusClass[$employee['status']] ?? 'secondary') ?> mt-1"><?= e(PersonnelService::STATUSES[$employee['status']] ?? $employee['status']) ?></span>
      <span class="badge text-bg-light border mt-1 font-monospace"><?= e($employee['employee_code']) ?></span>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-1"></i>ข้อมูลส่วนบุคคล</div>
      <div class="card-body">
        <dl class="row mb-0">
          <?= $row('ชื่อ-สกุล', $name) ?>
          <?= $row('เพศ', PersonnelService::GENDERS[$employee['gender'] ?? ''] ?? null) ?>
          <?= $row('วันเกิด', $thaiDate($employee['birth_date'])) ?>
          <dt class="col-sm-4 col-lg-3 text-muted fw-normal">เลขประจำตัวประชาชน</dt>
          <dd class="col-sm-8 col-lg-9">
            <?php if ($nationalId !== null): ?>
              <code class="fs-6"><?= e($nationalId) ?></code>
              <span class="badge text-bg-warning ms-1"><i class="bi bi-eye"></i> บันทึกการเปิดดูแล้ว</span>
            <?php elseif (!empty($employee['national_id_last4'])): ?>
              <span class="font-monospace">*********<?= e($employee['national_id_last4']) ?></span>
              <?php if ($canReveal): ?>
                <form method="post" action="<?= e(url("/personnel/{$id}/national-id")) ?>" class="d-inline"
                      data-confirm="เปิดดูเลขประจำตัวประชาชนเต็ม? การเปิดดูจะถูกบันทึกใน Audit Log">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-link p-0 ms-2 align-baseline"><i class="bi bi-eye"></i> แสดงเต็ม</button>
                </form>
              <?php endif; ?>
            <?php else: ?>-<?php endif; ?>
          </dd>
          <?= $row('เบอร์โทรศัพท์', $employee['phone']) ?>
          <?= $row('อีเมล', $employee['email']) ?>
          <?= $row('ที่อยู่', $employee['address']) ?>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-briefcase me-1"></i>ข้อมูลการปฏิบัติงาน</div>
      <div class="card-body">
        <dl class="row mb-0">
          <?= $row('ตำแหน่ง', $employee['position']) ?>
          <?= $row('เลขที่ตำแหน่ง', $employee['position_number']) ?>
          <?= $row('วิทยฐานะ', $employee['academic_rank']) ?>
          <?= $row('ประเภทบุคลากร', UserService::EMPLOYMENT_TYPES[$employee['employment_type']] ?? null) ?>
          <?= $row('ฝ่าย/งาน', $employee['department_name']) ?>
          <?= $row('วุฒิการศึกษา', trim(($employee['education_level'] ?? '') . ' ' . ($employee['education_major'] ?? ''))) ?>
          <?= $row('วันที่เริ่มปฏิบัติงาน', $thaiDate($employee['start_date'])) ?>
          <?= $row('วันที่สิ้นสุด', $thaiDate($employee['end_date'])) ?>
          <?= $row('หมายเหตุ', $employee['note']) ?>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card shadow-sm mb-3 <?= $employee['user_id'] === null ? 'border-warning' : '' ?>">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>บัญชีผู้ใช้ (1:1)</div>
      <div class="card-body">
        <?php if ($accountErrors !== []): ?>
          <div class="alert alert-danger small py-2"><?= implode('<br>', array_map('e', $accountErrors)) ?></div>
        <?php endif; ?>

        <?php if ($employee['user_id'] !== null): ?>
          <dl class="row small mb-2">
            <dt class="col-5 text-muted fw-normal">ชื่อผู้ใช้</dt><dd class="col-7 font-monospace"><?= e($employee['username']) ?></dd>
            <dt class="col-5 text-muted fw-normal">สถานะบัญชี</dt>
            <dd class="col-7"><span class="badge text-bg-<?= $employee['user_status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(UserService::STATUSES[$employee['user_status']] ?? $employee['user_status']) ?></span></dd>
            <dt class="col-5 text-muted fw-normal">เข้าใช้ล่าสุด</dt><dd class="col-7"><?= e($employee['last_login_at'] ?? '-') ?></dd>
          </dl>
          <?php if (Core\Rbac::scope('core.users', 'read') !== null): ?>
            <a href="<?= e(url('/users/' . (int) $employee['user_id'])) ?>" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-box-arrow-up-right me-1"></i>จัดการบัญชีผู้ใช้</a>
          <?php endif; ?>
          <?php if ($employee['status'] !== 'active' && $employee['user_status'] === 'active'): ?>
            <div class="alert alert-warning small mt-2 mb-0">บุคลากรพ้นสภาพแต่บัญชียังเปิดใช้งาน</div>
          <?php endif; ?>
        <?php else: ?>
          <p class="small text-muted">บุคลากรรายนี้ยังไม่มีบัญชีผู้ใช้</p>

          <?php if ($canSync): ?>
            <form method="post" action="<?= e(url("/personnel/{$id}/account")) ?>" class="mb-3">
              <?= csrf_field() ?>
              <label class="form-label small mb-1" for="acc-username">ชื่อผู้ใช้</label>
              <input type="text" id="acc-username" name="username" class="form-control mb-2" value="<?= e(old('username', $defaultUsername)) ?>" pattern="[A-Za-z0-9_.\-]{3,100}" required>
              <div class="form-text mt-n1 mb-2">ค่าเริ่มต้น = รหัสบุคลากร · อีเมลล็อกอิน = <?= e($employee['email'] ?? 'ไม่มี') ?></div>
              <div class="small mb-1">บทบาท</div>
              <div class="vstack gap-1 mb-2">
                <?php foreach ($roles as $role): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>" id="acc-role-<?= (int) $role['id'] ?>" <?= $role['code'] === 'teacher' ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="acc-role-<?= (int) $role['id'] ?>"><?= e($role['name_th']) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="submit" class="btn btn-warning w-100"><i class="bi bi-person-plus-fill me-1"></i>สร้างบัญชีผู้ใช้</button>
            </form>
          <?php elseif ($employee['status'] !== 'active'): ?>
            <p class="small text-muted mb-2">สร้างบัญชีได้เฉพาะบุคลากรที่สถานะ "ปฏิบัติงาน"</p>
          <?php endif; ?>

          <?php if ($canLink && $unlinkedUsers !== []): ?>
            <form method="post" action="<?= e(url("/personnel/{$id}/account/link")) ?>" class="border-top pt-3"
                  data-confirm="ผูกบัญชีที่เลือกเข้ากับบุคลากรรายนี้?">
              <?= csrf_field() ?>
              <label class="form-label small mb-1" for="link-user">หรือผูกบัญชีเดิมที่ยังไม่มีบุคลากร</label>
              <div class="input-group">
                <select id="link-user" name="user_id" class="form-select" required>
                  <option value="">-- เลือกบัญชี --</option>
                  <?php foreach ($unlinkedUsers as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['username']) ?><?= $u['email'] ? ' (' . e($u['email']) . ')' : '' ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-secondary">ผูก</button>
              </div>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body small text-muted">
        <div>สร้างเมื่อ: <?= e($employee['created_at']) ?></div>
        <div>แก้ไขล่าสุด: <?= e($employee['updated_at'] ?? '-') ?></div>
      </div>
    </div>
  </div>
</div>
