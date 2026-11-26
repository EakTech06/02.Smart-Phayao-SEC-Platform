<?php

use Modules\CoreAdmin\Services\FileService;
use Modules\CoreAdmin\Services\PdpaService;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\ReportService;
use Modules\CoreAdmin\Services\UserService;

/** @var array $report */
/** @var array $filters */
/** @var array $periodOptions */
/** @var array $departmentOptions */
/** @var string $orgName */

$p = $report['period'];
$u = $report['users'];
$types = ReportService::employmentTypes();
$personnel = $report['personnel'];
$personnelTotal = array_sum(array_column($personnel, 'total'));
$query = http_build_query(array_filter([
    'period' => $filters['period'],
    'from' => $filters['period'] === ReportService::PERIOD_CUSTOM ? $filters['from'] : null,
    'to' => $filters['period'] === ReportService::PERIOD_CUSTOM ? $filters['to'] : null,
    'department_id' => $filters['department_id'],
]));
$scopeLabel = $report['org_wide'] ? 'ทั้งหน่วยงาน' : implode(', ', array_column($report['departments_in_scope'], 'name_th'));
$pct = static fn (int $n, int $total): string => $total > 0 ? number_format($n * 100 / $total, 1) . '%' : '-';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
  <h1 class="h4 mb-0"><i class="bi bi-file-earmark-bar-graph me-1"></i>รายงานภาพรวมหน่วยงาน</h1>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-secondary" data-action="print"><i class="bi bi-printer me-1"></i>พิมพ์ / PDF</button>
    <a href="<?= e(url('/reports/overview/export' . ($query !== '' ? '?' . $query : ''))) ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>
  </div>
</div>

