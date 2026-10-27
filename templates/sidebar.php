<?php

use Core\Rbac;

$currentPath = current_path();
$menu = [
    ['url' => '/dashboard', 'icon' => 'speedometer2', 'label' => 'แดชบอร์ด', 'show' => can('core.dashboard.view')],
    ['url' => '/users', 'icon' => 'people', 'label' => 'ผู้ใช้งาน', 'show' => Rbac::scope('core.users', 'read') !== null],
    ['url' => '/password/change', 'icon' => 'shield-lock', 'label' => 'ความปลอดภัยบัญชี', 'show' => true],
];
?>
<nav class="col-lg-3 col-xl-2 offcanvas-lg offcanvas-start bg-white border-end sidebar p-0" tabindex="-1" id="sidebar" aria-label="เมนูหลัก">
  <div class="offcanvas-header d-lg-none border-bottom">
    <h5 class="offcanvas-title">เมนู</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="ปิด"></button>
  </div>
  <div class="offcanvas-body d-block p-2">
    <ul class="nav nav-pills flex-column gap-1">
      <?php foreach ($menu as $item): ?>
        <?php if (!$item['show']) { continue; } ?>
        <?php $active = $currentPath === $item['url'] || str_starts_with($currentPath, $item['url'] . '/'); ?>
        <li class="nav-item">
          <a class="nav-link <?= $active ? 'active' : 'link-body-emphasis' ?>" href="<?= e(url($item['url'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <i class="bi bi-<?= e($item['icon']) ?> me-2"></i><?= e($item['label']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>