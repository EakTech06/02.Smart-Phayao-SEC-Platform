<?php

use Core\Session;
use Modules\CoreAdmin\Services\UserService;

/** @var array|null $user */
/** @var array $roles */
/** @var array $departments */
/** @var list<int> $selectedRoles */

$isEdit = $user !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];

$val = static function (string $key) use ($user, $hasOld): string {
    if ($hasOld) {
        return old($key);
    }
    $value = $user[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
};

$checkedRoles = $hasOld ? array_map('intval', (array) ($oldInput['roles'] ?? [])) : $selectedRoles;

$input = static function (string $name, string $label, array $opt = []) use ($val): string {
    $error = error_for($name);
    $required = !empty($opt['required']);

    return '<div class="col-12 col-md-6">'
        . '<label class="form-label" for="f-' . e($name) . '">' . e($label)
        . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
        . '<input id="f-' . e($name) . '" name="' . e($name) . '" type="' . e($opt['type'] ?? 'text') . '"'
        . ' value="' . e($opt['value'] ?? $val($name)) . '"'
        . (isset($opt['placeholder']) ? ' placeholder="' . e($opt['placeholder']) . '"' : '')
        . (isset($opt['list']) ? ' list="' . e($opt['list']) . '"' : '')
        . ($required ? ' required' : '')
        . ' class="form-control' . ($error ? ' is-invalid' : '') . '">'
        . ($error ? '<div class="invalid-feedback">' . e($error) . '</div>' : '')
        . '</div>';
};

$currentEmployment = $val('employment_type') !== '' ? $val('employment_type') : 'civil_servant';
$currentDepartment = $val('department_id');
$currentStatus = $hasOld ? old('status', 'active') : (($user['status'] ?? 'active') === 'active' ? 'active' : 'inactive');
?>
<a href="<?= e(url($isEdit ? '/users/' . (int) $user['id'] : '/users')) ?>" class="btn btn-link px-0 mb-2">
  <i class="bi bi-arrow-left"></i> กลับ
</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน' ?></h1>

<form method="post" action="<?= e(url($isEdit ? '/users/' . (int) $user['id'] : '/users')) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>ข้อมูลบัญชี</div>
    <div class="card-body">
      <div class="row g-3">
        <?php if ($isEdit): ?>
          <div class="col-12 col-md-6">
            <label class="form-label">ชื่อผู้ใช้</label>
            <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
          </div>
        <?php else: ?>
          <?= $input('username', 'ชื่อผู้ใช้', ['required' => true, 'placeholder' => 'a-z 0-9 _ . -']) ?>
        <?php endif; ?>

        <?= $input('email', 'อีเมล', ['type' => 'email']) ?>

        <?php if ($isEdit): ?>
          <div class="col-12 col-md-6">
            <label class="form-label" for="f-status">สถานะ <span class="text-danger">*</span></label>
            <select id="f-status" name="status" class="form-select">
              <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>ใช้งาน</option>
              <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>ระงับ</option>
            </select>
          </div>
        <?php endif; ?>

        <div class="col-12">
          <label class="form-label d-block">บทบาท <span class="text-danger">*</span></label>
          <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-2">
            <?php foreach ($roles as $role): ?>
              <div class="col">
                <div class="form-check border rounded px-4 py-2 h-100">
                  <input class="form-check-input" type="checkbox" name="roles[]" id="role-<?= (int) $role['id'] ?>"
                         value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $checkedRoles, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="role-<?= (int) $role['id'] ?>"><?= e($role['name_th']) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($msg = error_for('roles')): ?><div class="text-danger small mt-1"><?= e($msg) ?></div><?php endif; ?>
          <?php if ($isEdit): ?>
            <div class="form-text">บทบาทที่คุณไม่มีสิทธิ์กำหนดจะคงไว้ตามเดิม</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-1"></i>ข้อมูลบุคลากร</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $input('employee_code', 'รหัสบุคลากร', ['required' => true]) ?>
        <?= $input('title_name', 'คำนำหน้า', ['list' => 'title-options']) ?>
        <?= $input('first_name', 'ชื่อ', ['required' => true]) ?>
        <?= $input('last_name', 'นามสกุล', ['required' => true]) ?>
        <?= $input('nickname', 'ชื่อเล่น') ?>
        <?= $input('position', 'ตำแหน่ง') ?>
        <?= $input('academic_rank', 'วิทยฐานะ') ?>

        <div class="col-12 col-md-6">
          <label class="form-label" for="f-employment_type">ประเภทบุคลากร <span class="text-danger">*</span></label>
          <select id="f-employment_type" name="employment_type" class="form-select <?= error_for('employment_type') ? 'is-invalid' : '' ?>">
            <?php foreach (UserService::EMPLOYMENT_TYPES as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $currentEmployment === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label" for="f-department_id">ฝ่าย/งาน <span class="text-danger">*</span></label>
          <select id="f-department_id" name="department_id" class="form-select <?= error_for('department_id') ? 'is-invalid' : '' ?>">
            <option value="">-- เลือก --</option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= (int) $dept['id'] ?>" <?= $currentDepartment === (string) $dept['id'] ? 'selected' : '' ?>><?= e($dept['name_th']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($msg = error_for('department_id')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif; ?>
        </div>

        <?= $input('phone', 'เบอร์โทรศัพท์', ['type' => 'tel']) ?>
        <?= $input('national_id', 'เลขประจำตัวประชาชน', [
            'value' => '',
            'placeholder' => ($isEdit && !empty($user['national_id_last4']))
                ? '*********' . $user['national_id_last4'] . ' (เว้นว่างหากไม่เปลี่ยน)'
                : '13 หลัก',
        ]) ?>
        <?= $input('start_date', 'วันที่เริ่มปฏิบัติงาน', ['type' => 'date']) ?>
      </div>
      <div class="form-text mt-2"><i class="bi bi-shield-check"></i> ระบบไม่เก็บเลขประจำตัวประชาชนเต็ม เก็บเฉพาะค่าเข้ารหัสและ 4 หลักท้าย</div>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= e(url('/users')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>

<datalist id="title-options">
  <option value="นาย"><option value="นาง"><option value="นางสาว"><option value="ว่าที่ร้อยตรี"><option value="ว่าที่ร้อยตรีหญิง">
</datalist>