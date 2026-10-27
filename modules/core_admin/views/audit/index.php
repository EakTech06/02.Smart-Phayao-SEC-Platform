<?php
/** @var array $logs */
/** @var array $modules */
/** @var string $filterModule */
/** @var string $filterEvent */
?>
<h1 class="h4 mb-3">ประวัติการใช้งานระบบ (Audit Logs)</h1>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/audit-logs')) ?>" class="row g-2 align-items-center">
      <div class="col-12 col-md-4">
        <select name="module" class="form-select">
          <option value="">-- ทุกโมดูล --</option>
          <?php foreach ($modules as $m): ?>
            <option value="<?= e($m) ?>" <?= $filterModule === $m ? 'selected' : '' ?>><?= e(strtoupper($m)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-5">
        <input type="text" name="event" value="<?= e($filterEvent) ?>" class="form-control" placeholder="ค้นหาชื่อเหตุการณ์ เช่น user.created">
      </div>
      <div class="col-12 col-md-3">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>กรองข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle small">
        <thead class="table-light">
          <tr><th>เวลา</th><th>ผู้ใช้</th><th>โมดูล</th><th>เหตุการณ์</th><th>Target ID</th><th>IP Address</th></tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">ไม่พบประวัติการใช้งาน</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td><?= e($log['created_at']) ?></td>
                <td><?= e($log['username'] ?? 'System/Guest') ?></td>
                <td><span class="badge text-bg-secondary"><?= e(strtoupper($log['module'])) ?></span></td>
                <td class="fw-semibold"><code><?= e($log['event'] ?? ($log['action'] ?? '-')) ?></code></td>

                <td><?= e($log['target_id'] ?? '-') ?></td>
                <td class="text-muted"><?= e($log['ip_address'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>