<?php

use Core\Hmvc;
use Modules\CoreAdmin\Services\PdpaService;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\ReportService;
use Modules\CoreAdmin\Services\UserService;

/** @var array $stats */
/** @var array $departments */
/** @var array $typeTotals */
/** @var array $chart */
/** @var array $upcoming */
/** @var array|null $pdpa */
/** @var bool $canReport */
/** @var array $recent */

$personnelTotal = array_sum($typeTotals);
$tile = static function (string $icon, string $color, int|string $value, string $label, ?string $href = null): string {
    $body = '<div class="card-body"><i class="bi bi-' . e($icon) . ' text-' . e($color) . '"></i>'
        . '<div class="fs-3 fw-semibold">' . e($value) . '</div><div class="small text-muted">' . e($label) . '</div></div>';

    return $href !== null
        ? '<a href="' . e(url($href)) . '" class="card stat-card h-100 text-decoration-none shadow-sm">' . $body . '</a>'
        : '<div class="card stat-card h-100 shadow-sm">' . $body . '</div>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">แดชบอร์ด</h1>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="badge text-bg-light border"><?= e($stats['academic_year'] ?? '-') ?> · <?= e($stats['fiscal_year'] ?? '-') ?></span>
    <?php if ($canReport): ?>
      <a href="<?= e(url('/reports/overview')) ?>" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i>รายงานภาพรวม</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-xl-4"><?= Hmvc::call('core_admin', 'DashboardController', 'profileWidget') ?></div>
  <div class="col-12 col-xl-8">
    <div class="row g-3">
      <div class="col-6 col-md-4"><?= $tile('person-vcard', 'primary', $stats['personnel'], 'บุคลากรที่ปฏิบัติงาน', can('core.employees.read_all') ? '/personnel' : null) ?></div>
      <?php if ($stats['users'] !== null): ?>
        <div class="col-6 col-md-4"><?= $tile('people', 'primary', $stats['users'], 'ผู้ใช้ที่มองเห็น', '/users') ?></div>
      <?php endif; ?>
      <?php if ($stats['students'] !== null): ?>
        <div class="col-6 col-md-4"><?= $tile('mortarboard', 'primary', $stats['students'], 'นักเรียนที่รับบริการปีนี้', '/academic/students') ?></div>
      <?php else: ?>
        <div class="col-6 col-md-4"><?= $tile('diagram-3', 'success', $stats['departments'], 'ฝ่าย/งาน') ?></div>
      <?php endif; ?>
      <div class="col-6 col-md-4"><?= $tile('door-open', 'success', $stats['rooms'], 'ห้องบริการปีการศึกษานี้', can('core.service_rooms.read_all') ? '/service-rooms' : null) ?></div>
      <div class="col-6 col-md-4"><?= $tile('calendar-event', 'warning', $stats['events_month'], 'กิจกรรมเดือนนี้', can('core.calendar.read_all') ? '/calendar' : null) ?></div>
      <div class="col-6 col-md-4"><?= $tile('bell', $stats['unread'] > 0 ? 'danger' : 'secondary', $stats['unread'], 'แจ้งเตือนที่ยังไม่อ่าน', '/notifications') ?></div>
    </div>
  </div>
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
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-bar-chart me-1"></i>บุคลากรรายฝ่าย แยกตามประเภทการจ้าง</div>
      <div class="card-body">
        <?php if ($personnelTotal === 0): ?>
          <p class="text-muted mb-0">ยังไม่มีข้อมูลบุคลากรที่ปฏิบัติงาน</p>
        <?php else: ?>
          <div class="chart-box" style="height: <?= 60 + 44 * count($departments) ?>px">
            <canvas id="chart-personnel" role="img" aria-label="กราฟแท่งจำนวนบุคลากรรายฝ่าย แยกตามประเภทการจ้าง (ตัวเลขอยู่ในตารางสถิติรายฝ่ายด้านล่าง)"></canvas>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1"></i>สัดส่วนประเภทบุคลากร</div>
      <div class="card-body">
        <?php foreach (ReportService::employmentTypes() as $type => $label): ?>
          <?php $n = $typeTotals[$type]; $pct = $personnelTotal > 0 ? round($n * 100 / $personnelTotal) : 0; ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between small">
              <span><span class="legend-swatch" style="background: <?= e(ReportService::EMPLOYMENT_COLORS[$type]) ?>"></span><?= e($label) ?></span>
              <span class="fw-semibold"><?= e($n) ?> <span class="text-muted fw-normal">(<?= e($pct) ?>%)</span></span>
            </div>
            <div class="progress meter" role="progressbar" aria-label="<?= e($label) ?>" aria-valuenow="<?= e($pct) ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar" style="width: <?= e($pct) ?>%; background: <?= e(ReportService::EMPLOYMENT_COLORS[$type]) ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-grid-3x3-gap me-1"></i>สถิติรายฝ่าย</div>
  <div class="table-responsive d-none d-md-block">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>ฝ่าย/งาน</th><th class="text-end">บุคลากร</th><th class="text-end">ชาย</th><th class="text-end">หญิง</th>
          <th class="text-end">มีบัญชีผู้ใช้</th><th class="text-end">ไฟล์/เอกสาร</th><th class="text-end">กิจกรรมเดือนนี้</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($departments as $d): ?>
          <tr>
            <td><?= e($d['name']) ?></td>
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
        <div class="small text-muted">ชาย <?= e($d['male']) ?> · หญิง <?= e($d['female']) ?> · บัญชี <?= e($d['users']) ?> · ไฟล์ <?= e($d['files']) ?> · กิจกรรม <?= e($d['events']) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 <?= $chart['trend']['logins'] !== null ? 'col-xl-6' : 'col-xl-8' ?>">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-1"></i>ไฟล์และกิจกรรมรายเดือน (6 เดือนล่าสุด)</div>
      <div class="card-body"><div class="chart-box" style="height: 240px"><canvas id="chart-trend" role="img" aria-label="กราฟเส้นจำนวนไฟล์ที่อัปโหลดและกิจกรรมในปฏิทินกลางรายเดือน"></canvas></div></div>
    </div>
  </div>
  <?php if ($chart['trend']['logins'] !== null): ?>
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>การเข้าสู่ระบบสำเร็จรายเดือน</div>
        <div class="card-body"><div class="chart-box" style="height: 240px"><canvas id="chart-logins" role="img" aria-label="กราฟเส้นจำนวนการเข้าสู่ระบบสำเร็จรายเดือน"></canvas></div></div>
      </div>
    </div>
  <?php endif; ?>
  <div class="col-12 <?= $chart['trend']['logins'] !== null ? '' : 'col-xl-4' ?>">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-calendar-week me-1"></i>กิจกรรมที่กำลังจะมาถึง</span>
        <?php if (can('core.calendar.read_all')): ?><a href="<?= e(url('/calendar')) ?>" class="small">ปฏิทินกลาง</a><?php endif; ?>
      </div>
      <?php if ($upcoming === []): ?>
        <div class="card-body text-muted">ยังไม่มีกิจกรรม</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($upcoming as $ev): ?>
            <li class="list-group-item">
              <a href="<?= e(url('/calendar/' . (int) $ev['id'])) ?>" class="text-decoration-none fw-semibold"><?= e($ev['title']) ?></a>
              <div class="small text-muted"><?= e(thai_date((string) $ev['start_at'], (int) $ev['is_all_day'] !== 1)) ?><?= $ev['department_name'] ? ' · ' . e($ev['department_name']) : '' ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($recent !== []): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
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
