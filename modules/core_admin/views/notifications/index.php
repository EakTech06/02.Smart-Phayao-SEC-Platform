<?php

use Modules\CoreAdmin\Services\NotificationService;

/** @var array $notifications */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var bool $unreadOnly */
/** @var int $unread */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">การแจ้งเตือน <?php if ($unread > 0): ?><span class="badge text-bg-danger align-middle"><?= $unread ?> ยังไม่อ่าน</span><?php endif; ?></h1>
  <div class="d-flex flex-wrap gap-2">
    <div class="btn-group" role="group" aria-label="ตัวกรอง">
      <a href="<?= e(url('/notifications')) ?>" class="btn btn-sm <?= $unreadOnly ? 'btn-outline-secondary' : 'btn-secondary' ?>">ทั้งหมด</a>
      <a href="<?= e(url('/notifications?filter=unread')) ?>" class="btn btn-sm <?= $unreadOnly ? 'btn-secondary' : 'btn-outline-secondary' ?>">ยังไม่อ่าน</a>
    </div>
    <?php if ($unread > 0): ?>
      <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-all me-1"></i>อ่านทั้งหมดแล้ว</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($notifications === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-bell-slash fs-1 d-block mb-2"></i><?= $unreadOnly ? 'ไม่มีการแจ้งเตือนที่ยังไม่อ่าน' : 'ยังไม่มีการแจ้งเตือน' ?>
  </div></div>
<?php else: ?>
  <div class="list-group shadow-sm">
    <?php foreach ($notifications as $n): ?>
      <?php $type = NotificationService::TYPES[$n['type']] ?? NotificationService::TYPES['system']; ?>
      <form method="post" action="<?= e(url('/notifications/' . (int) $n['id'] . '/open')) ?>" class="list-group-item list-group-item-action p-0<?= $n['read_at'] === null ? ' bg-primary-subtle' : '' ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn w-100 text-start d-flex gap-3 py-3 px-3 border-0 rounded-0">
          <i class="bi bi-<?= e($type['icon']) ?> fs-4 text-primary flex-shrink-0"></i>
          <span class="flex-grow-1 min-w-0">
            <span class="d-flex justify-content-between gap-2">
              <span class="<?= $n['read_at'] === null ? 'fw-semibold' : '' ?> text-break"><?= e($n['title']) ?></span>
              <small class="text-muted text-nowrap" title="<?= e(thai_date($n['created_at'])) ?>"><?= e(time_ago($n['created_at'])) ?></small>
            </span>
            <?php if (!empty($n['message'])): ?>
              <span class="d-block small text-muted text-break"><?= nl2br(e(mb_strimwidth((string) $n['message'], 0, 300, '…'))) ?></span>
            <?php endif; ?>
            <span class="badge text-bg-light border mt-1"><?= e($type['label']) ?></span>
          </span>
        </button>
      </form>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="mt-3" aria-label="หน้า">
      <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(url('/notifications?page=' . $i . ($unreadOnly ? '&filter=unread' : ''))) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
  <div class="small text-muted text-center mt-2">ทั้งหมด <?= $total ?> รายการ</div>
<?php endif; ?>
