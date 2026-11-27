<?php

use Core\Hmvc;
use Core\Rbac;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\ReportService;

/** @var array $stats */
/** @var array $departments */
/** @var array $typeTotals */
/** @var array $chart */
/** @var array $upcoming */
/** @var array|null $pdpa */
/** @var bool $canReport */
/** @var array $recent */

$personnelTotal = array_sum($typeTotals);
$academicOn = in_array('academic', (array) config('app.modules', []), true);

// การ์ดสถิติ: ไอคอน 3D + เอียงตามเมาส์ (สีตามชุดสีที่เลือก: tone-1..6)
$tile = static function (string $icon, string $tone, int $value, string $label, ?string $href, int $i): string {
    $body = '<div class="card-body d-flex align-items-center gap-3">'
        . '<span class="icon-3d ' . e($tone) . '" aria-hidden="true"><i class="bi bi-' . e($icon) . '"></i></span>'
        . '<div class="min-w-0"><div class="stat-value" data-count="' . $value . '">' . e(number_format($value)) . '</div>'
        . '<div class="stat-label">' . e($label) . '</div></div>'
        . ($href !== null ? '<i class="bi bi-arrow-up-right stat-go" aria-hidden="true"></i>' : '')
        . '</div>';
    $attrs = ' class="card stat-card tilt neon-edge h-100 text-decoration-none rise-in" data-tilt style="--i: ' . $i . '"';

    return $href !== null ? '<a href="' . e(url($href)) . '"' . $attrs . '>' . $body . '</a>' : '<div' . $attrs . '>' . $body . '</div>';
};
$tiles = [
    ['person-vcard', 'tone-1', $stats['personnel'], 'บุคลากรที่ปฏิบัติงาน', can('core.employees.read_all') ? '/personnel' : null],
];
if ($stats['students'] !== null) {
    $tiles[] = ['mortarboard', 'tone-2', $stats['students'], 'นักเรียนที่รับบริการปีนี้', '/academic/students'];
}
if ($stats['users'] !== null) {
    $tiles[] = ['people', 'tone-3', $stats['users'], 'ผู้ใช้ที่มองเห็น', '/users'];
}
if ($stats['students'] === null || $stats['users'] === null) {
    $tiles[] = ['diagram-3', 'tone-4', $stats['departments'], 'ฝ่าย/งาน', null];
}
$tiles[] = ['door-open', 'tone-4', $stats['rooms'], 'ห้องบริการปีการศึกษานี้', can('core.service_rooms.read_all') ? '/service-rooms' : null];
$tiles[] = ['calendar-event', 'tone-5', $stats['events_month'], 'กิจกรรมเดือนนี้', can('core.calendar.read_all') ? '/calendar' : null];
$tiles[] = ['bell', $stats['unread'] > 0 ? 'tone-danger' : 'tone-6', $stats['unread'], 'แจ้งเตือนที่ยังไม่อ่าน', '/notifications'];
$tiles = array_slice($tiles, 0, 6);

// เมนูด่วน: งานที่ใช้บ่อยตามสิทธิ์ของผู้ใช้ (แสดงสูงสุด 6 ปุ่ม)
$quick = array_slice(array_values(array_filter([
    $academicOn && can('academic.students.create_all') ? ['person-plus', 'เพิ่มนักเรียน', '/academic/students/create'] : null,
    $academicOn && Rbac::scope('academic.students', 'read') !== null ? ['person-lines-fill', 'ทะเบียนนักเรียน', '/academic/students'] : null,
    can('core.employees.create_all') ? ['person-badge', 'เพิ่มบุคลากร', '/personnel/create'] : null,
    can('core.calendar.read_all') ? ['calendar-plus', 'เพิ่มกิจกรรม', '/calendar/create'] : null,
    can('core.files.upload_own') ? ['cloud-arrow-up', 'อัปโหลดไฟล์', '/files'] : null,
    $canReport ? ['file-earmark-bar-graph', 'รายงานภาพรวม', '/reports/overview'] : null,
    !in_array(Rbac::scope('core.users', 'read'), [null, 'own'], true) ? ['person-gear', 'ผู้ใช้งาน', '/users'] : null,
    ['bell', 'การแจ้งเตือน', '/notifications'],
    ['person-circle', 'โปรไฟล์ของฉัน', '/profile'],
])), 0, 6);

