<?php

use Core\Session;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;

/** @var array|null $student */
/** @var array $guardians */
/** @var array|null $options ตัวเลือกการรับบริการ (เฉพาะตอนเพิ่ม) */
/** @var int|null $currentYearId */
/** @var string|null $nextCode */
/** @var bool $canEditBasicSensitive */
/** @var array $basicSensitive ประเภทความพิการ/กลุ่มเลือดที่บันทึกไว้ (ถอดรหัสแล้ว) */

$isEdit = $student !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];
$labels = StudentService::labels();

// ค่าปัจจุบันของแต่ละช่อง: ค่าที่กรอกค้าง (กรณี validate ไม่ผ่าน) > ข้อมูลเดิม > ค่าเริ่มต้น
$current = [];
if ($isEdit) {
    foreach ($student as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $current[$k] = (string) $v;
        }
    }
    foreach ((array) ($student['address_registered'] ?? []) as $k => $v) {
        $current['reg_' . $k] = (string) $v;
    }
    foreach ((array) ($student['address_current'] ?? []) as $k => $v) {
        $current['cur_' . $k] = (string) $v;
    }
    $current['cur_same'] = $student['address_current'] === null ? '1' : '';
    foreach ($guardians as $rel => $g) {
        foreach ($g as $k => $v) {
            $current["{$rel}_{$k}"] = (string) $v;
        }
        if ((int) $g['is_primary_contact'] === 1) {
            $current['primary_contact'] = $rel;
        }
    }
} else {
    $current = [
        'id_type' => 'citizen', 'status' => 'active', 'cur_same' => '1', 'nationality' => 'ไทย', 'ethnicity' => 'ไทย',
        'religion' => 'พุทธ', 'reg_province' => 'พะเยา', 'enroll_academic_year_id' => (string) ($currentYearId ?? ''),
        'admitted_at' => date('Y-m-d'),
    ];
}
$val = static fn (string $key): string => $hasOld ? old($key) : ($current[$key] ?? '');
$field = require __DIR__ . '/_field.php';
$idType = $val('id_type') ?: 'citizen';
$status = $val('status') ?: 'active';
$action = url($isEdit ? '/academic/students/' . (int) $student['id'] : '/academic/students');
?>
<a href="<?= e($action === url('/academic/students') ? url('/academic/students') : $action) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขข้อมูลนักเรียน: ' . e(StudentService::displayName($student)) : 'เพิ่มนักเรียน' ?></h1>