<form method="get" action="<?= e(url('/reports/overview')) ?>" class="card shadow-sm mb-3 d-print-none" id="report-filter">
  <div class="card-body row g-2 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label" for="f-period">ช่วงเวลา</label>
      <select id="f-period" name="period" class="form-select">
        <optgroup label="ปีการศึกษา">
          <?php foreach ($periodOptions['academic'] as $y): ?>
            <option value="ay-<?= (int) $y['id'] ?>" <?= $filters['period'] === 'ay-' . $y['id'] ? 'selected' : '' ?>><?= e($y['name']) ?><?= (int) $y['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="ปีงบประมาณ">
          <?php foreach ($periodOptions['fiscal'] as $y): ?>
            <option value="fy-<?= (int) $y['id'] ?>" <?= $filters['period'] === 'fy-' . $y['id'] ? 'selected' : '' ?>><?= e($y['name']) ?><?= (int) $y['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="อื่น ๆ">
          <option value="<?= e(ReportService::PERIOD_MONTH) ?>" <?= $filters['period'] === ReportService::PERIOD_MONTH ? 'selected' : '' ?>>เดือนนี้</option>
          <option value="<?= e(ReportService::PERIOD_CUSTOM) ?>" <?= $filters['period'] === ReportService::PERIOD_CUSTOM ? 'selected' : '' ?>>กำหนดเอง</option>
        </optgroup>
      </select>
    </div>
    <div class="col-6 col-md-2 custom-range">
      <label class="form-label" for="f-from">ตั้งแต่</label>
      <input type="date" id="f-from" name="from" class="form-control" value="<?= e($filters['from']) ?>">
    </div>
    <div class="col-6 col-md-2 custom-range">
      <label class="form-label" for="f-to">ถึง</label>
      <input type="date" id="f-to" name="to" class="form-control" value="<?= e($filters['to']) ?>">
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label" for="f-dept">ฝ่าย/งาน</label>
      <select id="f-dept" name="department_id" class="form-select">
        <option value=""><?= $report['org_wide'] || count($departmentOptions) > 1 ? 'ทุกฝ่ายในสิทธิ์' : 'ฝ่ายของฉัน' ?></option>
        <?php foreach ($departmentOptions as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $filters['department_id'] === $d['id'] ? 'selected' : '' ?>><?= e($d['name_th']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i><span class="d-md-none ms-1">แสดงรายงาน</span></button>
    </div>
  </div>
</form>

<div class="report-paper card shadow-sm">
  <div class="card-body">
    <div class="text-center mb-4">
      <h2 class="h5 mb-1">รายงานภาพรวม<?= e($orgName) ?></h2>
      <div><?= e($p['label']) ?> (<?= e(thai_date($p['from'], false, true)) ?> – <?= e(thai_date($p['to'], false, true)) ?>)</div>
      <div class="small text-muted">ขอบเขต: <?= e($scopeLabel) ?> · ออกรายงานเมื่อ <?= e(thai_date($report['generated_at'])) ?></div>
    </div>

    <!-- 1. สรุป -->
    <section class="report-section mb-4">
      <h3 class="h6 fw-semibold border-bottom pb-1">1. สรุปภาพรวม</h3>
      <div class="row g-2 row-cols-2 row-cols-md-4">
        <?php
        $summary = [
            ['บุคลากรที่ปฏิบัติงาน', $personnelTotal, 'คน'],
            ['บัญชีผู้ใช้ที่ใช้งานได้', $u['active'], 'บัญชี'],
            ['ห้องบริการ' . ($report['rooms']['year'] ? ' ' . $report['rooms']['year'] : ''), $report['rooms']['total'], 'ห้อง'],
            ['กิจกรรมในปฏิทินกลาง', $report['calendar']['total'], 'กิจกรรม'],
            ['ไฟล์ที่อัปโหลด', $report['files']['total'], 'ไฟล์ (' . FileService::humanSize($report['files']['bytes']) . ')'],
            ['เข้าสู่ระบบในช่วงเวลา', $u['logged_in'], 'บัญชี'],
        ];
        if ($report['pdpa'] !== null) {
            $summary[] = ['คำขอใช้สิทธิ์ PDPA', $report['pdpa']['total'], 'คำขอ'];
        }
        if ($report['logins'] !== null) {
            $summary[] = ['เข้าสู่ระบบไม่สำเร็จ', $report['logins']['failed'], 'ครั้ง'];
        }
        ?>
        <?php foreach ($summary as [$label, $value, $unit]): ?>
          <div class="col">
            <div class="border rounded p-2 h-100">
              <div class="small text-muted"><?= e($label) ?></div>
              <div class="fs-4 fw-semibold"><?= e(number_format((int) $value)) ?> <span class="fs-6 fw-normal text-muted"><?= e($unit) ?></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 2. บุคลากรรายฝ่าย -->
    <section class="report-section mb-4">
      <h3 class="h6 fw-semibold border-bottom pb-1">2. บุคลากรที่ปฏิบัติงาน จำแนกตามฝ่ายและประเภทการจ้าง</h3>
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle report-table mb-1">
          <thead class="table-light">
            <tr>
              <th>ฝ่าย/งาน</th>
              <?php foreach ($types as $label): ?><th class="text-end"><?= e($label) ?></th><?php endforeach; ?>
              <th class="text-end">ชาย</th><th class="text-end">หญิง</th><th class="text-end">รวม</th><th class="text-end">ร้อยละ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($personnel as $row): ?>
              <tr>
                <td><?= e($row['name']) ?></td>
                <?php foreach (array_keys($types) as $t): ?><td class="text-end"><?= e($row['by_type'][$t] ?? 0) ?></td><?php endforeach; ?>
                <td class="text-end"><?= e($row['male']) ?></td>
                <td class="text-end"><?= e($row['female']) ?></td>
                <td class="text-end fw-semibold"><?= e($row['total']) ?></td>
                <td class="text-end"><?= e($pct($row['total'], $personnelTotal)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-semibold">
            <tr>
              <td>รวมทั้งสิ้น</td>
              <?php foreach (array_keys($types) as $t): ?>
                <td class="text-end"><?= e(array_sum(array_map(static fn (array $r): int => $r['by_type'][$t] ?? 0, $personnel))) ?></td>
              <?php endforeach; ?>
              <td class="text-end"><?= e(array_sum(array_column($personnel, 'male'))) ?></td>
              <td class="text-end"><?= e(array_sum(array_column($personnel, 'female'))) ?></td>
              <td class="text-end"><?= e($personnelTotal) ?></td>
              <td class="text-end"><?= $personnelTotal > 0 ? '100.0%' : '-' ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="small text-muted">นับเฉพาะบุคลากรสถานะ "ปฏิบัติงาน" ณ วันที่ออกรายงาน</div>
    </section>

    <!-- 3. บุคลากรตามมิติ -->
    <section class="report-section mb-4">
      <h3 class="h6 fw-semibold border-bottom pb-1">3. บุคลากรจำแนกตามสถานะ วิทยฐานะ และวุฒิการศึกษา</h3>
      <div class="row g-3">
        <?php
        $groups = [
            'สถานะ (ทุกสถานะ)' => array_combine(
                array_map(static fn (string $k): string => PersonnelService::STATUSES[$k] ?? $k, array_keys($report['breakdown']['status'])),
                array_values($report['breakdown']['status'])
            ),
            'วิทยฐานะ' => $report['breakdown']['academic_rank'],
            'วุฒิการศึกษา' => $report['breakdown']['education_level'],
        ];
        ?>
        <?php foreach ($groups as $title => $rows): ?>
          <div class="col-12 col-md-4">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light"><tr><th><?= e($title) ?></th><th class="text-end">คน</th></tr></thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="2" class="text-muted">ไม่มีข้อมูล</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $label => $n): ?>
                  <tr><td><?= e($label) ?></td><td class="text-end"><?= e($n) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 4. บัญชีผู้ใช้ -->
    <section class="report-section mb-4">
      <h3 class="h6 fw-semibold border-bottom pb-1">4. บัญชีผู้ใช้งานระบบ</h3>
      <table class="table table-sm table-bordered mb-1">
        <tbody>
          <tr><td>บัญชีทั้งหมด</td><td class="text-end"><?= e($u['total']) ?></td></tr>
          <tr><td>ใช้งานได้ / ระงับ / ล็อก</td><td class="text-end"><?= e($u['active']) ?> / <?= e($u['inactive']) ?> / <?= e($u['locked']) ?></td></tr>
          <tr><td>เปิดใช้การยืนยันตัวตน 2 ขั้นตอน (2FA)</td><td class="text-end"><?= e($u['two_factor']) ?> (<?= e($pct($u['two_factor'], $u['total'])) ?>)</td></tr>
          <tr><td>เข้าสู่ระบบล่าสุดภายในช่วงเวลา</td><td class="text-end"><?= e($u['logged_in']) ?></td></tr>
          <tr><td>ยังไม่เคยเข้าสู่ระบบ</td><td class="text-end"><?= e($u['never_logged_in']) ?></td></tr>
          <?php if ($report['logins'] !== null): ?>
            <tr><td>การเข้าสู่ระบบสำเร็จ / ไม่สำเร็จ (ครั้ง)</td><td class="text-end"><?= e(number_format($report['logins']['success'])) ?> / <?= e(number_format($report['logins']['failed'])) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if (!$report['org_wide']): ?><div class="small text-muted">นับเฉพาะบัญชีที่ผูกกับบุคลากรในฝ่ายที่เลือก</div><?php endif; ?>
    </section>

    <!-- 5. ห้องบริการ -->
    <section class="report-section mb-4">
      <h3 class="h6 fw-semibold border-bottom pb-1">5. ห้องบริการ<?= $report['rooms']['year'] ? ' ' . e($report['rooms']['year']) : '' ?></h3>
      <?php if ($report['rooms']['rooms'] === []): ?>
        <p class="text-muted mb-0"><?= $report['rooms']['year'] ? 'ไม่มีข้อมูลห้องบริการในปีการศึกษานี้' : 'ช่วงเวลาที่เลือกไม่ตรงกับปีการศึกษาใด' ?></p>
      <?php else: ?>
        <table class="table table-sm table-bordered align-middle mb-1">
          <thead class="table-light"><tr><th style="width: 7rem">รหัสห้อง</th><th>ชื่อห้องบริการ</th><th>ระดับชั้น</th><th>ครูประจำชั้น</th></tr></thead>
          <tbody>
            <?php foreach ($report['rooms']['rooms'] as $r): ?>
              <tr>
                <td class="font-monospace"><?= e($r['room_code']) ?></td>
                <td><?= e($r['name_th']) ?></td>
                <td><?= e($r['grade_level'] ?: '-') ?></td>
                <td><?= e($r['teacher'] ?: '-') ?><?= $r['teacher'] && $r['teacher_status'] !== 'active' ? ' <span class="badge text-bg-warning">ไม่ได้ปฏิบัติงานแล้ว</span>' : '' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="small text-muted">ทั้งหมด <?= e($report['rooms']['total']) ?> ห้อง · มีครูประจำชั้นที่ปฏิบัติงาน <?= e($report['rooms']['with_teacher']) ?> ห้อง</div>
      <?php endif; ?>
    </section>

    <!-- 6. ปฏิทินและไฟล์ -->
    <section class="report-section mb-4">
      <h3 class="h6 fw-semibold border-bottom pb-1">6. กิจกรรมในปฏิทินกลางและไฟล์/เอกสาร</h3>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light"><tr><th>กิจกรรม: ฝ่าย/งาน</th><th class="text-end">จำนวน</th></tr></thead>
            <tbody>
              <?php if ($report['calendar']['by_department'] === []): ?><tr><td colspan="2" class="text-muted">ไม่มีกิจกรรมในช่วงเวลานี้</td></tr><?php endif; ?>
              <?php foreach ($report['calendar']['by_department'] as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td class="text-end"><?= e($r['n']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr><td>รวม</td><td class="text-end"><?= e($report['calendar']['total']) ?></td></tr></tfoot>
          </table>
        </div>
        <div class="col-12 col-md-6">
          <table class="table table-sm table-bordered mb-2">
            <thead class="table-light"><tr><th>ไฟล์: ฝ่าย/งาน</th><th class="text-end">จำนวน</th><th class="text-end">ขนาด</th></tr></thead>
            <tbody>
              <?php if ($report['files']['by_department'] === []): ?><tr><td colspan="3" class="text-muted">ไม่มีการอัปโหลดในช่วงเวลานี้</td></tr><?php endif; ?>
              <?php foreach ($report['files']['by_department'] as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td class="text-end"><?= e($r['n']) ?></td><td class="text-end"><?= e(FileService::humanSize($r['bytes'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-semibold"><tr><td>รวม</td><td class="text-end"><?= e($report['files']['total']) ?></td><td class="text-end"><?= e(FileService::humanSize($report['files']['bytes'])) ?></td></tr></tfoot>
          </table>
          <?php if ($report['files']['by_type'] !== []): ?>
            <div class="small text-muted">ประเภทไฟล์:
              <?= e(implode(' · ', array_map(static fn (string $k, int $n): string => "{$k} {$n}", array_keys($report['files']['by_type']), $report['files']['by_type']))) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if ($report['pdpa'] !== null): ?>
      <!-- 7. PDPA -->
      <section class="report-section mb-2">
        <h3 class="h6 fw-semibold border-bottom pb-1">7. คำขอใช้สิทธิ์ของเจ้าของข้อมูลส่วนบุคคล (PDPA)</h3>
        <div class="row g-3">
          <div class="col-12 col-md-5">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light"><tr><th>สถานะ</th><th class="text-end">จำนวน</th></tr></thead>
              <tbody>
                <?php foreach ($report['pdpa']['status'] as $k => $n): ?>
                  <tr><td><?= e(PdpaService::STATUSES[$k][0] ?? $k) ?></td><td class="text-end"><?= e($n) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot class="table-light fw-semibold"><tr><td>รวม</td><td class="text-end"><?= e($report['pdpa']['total']) ?></td></tr></tfoot>
            </table>
          </div>
          <div class="col-12 col-md-7">
            <table class="table table-sm table-bordered mb-2">
              <thead class="table-light"><tr><th>ประเภทคำขอ</th><th class="text-end">จำนวน</th></tr></thead>
              <tbody>
                <?php foreach ($report['pdpa']['type'] as $k => $n): ?>
                  <tr><td><?= e(PdpaService::TYPES[$k][0] ?? $k) ?></td><td class="text-end"><?= e($n) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="small <?= $report['pdpa']['overdue_open'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
              คำขอที่ยังไม่ปิดและเกินกำหนด <?= PdpaService::DUE_DAYS ?> วัน (ณ ปัจจุบัน): <?= e($report['pdpa']['overdue_open']) ?> รายการ
              · ปิดล่าช้ากว่ากำหนดในช่วงเวลานี้: <?= e($report['pdpa']['late_closed']) ?> รายการ
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>

<script src="<?= e(asset('js/reports.js')) ?>" defer></script>
