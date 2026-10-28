<div class="container-fluid px-4 py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people me-2"></i>จัดการข้อมูลบุคลากร</h2>
    <a href="<?= e(url('/admin/personnel/create')) ?>" class="btn btn-primary">
      <i class="bi bi-person-plus me-1"></i> เพิ่มบุคลากรใหม่
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <!-- ค้นหา -->
      <form method="get" class="row g-3 mb-3">
        <div class="col-md-4">
          <input type="text" name="q" class="form-control" placeholder="ค้นหา ชื่อ, นามสกุล, รหัสบุคลากร..." value="<?= e($search ?? '') ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-secondary">ค้นหา</button>
        </div>
      </form>

      <!-- ตารางรายชื่อ -->
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>รหัส</th>
              <th>ชื่อ-นามสกุล</th>
              <th>ตำแหน่ง</th>
              <th>ฝ่าย/งาน</th>
              <th>เบอร์โทร</th>
              <th>สถานะ</th>
              <th class="text-end">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($employees)): foreach ($employees as $emp): ?>
              <tr>
                <td><?= e($emp['employee_code']) ?></td>
                <td class="fw-semibold"><?= e($emp['title_name'] . ' ' . $emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                <td><?= e($emp['position']) ?></td>
                <td><?= e($emp['department_name'] ?? '-') ?></td>
                <td><?= e($emp['phone'] ?? '-') ?></td>
                <td>
                  <span class="badge bg-<?= $emp['status'] === 'active' ? 'success' : 'secondary' ?>">
                    <?= e($emp['status']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="<?= e(url('/admin/personnel/edit?id=' . $emp['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                  <a href="<?= e(url('/admin/personnel/delete?id=' . $emp['id'])) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบข้อมูลนี้?')"><i class="bi bi-trash"></i></a>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7" class="text-center text-muted py-4">ไม่พบข้อมูลบุคลากร</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>