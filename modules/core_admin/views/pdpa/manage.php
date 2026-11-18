<?php

use Modules\CoreAdmin\Services\PdpaService;

/** @var array $requests */
/** @var array<string, int> $counts */
/** @var array{status: string, q: string} $filters */
?>
<a href="<?= e(url('/pdpa')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> คำขอของฉัน</a>
<h1 class="h4 mb-3">จัดการคำขอใช้สิทธิ์ PDPA</h1>

<div class="row g-2 mb-3">
  <?php foreach ([
      ['ค้างดำเนินการ', $counts['open'] ?? 0, 'hourglass-split', 'warning'],
      ['เกินกำหนด 30 วัน', $counts['overdue'] ?? 0, 'exclamation-octagon', 'danger'],
      ['ครบกำหนดใน 7 วัน', $counts['due_soon'] ?? 0, 'alarm', 'info'],
      ['เสร็จสิ้น', $counts['completed'] ?? 0, 'check2-circle', 'success'],
  ] as [$label, $total, $icon, $color]): ?>
    <div class="col-6 col-lg-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
          <div><div class="small text-muted"><?= e($label) ?></div><div class="fs-4 fw-semibold"><?= (int) $total ?></div></div>
          <i class="bi bi-<?= e($icon) ?> fs-3 text-<?= e($color) ?>"></i>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/pdpa/manage')) ?>" class="row g-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="status">สถานะ</label>
        <select id="status" name="status" class="form-select">
          <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>ค้างดำเนินการ</option>
          <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>ทั้งหมด</option>
          <?php foreach (PdpaService::STATUSES as $key => [$label]): ?>
            <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-5">
        <label class="form-label small mb-1" for="q">ค้นหา</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="เลขที่คำขอ, ชื่อผู้ยื่น">
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>กรอง</button>
      </div>
    </form>
  </div>
</div>

<?php if ($requests === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>ไม่มีคำขอ</div></div>
<?php else: ?>
  <div class="list-group shadow-sm">
    <?php foreach ($requests as $r): ?>
      <?php [$statusLabel, $statusColor] = PdpaService::STATUSES[$r['status']]; $overdue = PdpaService::isOverdue($r); ?>
      <a href="<?= e(url('/pdpa/' . (int) $r['id'])) ?>" class="list-group-item list-group-item-action py-3<?= $overdue ? ' border-start border-4 border-danger' : '' ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2">
          <span><span class="fw-semibold font-monospace"><?= e($r['request_no']) ?></span> · <?= e(trim((string) $r['requester_name']) ?: $r['username']) ?></span>
          <span>
            <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
            <?php if ($overdue): ?><span class="badge text-bg-danger">เกินกำหนด</span><?php endif; ?>
          </span>
        </div>
        <div class="small"><?= e(PdpaService::TYPES[$r['request_type']][0] ?? $r['request_type']) ?></div>
        <div class="small text-muted">ยื่น <?= e(thai_date((string) $r['created_at'])) ?> · กำหนด <?= e(thai_date((string) $r['due_date'], false)) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
