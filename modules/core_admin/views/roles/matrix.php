<?php

use Modules\CoreAdmin\Services\RolePermissionService;

/** @var array $roles */
/** @var array $grouped */
/** @var int $selectedRoleId */

$currentRole = null;
foreach ($roles as $r) {
    if ((int) $r['id'] === $selectedRoleId) {
        $currentRole = $r;
        break;
    }
}
$assignedIds = RolePermissionService::rolePermissionIds($selectedRoleId);
$isSuperAdmin = $currentRole && $currentRole['code'] === 'super_admin';
?>
<h1 class="h4 mb-3">จัดการสิทธิ์บทบาท (Role Matrix)</h1>

<ul class="nav nav-tabs mb-3">
  <?php foreach ($roles as $r): ?>
    <li class="nav-item">
      <a class="nav-link <?= (int) $r['id'] === $selectedRoleId ? 'active' : '' ?>" href="<?= e(url('/roles?role_id=' . (int) $r['id'])) ?>">
        <?= e($r['name_th']) ?> <small class="text-muted">(<?= e($r['code']) ?>)</small>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<form method="post" action="<?= e(url('/roles/' . $selectedRoleId . '/matrix')) ?>">
  <?= csrf_field() ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <?php if ($isSuperAdmin): ?>
        <div class="alert alert-info small">บทบาท Super Admin มีสิทธิ์ทั้งหมดในระบบโดยอัตโนมัติและไม่สามารถแก้ไขได้</div>
      <?php endif; ?>

      <div class="accordion" id="permAccordion">
        <?php $i = 0; foreach ($grouped as $module => $resources): $i++; ?>
          <div class="accordion-item mb-2 border rounded">
            <h2 class="accordion-header" id="heading-<?= $i ?>">
              <button class="accordion-button <?= $i > 1 ? 'collapsed' : '' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $i ?>">
                โมดูล: <?= e(strtoupper($module)) ?>
              </button>
            </h2>
            <div id="collapse-<?= $i ?>" class="accordion-collapse collapse <?= $i === 1 ? 'show' : '' ?>" data-bs-parent="#permAccordion">
              <div class="accordion-body">
                <?php foreach ($resources as $resource => $permissions): ?>
                  <div class="mb-3">
                    <div class="fw-semibold text-secondary small mb-1 uppercase">Resource: <?= e($resource) ?></div>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
                      <?php foreach ($permissions as $p): ?>
                        <div class="col">
                          <div class="form-check border rounded p-2 h-100 bg-light-subtle">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>"
                                   id="perm-<?= (int) $p['id'] ?>"
                                   <?= in_array((int) $p['id'], $assignedIds, true) ? 'checked' : '' ?>
                                   <?= $isSuperAdmin ? 'disabled checked' : '' ?>>
                            <label class="form-check-label small" for="perm-<?= (int) $p['id'] ?>">
                              <strong><?= e($p['action']) ?></strong> <span class="text-muted">(.<?= e($p['module']) ?>)</span>
                              <div class="text-muted" style="font-size: 0.75rem;"><?= e($p['description']) ?></div>
                            </label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (!$isSuperAdmin && can('core.roles.update_all')): ?>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึกเมทริกซ์สิทธิ์</button>
  <?php endif; ?>
</form>