// ชุดสีกราฟประเภทการจ้าง = --chart-1..7 ของชุดสีที่เลือก (ลำดับคงที่ ผูกกับประเภท)
$typeKeys = array_keys(ReportService::employmentTypes());
$typeColor = static fn (string $type): string => 'var(--chart-' . ((array_search($type, $typeKeys, true) % 7) + 1) . ')';
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div>
    <h1 class="h3 mb-1">แดชบอร์ด</h1>
    <div class="text-body-secondary small">ภาพรวมการดำเนินงาน <?= e(Core\Setting::get('org.name', config('app.org'))) ?></div>
  </div>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-calendar3 me-1 text-accent"></i><?= e($stats['academic_year'] ?? '-') ?> · <?= e($stats['fiscal_year'] ?? '-') ?></span>
    <?php if ($canReport): ?>
      <a href="<?= e(url('/reports/overview')) ?>" class="btn btn-sm btn-primary px-3"><i class="bi bi-file-earmark-bar-graph me-1"></i>รายงานภาพรวม</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-xl-5"><?= Hmvc::call('core_admin', 'DashboardController', 'profileWidget') ?></div>
  <div class="col-12 col-xl-7">
    <div class="card h-100 rise-in" style="--i: 1">
      <div class="card-header d-flex align-items-center"><span><i class="bi bi-lightning-charge me-1"></i>เมนูด่วน</span></div>
      <div class="card-body">
        <div class="quick-grid">
          <?php foreach ($quick as $n => [$icon, $label, $href]): ?>
            <a href="<?= e(url($href)) ?>" class="quick-action lift neon-edge">
              <span class="icon-3d sm tone-<?= ($n % 6) + 1 ?>" aria-hidden="true"><i class="bi bi-<?= e($icon) ?>"></i></span>
              <span><?= e($label) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ($tiles as $i => [$icon, $tone, $value, $label, $href]): ?>
    <div class="col-6 col-md-4"><?= $tile($icon, $tone, (int) $value, $label, $href, $i) ?></div>
  <?php endforeach; ?>
</div>

<?php $license ??= ['own' => null, 'own_id' => null, 'expired' => 0, 'expiring' => 0, 'can_manage' => false]; ?>
<?php if ($license['own'] !== null): ?>
  <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <span class="fw-semibold"><?= e($license['own']['text']) ?></span>
    <a href="<?= e(url('/personnel/' . (int) $license['own_id'])) ?>" class="btn btn-sm btn-outline-danger ms-auto">ดูข้อมูลของฉัน</a>
  </div>
<?php endif; ?>
<?php if ($license['can_manage'] && ($license['expired'] + $license['expiring']) > 0): ?>
  <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-person-vcard fs-5"></i>
    <span>ใบประกอบวิชาชีพครู:
      <?php if ($license['expired'] > 0): ?>หมดอายุแล้ว <strong><?= (int) $license['expired'] ?></strong> คน<?php endif; ?>
      <?php if ($license['expired'] > 0 && $license['expiring'] > 0): ?> · <?php endif; ?>
      <?php if ($license['expiring'] > 0): ?>ใกล้หมดอายุ (ภายใน <?= PersonnelService::LICENSE_WARN_DAYS ?> วัน) <strong><?= (int) $license['expiring'] ?></strong> คน<?php endif; ?>
    </span>
    <a href="<?= e(url('/personnel?license=alert')) ?>" class="btn btn-sm btn-outline-danger ms-auto">ดูรายชื่อ</a>
  </div>
<?php endif; ?>
<?php $cards ??= null; ?>
<?php if ($cards !== null && ($cards['expired'] + $cards['expiring']) > 0): ?>
  <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-credit-card-2-front fs-5"></i>
    <span>บัตรประจำตัวคนพิการของนักเรียน:
      <?php if ($cards['expired'] > 0): ?>หมดอายุแล้ว <strong><?= (int) $cards['expired'] ?></strong> คน<?php endif; ?>
      <?php if ($cards['expired'] > 0 && $cards['expiring'] > 0): ?> · <?php endif; ?>
      <?php if ($cards['expiring'] > 0): ?>ใกล้หมดอายุ (ภายใน <?= Modules\Academic\Services\StudentLookup::CARD_EXPIRY_WARN_DAYS ?> วัน) <strong><?= (int) $cards['expiring'] ?></strong> คน<?php endif; ?>
    </span>
    <a href="<?= e(url('/academic/students/completeness')) ?>" class="btn btn-sm btn-outline-danger ms-auto">ดูรายชื่อ</a>
  </div>
<?php endif; ?>

<?php if ($pdpa !== null && ($pdpa['open'] > 0 || $pdpa['overdue'] > 0)): ?>
  <div class="alert <?= $pdpa['overdue'] > 0 ? 'alert-danger' : 'alert-warning' ?> d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-shield-exclamation fs-5"></i>
    <span>คำขอใช้สิทธิ์ PDPA ที่ยังไม่ปิด <strong><?= e($pdpa['open']) ?></strong> รายการ
      <?php if ($pdpa['overdue'] > 0): ?>· เกินกำหนด <strong><?= e($pdpa['overdue']) ?></strong> รายการ<?php endif; ?>
      <?php if ($pdpa['due_soon'] > 0): ?>· ครบกำหนดใน 7 วัน <strong><?= e($pdpa['due_soon']) ?></strong> รายการ<?php endif; ?>
    </span>
    <a href="<?= e(url('/pdpa/manage')) ?>" class="btn btn-sm btn-outline-dark ms-auto">จัดการคำขอ</a>
  </div>
<?php endif; ?>

