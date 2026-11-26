<?php

use Core\Session;
use Modules\CoreAdmin\Services\DepartmentService;

/** @var array|null $department */
/** @var array $parents ตัวเลือกสังกัด */
/** @var int $nextSort */

$isEdit = $department !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];
$defaults = ['sort_order' => (string) $nextSort, 'type' => 'division', 'is_active' => '1'];
$val = static function (string $key) use ($department, $hasOld, $defaults): string {
    if ($hasOld) {
        return old($key);
    }
    $v = $department[$key] ?? ($defaults[$key] ?? '');

    return is_scalar($v) ? (string) $v : '';
};
$invalid = static fn (string $f): string => error_for($f) ? ' is-invalid' : '';
$feedback = static fn (string $f): string => error_for($f) ? '<div class="invalid-feedback">' . e(error_for($f)) . '</div>' : '';
$action = url($isEdit ? '/departments/' . (int) $department['id'] : '/departments');
$active = $hasOld ? old('is_active') === '1' : $val('is_active') === '1';
?>
<a href="<?= e(url('/departments')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขฝ่าย/งาน' : 'เพิ่มฝ่าย/งาน' ?></h1>

<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-md-2">
          <label class="form-label" for="f-sort_order">ลำดับที่ <span class="text-danger">*</span></label>
          <input id="f-sort_order" name="sort_order" type="number" min="0" max="65535" step="1" inputmode="numeric" required
                 class="form-control<?= $invalid('sort_order') ?>" value="<?= e($val('sort_order')) ?>">
          <?= $feedback('sort_order') ?>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="f-name_th">ชื่อฝ่าย/งาน <span class="text-danger">*</span></label>
          <input id="f-name_th" name="name_th" type="text" maxlength="191" required list="department-names"
                 class="form-control<?= $invalid('name_th') ?>" value="<?= e($val('name_th')) ?>" placeholder="เช่น ฝ่ายบริหารวิชาการ">
          <datalist id="department-names">
            <?php foreach (['สำนักงานผู้อำนวยการ', 'ฝ่ายบริหารวิชาการ', 'ฝ่ายบริหารงบประมาณ', 'ฝ่ายบริหารงานบุคคล', 'ฝ่ายบริหารทั่วไป', 'งานหน่วยบริการ'] as $n): ?>
              <option value="<?= e($n) ?>">
            <?php endforeach; ?>
          </datalist>
          <?= $feedback('name_th') ?>
        </div>
        <div class="col-6 col-md-4">
          <label class="form-label" for="f-code">รหัส <span class="text-danger">*</span></label>
          <input id="f-code" name="code" type="text" maxlength="50" required autocomplete="off"
                 class="form-control font-monospace text-uppercase<?= $invalid('code') ?>" value="<?= e($val('code')) ?>" placeholder="เช่น ACADEMIC">
          <?= $feedback('code') ?>
          <div class="form-text">A-Z ตัวเลข _ ใช้อ้างอิงตอนนำเข้า CSV บุคลากร</div>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="f-type">ประเภท <span class="text-danger">*</span></label>
          <select id="f-type" name="type" class="form-select<?= $invalid('type') ?>" required>
            <?php foreach (DepartmentService::TYPES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= $val('type') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $feedback('type') ?>
          <div class="form-text">หน่วยบริการรายอำเภอ: เลือก "หน่วยบริการ/หน่วยย่อย" และสังกัด "งานหน่วยบริการ"</div>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="f-parent_id">สังกัด</label>
          <select id="f-parent_id" name="parent_id" class="form-select<?= $invalid('parent_id') ?>">
            <option value="">-- ไม่มี (ระดับบนสุด) --</option>
            <?php foreach ($parents as $p): ?>
              <option value="<?= (int) $p['id'] ?>"<?= $val('parent_id') === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['name_th']) ?><?= (int) $p['is_active'] === 1 ? '' : ' (ปิดใช้งาน)' ?></option>
            <?php endforeach; ?>
          </select>
          <?= $feedback('parent_id') ?>
        </div>
        <div class="col-12 col-md-4">
          <div class="form-label">สถานะการใช้งาน</div>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="f-is_active"<?= $active ? ' checked' : '' ?>>
            <label class="form-check-label" for="f-is_active">เปิดใช้งาน</label>
          </div>
          <div class="form-text">ปิดแล้วจะไม่แสดงในตัวเลือกฝ่าย/งาน</div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= e(url('/departments')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>
