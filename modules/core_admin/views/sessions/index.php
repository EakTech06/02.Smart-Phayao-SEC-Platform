<?php
/** @var array $sessions */
/** @var string $currentSessionId */
?>
<h1 class="h4 mb-3">จัดการเซสชันผู้ใช้งาน</h1>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th>ผู้ใช้</th><th>IP Address</th><th>User Agent</th><th>ใช้งานล่าสุด</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($sessions as $s): ?>
            <?php $isCurrent = $s['id'] === $currentSessionId; ?>
            <tr class="<?= $isCurrent ? 'table-success' : '' ?>">
              <td>
                <?= e($s['username'] ?? 'Guest') ?>
                <?php if ($isCurrent): ?><span class="badge text-bg-success ms-1">เซสชันปัจจุบันของคุณ</span><?php endif; ?>
              </td>
              <td class="small font-monospace"><?= e($s['ip_address']) ?></td>
              <td class="small text-muted text-truncate" style="max-width: 300px;" title="<?= e($s['user_agent']) ?>"><?= e($s['user_agent']) ?></td>
              <td class="small"><?= e(date('Y-m-d H:i:s', (int) $s['last_activity'])) ?></td>
              <td class="text-end">
                <?php if (!$isCurrent && can('core.sessions.manage_all')): ?>
                  <form method="post" action="<?= e(url('/sessions/' . $s['id'])) ?>" class="d-inline" data-confirm="ต้องการยุติเซสชันนี้?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-power me-1"></i>เตะออก</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>