<script type="application/json" id="dashboard-data"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="row g-3 mb-3">
  <div class="col-12 col-xl-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-1"></i>บุคลากรรายฝ่าย แยกตามประเภทการจ้าง</div>
      <div class="card-body">
        <?php if ($personnelTotal === 0): ?>
          <p class="text-body-secondary mb-0">ยังไม่มีข้อมูลบุคลากรที่ปฏิบัติงาน</p>
        <?php else: ?>
          <div class="chart-box" style="height: <?= 60 + 44 * count($departments) ?>px">
            <canvas id="chart-personnel" role="img" aria-label="กราฟแท่งจำนวนบุคลากรรายฝ่าย แยกตามประเภทการจ้าง (ตัวเลขอยู่ในตารางสถิติรายฝ่ายด้านล่าง)"></canvas>
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
  <div class="card-header"><i class="bi bi-grid-3x3-gap me-1"></i>สถิติรายฝ่าย</div>
  <div class="table-responsive d-none d-md-block">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>ฝ่าย/งาน</th><th class="text-end">บุคลากร</th><th class="text-end">ชาย</th><th class="text-end">หญิง</th>
          <th class="text-end">มีบัญชีผู้ใช้</th><th class="text-end">ไฟล์/เอกสาร</th><th class="text-end">กิจกรรมเดือนนี้</th>
        </tr>
      </thead>
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
            <td class="text-end"><?= e($d['users']) ?></td>
            <td class="text-end"><?= e($d['files']) ?></td>
            <td class="text-end"><?= e($d['events']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-semibold">
        <tr>
          <td>รวม</td>
          <?php foreach (['total', 'male', 'female', 'users', 'files', 'events'] as $k): ?>
            <td class="text-end"><?= e(array_sum(array_column($departments, $k))) ?></td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
  </div>
  <ul class="list-group list-group-flush d-md-none">
    <?php foreach ($departments as $d): ?>
      <li class="list-group-item">
        <div class="d-flex justify-content-between"><span class="fw-semibold"><?= e($d['name']) ?></span><span class="badge text-bg-primary"><?= e($d['total']) ?> คน</span></div>
        <div class="small text-body-secondary">ชาย <?= e($d['male']) ?> · หญิง <?= e($d['female']) ?> · บัญชี <?= e($d['users']) ?> · ไฟล์ <?= e($d['files']) ?> · กิจกรรม <?= e($d['events']) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 <?= $chart['trend']['logins'] !== null ? 'col-xl-6' : 'col-xl-8' ?>">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-graph-up me-1"></i>ไฟล์และกิจกรรมรายเดือน (6 เดือนล่าสุด)</div>
      <div class="card-body"><div class="chart-box" style="height: 240px"><canvas id="chart-trend" role="img" aria-label="กราฟพื้นที่จำนวนไฟล์ที่อัปโหลดและกิจกรรมในปฏิทินกลางรายเดือน"></canvas></div></div>
    </div>
  </div>
  <?php if ($chart['trend']['logins'] !== null): ?>
    <div class="col-12 col-xl-6">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-box-arrow-in-right me-1"></i>การเข้าสู่ระบบสำเร็จรายเดือน</div>
        <div class="card-body"><div class="chart-box" style="height: 240px"><canvas id="chart-logins" role="img" aria-label="กราฟแท่งจำนวนการเข้าสู่ระบบสำเร็จรายเดือน"></canvas></div></div>
      </div>
    </div>
  <?php endif; ?>
  <div class="col-12 <?= $chart['trend']['logins'] !== null ? '' : 'col-xl-4' ?>">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-calendar-week me-1"></i>กิจกรรมที่กำลังจะมาถึง</span>
        <?php if (can('core.calendar.read_all')): ?><a href="<?= e(url('/calendar')) ?>" class="small">ปฏิทินกลาง</a><?php endif; ?>
      </div>
      <?php if ($upcoming === []): ?>
        <div class="card-body text-body-secondary">ยังไม่มีกิจกรรม</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($upcoming as $ev): ?>
            <li class="list-group-item d-flex gap-3 align-items-start">
              <span class="event-date" aria-hidden="true"><?= e(date('j', strtotime((string) $ev['start_at']))) ?></span>
              <div class="min-w-0">
                <a href="<?= e(url('/calendar/' . (int) $ev['id'])) ?>" class="text-decoration-none fw-semibold"><?= e($ev['title']) ?></a>
                <div class="small text-body-secondary"><?= e(thai_date((string) $ev['start_at'], (int) $ev['is_all_day'] !== 1)) ?><?= $ev['department_name'] ? ' · ' . e($ev['department_name']) : '' ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($recent !== []): ?>
  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span><i class="bi bi-journal-text me-1"></i>กิจกรรมล่าสุด (Audit Log)</span>
      <a href="<?= e(url('/audit-logs')) ?>" class="small">ดูทั้งหมด</a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light"><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th class="d-none d-md-table-cell">IP</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td class="text-nowrap small"><?= e(thai_date((string) $row['created_at'])) ?></td>
              <td><?= e($row['username'] ?? '-') ?></td>
              <td><code><?= e($row['action']) ?></code></td>
              <td class="d-none d-md-table-cell small"><?= e($row['ip_address']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" defer></script>
<script src="<?= e(asset('js/dashboard.js')) ?>" defer></script>
