<?php

use Core\Hmvc;

/** @var array $stats */
/** @var array $recent */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">แดชบอร์ด</h1>
  <span class="badge text-bg-light border">
    <?= e($stats['academic_year'] ?? '-') ?> · <?= e($stats['fiscal_year'] ?? '-') ?>
  </span>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-xl-4"><?= Hmvc::call('core_admin', 'DashboardController', 'profileWidget') ?></div>
  <?php if ($stats['users'] !== null): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <a href="<?= e(url('/users')) ?>" class="card stat-card h-100 text-decoration-none shadow-sm">
        <div class="card-body"><i class="bi bi-people text-primary"></i>
          <div class="fs-3 fw-semibold"><?= e($stats['users']) ?></div><div class="small text-muted">ผู้ใช้ที่มองเห็น</div></div>
      </a>
    </div>
  <?php endif; ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card stat-card h-100 shadow-sm"><div class="card-body"><i class="bi bi-diagram-3 text-success"></i>
      <div class="fs-3 fw-semibold"><?= e($stats['departments']) ?></div><div class="small text-muted">ฝ่าย/งาน</div></div></div>
  </div>
  <div class="col-12 col-md-4 col-xl-4">
    <div class="card stat-card h-100 shadow-sm"><div class="card-body"><i class="bi bi-calendar2-check text-warning"></i>
      <div class="fw-semibold mt-1">รอบประเมินปัจจุบัน</div><div class="small text-muted"><?= e($stats['evaluation'] ?? '-') ?></div></div></div>
  </div>
</div>

<?php if ($recent !== []): ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text me-1"></i>กิจกรรมล่าสุด (Audit Log)</div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light"><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th class="d-none d-md-table-cell">IP</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td class="text-nowrap small"><?= e($row['created_at']) ?></td>
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