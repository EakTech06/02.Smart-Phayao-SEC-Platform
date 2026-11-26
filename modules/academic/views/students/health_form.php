<?php

use Core\Session;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;

/** @var array $student */
/** @var array $health ข้อมูลที่ถอดรหัสแล้ว */
/** @var array $teachers */

$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];
$labels = StudentService::SENSITIVE_LABELS;
$val = static function (string $key) use ($hasOld, $health): string {
    if ($hasOld) {
        return old($key);
    }
    $v = $health[$key] ?? '';

    return is_scalar($v) ? (string) $v : '';
};
$listVal = static function (string $key) use ($hasOld, $oldInput, $health): array {
    $v = $hasOld ? ($oldInput[$key] ?? []) : ($health[$key] ?? []);

    return is_array($v) ? array_map('strval', $v) : [];
};
$field = require __DIR__ . '/_field.php';

$levels = [];
foreach (StudentLookup::DISABILITY_LEVELS as $k => [$label, $type]) {
    $levels[$k] = $label;
}
$teacherOptions = [];
foreach ($teachers as $t) {
    $teacherOptions[(string) $t['id']] = trim(($t['title_name'] ?? '') . $t['first_name'] . ' ' . $t['last_name'])
        . (!empty($t['screener_reg_no']) ? ' (เลขทะเบียน ' . $t['screener_reg_no'] . ')' : '');
}
$screener = $val('screener_employee_id');
if ($screener !== '' && !isset($teacherOptions[$screener])) {
    $teacherOptions[$screener] = 'บุคลากรที่ไม่ได้ปฏิบัติงานแล้ว (#' . $screener . ')';
}
$type = $val('disability_type');
$multiple = $listVal('multiple_disabilities');
$cards = $listVal('card_types');
// ช่องการคัดกรองแสดงตามประเภทผู้คัดกรอง (academic-students.js สลับเมื่อเปลี่ยนตัวเลือก)
$screeningType = $val('screening_type');
$sc = static fn (string $kind, string $col = 'col-12 col-md-6 col-lg-4'): string
    => $col . ' screening-field screening-' . $kind . ($screeningType === $kind ? '' : ' d-none');
$cardStatus = $screeningType === 'disability_card'
    ? \Modules\Academic\Services\StudentCompleteness::cardStatus(['card_expires_at' => $val('card_expires_at'), 'screening_type' => $screeningType])
    : null;
$back = url('/academic/students/' . (int) $student['id']);
?>
<a href="<?= e($back) ?>#health" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-1">ข้อมูลความพิการและสุขภาพ</h1>
<p class="text-muted mb-3"><?= e($student['student_code'] . ' ' . StudentService::displayName($student)) ?></p>
<?php if (errors() !== []): ?>
  <!-- ฟอร์มนี้ไม่เก็บค่าที่กรอกค้างไว้ใน Session (ข้อมูลอ่อนไหว) จึงสรุปข้อผิดพลาดทั้งหมดไว้ด้านบน -->
  <div class="alert alert-danger">
    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-circle me-1"></i>บันทึกไม่สำเร็จ กรุณากรอกข้อมูลใหม่อีกครั้ง</div>
    <ul class="mb-0 small">
      <?php foreach (errors() as $key => $messages): ?>
        <li><?= e(($labels[$key] ?? $key) . ': ' . implode(', ', (array) $messages)) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<div class="alert alert-info small"><i class="bi bi-shield-lock me-1"></i>ข้อมูลส่วนนี้เป็นข้อมูลอ่อนไหวตาม PDPA มาตรา 26 ระบบเข้ารหัสก่อนบันทึก และบันทึกประวัติทุกครั้งที่มีการเปิดดูหรือแก้ไข</div>

