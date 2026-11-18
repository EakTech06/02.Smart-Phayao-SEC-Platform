<?php

use Core\Session;
use Modules\CoreAdmin\Services\CalendarService;

/** @var array|null $event */
/** @var string|null $date */
/** @var array<string, string> $visibilities */
/** @var array $departments */

$isEdit = $event !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];
$fromEvent = static function (string $key) use ($event, $date): string {
    if ($event === null) {
        return match ($key) {
            'start_date' => (string) $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'visibility' => 'private',
            'color' => '#0d6efd',
            default => '',
        };
    }
    $allDay = (int) $event['is_all_day'] === 1;

    return match ($key) {
        'start_date' => substr((string) $event['start_at'], 0, 10),
        'start_time' => $allDay ? '' : substr((string) $event['start_at'], 11, 5),
        'end_date' => $event['end_at'] !== null ? substr((string) $event['end_at'], 0, 10) : '',
        'end_time' => $event['end_at'] !== null && !$allDay ? substr((string) $event['end_at'], 11, 5) : '',
        default => (string) ($event[$key] ?? ''),
    };
};
$val = static fn (string $key): string => $hasOld ? old($key) : $fromEvent($key);
$allDayChecked = $hasOld ? !empty($oldInput['is_all_day']) : ($event !== null && (int) $event['is_all_day'] === 1);
$invalid = static fn (string $f): string => error_for($f) ? ' is-invalid' : '';
$feedback = static fn (string $f): string => error_for($f) ? '<div class="invalid-feedback">' . e(error_for($f)) . '</div>' : '';
$action = url($isEdit ? '/calendar/' . (int) $event['id'] : '/calendar');
?>
<a href="<?= e(url($isEdit ? '/calendar/' . (int) $event['id'] : '/calendar')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขกิจกรรม' : 'เพิ่มกิจกรรม' ?></h1>

<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-lg-8">
          <label class="form-label" for="f-title">ชื่อกิจกรรม <span class="text-danger">*</span></label>
          <input id="f-title" name="title" type="text" class="form-control<?= $invalid('title') ?>" maxlength="255" required value="<?= e($val('title')) ?>">
          <?= $feedback('title') ?>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="f-location">สถานที่</label>
          <input id="f-location" name="location" type="text" class="form-control<?= $invalid('location') ?>" maxlength="255" value="<?= e($val('location')) ?>">
          <?= $feedback('location') ?>
        </div>

        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="f-is_all_day" name="is_all_day" value="1" <?= $allDayChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-is_all_day">ทั้งวัน</label>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="f-start_date">วันที่เริ่ม <span class="text-danger">*</span></label>
          <input id="f-start_date" name="start_date" type="date" class="form-control<?= $invalid('start_date') ?>" required value="<?= e($val('start_date')) ?>">
          <?= $feedback('start_date') ?>
        </div>
        <div class="col-6 col-md-3 js-time">
          <label class="form-label" for="f-start_time">เวลาเริ่ม</label>
          <input id="f-start_time" name="start_time" type="time" class="form-control<?= $invalid('start_time') ?>" value="<?= e($val('start_time')) ?>">
          <?= $feedback('start_time') ?>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="f-end_date">วันที่สิ้นสุด</label>
          <input id="f-end_date" name="end_date" type="date" class="form-control<?= $invalid('end_date') ?>" value="<?= e($val('end_date')) ?>">
          <?= $feedback('end_date') ?>
          <div class="form-text">เว้นว่าง = วันเดียวกับวันที่เริ่ม</div>
        </div>
        <div class="col-6 col-md-3 js-time">
          <label class="form-label" for="f-end_time">เวลาสิ้นสุด</label>
          <input id="f-end_time" name="end_time" type="time" class="form-control<?= $invalid('end_time') ?>" value="<?= e($val('end_time')) ?>">
          <?= $feedback('end_time') ?>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="f-visibility">การมองเห็น <span class="text-danger">*</span></label>
          <select id="f-visibility" name="visibility" class="form-select<?= $invalid('visibility') ?>">
            <?php foreach ($visibilities as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $val('visibility') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $feedback('visibility') ?>
          <?php if (isset($visibilities['public'])): ?><div class="form-text">"ทั้งองค์กร" จะซิงก์ไป Google Calendar ขององค์กรด้วย</div><?php endif; ?>
        </div>
        <div class="col-12 col-md-4 js-department">
          <label class="form-label" for="f-department_id">ฝ่าย/งาน</label>
          <select id="f-department_id" name="department_id" class="form-select<?= $invalid('department_id') ?>">
            <option value="">-- เลือก --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= (int) $d['id'] ?>" <?= (int) $val('department_id') === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name_th']) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $feedback('department_id') ?>
        </div>
        <div class="col-12 col-md-4">
          <span class="form-label d-block">สี</span>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach (CalendarService::COLORS as $hex => $label): ?>
              <input type="radio" class="btn-check" name="color" id="color-<?= e(substr($hex, 1)) ?>" value="<?= e($hex) ?>" <?= $val('color') === $hex ? 'checked' : '' ?>>
              <label class="btn btn-sm rounded-circle p-0 border" for="color-<?= e(substr($hex, 1)) ?>" title="<?= e($label) ?>"
                     style="width: 2.75rem; height: 2.75rem; background: <?= e($hex) ?>;"><span class="visually-hidden"><?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label" for="f-description">รายละเอียด</label>
          <textarea id="f-description" name="description" rows="4" class="form-control<?= $invalid('description') ?>" maxlength="5000"><?= e($val('description')) ?></textarea>
          <?= $feedback('description') ?>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="f-notify" name="notify" value="1" <?= $isEdit ? '' : 'checked' ?>>
            <label class="form-check-label" for="f-notify">แจ้งเตือนผู้ที่เห็นกิจกรรมนี้ (กระดิ่งแจ้งเตือน) — ไม่มีผลกับกิจกรรมส่วนตัว</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <a href="<?= e(url('/calendar')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>
<script src="<?= e(asset('js/calendar.js')) ?>" defer></script>
