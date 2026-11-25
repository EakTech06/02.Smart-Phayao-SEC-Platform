<?php

use Modules\Academic\Services\StudentCompleteness;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;

/** @var array{rows:list<array>, summary:array, cards:list<array>, can_sensitive:bool} $report */
/** @var array $filters */
/** @var list<array> $units */

$summary = $report['summary'];
$avg = $summary['students'] > 0 ? (int) floor($summary['score_sum'] / $summary['students']) : 0;
$link = static function (array $override) use ($filters): string {
    $q = array_merge([
        'status_group' => $filters['status_group'] === 'active' ? 'active' : null,
        'department_id' => $filters['department_id'] ?: null,
        'category' => $filters['category'] ?: null,
        'all' => $filters['only_incomplete'] ? null : '1',
    ], $override);

    $qs = http_build_query(array_filter($q, static fn ($v): bool => $v !== null && $v !== ''));

    return url('/academic/students/completeness' . ($qs !== '' ? '?' . $qs : ''));
};
$color = static fn (int $score): string => $score >= 100 ? 'success' : ($score >= 70 ? 'warning' : 'danger');
?>
<a href="<?= e(url('/academic/students')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> ทะเบียนนักเรียน</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">ตรวจความครบถ้วนข้อมูลนักเรียน</h1>
    <div class="small text-muted">ตรวจตามช่องบังคับของระบบ SET · นักเรียนที่ยังอยู่ในความดูแล<?= $filters['status_group'] === 'active' ? ' (เฉพาะกำลังรับบริการ)' : ' (ผู้สมัคร กำลังรับบริการ พักการเรียน)' ?></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">นักเรียนที่ตรวจ</div>
      <div class="fs-3 fw-semibold"><?= (int) $summary['students'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">ข้อมูลครบทุกหมวด</div>
      <div class="fs-3 fw-semibold text-success"><?= (int) $summary['complete'] ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">ยังไม่ครบ</div>
      <div class="fs-3 fw-semibold text-danger"><?= (int) ($summary['students'] - $summary['complete']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card shadow-sm h-100"><div class="card-body">
      <div class="text-muted small">ความครบถ้วนเฉลี่ย</div>
      <div class="fs-3 fw-semibold text-<?= e($color($avg)) ?>"><?= $avg ?>%</div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">จำนวนนักเรียนที่ยังขาดข้อมูล แยกตามหมวด <span class="small text-muted fw-normal">(คลิกเพื่อกรอง)</span></div>
  <div class="card-body d-flex flex-wrap gap-2">
    <a href="<?= e($link(['category' => null])) ?>" class="btn btn-sm <?= $filters['category'] === '' ? 'btn-secondary' : 'btn-outline-secondary' ?>">ทุกหมวด</a>
    <?php foreach (StudentCompleteness::CATEGORIES as $key => [$label, $icon]): ?>
      <?php if ($key === 'disability' && !$report['can_sensitive']) { continue; } ?>
      <?php $n = (int) $summary['by_category'][$key]; ?>
      <a href="<?= e($link(['category' => $key])) ?>" class="btn btn-sm <?= $filters['category'] === $key ? 'btn-primary' : 'btn-outline-primary' ?>">
        <i class="bi bi-<?= e($icon) ?> me-1"></i><?= e($label) ?> <span class="badge <?= $n > 0 ? 'text-bg-danger' : 'text-bg-light' ?>"><?= $n ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<form method="get" action="<?= e(url('/academic/students/completeness')) ?>" class="card shadow-sm mb-3">
  <div class="card-body row g-2 align-items-end">
    <?php if ($filters['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($filters['category']) ?>"><?php endif; ?>
    <div class="col-12 col-md-4">
      <label class="form-label" for="c-group">สถานะ</label>
      <select id="c-group" name="status_group" class="form-select">
        <option value="open">ผู้สมัคร + กำลังรับบริการ + พักการเรียน</option>
        <option value="active"<?= $filters['status_group'] === 'active' ? ' selected' : '' ?>>เฉพาะกำลังรับบริการ</option>
      </select>
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label" for="c-unit">หน่วยบริการ (ปีปัจจุบัน)</label>
      <select id="c-unit" name="department_id" class="form-select">
        <option value="">ทั้งหมด</option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int) $u['id'] ?>"<?= (int) $filters['department_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name_th']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-4 d-flex align-items-center gap-3">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="all" value="1" id="c-all"<?= $filters['only_incomplete'] ? '' : ' checked' ?>>
        <label class="form-check-label" for="c-all">แสดงคนที่ครบแล้วด้วย</label>
      </div>
      <button type="submit" class="btn btn-primary ms-auto"><i class="bi bi-funnel me-1"></i>กรอง</button>
    </div>
  </div>
</form>

<?php if ($report['cards'] !== []): ?>
  <section class="card shadow-sm border-warning mb-3">
    <div class="card-header bg-white fw-semibold">
      <i class="bi bi-credit-card-2-front me-1"></i>บัตรประจำตัวคนพิการหมดอายุ/ใกล้หมดอายุ (ภายใน <?= StudentLookup::CARD_EXPIRY_WARN_DAYS ?> วัน)
      <span class="badge text-bg-warning">ข้อมูลอ่อนไหว</span>
    </div>
    <ul class="list-group list-group-flush">
      <?php foreach ($report['cards'] as $c): ?>
        <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
          <div>
            <a href="<?= e(url('/academic/students/' . (int) $c['student']['id'] . '#health')) ?>" class="fw-semibold text-decoration-none"><?= e(StudentService::displayName($c['student'])) ?></a>
            <span class="font-monospace small text-muted"><?= e($c['student']['student_code']) ?></span>
            <?php if ($c['enrollment'] !== null): ?>
              <span class="small text-muted">· <?= e(StudentLookup::serviceLabel($c['enrollment']['service_type'])) ?><?= $c['enrollment']['department_name'] ? ' ' . e($c['enrollment']['department_name']) : '' ?></span>
            <?php endif; ?>
          </div>
          <div class="small">
            หมดอายุ <?= e(thai_date((string) $c['expires'], false, true)) ?>
            <?php if ($c['days'] < 0): ?>
              <span class="badge text-bg-danger">หมดแล้ว <?= abs((int) $c['days']) ?> วัน</span>
            <?php else: ?>
              <span class="badge text-bg-warning">อีก <?= (int) $c['days'] ?> วัน</span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php if ($report['rows'] === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success"></i>
    <?= $summary['students'] === 0 ? 'ไม่มีนักเรียนในขอบเขตข้อมูลของคุณ' : 'ไม่มีนักเรียนที่ยังขาดข้อมูลตามเงื่อนไข' ?>
  </div></div>
<?php else: ?>
  <p class="small text-muted mb-2">แสดง <?= count($report['rows']) ?> คน · เรียงจากข้อมูลครบน้อยที่สุด</p>
  <div class="card shadow-sm">
    <ul class="list-group list-group-flush">
      <?php foreach ($report['rows'] as $r): ?>
        <?php
        $s = $r['student'];
        $res = $r['result'];
        [$statusLabel, $statusColor] = StudentLookup::STATUSES[$s['status']];
        ?>
        <li class="list-group-item">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <a href="<?= e(url('/academic/students/' . (int) $s['id'] . '#completeness')) ?>" class="fw-semibold text-decoration-none"><?= e(StudentService::displayName($s)) ?></a>
              <span class="font-monospace small text-muted"><?= e($s['student_code']) ?></span>
              <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <div class="progress completeness-bar mini" role="progressbar" aria-label="ความครบถ้วน" aria-valuenow="<?= (int) $res['score'] ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-<?= e($color((int) $res['score'])) ?>" style="width: <?= (int) $res['score'] ?>%"></div>
              </div>
              <span class="small fw-semibold"><?= (int) $res['score'] ?>%</span>
              <?php if (can('academic.students.update_all')): ?>
                <a href="<?= e(url('/academic/students/' . (int) $s['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary" aria-label="แก้ไข"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($res['missing'] > 0): ?>
            <div class="small mt-1">
              <?php foreach ($res['categories'] as $key => $c): ?>
                <?php if ($c['skipped'] || $c['missing'] === [] || ($filters['category'] !== '' && $filters['category'] !== $key)) { continue; } ?>
                <div><span class="text-muted"><i class="bi bi-<?= e($c['icon']) ?> me-1"></i><?= e($c['label']) ?>:</span> <span class="text-danger"><?= e(implode(', ', $c['missing'])) ?></span></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($res['warnings'] !== []): ?>
            <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i><?= e(implode(' · ', $res['warnings'])) ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
