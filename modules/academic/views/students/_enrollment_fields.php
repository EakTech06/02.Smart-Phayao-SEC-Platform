<?php

use Modules\Academic\Services\StudentLookup;

/**
 * ช่องกรอกการรับบริการรายปี (ใช้ทั้งฟอร์มเพิ่มนักเรียนและฟอร์มการรับบริการ)
 * @var callable $field
 * @var callable $val
 * @var array $options years/units/rooms/teachers
 * @var bool $enrollRequired
 */
$enrollRequired ??= false;
$years = [];
foreach ($options['years'] as $y) {
    $years[(string) $y['id']] = $y['name'] . ((int) $y['is_current'] === 1 ? ' (ปัจจุบัน)' : '');
}
$serviceOptions = [];
foreach (StudentLookup::SERVICE_TYPES as $key => [$label, $group]) {
    $serviceOptions[$key] = StudentLookup::SERVICE_GROUPS[$group] . ': ' . $label;
}
$units = [];
foreach ($options['units'] as $u) {
    $units[(string) $u['id']] = $u['name_th'];
}
$teachers = [];
foreach ($options['teachers'] as $t) {
    $teachers[(string) $t['id']] = trim(($t['title_name'] ?? '') . $t['first_name'] . ' ' . $t['last_name']) . ($t['status'] !== 'active' ? ' (ไม่ได้ปฏิบัติงาน)' : '');
}
$selectedRoom = $val('enroll_service_room_id');
?>
<div class="row g-3" data-enrollment>
  <?= $field('enroll_academic_year_id', ['type' => 'select', 'options' => $years, 'required' => $enrollRequired, 'placeholder_option' => '-- เลือกปีการศึกษา --', 'attrs' => ' data-enroll-year']) ?>
  <?= $field('enroll_service_type', ['type' => 'select', 'options' => $serviceOptions, 'required' => $enrollRequired, 'placeholder_option' => '-- เลือกประเภท --', 'attrs' => ' data-service-type']) ?>
  <?= $field('enroll_department_id', ['type' => 'select', 'options' => $units, 'help' => 'จำเป็นเมื่อรับบริการที่หน่วยบริการ']) ?>
  <?php $roomError = error_for('enroll_service_room_id'); ?>
  <div class="col-12 col-md-6 col-lg-4">
    <label class="form-label" for="f-enroll_service_room_id"><?= e(\Modules\Academic\Services\StudentService::LABELS['service_room_id']) ?></label>
    <select id="f-enroll_service_room_id" name="enroll_service_room_id" class="form-select<?= $roomError ? ' is-invalid' : '' ?>" data-room-select>
      <option value="">-- ไม่ระบุ --</option>
      <?php foreach ($options['rooms'] as $r): ?>
        <option value="<?= (int) $r['id'] ?>" data-year="<?= (int) $r['academic_year_id'] ?>" data-grade="<?= e((string) ($r['grade_level'] ?? '')) ?>"<?= (string) $r['id'] === $selectedRoom ? ' selected' : '' ?>><?= e($r['room_code'] . ' ' . $r['name_th']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($roomError): ?><div class="invalid-feedback"><?= e($roomError) ?></div><?php endif; ?>
    <div class="form-text">แสดงเฉพาะห้องของปีการศึกษาที่เลือก</div>
  </div>
  <?= $field('enroll_grade_level', ['max' => 50, 'list' => 'grade-options']) ?>
  <?= $field('enroll_responsible_employee_id', ['type' => 'select', 'options' => $teachers]) ?>
  <?= $field('enroll_note', ['label' => 'หมายเหตุ', 'max' => 255, 'col' => 'col-12']) ?>
</div>
<datalist id="grade-options"><?php foreach (StudentLookup::GRADE_LEVELS as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
