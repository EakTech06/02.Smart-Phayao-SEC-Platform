<?php

use Core\Session;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\ServiceRoomService;

/** @var array|null $room */
/** @var array $years */
/** @var int|null $currentYearId */
/** @var array<int, string> $nextCodes */
/** @var array $teachers */
/** @var list<string> $gradeLevels */
/** @var array<int, array<int, string>> $assignments */

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
$teacherValues = $hasOld
    ? ServiceRoomService::teacherIdsInput($oldInput['teacher_ids'] ?? [])
    : array_map(static fn (array $t): int => (int) $t['id'], $room['teachers'] ?? []);
if ($teacherValues === []) {
    $teacherValues = [0]; // แสดงช่องว่าง 1 ช่อง
}
$teacherOption = static function (int $selected) use ($teachers, $assignments): string {
    $html = '<option value="">-- เลือกครู --</option>';
    foreach ($teachers as $t) {
        // data-rooms = {ปีการศึกษา: รหัสห้อง} ที่ครูเป็นครูประจำชั้นอยู่แล้ว → JS ซ่อนตามปีที่เลือก
        $rooms = $assignments[(int) $t['id']] ?? [];
        $html .= '<option value="' . (int) $t['id'] . '"' . ($selected === (int) $t['id'] ? ' selected' : '')
            . ($rooms !== [] ? ' data-rooms="' . e((string) json_encode($rooms, JSON_UNESCAPED_UNICODE)) . '"' : '') . '>'
            . e(PersonnelService::displayName($t) . ($t['position'] ? ' — ' . $t['position'] : '')
                . ($t['status'] !== 'active' ? ' (ไม่ปฏิบัติงาน)' : '')) . '</option>';
    }

    return $html;
};
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
          <label class="form-label" for="f-grade_level">ระดับชั้น</label>
          <input id="f-grade_level" name="grade_level" type="text" class="form-control<?= $invalid('grade_level') ?>"
                 value="<?= e($val('grade_level')) ?>" maxlength="50" list="room-grade-options" placeholder="เช่น เตรียมความพร้อม">
          <datalist id="room-grade-options"><?php foreach ($gradeLevels as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
          <?= $feedback('grade_level') ?>
          <div class="form-text">เลือกจากรายการหรือพิมพ์เอง · ใช้เป็นค่าเริ่มต้นระดับชั้นของนักเรียนในห้องนี้</div>
        </div>
        <div class="col-12">
          <label class="form-label">ครูประจำชั้น <span class="text-muted small">(ได้สูงสุด <?= ServiceRoomService::MAX_TEACHERS ?> คน · คนแรก = ครูประจำชั้นหลัก)</span></label>
          <div class="d-grid gap-2" data-teacher-list data-max="<?= ServiceRoomService::MAX_TEACHERS ?>">
            <?php foreach ($teacherValues as $i => $tid): ?>
              <div class="input-group" data-teacher-row>
                <span class="input-group-text" data-teacher-no style="min-width: 2.75rem;"><?= $i + 1 ?></span>
                <select name="teacher_ids[]" class="form-select<?= $invalid('teacher_ids') ?>" aria-label="ครูประจำชั้นคนที่ <?= $i + 1 ?>"><?= $teacherOption($tid) ?></select>
                <button type="button" class="btn btn-outline-danger" data-teacher-remove title="เอาออก"><i class="bi bi-x-lg"></i></button>
              </div>
            <?php endforeach; ?>
          </div>
          <?php foreach (errors()['teacher_ids'] ?? [] as $msg): ?><div class="invalid-feedback d-block"><?= e($msg) ?></div><?php endforeach; ?>
          <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-teacher-add><i class="bi bi-plus-lg me-1"></i>เพิ่มครูประจำชั้น</button>
          <div class="form-text">ดึงจากข้อมูลบุคลากร (เฉพาะสถานะปฏิบัติงาน) · ครู 1 คนเป็นครูประจำชั้นได้ 1 ห้องต่อปีการศึกษา: ครูที่มีห้องแล้วจะไม่แสดงในรายการ</div>
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