<?php if (errors() !== []): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>กรุณาตรวจสอบข้อมูลที่ไฮไลต์สีแดง (<?= count(errors()) ?> รายการ)</div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" novalidate id="student-form">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

  <!-- 1. ข้อมูลนักเรียน -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>ข้อมูลนักเรียน</div>
    <div class="card-body">
      <div class="row g-3">
        <?= $field('student_code', [
            'max' => 10, 'inputmode' => 'numeric', 'placeholder' => $isEdit ? '' : (string) $nextCode,
            'help' => $isEdit ? 'ตัวเลข 3-10 หลัก ไม่ซ้ำกับนักเรียนคนอื่น' : 'เว้นว่างให้ระบบกำหนด (' . $nextCode . ') หรือกรอกเลขเดิมจากระบบ SET',
        ]) ?>
        <?= $field('id_type', ['type' => 'select', 'options' => StudentLookup::ID_TYPES, 'required' => true, 'attrs' => ' data-id-type']) ?>
        <div class="col-12 col-md-6 col-lg-4 id-field id-citizen id-passport<?= in_array($idType, ['citizen', 'passport'], true) ? '' : ' d-none' ?>">
          <?php $nidError = error_for('national_id'); ?>
          <label class="form-label" for="f-national_id">เลขประจำตัวประชาชน/หนังสือเดินทาง</label>
          <input id="f-national_id" name="national_id" type="text" maxlength="20" autocomplete="off" inputmode="text"
                 class="form-control<?= $nidError ? ' is-invalid' : '' ?>"
                 placeholder="<?= e($isEdit && !empty($student['national_id_last4']) ? '*********' . $student['national_id_last4'] . ' (เว้นว่างหากไม่เปลี่ยน)' : '13 หลัก') ?>">
          <?php if ($nidError): ?><div class="invalid-feedback"><?= e($nidError) ?></div><?php endif; ?>
          <div class="form-text">เข้ารหัสก่อนบันทึก แสดงทั่วไปเฉพาะ 4 หลักท้าย</div>
        </div>
        <?= $field('g_code', ['max' => 20, 'placeholder' => 'G123456789', 'col' => 'col-12 col-md-6 col-lg-4 id-field id-g_code' . ($idType === 'g_code' ? '' : ' d-none'),
            'help' => 'ลงทะเบียนขอเลข G ที่ gcode.moe.go.th แล้วแนบไฟล์ PDF ในหน้าข้อมูลนักเรียน']) ?>
        <?php if ($isEdit): ?>
          <?= $field('identity_change_reason', ['max' => 255, 'col' => 'col-12 col-lg-8',
              'placeholder' => 'เช่น ได้รับเลขประจำตัวประชาชนแทนเลข G, แก้เลขตามหนังสือ สพฐ.',
              'help' => 'กรอกเมื่อเปลี่ยนเลขประจำตัว ระบบเก็บเลขเดิมแบบเข้ารหัสไว้ในประวัติ']) ?>
        <?php endif; ?>
      </div>
      <hr>
      <div class="row g-3">
        <?= $field('title_name', ['max' => 30, 'list' => 'title-options', 'col' => 'col-6 col-md-3 col-lg-2']) ?>
        <?= $field('first_name', ['required' => true, 'max' => 100, 'col' => 'col-6 col-md-4 col-lg-3']) ?>
        <?= $field('last_name', ['required' => true, 'max' => 100, 'col' => 'col-12 col-md-5 col-lg-4']) ?>
        <?= $field('nickname', ['max' => 50, 'col' => 'col-6 col-md-3 col-lg-3']) ?>
        <?= $field('first_name_en', ['max' => 100, 'col' => 'col-6 col-md-4']) ?>
        <?= $field('last_name_en', ['max' => 100, 'col' => 'col-12 col-md-5']) ?>
        <?= $field('gender', ['type' => 'select', 'options' => StudentLookup::GENDERS, 'col' => 'col-6 col-md-3']) ?>
        <?= $field('birth_date', ['type' => 'date', 'col' => 'col-6 col-md-3', 'attrs' => ' data-birth-date']) ?>
        <div class="col-6 col-md-3">
          <label class="form-label" for="f-age">อายุ</label>
          <input id="f-age" type="text" class="form-control bg-body-tertiary" readonly tabindex="-1" data-age
                 value="<?= e($val('birth_date') !== '' ? StudentLookup::age($val('birth_date')) : '') ?>" placeholder="คำนวณจากวันเกิด">
        </div>
        <?= $field('nationality', ['max' => 50, 'list' => 'nationality-options', 'col' => 'col-6 col-md-3']) ?>
        <?= $field('ethnicity', ['max' => 50, 'list' => 'nationality-options', 'col' => 'col-6 col-md-3']) ?>
        <?= $field('religion', ['max' => 50, 'list' => 'religion-options', 'col' => 'col-6 col-md-3']) ?>
        <?= $field('tribe', ['max' => 50, 'list' => 'tribe-options', 'col' => 'col-6 col-md-3']) ?>
        <?= $field('phone', ['type' => 'tel', 'max' => 30, 'inputmode' => 'tel', 'col' => 'col-12 col-md-6']) ?>
      </div>
      <?php if ($canEditBasicSensitive): ?>
        <?php
        // ข้อมูลอ่อนไหว: ไม่เก็บค่าที่กรอกค้างใน Session จึงแสดงค่าที่บันทึกไว้เสมอ
        $sv = static fn (string $k): string => is_scalar($basicSensitive[$k] ?? null) ? (string) $basicSensitive[$k] : '';
        $disType = $sv('disability_type');
        $multiple = is_array($basicSensitive['multiple_disabilities'] ?? null) ? $basicSensitive['multiple_disabilities'] : [];
        $sensitiveSelect = static function (string $name, array $options, string $value, string $col, string $attrs = ''): string {
            $err = error_for($name);
            $html = '<div class="' . $col . '"><label class="form-label" for="f-' . $name . '">' . e(StudentService::SENSITIVE_LABELS[$name]) . '</label>'
                . '<select id="f-' . $name . '" name="' . $name . '" class="form-select' . ($err ? ' is-invalid' : '') . '"' . $attrs . '>'
                . '<option value="">-- ไม่ระบุ --</option>';
            foreach ($options as $k => $label) {
                $html .= '<option value="' . e((string) $k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . e($label) . '</option>';
            }

            return $html . '</select>' . ($err ? '<div class="invalid-feedback">' . e($err) . '</div>' : '') . '</div>';
        };
        ?>
        <hr>
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-shield-lock text-primary"></i>
          <span class="small text-muted">ข้อมูลอ่อนไหว (PDPA) เข้ารหัสก่อนบันทึก รายละเอียดอื่นบันทึกที่ "ข้อมูลความพิการและสุขภาพ"</span>
        </div>
        <?php if (errors() !== []): ?>
          <div class="alert alert-warning py-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>ช่องประเภทความพิการและกลุ่มเลือดไม่เก็บค่าที่กรอกค้างไว้ กรุณาเลือกใหม่อีกครั้ง</div>
        <?php endif; ?>
        <div class="row g-3">
          <?= $sensitiveSelect('disability_type', StudentLookup::DISABILITY_TYPES, $disType, 'col-12 col-md-8 col-lg-6', ' data-disability-type') ?>
          <?= $sensitiveSelect('blood_group', StudentLookup::BLOOD_GROUPS, $sv('blood_group'), 'col-6 col-md-4 col-lg-3') ?>
          <div class="col-12 multiple-fields<?= $disType === 'multiple' || error_for('multiple_disabilities') ? '' : ' d-none' ?>">
            <div class="form-label">ประเภทความพิการ (เลือกอย่างน้อย 2 ประเภท) <span class="text-danger">*</span></div>
            <div class="d-flex flex-wrap gap-3">
              <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
                <?php if ($k === 'multiple') { continue; } ?>
                <div class="form-check">
                  <input class="form-check-input<?= error_for('multiple_disabilities') ? ' is-invalid' : '' ?>" type="checkbox" name="multiple_disabilities[]"
                         value="<?= e($k) ?>" id="md-<?= e($k) ?>"<?= in_array($k, $multiple, true) ? ' checked' : '' ?>>
                  <label class="form-check-label" for="md-<?= e($k) ?>"><?= e($label) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (error_for('multiple_disabilities')): ?><div class="invalid-feedback d-block"><?= e(error_for('multiple_disabilities')) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 2. สถานะการรับบริการ -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-flag me-1"></i>สถานะ</div>
    <div class="card-body">
      <div class="row g-3">
        <?php if ($isEdit): ?>
          <?php [$statusLabel, $statusColor] = StudentLookup::STATUSES[$student['status']]; ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="form-label">สถานะปัจจุบัน</div>
            <div><span class="badge text-bg-<?= e($statusColor) ?> fs-6"><?= e($statusLabel) ?></span></div>
            <div class="form-text">เปลี่ยนสถานะ (พักการเรียน จบ ย้าย จำหน่าย) ที่หน้าข้อมูลนักเรียน เพื่อบันทึกวันที่มีผลและเหตุผลไว้ในประวัติ</div>
          </div>
        <?php else: ?>
          <?= $field('status', ['type' => 'select', 'required' => true,
              'options' => array_map(static fn (string $k): string => StudentLookup::statusLabel($k), array_combine(StudentLookup::INITIAL_STATUSES, StudentLookup::INITIAL_STATUSES))]) ?>
        <?php endif; ?>
        <?= $field('applied_at', ['type' => 'date']) ?>
        <?= $field('admitted_at', ['type' => 'date']) ?>
        <?= $field('note', ['type' => 'textarea', 'max' => 500, 'col' => 'col-12']) ?>
      </div>
    </div>
  </div>

  <!-- 3. ที่อยู่ -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-house me-1"></i>ที่อยู่</div>
    <div class="card-body">
      <h2 class="h6 fw-semibold">ที่อยู่ตามทะเบียนบ้าน</h2>
      <div class="row g-3 mb-3">
        <?php foreach (StudentLookup::ADDRESS_FIELDS as $key => [$label, $max]): ?>
          <?= $field('reg_' . $key, [
              'label' => $label, 'max' => $max, 'col' => in_array($key, ['village', 'road', 'soi'], true) ? 'col-12 col-md-4' : 'col-6 col-md-4 col-lg-3',
              'list' => $key === 'province' ? 'province-options' : null,
              'inputmode' => in_array($key, ['house_code', 'postcode'], true) ? 'numeric' : null,
          ]) ?>
        <?php endforeach; ?>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="cur_same" value="1" id="f-cur_same" data-toggle-target="#current-address"<?= $val('cur_same') === '1' ? ' checked' : '' ?>>
        <label class="form-check-label" for="f-cur_same">ที่อยู่ปัจจุบันตามทะเบียนบ้าน</label>
      </div>
      <div id="current-address" class="<?= $val('cur_same') === '1' ? 'd-none' : '' ?>">
        <h2 class="h6 fw-semibold">ที่อยู่ปัจจุบัน</h2>
        <div class="row g-3">
          <?php foreach (StudentLookup::ADDRESS_FIELDS as $key => [$label, $max]): ?>
            <?php if ($key === 'house_code') { continue; } ?>
            <?= $field('cur_' . $key, [
                'label' => $label, 'max' => $max, 'col' => in_array($key, ['village', 'road', 'soi'], true) ? 'col-12 col-md-4' : 'col-6 col-md-4 col-lg-3',
                'list' => $key === 'province' ? 'province-options' : null,
                'inputmode' => $key === 'postcode' ? 'numeric' : null,
            ]) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <h2 class="h6 fw-semibold mt-3">ระยะทางจากบ้านถึงที่รับบริการ (กม.)</h2>
      <div class="row g-3">
        <?php foreach (StudentLookup::TRAVEL_FIELDS as $key => $label): ?>
          <?= $field($key, ['label' => $label, 'type' => 'number', 'col' => 'col-4 col-lg-3', 'inputmode' => 'decimal', 'attrs' => ' min="0" max="9999.9" step="0.1"']) ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 4. ครอบครัว -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i>บิดา มารดา และผู้ปกครอง</div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <?= $field('parents_status', ['type' => 'select', 'options' => StudentLookup::PARENTS_STATUSES]) ?>
        <?= $field('lives_with', ['type' => 'select', 'options' => StudentLookup::LIVES_WITH]) ?>
        <?= $field('primary_contact', ['type' => 'select', 'options' => StudentLookup::RELATIONS, 'help' => 'ผู้ที่ติดต่อเป็นลำดับแรก']) ?>
      </div>
      <div class="accordion" id="guardians">
        <?php foreach (StudentLookup::RELATIONS as $rel => $relLabel): ?>
          <?php
          $hasData = $val("{$rel}_first_name") !== '';
          $hasError = array_filter(array_keys(errors()), static fn (string $k): bool => str_starts_with($k, $rel . '_')) !== [];
          $open = $hasData || $hasError || (!$isEdit && $rel === 'mother');
          ?>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button<?= $open ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#g-<?= e($rel) ?>">
                ข้อมูล<?= e($relLabel) ?>
                <?php if ($hasData): ?><span class="badge text-bg-light border ms-2"><?= e(trim($val("{$rel}_title_name") . $val("{$rel}_first_name") . ' ' . $val("{$rel}_last_name"))) ?></span><?php endif; ?>
              </button>
            </h3>
            <div id="g-<?= e($rel) ?>" class="accordion-collapse collapse<?= $open ? ' show' : '' ?>">
              <div class="accordion-body">
                <div class="row g-3">
                  <?php if ($rel === 'guardian'): ?>
                    <?php
                    // ผู้ปกครองคือบิดา/มารดา: ค่าที่กรอกค้าง > เดาจากข้อมูลเดิม (ความเกี่ยวข้อง + ชื่อตรงกัน)
                    $source = $hasOld ? old('guardian_source') : '';
                    if (!$hasOld && $isEdit) {
                        foreach (['father', 'mother'] as $p) {
                            if (($current['guardian_relation_note'] ?? '') === StudentLookup::RELATIONS[$p] && ($current['guardian_first_name'] ?? '') !== ''
                                && ($current['guardian_first_name'] ?? '') === ($current["{$p}_first_name"] ?? '')
                                && ($current['guardian_last_name'] ?? '') === ($current["{$p}_last_name"] ?? '')) {
                                $source = $p;
                            }
                        }
                    }
                    ?>
                    <div class="col-12 col-md-6 col-lg-4">
                      <label class="form-label" for="f-guardian_source">ผู้ปกครองคือ</label>
                      <select id="f-guardian_source" name="guardian_source" class="form-select" data-guardian-source>
                        <option value="">บุคคลอื่น (กรอกเอง)</option>
                        <option value="father"<?= $source === 'father' ? ' selected' : '' ?>>บิดา (ใช้ข้อมูลบิดา)</option>
                        <option value="mother"<?= $source === 'mother' ? ' selected' : '' ?>>มารดา (ใช้ข้อมูลมารดา)</option>
                      </select>
                      <div class="form-text">เลือกบิดา/มารดา ระบบนำข้อมูลมาใส่ให้อัตโนมัติ (รวมเลขประจำตัวประชาชน)</div>
                    </div>
                    <?= $field('guardian_relation_note', ['label' => 'ความเกี่ยวข้องกับนักเรียน', 'max' => 50, 'placeholder' => 'เช่น ปู่ ย่า ตา ยาย ลุง ป้า']) ?>
                  <?php endif; ?>
                  <?= $field("{$rel}_title_name", ['label' => 'คำนำหน้า', 'max' => 30, 'list' => 'adult-title-options', 'col' => 'col-6 col-md-3 col-lg-2']) ?>
                  <?= $field("{$rel}_first_name", ['label' => 'ชื่อ', 'max' => 100, 'col' => 'col-6 col-md-4 col-lg-3']) ?>
                  <?= $field("{$rel}_last_name", ['label' => 'นามสกุล', 'max' => 100, 'col' => 'col-12 col-md-5 col-lg-3']) ?>
                  <?= $field("{$rel}_birth_date", ['label' => 'วันเกิด', 'type' => 'date', 'col' => 'col-6 col-md-4 col-lg-2']) ?>
                  <?= $field("{$rel}_life_status", ['label' => 'สถานะ', 'type' => 'select', 'options' => StudentLookup::LIFE_STATUSES, 'col' => 'col-6 col-md-4 col-lg-2']) ?>
                  <?= $field("{$rel}_national_id", ['label' => 'เลขประจำตัวประชาชน', 'value' => '', 'max' => 17, 'inputmode' => 'numeric', 'col' => 'col-12 col-md-4',
                      'placeholder' => !empty($current["{$rel}_national_id_last4"]) ? '*********' . $current["{$rel}_national_id_last4"] . ' (เว้นว่างหากไม่เปลี่ยน)' : '13 หลัก']) ?>
                  <?= $field("{$rel}_occupation", ['label' => 'อาชีพ', 'max' => 100, 'list' => 'occupation-options']) ?>
                  <?= $field("{$rel}_income_per_year", ['label' => 'รายได้ต่อปี (บาท)', 'type' => 'number', 'attrs' => ' min="0" step="1"']) ?>
                  <?= $field("{$rel}_phone", ['label' => 'เบอร์โทรศัพท์', 'type' => 'tel', 'max' => 30, 'inputmode' => 'tel']) ?>
                  <?= $field("{$rel}_line_id", ['label' => 'ID Line', 'max' => 100]) ?>
                </div>
                <?php if ($isEdit && $hasData): ?>
                  <div class="form-text mt-2">ลบชื่อออกแล้วบันทึก เพื่อลบข้อมูล<?= e($relLabel) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 5. พี่น้องและกิจกรรม (ตามแบบฟอร์ม SET) -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-2 me-1"></i>พี่น้องและกิจกรรม</div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <?php foreach (StudentLookup::SIBLING_FIELDS as $key => $label): ?>
          <?= $field($key, ['type' => 'number', 'col' => 'col-6 col-md-4 col-lg-2', 'inputmode' => 'numeric',
              'attrs' => ' min="' . ($key === 'child_order' ? 1 : 0) . '" max="30" step="1"']) ?>
        <?php endforeach; ?>
      </div>
      <div class="row g-3">
        <?= $field('scout_level', ['type' => 'select', 'options' => StudentLookup::SCOUT_LEVELS]) ?>
        <?= $field('scout_group', ['type' => 'select', 'options' => StudentLookup::SCOUT_GROUPS]) ?>
        <?= $field('redcross_status', ['type' => 'select', 'options' => StudentLookup::REDCROSS_STATUSES]) ?>
      </div>
    </div>
  </div>

  <?php if (!$isEdit && $options !== null): ?>
    <!-- 6. การรับบริการปีแรก -->
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-door-open me-1"></i>การรับบริการ (ไม่บังคับ บันทึกภายหลังได้)</div>
      <div class="card-body">
        <?php require __DIR__ . '/_enrollment_fields.php'; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-end gap-2 mb-4">
    <a href="<?= e($isEdit ? $action : url('/academic/students')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>

<datalist id="title-options"><?php foreach (StudentLookup::TITLES as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<datalist id="adult-title-options"><?php foreach (['นาย', 'นาง', 'นางสาว'] as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<datalist id="nationality-options"><?php foreach (StudentLookup::NATIONALITIES as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<datalist id="religion-options"><?php foreach (StudentLookup::RELIGIONS as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<datalist id="tribe-options"><?php foreach (StudentLookup::TRIBES as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<datalist id="occupation-options"><?php foreach (StudentLookup::OCCUPATIONS as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<datalist id="province-options"><?php foreach (StudentLookup::PROVINCES as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
<script src="<?= e(asset('js/academic-students.js')) ?>" defer></script>
