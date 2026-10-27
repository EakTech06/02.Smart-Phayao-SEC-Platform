<?php /** @var array|null $user */ /** @var string $name */ /** @var array $roles */ ?>
<div class="card h-100 shadow-sm">
  <div class="card-body d-flex align-items-center gap-3">
    <i class="bi bi-person-circle display-5 text-primary"></i>
    <div class="min-w-0">
      <div class="fw-semibold text-truncate"><?= e($name) ?></div>
      <div class="small text-muted text-truncate"><?= e($user['position'] ?? '') ?></div>
      <div class="mt-1">
        <?php foreach ($roles as $role): ?>
          <span class="badge text-bg-primary-subtle border border-primary-subtle text-primary-emphasis"><?= e($role['name_th']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>