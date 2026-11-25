<?php

use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;

/** @var array $students */
/** @var array $counts */
/** @var array $filters */
/** @var array $options */
/** @var int|null $currentYearId */
/** @var string|null $scope */
/** @var string|null $sensitiveScope */

$scopeLabels = ['all' => 'ทั้งหมด', 'service_unit' => 'หน่วยบริการที่สังกัด', 'department' => 'ฝ่าย/งานที่สังกัด', 'own' => 'นักเรียนที่รับผิดชอบ'];
$yearId = $filters['year_id'] ?: $currentYearId;
$yearName = '';
foreach ($options['years'] as $y) {
    if ((int) $y['id'] === (int) $yearId) {
        $yearName = $y['name'];
    }
}
$query = http_build_query(array_filter([
    'q' => $filters['q'], 'status' => $filters['status'] === '' ? 'all' : $filters['status'], 'year_id' => $filters['year_id'] ?: null,
    'service_type' => $filters['service_type'], 'department_id' => $filters['department_id'] ?: null, 'room_id' => $filters['room_id'] ?: null,
    'disability' => $filters['disability'], 'mine' => $filters['mine'] ? '1' : null, 'enrolled' => $filters['enrolled'],
]));
$showDisability = $sensitiveScope !== null;
$hasEmployee = StudentService::employeeIdOf() !== null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">ทะเบียนนักเรียน</h1>
    <span class="badge text-bg-secondary">ขอบเขตข้อมูล: <?= e($scopeLabels[$scope] ?? '-') ?></span>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('/academic/students/completeness')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clipboard-check me-1"></i>ตรวจความครบถ้วน</a>
    <?php if (can('academic.students.update_all')): ?>
      <a href="<?= e(url('/academic/enrollments/promote')) ?>" class="btn btn-outline-secondary"><i class="bi bi-skip-forward me-1"></i>ยกยอดปีการศึกษา</a>
    <?php endif; ?>
    <?php if (can('academic.students.export_all')): ?>
      <a href="<?= e(url('/academic/students/export' . ($query !== '' ? '?' . $query : ''))) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>
    <?php endif; ?>
    <?php if (can('academic.students.create_all')): ?>
      <a href="<?= e(url('/academic/students/import')) ?>" class="btn btn-outline-primary"><i class="bi bi-filetype-csv me-1"></i>นำเข้า CSV</a>
      <a href="<?= e(url('/academic/students/create')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>เพิ่มนักเรียน</a>
    <?php endif; ?>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach (StudentLookup::STATUSES as $key => [$label, $color]): ?>
    <a href="<?= e(url('/academic/students?status=' . $key)) ?>" class="btn btn-sm <?= $filters['status'] === $key ? 'btn-' . e($color) : 'btn-outline-' . e($color) ?>">
      <?= e($label) ?> <span class="badge text-bg-light"><?= (int) $counts[$key] ?></span>
    </a>
  <?php endforeach; ?>
  <a href="<?= e(url('/academic/students?status=all')) ?>" class="btn btn-sm <?= $filters['status'] === '' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
    ทุกสถานะ <span class="badge text-bg-light"><?= array_sum($counts) ?></span>
  </a>
</div>

