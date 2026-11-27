<?php

use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\StudentLookup;

/** @var array $works */
/** @var array $filters */
/** @var bool $mine */
/** @var bool $canListAll */
/** @var array $types */
/** @var array $years */

$query = static fn (array $over = []): string => http_build_query(array_filter(
    array_merge(['view' => $mine ? 'mine' : 'all'], $filters, $over),
    static fn ($v): bool => $v !== '' && $v !== null
));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-1">
      <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
      <li class="breadcrumb-item active" aria-current="page">รายการงานวิจัย</li>
    </ol></nav>
    <h1 class="h4 mb-0"><?= $mine ? 'งานวิจัยของฉัน' : 'งานวิจัยทั้งหมด' ?></h1>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canListAll): ?>
      <div class="btn-group" role="group" aria-label="มุมมองข้อมูล">
        <a href="<?= e(url('/academic/research/works?' . $query(['view' => 'all']))) ?>" class="btn btn-outline-primary<?= $mine ? '' : ' active' ?>">ทั้งหมด</a>
        <a href="<?= e(url('/academic/research/works?' . $query(['view' => 'mine']))) ?>" class="btn btn-outline-primary<?= $mine ? ' active' : '' ?>">เฉพาะของฉัน</a>
      </div>
    <?php endif; ?>
    <?php if (can(ResearchLookup::PERM_SUBMIT)): ?>
      <a href="<?= e(url('/academic/research/works/create')) ?>" class="btn btn-primary"><i class="bi bi-upload me-1"></i>ส่งงานวิจัย</a>
    <?php endif; ?>
  </div>
</div>

<form method="get" action="<?= e(url('/academic/research/works')) ?>" class="card shadow-sm mb-3">
  <input type="hidden" name="view" value="<?= $mine ? 'mine' : 'all' ?>">
  <div class="card-body row g-2 align-items-end">
    <div class="col-12 col-lg-4">
      <label class="form-label" for="f-q">ค้นหา</label>
      <input type="search" id="f-q" name="q" value="<?= e($filters['q']) ?>" class="form-control" placeholder="ชื่อเรื่อง, ชื่อผู้จัดทำ, คำสำคัญ">
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <label class="form-label" for="f-year">ปีการศึกษา</label>
      <select id="f-year" name="year_id" class="form-select">
        <option value="0"<?= $filters['year_id'] === 0 ? ' selected' : '' ?>>ทุกปี</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === $filters['year_id'] ? ' selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <label class="form-label" for="f-semester">ภาคเรียน</label>
      <select id="f-semester" name="semester" class="form-select">
        <option value="">ทุกภาคเรียน</option>
        <?php foreach (ResearchLookup::SEMESTERS as $k => $label): ?>
          <option value="<?= $k ?>"<?= $filters['semester'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <label class="form-label" for="f-status">สถานะ</label>
      <select id="f-status" name="status" class="form-select">
        <option value="">ทุกสถานะ</option>
        <?php foreach (ResearchLookup::STATUSES as $k => [$label]): ?>
          <option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <label class="form-label" for="f-type">ประเภทงานวิจัย</label>
      <select id="f-type" name="type_id" class="form-select">
        <option value="">ทุกประเภท</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === $filters['type_id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <label class="form-label" for="f-disability">ประเภทความพิการ</label>
      <select id="f-disability" name="disability" class="form-select">
        <option value="">ทุกประเภท</option>
        <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= $filters['disability'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-lg-4 d-flex gap-2">
      <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>ค้นหา</button>
      <a href="<?= e(url('/academic/research/works?view=' . ($mine ? 'mine' : 'all'))) ?>" class="btn btn-outline-secondary" title="ล้างตัวกรอง" aria-label="ล้างตัวกรอง"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<div class="card shadow-sm">
  <div class="card-header small text-body-secondary">พบ <?= number_format(count($works)) ?> รายการ</div>
  <?php if ($works === []): ?>
    <div class="card-body text-center text-body-secondary py-5">
      <i class="bi bi-journal-x fs-1 d-block mb-2"></i>ไม่พบงานวิจัยตามเงื่อนไข
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr>
          <th scope="col">ชื่องานวิจัย</th>
          <?php if (!$mine): ?><th scope="col">ผู้จัดทำ</th><?php endif; ?>
          <th scope="col">ประเภท</th>
          <th scope="col" class="text-nowrap">ปี/ภาคเรียน</th>
          <th scope="col">สถานะ</th>
          <th scope="col" class="text-nowrap">อัปเดตล่าสุด</th>
        </tr></thead>
        <tbody>
          <?php foreach ($works as $w): ?>
            <tr>
              <td class="min-w-0">
                <a href="<?= e(url('/academic/research/works/' . (int) $w['id'])) ?>" class="fw-semibold"><?= e($w['title']) ?></a>
                <div class="small text-body-secondary">
                  <?= e(implode(', ', array_map(static fn (string $d): string => StudentLookup::DISABILITY_TYPES[$d] ?? $d, $w['disabilities']))) ?>
                  · <?= number_format((int) $w['target_count']) ?> คน · v<?= (int) $w['current_version'] ?>
                </div>
              </td>
              <?php if (!$mine): ?><td><?= e($w['owner_name']) ?></td><?php endif; ?>
              <td class="small"><?= e($w['type_name']) ?></td>
              <td class="small text-nowrap"><?= e((string) $w['year_be']) ?>/<?= (int) $w['semester'] ?></td>
              <td><?= ResearchLookup::statusBadge((string) $w['status']) ?></td>
              <td class="small text-nowrap text-body-secondary"><?= e(time_ago($w['status_changed_at'] ?? $w['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
