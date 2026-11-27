<?php

use Modules\Academic\Services\InnovationLookup;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\StudentLookup;

/**
 * แดชบอร์ดงานวิจัยและพัฒนานวัตกรรม
 * มุมมอง "ทั้งหมด" ของครูทั่วไปแสดงเฉพาะตัวเลขสรุปและกราฟ (ไม่มีรายละเอียดหรือไฟล์ของผู้อื่น)
 */
/** @var array $filters */
/** @var bool $mine */
/** @var array $stats */
/** @var array $recent */
/** @var array $recentMedia */
/** @var array $mediaTypes */
/** @var bool $canListAll */
/** @var array $types */
/** @var array $years */
/** @var array|null $round */

$query = static fn (array $over = []): string => http_build_query(array_filter(
    array_merge(['view' => $mine ? 'mine' : 'all'], $filters, $over),
    static fn ($v): bool => $v !== '' && $v !== null
));
$cards = $stats['cards'];
$tiles = [
    ['research_total', 'journal-text', 'tone-1', 'จำนวนงานวิจัย'],
    ['media_total', 'lightbulb', 'tone-2', 'จำนวนสื่อนวัตกรรม'],
    ['pending_review', 'hourglass-split', 'tone-3', 'งานรอตรวจ'],
    ['revision', 'arrow-return-left', 'tone-4', 'งานที่ต้องแก้ไข'],
    ['approved', 'patch-check', 'tone-1', 'งานผ่านการตรวจ/อนุมัติแล้ว'],
    ['in_use', 'easel', 'tone-2', 'สื่ออยู่ระหว่างใช้'],
    ['overdue_reports', 'alarm', 'tone-3', 'รายงานการใช้สื่อเลยกำหนด'],
];
if ($mine) {
    $tiles[] = ['draft', 'pencil-square', 'tone-1', 'ร่างที่ยังไม่ส่ง'];
}
$json = static fn (mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$daysLeft = $round !== null ? (int) floor((strtotime((string) $round['due_on']) - strtotime(date('Y-m-d'))) / 86400) : null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">งานวิจัยและพัฒนานวัตกรรม</h1>
    <div class="small text-body-secondary">ฝ่ายบริหารวิชาการ · ข้อมูลอัปเดตอัตโนมัติ <span data-research-updated></span></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <div class="btn-group" role="group" aria-label="มุมมองข้อมูล">
      <a href="<?= e(url('/academic/research?' . $query(['view' => 'all']))) ?>" class="btn btn-outline-primary<?= $mine ? '' : ' active' ?>"<?= $mine ? '' : ' aria-current="page"' ?>>ทั้งหมด</a>
      <a href="<?= e(url('/academic/research?' . $query(['view' => 'mine']))) ?>" class="btn btn-outline-primary<?= $mine ? ' active' : '' ?>"<?= $mine ? ' aria-current="page"' : '' ?>>เฉพาะของฉัน</a>
    </div>
    <a href="<?= e(url('/academic/research/works' . ($canListAll && !$mine ? '' : '?view=mine'))) ?>" class="btn btn-outline-secondary"><i class="bi bi-list-ul me-1"></i><?= $canListAll ? 'รายการงานวิจัย' : 'งานวิจัยของฉัน' ?></a>
    <a href="<?= e(url('/academic/innovation/media' . ($canListAll && !$mine ? '' : '?view=mine'))) ?>" class="btn btn-outline-secondary"><i class="bi bi-lightbulb me-1"></i><?= $canListAll ? 'รายการสื่อนวัตกรรม' : 'สื่อนวัตกรรมของฉัน' ?></a>
    <?php if (can(ResearchLookup::PERM_SUBMIT)): ?>
      <a href="<?= e(url('/academic/research/works/create')) ?>" class="btn btn-primary"><i class="bi bi-upload me-1"></i>ส่งงานวิจัย</a>
      <a href="<?= e(url('/academic/innovation/media/create')) ?>" class="btn btn-primary"><i class="bi bi-lightbulb me-1"></i>ส่งสื่อนวัตกรรม</a>
    <?php endif; ?>
  </div>
</div>

<form method="get" action="<?= e(url('/academic/research')) ?>" class="card shadow-sm mb-3">
  <input type="hidden" name="view" value="<?= $mine ? 'mine' : 'all' ?>">
  <div class="card-body row g-2 align-items-end">
    <div class="col-6 col-md-3 col-xl-2">
      <label class="form-label" for="f-year">ปีการศึกษา</label>
      <select id="f-year" name="year_id" class="form-select">
        <option value="0"<?= $filters['year_id'] === 0 ? ' selected' : '' ?>>ทุกปีการศึกษา</option>
        <?php foreach ($years as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === $filters['year_id'] ? ' selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <label class="form-label" for="f-semester">ภาคเรียน</label>
      <select id="f-semester" name="semester" class="form-select">
        <option value="">ทุกภาคเรียน</option>
        <?php foreach (ResearchLookup::SEMESTERS as $k => $label): ?>
          <option value="<?= $k ?>"<?= $filters['semester'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="f-type">ประเภทงานวิจัย</label>
      <select id="f-type" name="type_id" class="form-select">
        <option value="">ทุกประเภท</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === $filters['type_id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="f-mtype">ประเภทสื่อนวัตกรรม</label>
      <select id="f-mtype" name="media_type_id" class="form-select">
        <option value="">ทุกประเภท</option>
        <?php foreach ($mediaTypes as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === $filters['media_type_id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-xl-2">
      <label class="form-label" for="f-disability">ประเภทความพิการ</label>
      <select id="f-disability" name="disability" class="form-select">
        <option value="">ทุกประเภท</option>
        <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= $filters['disability'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-xl-2 d-flex gap-2 ms-xl-auto">
      <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>กรอง</button>
      <a href="<?= e(url('/academic/research?view=' . ($mine ? 'mine' : 'all'))) ?>" class="btn btn-outline-secondary" title="ล้างตัวกรอง" aria-label="ล้างตัวกรอง"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</form>

<?php if ($round !== null): ?>
  <div class="alert alert-<?= $daysLeft < 0 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'info') ?> d-flex align-items-center gap-2">
    <i class="bi bi-calendar-event"></i>
    <div><strong><?= e($round['title']) ?></strong> · ครบกำหนดส่ง <?= e(thai_date((string) $round['due_on'], false, true)) ?>
      (<?= $daysLeft < 0 ? 'เลยกำหนด ' . abs($daysLeft) . ' วัน' : ($daysLeft === 0 ? 'วันนี้' : 'อีก ' . $daysLeft . ' วัน') ?>)</div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <?php foreach ($tiles as $i => [$key, $icon, $tone, $label]): ?>
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100 rise-in" style="--i: <?= $i ?>">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="icon-3d <?= e($tone) ?>" aria-hidden="true"><i class="bi bi-<?= e($icon) ?>"></i></span>
          <div class="min-w-0"><div class="stat-value" data-research-card="<?= e($key) ?>"><?= e(number_format((int) $cards[$key])) ?></div>
            <div class="stat-label"><?= e($label) ?></div></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script type="application/json" id="research-dashboard-data"><?= $json([
    'stats' => $stats,
    'endpoint' => url('/academic/research/dashboard-data?' . $query()),
]) ?></script>

<div class="row g-3 mb-3">
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i>สัดส่วนงานวิจัยตามสถานะ</div>
      <div class="card-body">
        <div class="chart-box doughnut-box mb-3"><canvas id="chart-research-status" role="img" aria-label="กราฟวงกลมสัดส่วนงานวิจัยตามสถานะ (ตัวเลขอยู่ในรายการด้านล่าง)"></canvas></div>
        <ul class="list-unstyled small mb-0 row row-cols-2 g-1" data-research-status-list>
          <?php foreach ($stats['research']['status'] as $i => $s): ?>
            <li class="d-flex justify-content-between gap-2"><span><span class="legend-swatch" style="background: var(--chart-<?= $i % 7 + 1 ?>)"></span><?= e($s['label']) ?></span><strong data-status-count="<?= e($s['key']) ?>"><?= (int) $s['count'] ?></strong></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-1"></i>จำนวนงานวิจัยแยกตามประเภท</div>
      <div class="card-body"><div class="chart-box" style="height: 260px"><canvas id="chart-research-types" role="img" aria-label="กราฟแท่งจำนวนงานวิจัยแยกตามประเภท"></canvas></div></div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i>สัดส่วนสื่อนวัตกรรมตามสถานะ</div>
      <div class="card-body">
        <div class="chart-box doughnut-box mb-3"><canvas id="chart-media-status" role="img" aria-label="กราฟวงกลมสัดส่วนสื่อนวัตกรรมตามสถานะ (ตัวเลขอยู่ในรายการด้านล่าง)"></canvas></div>
        <ul class="list-unstyled small mb-0 row row-cols-2 g-1">
          <?php foreach ($stats['media']['status'] as $i => $s): ?>
            <li class="d-flex justify-content-between gap-2"><span><span class="legend-swatch" style="background: var(--chart-<?= $i % 7 + 1 ?>)"></span><?= e($s['label']) ?></span><strong data-media-status-count="<?= e($s['key']) ?>"><?= (int) $s['count'] ?></strong></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-1"></i>จำนวนสื่อนวัตกรรมแยกตามประเภท</div>
      <div class="card-body"><div class="chart-box" style="height: 260px"><canvas id="chart-media-types" role="img" aria-label="กราฟแท่งจำนวนสื่อนวัตกรรมแยกตามประเภท"></canvas></div></div>
    </div>
  </div>
  <div class="col-12 col-xl-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-people me-1"></i>กลุ่มเป้าหมายของสื่อนวัตกรรม: ตามแผน / ใช้จริง (คน)</div>
      <div class="card-body"><div class="chart-box" style="height: 380px"><canvas id="chart-media-targets" role="img" aria-label="กราฟแท่งจำนวนกลุ่มเป้าหมายตามแผนเทียบกับใช้จริง แยกตามประเภทความพิการ"></canvas></div>
        <div class="form-text">นับเป็นจำนวนคนเท่านั้น ไม่มีข้อมูลระบุตัวผู้เรียน · ใช้จริงนับจากรายงานการใช้สื่อที่ส่งแล้ว</div></div>
    </div>
  </div>
  <div class="col-12 col-xl-6">
    <div class="card shadow-sm h-100">
      <div class="card-header"><i class="bi bi-bar-chart-steps me-1"></i>จำนวนชิ้นงานแยกตาม 9 ประเภทความพิการของกลุ่มเป้าหมาย</div>
      <div class="card-body"><div class="chart-box" style="height: 380px"><canvas id="chart-research-disabilities" role="img" aria-label="กราฟแท่งซ้อนจำนวนงานวิจัยและสื่อนวัตกรรมแยกตามประเภทความพิการ"></canvas></div>
        <div class="form-text">งาน 1 ชิ้นอาจมีกลุ่มเป้าหมายหลายประเภทความพิการ จึงนับในทุกประเภทที่เลือก</div></div>
    </div>
  </div>
</div>

<?php if ($recent !== []): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-clock-history me-1"></i><?= $mine ? 'งานวิจัยล่าสุดของฉัน' : 'งานวิจัยที่อัปเดตล่าสุด' ?></span>
      <a href="<?= e(url('/academic/research/works?' . $query())) ?>" class="small">ดูทั้งหมด</a>
    </div>
    <div class="list-group list-group-flush">
      <?php foreach ($recent as $w): ?>
        <a href="<?= e(url('/academic/research/works/' . (int) $w['id'])) ?>" class="list-group-item list-group-item-action d-flex flex-wrap gap-2 align-items-center">
          <span class="flex-grow-1 min-w-0">
            <span class="fw-semibold d-block text-truncate"><?= e($w['title']) ?></span>
            <span class="small text-body-secondary"><?= e($w['owner_name']) ?> · <?= e($w['type_name']) ?> · <?= e(time_ago($w['status_changed_at'] ?? $w['created_at'])) ?></span>
          </span>
          <?= ResearchLookup::statusBadge((string) $w['status']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($recentMedia !== []): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-lightbulb me-1"></i><?= $mine ? 'สื่อนวัตกรรมล่าสุดของฉัน' : 'สื่อนวัตกรรมที่อัปเดตล่าสุด' ?></span>
      <a href="<?= e(url('/academic/innovation/media?' . $query())) ?>" class="small">ดูทั้งหมด</a>
    </div>
    <div class="list-group list-group-flush">
      <?php foreach ($recentMedia as $m): ?>
        <a href="<?= e(url('/academic/innovation/media/' . (int) $m['id'])) ?>" class="list-group-item list-group-item-action d-flex flex-wrap gap-2 align-items-center">
          <span class="flex-grow-1 min-w-0">
            <span class="fw-semibold d-block text-truncate"><?= e($m['title']) ?></span>
            <span class="small text-body-secondary"><?= e($m['owner_name']) ?> · <?= e($m['type_name']) ?> · <?= e(time_ago($m['status_changed_at'] ?? $m['created_at'])) ?></span>
          </span>
          <?= InnovationLookup::statusBadge((string) $m['status']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($recent === [] && $recentMedia === [] && !$mine && !$canListAll): ?>
  <p class="small text-body-secondary"><i class="bi bi-shield-lock me-1"></i>มุมมอง "ทั้งหมด" แสดงเฉพาะตัวเลขสรุป รายละเอียดและไฟล์ดูได้เฉพาะงานของตนเอง</p>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" defer></script>
<script src="<?= e(asset('js/research-dashboard.js')) ?>" defer></script>