<form method="get" action="<?= e(url('/academic/students')) ?>" class="card shadow-sm mb-3">
  <div class="card-body row g-2 align-items-end">
    <input type="hidden" name="status" value="<?= e($filters['status'] === '' ? 'all' : $filters['status']) ?>">
    <div class="col-12 col-md-6 col-xl-3">
      <label class="form-label" for="f-q">ค้นหา</label>
      <input type="search" id="f-q" name="q" value="<?= e($filters['q']) ?>" class="form-control" placeholder="ชื่อ, ชื่อเล่น, เลขประจำตัว, เลข G, เลขบัตร 13 หลัก">
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <label class="form-label" for="f-year">ปีการศึกษา</label>
      <select id="f-year" name="year_id" class="form-select" data-enroll-year>
        <?php foreach ($options['years'] as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === (int) $yearId ? ' selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <label class="form-label" for="f-service">ประเภทการรับบริการ</label>
      <select id="f-service" name="service_type" class="form-select">
        <option value="">ทั้งหมด</option>
        <?php foreach (StudentLookup::SERVICE_TYPES as $k => [$label]): ?>
          <option value="<?= e($k) ?>"<?= $filters['service_type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <label class="form-label" for="f-unit">หน่วยบริการ</label>
      <select id="f-unit" name="department_id" class="form-select">
        <option value="">ทั้งหมด</option>
        <?php foreach ($options['units'] as $u): ?>
          <option value="<?= (int) $u['id'] ?>"<?= (int) $filters['department_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name_th']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-4 col-xl-3">
      <label class="form-label" for="f-room">ห้องบริการ</label>
      <select id="f-room" name="room_id" class="form-select" data-room-select>
        <option value="">ทั้งหมด</option>
        <?php foreach ($options['rooms'] as $r): ?>
          <option value="<?= (int) $r['id'] ?>" data-year="<?= (int) $r['academic_year_id'] ?>"<?= (int) $filters['room_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['room_code'] . ' ' . $r['name_th']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($showDisability): ?>
      <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label" for="f-dis">ประเภทความพิการ</label>
        <select id="f-dis" name="disability" class="form-select">
          <option value="">ทั้งหมด</option>
          <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $filters['disability'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="col-12 col-xl d-flex flex-wrap align-items-center gap-3">
      <?php if ($hasEmployee): ?>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="mine" value="1" id="f-mine"<?= $filters['mine'] ? ' checked' : '' ?>>
          <label class="form-check-label" for="f-mine">เฉพาะนักเรียนที่ฉันรับผิดชอบ</label>
        </div>
      <?php endif; ?>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="enrolled" value="no" id="f-enrolled"<?= $filters['enrolled'] === 'no' ? ' checked' : '' ?>>
        <label class="form-check-label" for="f-enrolled">ยังไม่มีข้อมูลการรับบริการปีนี้</label>
      </div>
      <div class="ms-auto d-flex gap-2">
        <a href="<?= e(url('/academic/students')) ?>" class="btn btn-outline-secondary">ล้าง</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>ค้นหา</button>
      </div>
    </div>
  </div>
</form>

<?php if ($students === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-mortarboard fs-1 d-block mb-2"></i>ไม่พบนักเรียนตามเงื่อนไข
  </div></div>
<?php else: ?>
  <p class="small text-muted mb-2">พบ <?= count($students) ?> คน · ข้อมูลการรับบริการ<?= e($yearName) ?></p>
  <div class="card shadow-sm d-none d-md-block">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>เลขประจำตัว</th><th>ชื่อ-สกุล</th><th>อายุ</th>
            <?php if ($showDisability): ?><th>ประเภทความพิการ</th><?php endif; ?>
            <th>การรับบริการ</th><th>ห้อง/ครูผู้รับผิดชอบ</th><th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s): ?>
            <?php [$statusLabel, $statusColor] = StudentLookup::STATUSES[$s['status']]; ?>
            <tr>
              <td class="font-monospace"><?= e($s['student_code']) ?></td>
              <td>
                <a href="<?= e(url('/academic/students/' . (int) $s['id'])) ?>" class="fw-semibold text-decoration-none"><?= e(StudentService::displayName($s)) ?></a>
                <?php if ($s['nickname']): ?><span class="text-muted small">(<?= e($s['nickname']) ?>)</span><?php endif; ?>
              </td>
              <td class="small text-nowrap"><?= e(StudentLookup::age($s['birth_date'])) ?></td>
              <?php if ($showDisability): ?>
                <td class="small"><?= $s['sensitive_visible'] ? e(StudentLookup::DISABILITY_TYPES[$s['disability_type'] ?? ''] ?? '-') : '<span class="text-muted" title="ไม่มีสิทธิ์ดูข้อมูลอ่อนไหวของนักเรียนคนนี้"><i class="bi bi-lock"></i></span>' ?></td>
              <?php endif; ?>
              <td class="small">
                <?php if ($s['enrollment_id'] === null): ?>
                  <span class="badge text-bg-warning">ยังไม่บันทึก</span>
                <?php else: ?>
                  <?= e(StudentLookup::serviceLabel($s['service_type'])) ?><?= $s['department_name'] ? '<br><span class="text-muted">' . e($s['department_name']) . '</span>' : '' ?>
                <?php endif; ?>
              </td>
              <td class="small">
                <?= $s['room_code'] ? e($s['room_code'] . ' ' . $s['room_name']) : '' ?>
                <?= $s['teacher_name'] ? '<br><span class="text-muted">' . e($s['teacher_name']) . '</span>' : '' ?>
              </td>
              <td><span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="d-md-none">
    <?php foreach ($students as $s): ?>
      <?php [$statusLabel, $statusColor] = StudentLookup::STATUSES[$s['status']]; ?>
      <a href="<?= e(url('/academic/students/' . (int) $s['id'])) ?>" class="card shadow-sm mb-2 text-decoration-none text-body">
        <div class="card-body py-2">
          <div class="d-flex justify-content-between gap-2">
            <div class="fw-semibold"><?= e(StudentService::displayName($s)) ?></div>
            <span class="badge text-bg-<?= e($statusColor) ?> align-self-start"><?= e($statusLabel) ?></span>
          </div>
          <div class="small text-muted">
            <span class="font-monospace"><?= e($s['student_code']) ?></span> · <?= e(StudentLookup::age($s['birth_date'])) ?>
            <?php if ($showDisability && $s['sensitive_visible'] && $s['disability_type']): ?> · <?= e(StudentLookup::DISABILITY_TYPES[$s['disability_type']] ?? '') ?><?php endif; ?>
          </div>
          <div class="small"><?= $s['enrollment_id'] ? e(StudentLookup::serviceLabel($s['service_type']) . ($s['room_code'] ? ' · ' . $s['room_code'] : '')) : '<span class="text-warning">ยังไม่บันทึกการรับบริการ</span>' ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<script src="<?= e(asset('js/academic-students.js')) ?>" defer></script>
