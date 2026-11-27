<?php

use Modules\CoreAdmin\Services\ReportService;

/**
 * แดชบอร์ดสาธารณะ (ผู้ใช้งานทั่วไป ไม่ต้องเข้าสู่ระบบ)
 * แสดงเฉพาะตัวเลขสรุป: ห้ามเพิ่มข้อมูลรายบุคคล ชื่อนักเรียน/บุคลากร ข้อมูลความพิการรายคน หรือรายละเอียดกิจกรรมภายใน
 */
/** @var array $stats */
/** @var array $departments */
/** @var array $typeTotals */
/** @var array $chart */

$personnelTotal = array_sum($typeTotals);
$orgName = (string) Core\Setting::get('org.name', config('app.org'));
$tile = static function (string $icon, string $tone, int $value, string $label, int $i): string {
    return '<div class="card stat-card tilt neon-edge h-100 rise-in" data-tilt style="--i: ' . $i . '">'
        . '<div class="card-body d-flex align-items-center gap-3">'
        . '<span class="icon-3d ' . e($tone) . '" aria-hidden="true"><i class="bi bi-' . e($icon) . '"></i></span>'
        . '<div class="min-w-0"><div class="stat-value" data-count="' . $value . '">' . e(number_format($value)) . '</div>'
        . '<div class="stat-label">' . e($label) . '</div></div></div></div>';
};
$tiles = [['person-vcard', 'tone-1', $stats['personnel'], 'บุคลากรที่ปฏิบัติงาน']];
if ($stats['students'] !== null) {
    $tiles[] = ['mortarboard', 'tone-2', $stats['students'], 'นักเรียนที่รับบริการปีการศึกษานี้'];
}
$tiles[] = ['geo-alt', 'tone-3', $stats['units'], 'หน่วยบริการ'];
$tiles[] = ['door-open', 'tone-4', $stats['rooms'], 'ห้องบริการปีการศึกษานี้'];
$typeKeys = array_keys(ReportService::employmentTypes());
$typeColor = static fn (string $type): string => 'var(--chart-' . ((array_search($type, $typeKeys, true) % 7) + 1) . ')';
?>
<section class="card hero-card public-hero mb-3 rise-in">
  <div class="card-body p-4 p-md-5 d-flex flex-wrap align-items-center gap-4">
    <div class="flex-grow-1 min-w-0">
      <div class="small text-body-secondary mb-1"><?= e(thai_date(date('Y-m-d'), false)) ?> · <?= e($stats['academic_year'] ?? '-') ?> · <?= e($stats['fiscal_year'] ?? '-') ?></div>
      <h1 class="h2 mb-2"><span class="text-gradient"><?= e(app_name('brand_main')) ?></span> <?= e(app_name('brand_sub')) ?></h1>
      <p class="text-body-secondary mb-0"><?= e(implode(' · ', array_filter([$orgName, app_name('tagline')], 'strlen'))) ?></p>
    </div>
    <a href="<?= e(url('/login')) ?>" class="btn btn-primary btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบสำหรับบุคลากร</a>
  </div>
</section>

<div class="row g-3 mb-3">
  <?php foreach ($tiles as $i => [$icon, $tone, $value, $label]): ?>
    <div class="col-6 col-lg-3"><?= $tile($icon, $tone, (int) $value, $label, $i) ?></div>
  <?php endforeach; ?>
</div>

<script type="application/json" id="dashboard-data"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="row g-3 mb-3">
  <div class="col-12 col-xl-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-1"></i>บุคลากรรายฝ่าย แยกตามประเภทการจ้าง</div>
      <div class="card-body">
        <?php if ($personnelTotal === 0): ?>
          <p class="text-body-secondary mb-0">ยังไม่มีข้อมูลบุคลากร</p>
        <?php else: ?>
          <div class="chart-box" style="height: <?= 60 + 44 * count($departments) ?>px">
            <canvas id="chart-personnel" role="img" aria-label="กราฟแท่งจำนวนบุคลากรรายฝ่าย แยกตามประเภทการจ้าง (ตัวเลขอยู่ในตารางด้านล่าง)"></canvas>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-1"></i>สัดส่วนประเภทบุคลากร</div>
      <div class="card-body">
        <?php if ($personnelTotal > 0): ?>
          <div class="chart-box doughnut-box mb-3">
            <canvas id="chart-types" role="img" aria-label="กราฟโดนัทสัดส่วนประเภทบุคลากร (ตัวเลขอยู่ในรายการด้านล่าง)"></canvas>
            <div class="doughnut-center"><div class="stat-value" data-count="<?= (int) $personnelTotal ?>"><?= e($personnelTotal) ?></div><div class="small text-body-secondary">คน</div></div>
          </div>
        <?php endif; ?>
        <?php foreach (ReportService::employmentTypes() as $type => $label): ?>
          <?php $n = $typeTotals[$type]; $pct = $personnelTotal > 0 ? round($n * 100 / $personnelTotal) : 0; ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between small">
              <span><span class="legend-swatch" style="background: <?= e($typeColor($type)) ?>"></span><?= e($label) ?></span>
              <span class="fw-semibold"><?= e($n) ?> <span class="text-body-secondary fw-normal">(<?= e($pct) ?>%)</span></span>
            </div>
            <div class="progress meter" role="progressbar" aria-label="<?= e($label) ?>" aria-valuenow="<?= e($pct) ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar" style="width: <?= e($pct) ?>%; background: <?= e($typeColor($type)) ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-grid-3x3-gap me-1"></i>บุคลากรรายฝ่าย</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light"><tr><th>ฝ่าย/งาน</th><th class="text-end">บุคลากร</th><th class="text-end">ชาย</th><th class="text-end">หญิง</th></tr></thead>
      <tbody>
        <?php $maxTotal = max([1, ...array_column($departments, 'total')]); ?>
        <?php foreach ($departments as $d): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($d['name']) ?></div>
              <div class="progress meter mt-1" style="height: 4px; max-width: 220px;" aria-hidden="true"><div class="progress-bar" style="width: <?= e(round($d['total'] * 100 / $maxTotal)) ?>%"></div></div>
            </td>
            <td class="text-end fw-semibold"><?= e($d['total']) ?></td>
            <td class="text-end"><?= e($d['male']) ?></td>
            <td class="text-end"><?= e($d['female']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-semibold">
        <tr><td>รวม</td>
          <?php foreach (['total', 'male', 'female'] as $k): ?><td class="text-end"><?= e(array_sum(array_column($departments, $k))) ?></td><?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" defer></script>
<script src="<?= e(asset('js/dashboard.js')) ?>" defer></script>
