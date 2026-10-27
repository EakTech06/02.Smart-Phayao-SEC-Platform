<?php $currentPath = current_path(); ?>
<nav class="bottom-nav d-lg-none fixed-bottom bg-white border-top shadow-sm" aria-label="เมนูด่วน">
  <div class="d-flex">
    <a href="<?= e(url('/dashboard')) ?>" class="<?= $currentPath === '/dashboard' ? 'active' : '' ?>"><i class="bi bi-house"></i><span>หน้าหลัก</span></a>
    <?php if (Core\Rbac::scope('core.users', 'read') !== null): ?>
      <a href="<?= e(url('/users')) ?>" class="<?= str_starts_with($currentPath, '/users') ? 'active' : '' ?>"><i class="bi bi-people"></i><span>ผู้ใช้</span></a>
    <?php endif; ?>
    <a href="<?= e(url('/password/change')) ?>" class="<?= $currentPath === '/password/change' ? 'active' : '' ?>"><i class="bi bi-person"></i><span>บัญชี</span></a>
    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-grid"></i><span>เมนู</span></button>
  </div>
</nav>