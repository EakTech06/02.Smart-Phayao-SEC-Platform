<?php

use Modules\Academic\Services\EnrollmentPromotionService;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;

/** @var int $fromYearId */
/** @var int $toYearId */
/** @var int|null $fromRoomId */
/** @var int|null $departmentId */
/** @var array $options years, units, rooms, teachers */
/** @var list<array> $candidates */

$yearName = static function (int $id) use ($options): string {
    foreach ($options['years'] as $y) {
        if ((int) $y['id'] === $id) {
            return (string) $y['name'];
        }
    }

    return '-';
};
$roomsOf = static fn (int $yearId): array => array_values(array_filter($options['rooms'], static fn (array $r): bool => (int) $r['academic_year_id'] === $yearId));
$ready = array_values(array_filter($candidates, static fn (array $c): bool => $c['target_enrollment_id'] === null));
$selectedIds = array_map('intval', array_column($ready, 'id'));
?>
<a href="<?= e(url('/academic/students')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> ทะเบียนนักเรียน</a>
<h1 class="h4 mb-1">ยกยอดการรับบริการปีการศึกษาใหม่</h1>
<p class="text-muted small mb-3">คัดลอกประเภทการรับบริการ หน่วยบริการ ระดับชั้น และครูผู้รับผิดชอบของปีต้นทางไปปีใหม่ทั้งห้องในครั้งเดียว แล้วแก้ไขเฉพาะรายที่เปลี่ยน · นักเรียนที่มีข้อมูลปีปลายทางแล้วจะถูกข้าม</p>

<?php if (errors() !== []): ?>
  <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e(implode(' · ', array_map(static fn (array $m): string => (string) $m[0], errors()))) ?></div>
<?php endif; ?>

