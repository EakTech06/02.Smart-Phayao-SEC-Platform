<?php

use Modules\CoreAdmin\Services\PersonnelService;

/** @var array $employees บุคลากรสถานะปฏิบัติงานที่ยังไม่มีบัญชี (ตาม Data Scope) */
/** @var int $selectedEmployeeId */
/** @var array $roles */
/** @var bool $isSuperAdmin */

$oldEmployee = (int) old('employee_id', (string) $selectedEmployeeId);
$oldRoles = Core\Session::getFlash('old', []);
$checkedRoles = is_array($oldRoles) && isset($oldRoles['roles']) ? array_map('intval', (array) $oldRoles['roles']) : null;
$errors = array_filter([
    error_for('employee_id'), error_for('account'), error_for('username'), error_for('roles'),
]);
?>
<a href="<?= e(url('/users')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">เพิ่มผู้ใช้งาน</h1>
  <?php if ($isSuperAdmin): ?>
    <a href="<?= e(url('/users/create?mode=manual')) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-pencil-square me-1"></i>กรอกข้อมูลเอง (เฉพาะ Super Admin)
    </a>
  <?php endif; ?>
</div>

<div class="alert alert-info small">
  <i class="bi bi-info-circle me-1"></i>บัญชีผู้ใช้ต้องสร้างจาก <strong>ข้อมูลบุคลากร</strong> เท่านั้น (1 บุคลากร = 1 บัญชี)
  หากไม่พบรายชื่อ ให้เพิ่มที่เมนู
  <?php if (can('core.employees.create_all')): ?>
    <a href="<?= e(url('/personnel/create')) ?>">ข้อมูลบุคลากร</a>
  <?php else: ?>
    "ข้อมูลบุคลากร"
  <?php endif; ?>
  ก่อน
</div>

<?php if ($errors !== []): ?>
  <div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<?php if ($employees === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-person-check fs-1 d-block mb-2"></i>
    ไม่มีบุคลากร (สถานะปฏิบัติงาน) ที่ยังไม่มีบัญชีผู้ใช้ในขอบเขตของคุณ
  </div></div>
<?php else: ?>
  <form method="post" action="<?= e(url('/users')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-1"></i>เลือกบุคลากร</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-lg-8">
            <label class="form-label" for="f-employee_id">บุคลากร <span class="text-danger">*</span></label>
            <select id="f-employee_id" name="employee_id" class="form-select <?= error_for('employee_id') ? 'is-invalid' : '' ?>" required>
              <option value="">-- เลือกบุคลากร (<?= count($employees) ?> คนที่ยังไม่มีบัญชี) --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>"
                        data-username="<?= e(mb_strtolower((string) $emp['employee_code'])) ?>"
                        data-email="<?= e($emp['email'] ?? '') ?>"
                        data-department="<?= e($emp['department_name'] ?? '') ?>"
                        data-position="<?= e($emp['position'] ?? '') ?>"
                        <?= $oldEmployee === (int) $emp['id'] ? 'selected' : '' ?>>
                  <?= e($emp['employee_code'] . ' — ' . PersonnelService::displayName($emp) . ($emp['department_name'] ? ' (' . $emp['department_name'] . ')' : '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label" for="f-username">ชื่อผู้ใช้</label>
            <input type="text" id="f-username" name="username" class="form-control <?= error_for('username') ? 'is-invalid' : '' ?>"
                   value="<?= e(old('username')) ?>" placeholder="ค่าเริ่มต้น = รหัสบุคลากร" pattern="[A-Za-z0-9_.\-]{3,100}" autocomplete="off">
          </div>
          <div class="col-12">
            <div id="employee-summary" class="small text-muted"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-check me-1"></i>บทบาท <span class="text-danger">*</span></div>
      <div class="card-body">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-2">
          <?php foreach ($roles as $role): ?>
            <?php $checked = $checkedRoles !== null ? in_array((int) $role['id'], $checkedRoles, true) : $role['code'] === 'teacher'; ?>
            <div class="col">
              <div class="form-check border rounded px-4 py-2 h-100">
                <input class="form-check-input" type="checkbox" name="roles[]" id="role-<?= (int) $role['id'] ?>" value="<?= (int) $role['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                <label class="form-check-label" for="role-<?= (int) $role['id'] ?>"><?= e($role['name_th']) ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
      <a href="<?= e(url('/users')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
      <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>สร้างบัญชีผู้ใช้</button>
    </div>
  </form>
  <script src="<?= e(asset('js/personnel.js')) ?>" defer></script>
<?php endif; ?>
