<?php /** @var array $users */ /** @var string|null $scope */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">ผู้ใช้งาน</h1>
  <span class="badge text-bg-secondary">ขอบเขตข้อมูล: <?= e($scope) ?></span>
</div>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle js-datatable w-100">
        <thead class="table-light">
          <tr><th>รหัส</th><th>ชื่อ-สกุล</th><th>ชื่อผู้ใช้</th><th>ฝ่าย/งาน</th><th>บทบาท</th><th>สถานะ</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= e($u['employee_code']) ?></td>
              <td><?= e(trim(($u['title_name'] ?? '') . ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
              <td><?= e($u['username']) ?></td>
              <td><?= e($u['department_name'] ?? '-') ?></td>
              <td class="small"><?= e($u['role_names'] ?? '-') ?></td>
              <td><span class="badge text-bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($u['status']) ?></span></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/users/' . (int) $u['id'])) ?>"><i class="bi bi-eye"></i></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>