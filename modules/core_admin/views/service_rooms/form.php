<?php

use Core\Session;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\ServiceRoomService;

/** @var array|null $room */
/** @var array $years */
/** @var int|null $currentYearId */
/** @var array<int, string> $nextCodes */
/** @var array $teachers */

$isEdit = $room !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];
$val = static function (string $key, string $default = '') use ($room, $hasOld): string {
    if ($hasOld) {
        return old($key);
    }
    $value = $room[$key] ?? $default;

    return is_scalar($value) ? (string) $value : '';
};

$yearValue = (int) $val('academic_year_id', (string) ($currentYearId ?? ''));
$codeValue = $val('room_code', $nextCodes[$yearValue] ?? 'R001');
$teacherValue = (int) $val('homeroom_employee_id');
$action = url($isEdit ? '/service-rooms/' . (int) $room['id'] : '/service-rooms');
$invalid = static fn (string $f): string => error_for($f) ? ' is-invalid' : '';
$feedback = static fn (string $f): string => error_for($f) ? '<div class="invalid-feedback">' . e(error_for($f)) . '</div>' : '';
?>
<a href="<?= e(url('/service-rooms')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขห้องบริการ' : 'เพิ่มห้องบริการ' ?></h1>

<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <label class="form-label" for="f-academic_year_id">ปีการศึกษา <span class="text-danger">*</span></label>
          <select id="f-academic_year_id" name="academic_year_id" class="form-select<?= $invalid('academic_year_id') ?>" required>
            <?php foreach ($years as $y): ?>
              <option value="<?= (int) $y['id'] ?>" data-next-code="<?= e($nextCodes[(int) $y['id']] ?? 'R001') ?>"
                      <?= $yearValue === (int) $y['id'] ? 'selected' : '' ?>>
                <?= (int) $y['year_be'] ?><?= (int) $y['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= $feedback('academic_year_id') ?>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="f-room_code">รหัสห้องบริการ <span class="text-danger">*</span></label>
          <input id="f-room_code" name="room_code" type="text" class="form-control font-monospace text-uppercase<?= $invalid('room_code') ?>"
                 value="<?= e($codeValue) ?>" maxlength="4" pattern="[Rr][0-9]{3}" required autocomplete="off"
                 data-auto="<?= $isEdit ? '0' : '1' ?>">
          <?= $feedback('room_code') ?>
          <div class="form-text">รูปแบบ R + ตัวเลข 3 หลัก<?= $isEdit ? '' : ' (ระบบแนะนำรหัสถัดไปให้)' ?></div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="f-name_th">ชื่อห้องบริการ <span class="text-danger">*</span></label>
          <input id="f-name_th" name="name_th" type="text" class="form-control<?= $invalid('name_th') ?>"
                 value="<?= e($val('name_th')) ?>" maxlength="255" required
                 placeholder="เช่น ห้องบริการช่วยเหลือระยะเริ่มและเตรียมความพร้อม (EI)-1">
          <?= $feedback('name_th') ?>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="f-homeroom_employee_id">ครูประจำชั้น</label>
          <select id="f-homeroom_employee_id" name="homeroom_employee_id" class="form-select<?= $invalid('homeroom_employee_id') ?>">
            <option value="">-- ยังไม่กำหนด --</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= $teacherValue === (int) $t['id'] ? 'selected' : '' ?>>
                <?= e(PersonnelService::displayName($t) . ($t['position'] ? ' — ' . $t['position'] : '')
                    . ($t['status'] !== 'active' ? ' (ไม่ปฏิบัติงาน)' : '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?= $feedback('homeroom_employee_id') ?>
          <div class="form-text">ดึงจากข้อมูลบุคลากร (เฉพาะสถานะปฏิบัติงาน)</div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= e(url('/service-rooms')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>
<script src="<?= e(asset('js/service-rooms.js')) ?>" defer></script>
