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
  <div class="dropdown">
    <button class="btn btn-link text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="bi bi-person-circle me-1"></i><span class="d-none d-md-inline"><?= e(Auth::displayName()) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow">
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