<?php

use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;

/**
 * ช่องกรอกการรับบริการรายปี (ใช้ทั้งฟอร์มเพิ่มนักเรียนและฟอร์มการรับบริการ)
 * - ประเภทการรับบริการเลือก 2 ขั้น: กลุ่ม (ที่ศูนย์/นอกศูนย์) → ประเภท
 * - หน่วยบริการแสดงเมื่อเลือก "ที่หน่วยบริการ"
 * - เลือกห้องบริการ → ครูผู้รับผิดชอบเหลือเฉพาะครูประจำชั้นของห้อง (ห้องที่ไม่มีครู = ว่าง) และดึงระดับชั้นของห้อง
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
$units = [];
foreach ($options['units'] as $u) {
    $units[(string) $u['id']] = $u['name_th'];
}
$teachers = [];
foreach ($options['teachers'] as $t) {
    $teachers[(string) $t['id']] = trim(($t['title_name'] ?? '') . $t['first_name'] . ' ' . $t['last_name']) . ($t['status'] !== 'active' ? ' (ไม่ได้ปฏิบัติงาน)' : '');
}
$selectedRoom = $val('enroll_service_room_id');
$selectedType = $val('enroll_service_type');
$selectedGroup = StudentLookup::SERVICE_TYPES[$selectedType][1] ?? ($val('enroll_service_group') ?: '');
$typeError = error_for('enroll_service_type');
$roomError = error_for('enroll_service_room_id');
$req = $enrollRequired ? ' <span class="text-danger">*</span>' : '';
?>
<div class="row g-3" data-enrollment>
  <?= $field('enroll_academic_year_id', ['type' => 'select', 'options' => $years, 'required' => $enrollRequired, 'placeholder_option' => '-- เลือกปีการศึกษา --', 'attrs' => ' data-enroll-year']) ?>

  <div class="col-12 col-md-6 col-lg-4">
    <label class="form-label" for="f-enroll_service_group">กลุ่มการรับบริการ<?= $req ?></label>
    <select id="f-enroll_service_group" name="enroll_service_group" class="form-select<?= $typeError ? ' is-invalid' : '' ?>" data-service-group>
      <option value="">-- เลือก --</option>
      <?php foreach (StudentLookup::SERVICE_GROUPS as $g => $label): ?>
        <option value="<?= e($g) ?>"<?= $selectedGroup === $g ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-6 col-lg-4">
    <label class="form-label" for="f-enroll_service_type"><?= e(StudentService::LABELS['service_type']) ?><?= $req ?></label>
    <select id="f-enroll_service_type" name="enroll_service_type" class="form-select<?= $typeError ? ' is-invalid' : '' ?>" data-service-type>
      <option value="">-- เลือกกลุ่มก่อน --</option>
      <?php foreach (StudentLookup::SERVICE_TYPES as $key => [, $group]): ?>
        <option value="<?= e($key) ?>" data-group="<?= e($group) ?>"<?= $selectedType === $key ? ' selected' : '' ?>><?= e(StudentLookup::serviceTypeShort($key)) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($typeError): ?><div class="invalid-feedback"><?= e($typeError) ?></div><?php endif; ?>
  </div>
  <?= $field('enroll_department_id', ['type' => 'select', 'options' => $units, 'placeholder_option' => '-- เลือกหน่วยบริการ --',
      'col' => 'col-12 col-md-6 col-lg-4 enroll-unit-field' . ($selectedType === 'service_unit' || error_for('enroll_department_id') ? '' : ' d-none'),
      'help' => count($units) . ' หน่วยบริการ']) ?>

  <div class="col-12 col-md-6 col-lg-4">
    <label class="form-label" for="f-enroll_service_room_id"><?= e(StudentService::LABELS['service_room_id']) ?></label>
    <select id="f-enroll_service_room_id" name="enroll_service_room_id" class="form-select<?= $roomError ? ' is-invalid' : '' ?>" data-room-select>
      <option value="">-- ไม่ระบุ --</option>
      <?php foreach ($options['rooms'] as $r): ?>
        <option value="<?= (int) $r['id'] ?>" data-year="<?= (int) $r['academic_year_id'] ?>" data-grade="<?= e((string) ($r['grade_level'] ?? '')) ?>"
                data-teachers="<?= e((string) ($r['teacher_ids'] ?? '')) ?>"<?= (string) $r['id'] === $selectedRoom ? ' selected' : '' ?>><?= e($r['room_code'] . ' ' . $r['name_th']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($roomError): ?><div class="invalid-feedback"><?= e($roomError) ?></div><?php endif; ?>
    <div class="form-text">แสดงเฉพาะห้องของปีการศึกษาที่เลือก · เลือกแล้วดึงครูและระดับชั้นของห้องให้</div>
  </div>
  <?= $field('enroll_responsible_employee_id', ['type' => 'select', 'options' => $teachers, 'attrs' => ' data-room-teacher',
      'help' => 'เมื่อเลือกห้องบริการ แสดงเฉพาะครูประจำชั้นของห้องนั้น (ห้องที่ยังไม่มีครูประจำชั้น เว้นว่าง)']) ?>
  <?= $field('enroll_grade_level', ['max' => 50, 'list' => 'grade-options', 'attrs' => ' data-room-grade', 'help' => 'ดึงจากระดับชั้นของห้องบริการ (แก้ไขได้)']) ?>
  <?= $field('enroll_note', ['label' => 'หมายเหตุ', 'max' => 255, 'col' => 'col-12']) ?>
</div>
<datalist id="grade-options"><?php foreach (StudentLookup::GRADE_LEVELS as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
