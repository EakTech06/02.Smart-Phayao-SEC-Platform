<?php

use Core\Session;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\UserService;

/** @var array|null $employee */
/** @var array $departments */
/** @var bool $canChangeStatus */

$isEdit = $employee !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];

$val = static function (string $key) use ($employee, $hasOld): string {
    if ($hasOld) {
        return old($key);
    }
    $value = $employee[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
};

$field = static function (string $name, array $opt = []) use ($val): string {
    $label = PersonnelService::LABELS[$name] ?? $name;
    $error = error_for($name);
    $required = !empty($opt['required']);
    $col = $opt['col'] ?? 'col-12 col-md-6 col-lg-4';
    $attrs = ' id="f-' . e($name) . '" name="' . e($name) . '"'
        . ($required ? ' required' : '')
        . (isset($opt['max']) ? ' maxlength="' . (int) $opt['max'] . '"' : '')
        . (isset($opt['placeholder']) ? ' placeholder="' . e($opt['placeholder']) . '"' : '')
        . (isset($opt['list']) ? ' list="' . e($opt['list']) . '"' : '')
        . (isset($opt['inputmode']) ? ' inputmode="' . e($opt['inputmode']) . '"' : '')
        . (isset($opt['autocomplete']) ? ' autocomplete="' . e($opt['autocomplete']) . '"' : '');
    $value = array_key_exists('value', $opt) ? (string) $opt['value'] : $val($name);
    $class = ($opt['type'] ?? '') === 'select' ? 'form-select' : 'form-control';
    $class .= $error ? ' is-invalid' : '';

    if (($opt['type'] ?? '') === 'select') {
        $control = '<select' . $attrs . ' class="' . $class . '">';
        if (!$required) {
            $control .= '<option value="">-- ไม่ระบุ --</option>';
        }
        foreach ($opt['options'] as $key => $text) {
            $control .= '<option value="' . e($key) . '"' . ((string) $key === $value ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        $control .= '</select>';
    } elseif (($opt['type'] ?? '') === 'textarea') {
        $control = '<textarea' . $attrs . ' rows="2" class="' . $class . '">' . e($value) . '</textarea>';
    } else {
        $control = '<input' . $attrs . ' type="' . e($opt['type'] ?? 'text') . '" value="' . e($value) . '" class="' . $class . '">';
    }

    return '<div class="' . e($col) . '">'
        . '<label class="form-label" for="f-' . e($name) . '">' . e($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
        . $control
        . ($error ? '<div class="invalid-feedback">' . e($error) . '</div>' : '')
        . (isset($opt['help']) ? '<div class="form-text">' . e($opt['help']) . '</div>' : '')
        . '</div>';
};

$departmentOptions = [];
foreach ($departments as $d) {
    $departmentOptions[(string) $d['id']] = $d['name_th'];
}
$statusValue = $val('status') !== '' ? $val('status') : 'active';
$action = url($isEdit ? '/personnel/' . (int) $employee['id'] : '/personnel');
?>
<a href="<?= e(url($isEdit ? '/personnel/' . (int) $employee['id'] : '/personnel')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขข้อมูลบุคลากร' : 'เพิ่มบุคลากร' ?></h1>

<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-1"></i>ข้อมูลส่วนบุคคล</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $field('employee_code', ['required' => true, 'max' => 30, 'placeholder' => 'เช่น T-0101', 'help' => 'ใช้เป็นชื่อผู้ใช้เริ่มต้นเมื่อสร้างบัญชี (a-z 0-9 _ . -)']) ?>
        <?= $field('title_name', ['max' => 50, 'list' => 'title-options']) ?>
        <?= $field('first_name', ['required' => true, 'max' => 100, 'autocomplete' => 'off']) ?>
        <?= $field('last_name', ['required' => true, 'max' => 100, 'autocomplete' => 'off']) ?>
        <?= $field('nickname', ['max' => 50]) ?>
        <?= $field('gender', ['type' => 'select', 'options' => PersonnelService::GENDERS]) ?>
        <?= $field('birth_date', ['type' => 'date']) ?>
        <?= $field('national_id', [
            'value' => '',
            'inputmode' => 'numeric',
            'autocomplete' => 'off',
            'placeholder' => ($isEdit && !empty($employee['national_id_last4']))
                ? '*********' . $employee['national_id_last4'] . ' (เว้นว่างหากไม่เปลี่ยน)'
                : '13 หลัก',
            'help' => 'เข้ารหัส AES-256-GCM ก่อนบันทึก แสดงทั่วไปเฉพาะ 4 หลักท้าย',
        ]) ?>
        <?= $field('phone', ['type' => 'tel', 'max' => 30, 'inputmode' => 'tel']) ?>
        <?= $field('email', ['type' => 'email', 'max' => 191, 'help' => 'ซิงก์ไปยังบัญชีผู้ใช้ (ล็อกอินด้วยอีเมลได้)']) ?>
        <?= $field('address', ['type' => 'textarea', 'max' => 500, 'col' => 'col-12 col-lg-8']) ?>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-briefcase me-1"></i>ข้อมูลการปฏิบัติงาน</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $field('position', ['max' => 150, 'list' => 'position-options']) ?>
        <?= $field('position_number', ['max' => 30]) ?>
        <?= $field('academic_rank', ['max' => 100, 'list' => 'rank-options']) ?>
        <?= $field('employment_type', ['type' => 'select', 'required' => true, 'options' => UserService::EMPLOYMENT_TYPES,
            'value' => $val('employment_type') !== '' ? $val('employment_type') : 'civil_servant']) ?>
        <?= $field('department_id', ['type' => 'select', 'required' => true, 'options' => ['' => '-- เลือก --'] + $departmentOptions]) ?>
        <?= $field('education_level', ['max' => 100, 'list' => 'education-options']) ?>
        <?= $field('education_major', ['max' => 150]) ?>
        <?= $field('start_date', ['type' => 'date']) ?>
        <?= $field('end_date', ['type' => 'date']) ?>

        <?php if ($canChangeStatus): ?>
          <?= $field('status', ['type' => 'select', 'required' => true, 'options' => PersonnelService::STATUSES, 'value' => $statusValue,
              'help' => 'เปลี่ยนเป็นสถานะอื่นที่ไม่ใช่ "ปฏิบัติงาน" ระบบจะระงับบัญชีผู้ใช้ที่ผูกไว้อัตโนมัติ']) ?>
        <?php else: ?>
          <input type="hidden" name="status" value="<?= e($employee['status']) ?>">
          <div class="col-12 col-md-6 col-lg-4">
            <label class="form-label">สถานะ</label>
            <input type="text" class="form-control" value="<?= e(PersonnelService::STATUSES[$employee['status']] ?? $employee['status']) ?>" disabled>
            <div class="form-text">ไม่สามารถเปลี่ยนสถานะของบุคลากรที่มีบัญชีระดับสิทธิ์เท่ากันหรือสูงกว่าได้</div>
          </div>
        <?php endif; ?>

        <?= $field('note', ['type' => 'textarea', 'max' => 500, 'col' => 'col-12 col-lg-8']) ?>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= e(url($isEdit ? '/personnel/' . (int) $employee['id'] : '/personnel')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>

<datalist id="title-options">
  <option value="นาย"><option value="นาง"><option value="นางสาว"><option value="ว่าที่ร้อยตรี"><option value="ว่าที่ร้อยตรีหญิง"><option value="ดร.">
</datalist>
<datalist id="position-options">
  <option value="ผู้อำนวยการ"><option value="รองผู้อำนวยการ"><option value="ครู"><option value="ครูผู้ช่วย"><option value="พนักงานราชการ (ครู)">
  <option value="ครูอัตราจ้าง"><option value="พี่เลี้ยงเด็กพิการ"><option value="นักกายภาพบำบัด"><option value="เจ้าหน้าที่ธุรการ"><option value="นักการภารโรง">
</datalist>
<datalist id="rank-options">
  <option value="ครูชำนาญการ"><option value="ครูชำนาญการพิเศษ"><option value="ครูเชี่ยวชาญ"><option value="ครูเชี่ยวชาญพิเศษ">
  <option value="ผู้อำนวยการชำนาญการพิเศษ"><option value="รองผู้อำนวยการชำนาญการพิเศษ">
</datalist>
<datalist id="education-options">
  <option value="ปริญญาเอก"><option value="ปริญญาโท"><option value="ปริญญาตรี"><option value="อนุปริญญา/ปวส."><option value="มัธยมศึกษาตอนปลาย/ปวช."><option value="ต่ำกว่ามัธยมศึกษา">
</datalist>