<form method="post" action="<?= e($back . '/health') ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_method" value="PUT">

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-universal-access me-1"></i>ความพิการ</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $field('disability_type', ['type' => 'select', 'options' => StudentLookup::DISABILITY_TYPES, 'col' => 'col-12 col-lg-6', 'attrs' => ' data-disability-type']) ?>
        <?= $field('disability_level', ['type' => 'select', 'options' => $levels, 'col' => 'col-12 col-md-6 col-lg-3', 'help' => 'เฉพาะการเห็น/การได้ยิน', 'attrs' => ' data-disability-level']) ?>
        <?= $field('iq', ['type' => 'number', 'col' => 'col-6 col-lg-3', 'attrs' => ' min="0" max="250"']) ?>
        <div class="col-12 multiple-fields<?= $type === 'multiple' || error_for('multiple_disabilities') ? '' : ' d-none' ?>">
          <div class="form-label"><?= e($labels['multiple_disabilities']) ?> <span class="text-danger">*</span></div>
          <div class="d-flex flex-wrap gap-3<?= error_for('multiple_disabilities') ? ' is-invalid' : '' ?>">
            <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
              <?php if ($k === 'multiple') { continue; } ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="multiple_disabilities[]" value="<?= e($k) ?>" id="md-<?= e($k) ?>"<?= in_array($k, $multiple, true) ? ' checked' : '' ?>>
                <label class="form-check-label" for="md-<?= e($k) ?>"><?= e(str_replace('บกพร่องทาง', '', $label)) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (error_for('multiple_disabilities')): ?><div class="invalid-feedback d-block"><?= e(error_for('multiple_disabilities')) ?></div><?php endif; ?>
        </div>
        <?= $field('disability_description', ['type' => 'textarea', 'max' => 1000, 'col' => 'col-12', 'rows' => 3]) ?>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard2-pulse me-1"></i>การคัดกรองและบัตรประจำตัวคนพิการ</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $field('screening_type', ['type' => 'select', 'options' => StudentLookup::SCREENING_TYPES, 'col' => 'col-12 col-lg-6', 'attrs' => ' data-screening-type']) ?>
        <div class="col-12 col-lg-6 d-none d-lg-block"></div>
        <?php if ($screeningType === ''): ?>
          <div class="col-12 small text-muted screening-hint"><i class="bi bi-info-circle me-1"></i>เลือกประเภทผู้คัดกรองเพื่อกรอกข้อมูลการคัดกรอง</div>
        <?php endif; ?>
        <?= $field('screener_employee_id', ['type' => 'select', 'options' => $teacherOptions, 'col' => $sc('none', 'col-12 col-lg-6'),
            'help' => $teacherOptions === [] ? 'ยังไม่มีบุคลากรที่มีเลขทะเบียนผู้คัดกรองคนพิการ (กรอกได้ที่ข้อมูลบุคลากร)' : 'แสดงเฉพาะบุคลากรที่มีเลขทะเบียนผู้คัดกรองคนพิการ']) ?>
        <?= $field('hospital', ['max' => 150, 'col' => $sc('medical_cert')]) ?>
        <?= $field('doctor', ['max' => 150, 'col' => $sc('medical_cert')]) ?>
        <?= $field('examined_at', ['type' => 'date', 'col' => $sc('medical_cert')]) ?>
        <div class="<?= e($sc('disability_card', 'col-12')) ?>">
          <div class="form-label"><?= e($labels['card_types']) ?></div>
          <div class="row row-cols-1 row-cols-md-2 g-1">
            <?php foreach (StudentLookup::CARD_TYPES as $k => $label): ?>
              <div class="col"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="card_types[]" value="<?= e($k) ?>" id="ct-<?= e($k) ?>"<?= in_array($k, $cards, true) ? ' checked' : '' ?>>
                <label class="form-check-label" for="ct-<?= e($k) ?>"><?= e($label) ?></label>
              </div></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?= $field('card_issued_at', ['type' => 'date', 'col' => $sc('disability_card', 'col-6 col-md-4')]) ?>
        <?= $field('card_expires_at', ['type' => 'date', 'col' => $sc('disability_card', 'col-6 col-md-4')]) ?>
        <div class="<?= e($sc('disability_card', 'col-12 col-md-4 d-flex align-items-md-end')) ?>">
          <div class="small fw-semibold pb-md-2 <?= e($cardStatus['class'] ?? '') ?>" data-card-status data-warn-days="<?= StudentLookup::CARD_EXPIRY_WARN_DAYS ?>"><?= $cardStatus !== null ? e($cardStatus['text']) : '' ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-heart-pulse me-1"></i>สุขภาพ</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $field('blood_group', ['type' => 'select', 'options' => StudentLookup::BLOOD_GROUPS, 'col' => 'col-6 col-md-3']) ?>
        <?= $field('chronic_conditions', ['type' => 'textarea', 'max' => 500, 'col' => 'col-12 col-md-9']) ?>
        <?= $field('allergies', ['type' => 'textarea', 'max' => 500, 'col' => 'col-12 col-md-6']) ?>
        <?= $field('medications', ['type' => 'textarea', 'max' => 500, 'col' => 'col-12 col-md-6']) ?>
        <?= $field('health_note', ['type' => 'textarea', 'max' => 1000, 'col' => 'col-12', 'rows' => 3]) ?>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end gap-2 mb-4">
    <a href="<?= e($back) ?>#health" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i>บันทึก (เข้ารหัส)</button>
  </div>
</form>
<script src="<?= e(asset('js/academic-students.js')) ?>" defer></script>