<form method="get" action="<?= e(url('/academic/enrollments/promote')) ?>" class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">1. เลือกนักเรียนจากปีต้นทาง</div>
  <div class="card-body row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label" for="p-from">ปีต้นทาง</label>
      <select id="p-from" name="from_year_id" class="form-select">
        <?php foreach ($options['years'] as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === $fromYearId ? ' selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="p-to">ปีปลายทาง</label>
      <select id="p-to" name="to_year_id" class="form-select">
        <?php foreach ($options['years'] as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === $toYearId ? ' selected' : '' ?>><?= e($y['name']) ?><?= (int) $y['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="p-room">ห้องบริการต้นทาง</label>
      <select id="p-room" name="from_room_id" class="form-select">
        <option value="">ทุกห้อง</option>
        <?php foreach ($roomsOf($fromYearId) as $r): ?>
          <option value="<?= (int) $r['id'] ?>"<?= (int) $r['id'] === (int) $fromRoomId ? ' selected' : '' ?>><?= e($r['room_code'] . ' ' . $r['name_th']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label" for="p-unit">หน่วยบริการ</label>
      <select id="p-unit" name="department_id" class="form-select">
        <option value="">ทั้งหมด</option>
        <?php foreach ($options['units'] as $u): ?>
          <option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === (int) $departmentId ? ' selected' : '' ?>><?= e($u['name_th']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button type="submit" class="btn btn-outline-primary" aria-label="แสดงรายชื่อ"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<?php if ($fromYearId === $toYearId): ?>
  <div class="alert alert-warning">ปีต้นทางและปลายทางต้องเป็นคนละปีการศึกษา</div>
<?php elseif ($candidates === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-inbox fs-1 d-block mb-2"></i>ไม่พบนักเรียนที่มีข้อมูลการรับบริการ<?= e($yearName($fromYearId)) ?> และยังอยู่ในความดูแลของศูนย์
  </div></div>
<?php else: ?>
  <form method="post" action="<?= e(url('/academic/enrollments/promote')) ?>" novalidate
        data-confirm="ยกยอดนักเรียนที่เลือกไป<?= e($yearName($toYearId)) ?>?">
    <?= csrf_field() ?>
    <input type="hidden" name="from_year_id" value="<?= $fromYearId ?>">
    <input type="hidden" name="to_year_id" value="<?= $toYearId ?>">

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">2. กำหนดข้อมูลใน<?= e($yearName($toYearId)) ?></div>
      <div class="card-body row g-3">
        <div class="col-12 col-md-5">
          <label class="form-label" for="p-target-room">ห้องบริการปลายทาง</label>
          <select id="p-target-room" name="target_room" class="form-select<?= error_for('target_room') ? ' is-invalid' : '' ?>">
            <option value="<?= e(EnrollmentPromotionService::ROOM_MATCH) ?>">ห้องรหัสเดียวกับปีเดิม (ถ้ามี)</option>
            <option value="">ไม่ระบุห้อง (กำหนดภายหลัง)</option>
            <?php foreach ($roomsOf($toYearId) as $r): ?>
              <option value="<?= (int) $r['id'] ?>"<?= old('target_room') === (string) $r['id'] ? ' selected' : '' ?>>ทุกคนไปห้อง <?= e($r['room_code'] . ' ' . $r['name_th']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($roomsOf($toYearId) === []): ?><div class="form-text text-warning-emphasis">ยังไม่มีห้องบริการของปีปลายทาง (สร้างได้ที่เมนูข้อมูลห้องบริการ)</div><?php endif; ?>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="p-grade">ระดับชั้น</label>
          <select id="p-grade" name="grade_mode" class="form-select">
            <?php foreach (EnrollmentPromotionService::GRADE_MODES as $k => $label): ?>
              <option value="<?= e($k) ?>"<?= old('grade_mode') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">เลื่อนตามลำดับ: <?= e(implode(' → ', array_slice(StudentLookup::GRADE_LEVELS, 0, 3))) ?> → ...</div>
        </div>
        <div class="col-12 col-md-3 d-flex align-items-center">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="copy_teacher" value="1" id="p-teacher" checked>
            <label class="form-check-label" for="p-teacher">คัดลอกครูผู้รับผิดชอบ <span class="small text-muted d-block">(เฉพาะครูที่ยังปฏิบัติงาน)</span></label>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>3. นักเรียน<?= e($yearName($fromYearId)) ?> (<?= count($candidates) ?> คน · ยกยอดได้ <?= count($ready) ?> คน)</span>
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="p-all" data-check-all="student_ids[]"<?= $ready !== [] ? ' checked' : '' ?>>
          <label class="form-check-label small" for="p-all">เลือกทั้งหมด</label>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light"><tr><th></th><th>ชื่อ-สกุล</th><th>ห้อง / ระดับชั้นเดิม</th><th>การรับบริการ</th><th>ครูผู้รับผิดชอบ</th></tr></thead>
          <tbody>
            <?php foreach ($candidates as $c): ?>
              <?php $done = $c['target_enrollment_id'] !== null; ?>
              <tr class="<?= $done ? 'table-light text-muted' : '' ?>">
                <td>
                  <?php if ($done): ?>
                    <span class="badge text-bg-success" title="มีข้อมูลปีปลายทางแล้ว"><i class="bi bi-check-lg"></i></span>
                  <?php else: ?>
                    <input class="form-check-input" type="checkbox" name="student_ids[]" value="<?= (int) $c['id'] ?>" aria-label="เลือก <?= e(StudentService::displayName($c)) ?>"<?= in_array((int) $c['id'], $selectedIds, true) ? ' checked' : '' ?>>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?= e(url('/academic/students/' . (int) $c['id'] . '#enrollments')) ?>" class="text-decoration-none"><?= e(StudentService::displayName($c)) ?></a>
                  <span class="font-monospace small text-muted"><?= e($c['student_code']) ?></span>
                  <?php if ($c['status'] !== 'active'): ?><span class="badge text-bg-<?= e(StudentLookup::STATUSES[$c['status']][1]) ?>"><?= e(StudentLookup::statusLabel($c['status'])) ?></span><?php endif; ?>
                </td>
                <td class="small"><?= $c['room_code'] ? e($c['room_code'] . ' ' . $c['room_name']) : '-' ?><?= $c['grade_level'] ? '<div class="text-muted">' . e($c['grade_level']) . '</div>' : '' ?></td>
                <td class="small"><?= e(StudentLookup::serviceLabel($c['service_type'])) ?><?= $c['department_name'] ? '<div class="text-muted">' . e($c['department_name']) . '</div>' : '' ?></td>
                <td class="small">
                  <?= $c['teacher_name'] ? e($c['teacher_name']) : '-' ?>
                  <?php if ($c['teacher_name'] && $c['teacher_status'] !== 'active'): ?><span class="badge text-bg-warning">ไม่ได้ปฏิบัติงานแล้ว</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white d-flex justify-content-end">
        <button type="submit" class="btn btn-primary"<?= $ready === [] ? ' disabled' : '' ?>><i class="bi bi-skip-forward me-1"></i>ยกยอดไป<?= e($yearName($toYearId)) ?></button>
      </div>
    </div>
  </form>
<?php endif; ?>
<script src="<?= e(asset('js/academic-students.js')) ?>" defer></script>
