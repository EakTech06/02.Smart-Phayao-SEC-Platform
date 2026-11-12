<?php

use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\UserService;

/** @var array $employees */
/** @var array $counts */
/** @var array $filters */
/** @var array $departments */
/** @var string|null $scope */
/** @var bool $canSync */
/** @var array $roles */
/** @var array|null $bulkResult */

$statusClass = ['active' => 'success', 'inactive' => 'secondary', 'retired' => 'info', 'resigned' => 'dark'];
$hasFilter = array_filter($filters) !== [];
$selectable = static fn (array $emp): bool => $canSync && $emp['user_id'] === null && $emp['status'] === 'active';
$selectableCount = count(array_filter($employees, $selectable));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">ข้อมูลบุคลากร</h1>
    <span class="badge text-bg-secondary">ขอบเขตข้อมูล: <?= e($scope) ?></span>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (can('core.employees.import_all')): ?>
      <a href="<?= e(url('/personnel/import')) ?>" class="btn btn-outline-primary"><i class="bi bi-file-earmark-arrow-up me-1"></i>นำเข้า CSV</a>
    <?php endif; ?>
    <?php if (can('core.employees.create_all')): ?>
      <a href="<?= e(url('/personnel/create')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>เพิ่มบุคลากร</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($bulkResult !== null): ?>
  <?php if ($bulkResult['created'] !== []): ?>
    <div class="alert alert-warning">
      <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>บัญชีที่สร้างใหม่ และรหัสผ่านชั่วคราว (แสดงครั้งเดียว)</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered bg-white mb-2" id="bulk-accounts">
          <thead><tr><th>ชื่อ-สกุล</th><th>ชื่อผู้ใช้</th><th>รหัสผ่านชั่วคราว</th></tr></thead>
          <tbody>
            <?php foreach ($bulkResult['created'] as $row): ?>
              <tr><td><?= e($row['name']) ?></td><td><code><?= e($row['username']) ?></code></td><td><code class="user-select-all"><?= e($row['password']) ?></code></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-outline-dark" data-copy="#bulk-accounts tbody"><i class="bi bi-clipboard me-1"></i>คัดลอกทั้งหมด</button>
      <div class="small mt-2">แจ้งผู้ใช้แต่ละคนผ่านช่องทางที่ปลอดภัย ระบบจะบังคับให้เปลี่ยนรหัสผ่านเมื่อเข้าใช้ครั้งแรก</div>
    </div>
  <?php endif; ?>
  <?php if ($bulkResult['failed'] !== []): ?>
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">สร้างบัญชีไม่สำเร็จ</div>
      <ul class="mb-0 small">
        <?php foreach ($bulkResult['failed'] as $row): ?>
          <li><?= e($row['name']) ?>: <?= e($row['reason']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="row g-2 mb-3">
  <?php
  $cards = [
      ['ทั้งหมด', $counts['all'] ?? 0, 'people', 'primary', ''],
      ['ปฏิบัติงาน', $counts['active'] ?? 0, 'person-check', 'success', 'active'],
      ['พ้นสภาพ/พักงาน', ($counts['inactive'] ?? 0) + ($counts['retired'] ?? 0) + ($counts['resigned'] ?? 0), 'person-dash', 'secondary', null],
      ['ยังไม่มีบัญชี', $counts['no_account'] ?? 0, 'person-exclamation', 'warning', 'without'],
  ];
  ?>
  <?php foreach ($cards as [$label, $total, $icon, $color, $link]): ?>
    <div class="col-6 col-lg-3">
      <?php $href = $link === null ? null : url('/personnel' . ($link === '' ? '' : ($link === 'without' ? '?account=without&status=active' : '?status=' . $link))); ?>
      <<?= $href ? 'a href="' . e($href) . '"' : 'div' ?> class="card shadow-sm stat-card text-decoration-none h-100">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
          <div><div class="small text-muted"><?= e($label) ?></div><div class="fs-4 fw-semibold text-body"><?= (int) $total ?></div></div>
          <i class="bi bi-<?= e($icon) ?> text-<?= e($color) ?>"></i>
        </div>
      </<?= $href ? 'a' : 'div' ?>>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/personnel')) ?>" class="row g-2 align-items-end">
      <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label small mb-1" for="q">ค้นหา</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="รหัส, ชื่อ, นามสกุล, ตำแหน่ง">
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <label class="form-label small mb-1" for="department_id">ฝ่าย/งาน</label>
        <select id="department_id" name="department_id" class="form-select">
          <option value="">ทั้งหมด</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= (int) $filters['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name_th']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <label class="form-label small mb-1" for="status">สถานะ</label>
        <select id="status" name="status" class="form-select">
          <option value="">ทั้งหมด</option>
          <?php foreach (PersonnelService::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <label class="form-label small mb-1" for="employment_type">ประเภท</label>
        <select id="employment_type" name="employment_type" class="form-select">
          <option value="">ทั้งหมด</option>
          <?php foreach (UserService::EMPLOYMENT_TYPES as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $filters['employment_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4 col-xl-1">
        <label class="form-label small mb-1" for="account">บัญชี</label>
        <select id="account" name="account" class="form-select">
          <option value="">ทั้งหมด</option>
          <option value="with" <?= $filters['account'] === 'with' ? 'selected' : '' ?>>มีบัญชี</option>
          <option value="without" <?= $filters['account'] === 'without' ? 'selected' : '' ?>>ยังไม่มี</option>
        </select>
      </div>
      <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary flex-fill"><i class="bi bi-funnel me-1"></i>กรอง</button>
        <?php if ($hasFilter): ?>
          <a href="<?= e(url('/personnel')) ?>" class="btn btn-outline-secondary" title="ล้างตัวกรอง"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<form method="post" action="<?= e(url('/personnel/accounts/bulk')) ?>" id="bulk-form"
      data-confirm="สร้างบัญชีผู้ใช้ให้บุคลากรที่เลือก? (ชื่อผู้ใช้ = รหัสบุคลากร)">
  <?= csrf_field() ?>

  <?php if ($selectableCount > 0): ?>
    <div class="card shadow-sm mb-3 border-warning-subtle">
      <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div class="me-auto">
          <div class="fw-semibold"><i class="bi bi-person-gear me-1"></i>สร้างบัญชีผู้ใช้จากบุคลากร (Account Sync)</div>
          <div class="small text-muted">เลือกบุคลากรที่ยังไม่มีบัญชีในตารางด้านล่าง · ชื่อผู้ใช้ = รหัสบุคลากร · <span id="bulk-count">0</span> คนที่เลือก</div>
        </div>
        <div>
          <label class="form-label small mb-1" for="bulk-role">บทบาทเริ่มต้น</label>
          <select id="bulk-role" name="role_id" class="form-select" required>
            <?php foreach ($roles as $role): ?>
              <option value="<?= (int) $role['id'] ?>" <?= $role['code'] === 'teacher' ? 'selected' : '' ?>><?= e($role['name_th']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-warning" id="bulk-submit" disabled><i class="bi bi-person-plus-fill me-1"></i>สร้างบัญชี</button>
      </div>
      <?php if ($msg = error_for('employee_ids') ?? error_for('roles')): ?><div class="card-footer text-danger small"><?= e($msg) ?></div><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($employees === []): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
      <i class="bi bi-people fs-1 d-block mb-2"></i>ไม่พบข้อมูลบุคลากร<?= $hasFilter ? 'ตามเงื่อนไขที่เลือก' : '' ?>
    </div></div>
  <?php else: ?>
    <!-- Desktop / Tablet: ตาราง -->
    <div class="card shadow-sm d-none d-md-block">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <?php if ($selectableCount > 0): ?>
                <th style="width:2.5rem"><input class="form-check-input" type="checkbox" id="select-all" aria-label="เลือกทั้งหมด"></th>
              <?php endif; ?>
              <th>รหัส</th><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>ฝ่าย/งาน</th><th>สถานะ</th><th>บัญชีผู้ใช้</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp): ?>
              <tr>
                <?php if ($selectableCount > 0): ?>
                  <td>
                    <?php if ($selectable($emp)): ?>
                      <input class="form-check-input js-bulk" type="checkbox" name="employee_ids[]" value="<?= (int) $emp['id'] ?>" aria-label="เลือก <?= e($emp['employee_code']) ?>">
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td class="font-monospace small"><?= e($emp['employee_code']) ?></td>
                <td>
                  <div class="fw-semibold"><?= e(PersonnelService::displayName($emp)) ?></div>
                  <?php if (!empty($emp['nickname'])): ?><div class="small text-muted">(<?= e($emp['nickname']) ?>)</div><?php endif; ?>
                </td>
                <td class="small"><?= e($emp['position'] ?? '-') ?><?php if (!empty($emp['academic_rank'])): ?><div class="text-muted"><?= e($emp['academic_rank']) ?></div><?php endif; ?></td>
                <td class="small"><?= e($emp['department_name'] ?? '-') ?></td>
                <td><span class="badge text-bg-<?= e($statusClass[$emp['status']] ?? 'secondary') ?>"><?= e(PersonnelService::STATUSES[$emp['status']] ?? $emp['status']) ?></span></td>
                <td class="small">
                  <?php if ($emp['user_id'] !== null): ?>
                    <i class="bi bi-person-check text-success"></i> <?= e($emp['username']) ?>
                    <?php if ($emp['user_status'] !== 'active'): ?><span class="badge text-bg-secondary">ระงับ</span><?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted"><i class="bi bi-dash-circle"></i> ยังไม่มี</span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/personnel/' . (int) $emp['id'])) ?>" title="ดูข้อมูล"><i class="bi bi-eye"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile: Card List -->
    <div class="d-md-none vstack gap-2">
      <?php foreach ($employees as $emp): ?>
        <div class="card shadow-sm">
          <div class="card-body py-2 d-flex gap-2 align-items-start">
            <?php if ($selectable($emp)): ?>
              <input class="form-check-input js-bulk mt-1 flex-shrink-0" style="width:1.4rem;height:1.4rem" type="checkbox" name="employee_ids[]" value="<?= (int) $emp['id'] ?>" aria-label="เลือก <?= e($emp['employee_code']) ?>">
            <?php endif; ?>
            <a class="text-decoration-none text-body flex-fill min-w-0" href="<?= e(url('/personnel/' . (int) $emp['id'])) ?>">
              <div class="d-flex justify-content-between gap-2">
                <span class="fw-semibold text-truncate"><?= e(PersonnelService::displayName($emp)) ?></span>
                <span class="badge text-bg-<?= e($statusClass[$emp['status']] ?? 'secondary') ?> align-self-start"><?= e(PersonnelService::STATUSES[$emp['status']] ?? $emp['status']) ?></span>
              </div>
              <div class="small text-muted"><?= e($emp['employee_code']) ?> · <?= e($emp['position'] ?? '-') ?></div>
              <div class="small text-muted"><?= e($emp['department_name'] ?? '-') ?> ·
                <?= $emp['user_id'] !== null ? '<i class="bi bi-person-check text-success"></i> ' . e($emp['username']) : 'ยังไม่มีบัญชี' ?>
              </div>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="small text-muted mt-2">ทั้งหมด <?= count($employees) ?> รายการ</div>
  <?php endif; ?>
</form>

<script src="<?= e(asset('js/personnel.js')) ?>" defer></script>
