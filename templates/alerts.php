<?php foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $class): ?>
  <?php $msg = Core\Session::getFlash($key); ?>
  <?php if (is_string($msg) && $msg !== ''): ?>
    <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
      <?= e($msg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
    </div>
  <?php endif; ?>
<?php endforeach; ?>