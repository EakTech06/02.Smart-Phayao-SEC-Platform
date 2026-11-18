<?php use Core\Auth; ?>
<header class="navbar bg-primary navbar-dark sticky-top shadow-sm px-2 px-md-3 app-header">
  <button class="btn btn-link text-white d-lg-none me-1" type="button" data-bs-toggle="offcanvas"
          data-bs-target="#sidebar" aria-controls="sidebar" aria-label="เปิดเมนู">
    <i class="bi bi-list fs-4"></i>
  </button>
  <a class="navbar-brand fw-semibold text-truncate me-auto" href="<?= e(url('/dashboard')) ?>">
    <i class="bi bi-mortarboard-fill me-1"></i>
    <span class="d-none d-sm-inline"><?= e(config('app.name')) ?></span><span class="d-sm-none">Smart SEC</span>
  </a>
  <?php
  $bellUnread = Modules\CoreAdmin\Services\NotificationService::unreadCount();
  $bellLatest = Modules\CoreAdmin\Services\NotificationService::latest(5);
  ?>
  <div class="dropdown me-1">
    <button class="btn btn-link text-white position-relative px-2" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="การแจ้งเตือน" id="notification-bell" data-count-url="<?= e(url('/notifications/unread-count')) ?>">
      <i class="bi bi-bell fs-5"></i>
      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger<?= $bellUnread > 0 ? '' : ' d-none' ?>"
            id="notification-count"><?= $bellUnread > 99 ? '99+' : $bellUnread ?></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow p-0 notification-menu">
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
        <span class="fw-semibold">การแจ้งเตือน</span>
        <a href="<?= e(url('/notifications')) ?>" class="small">ดูทั้งหมด</a>
      </div>
      <?php if ($bellLatest === []): ?>
        <div class="text-muted small text-center py-4">ยังไม่มีการแจ้งเตือน</div>
      <?php endif; ?>
      <?php foreach ($bellLatest as $n): ?>
        <form method="post" action="<?= e(url('/notifications/' . (int) $n['id'] . '/open')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="dropdown-item py-2 text-wrap<?= $n['read_at'] === null ? ' bg-primary-subtle' : '' ?>">
            <span class="d-block small<?= $n['read_at'] === null ? ' fw-semibold' : '' ?>"><?= e($n['title']) ?></span>
            <span class="d-block text-muted" style="font-size: .75rem;"><?= e(time_ago($n['created_at'])) ?></span>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="dropdown">
    <button class="btn btn-link text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="bi bi-person-circle me-1"></i><span class="d-none d-md-inline"><?= e(Auth::displayName()) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow">
      <li><a class="dropdown-item" href="<?= e(url('/profile')) ?>"><i class="bi bi-person-circle me-2"></i>โปรไฟล์ของฉัน</a></li>
      <li><a class="dropdown-item" href="<?= e(url('/password/change')) ?>"><i class="bi bi-key me-2"></i>เปลี่ยนรหัสผ่าน</a></li>
      <li><hr class="dropdown-divider"></li>
      <li>
        <form method="post" action="<?= e(url('/logout')) ?>" data-confirm="ต้องการออกจากระบบใช่หรือไม่?">
          <?= csrf_field() ?>
          <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</button>
        </form>
      </li>
    </ul>
  </div>
</header>