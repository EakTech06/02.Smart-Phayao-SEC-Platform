<?php

use Core\Auth;
use Core\Rbac;
use Modules\CoreAdmin\Services\NotificationService;

$bellUnread = NotificationService::unreadCount();
$bellLatest = NotificationService::latest(5);
$me = Auth::user() ?? [];
$meAvatar = !empty($me['employee_id'])
    ? Core\Database::instance()->fetchColumn('SELECT avatar FROM employees WHERE id = ? AND deleted_at IS NULL', [(int) $me['employee_id']])
    : null;
$meName = (string) Auth::displayName();
$meInitial = mb_substr(trim($meName) !== '' ? trim($meName) : 'U', 0, 1);

// ช่องค้นหา: ค้นเมนูทั้งหมดในเครื่อง + ส่งคำค้นไปหน้ารายการที่ผู้ใช้มีสิทธิ์
$searchTargets = array_values(array_filter([
    in_array('academic', (array) config('app.modules', []), true) && Rbac::scope('academic.students', 'read') !== null
        ? ['label' => 'ค้นหาในทะเบียนนักเรียน', 'icon' => 'person-lines-fill', 'url' => url('/academic/students') . '?q='] : null,
    !in_array(Rbac::scope('core.employees', 'read'), [null, 'own'], true)
        ? ['label' => 'ค้นหาในข้อมูลบุคลากร', 'icon' => 'people', 'url' => url('/personnel') . '?q='] : null,
    can('core.files.read_own') ? ['label' => 'ค้นหาไฟล์/เอกสาร', 'icon' => 'folder2-open', 'url' => url('/files') . '?q='] : null,
    can('core.service_rooms.read_all') ? ['label' => 'ค้นหาห้องบริการ', 'icon' => 'door-open', 'url' => url('/service-rooms') . '?q='] : null,
]));
$palettes = [
    'cyber' => 'Cyber Blue',
    'neon' => 'Neon Purple',
    'emerald' => 'Emerald',
    'sunset' => 'Sunset',
    'phayao' => 'Phayao Lake',
];
?>
<header class="app-topbar" id="app-topbar">
  <button class="topbar-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="เปิดเมนู">
    <i class="bi bi-list"></i>
  </button>
  <button class="topbar-btn d-none d-lg-inline-flex" type="button" data-sidebar-toggle aria-controls="sidebar" aria-label="ย่อ/ขยายเมนูด้านข้าง" title="ย่อ/ขยายเมนู">
    <i class="bi bi-layout-sidebar-inset"></i>
  </button>
  <a class="topbar-brand d-lg-none me-auto me-md-2" href="<?= e(url('/dashboard')) ?>"><?= e(app_name('short_name')) ?></a>

  <div class="topbar-search" role="search">
    <i class="bi bi-search search-icon"></i>
    <input type="search" class="form-control" id="global-search" placeholder="ค้นหาเมนู นักเรียน บุคลากร…" autocomplete="off"
           aria-label="ค้นหา" aria-controls="global-search-results" aria-expanded="false" aria-autocomplete="list" role="combobox"
           data-targets="<?= e(json_encode($searchTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <kbd>Ctrl K</kbd>
    <div class="dropdown-menu search-results" id="global-search-results" role="listbox" aria-label="ผลการค้นหา"></div>
  </div>

  <div class="d-flex align-items-center gap-2 ms-auto">
    <button class="topbar-btn d-md-none" type="button" data-search-open aria-label="ค้นหา"><i class="bi bi-search"></i></button>

    <div class="dropdown">
      <button class="topbar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="เลือกชุดสี" title="ชุดสี (Color Palette)">
        <i class="bi bi-palette2 text-gradient"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end palette-menu" role="menu" aria-label="ชุดสี">
        <div class="small text-body-secondary px-2 pb-1">ชุดสี (Color Palette)</div>
        <?php foreach ($palettes as $key => $label): ?>
          <button type="button" class="palette-option" role="menuitemradio" aria-checked="false" data-palette-option="<?= e($key) ?>">
            <span class="palette-swatch swatch-<?= e($key) ?>"></span><span><?= e($label) ?></span><i class="bi bi-check-lg check"></i>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <button class="topbar-btn theme-toggle" type="button" data-theme-toggle aria-label="สลับโหมดมืด/สว่าง" title="สลับโหมดมืด/สว่าง">
      <i class="bi bi-moon-stars theme-icon-light"></i><i class="bi bi-sun theme-icon-dark"></i>
    </button>

    <div class="dropdown">
      <button class="topbar-btn" data-bs-toggle="dropdown" aria-expanded="false" type="button"
              aria-label="การแจ้งเตือน" id="notification-bell" data-count-url="<?= e(url('/notifications/unread-count')) ?>">
        <i class="bi bi-bell"></i>
        <span class="badge rounded-pill text-bg-danger<?= $bellUnread > 0 ? '' : ' d-none' ?>" id="notification-count"><?= $bellUnread > 99 ? '99+' : $bellUnread ?></span>
      </button>
      <div class="dropdown-menu dropdown-menu-end p-0 notification-menu">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
          <span class="fw-semibold">การแจ้งเตือน</span>
          <a href="<?= e(url('/notifications')) ?>" class="small">ดูทั้งหมด</a>
        </div>
        <?php if ($bellLatest === []): ?>
          <div class="text-body-secondary small text-center py-4">ยังไม่มีการแจ้งเตือน</div>
        <?php endif; ?>
        <?php foreach ($bellLatest as $n): ?>
          <form method="post" action="<?= e(url('/notifications/' . (int) $n['id'] . '/open')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item py-2 text-wrap<?= $n['read_at'] === null ? ' bg-primary-subtle' : '' ?>">
              <span class="d-block small<?= $n['read_at'] === null ? ' fw-semibold' : '' ?>"><?= e($n['title']) ?></span>
              <span class="d-block text-body-secondary" style="font-size: .75rem;"><?= e(time_ago($n['created_at'])) ?></span>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dropdown">
      <button class="topbar-profile" data-bs-toggle="dropdown" aria-expanded="false" type="button" aria-label="เมนูผู้ใช้">
        <span class="avatar-ring">
          <?php if (is_string($meAvatar) && $meAvatar !== ''): ?>
            <img src="<?= e(url('/uploads/avatars/' . rawurlencode($meAvatar))) ?>" alt="" class="avatar">
          <?php else: ?>
            <span class="avatar"><?= e($meInitial) ?></span>
          <?php endif; ?>
        </span>
        <span class="d-none d-xl-inline text-truncate small fw-semibold" style="max-width: 11rem;"><?= e($meName) ?></span>
        <i class="bi bi-chevron-down small d-none d-xl-inline"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li class="px-3 py-2 small"><div class="fw-semibold text-truncate" style="max-width: 14rem;"><?= e($meName) ?></div><div class="text-body-secondary"><?= e($me['username'] ?? '') ?></div></li>
        <li><hr class="dropdown-divider"></li>
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
  </div>
</header>
