<?php

use Modules\CoreAdmin\Services\PersonnelImportService;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\UserService;

/** @var array|null $preview */
/** @var array $departments */
?>
<a href="<?= e(url('/personnel')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-3">นำเข้าข้อมูลบุคลากร (CSV)</h1>

<?php if ($msg = error_for('file')): ?>
  <div class="alert alert-danger"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($preview === null): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-1-circle me-1"></i>อัปโหลดไฟล์</div>
        <div class="card-body">
          <form method="post" action="<?= e(url('/personnel/import')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="file">ไฟล์ CSV (UTF-8, ไม่เกิน 2 MB / <?= PersonnelImportService::MAX_ROWS ?> แถว)</label>
              <input type="file" id="file" name="file" accept=".csv,text/csv" class="form-control" required>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="update_existing" value="1" id="update_existing">
              <label class="form-check-label" for="update_existing">อัปเดตข้อมูลเดิม เมื่อพบรหัสบุคลากรที่มีอยู่แล้ว</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>ตรวจสอบข้อมูล</button>
            <span class="small text-muted ms-2">ยังไม่บันทึก จนกว่าจะกดยืนยันในขั้นถัดไป</span>
          </form>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i>รูปแบบไฟล์</div>
        <div class="card-body small">
          <a href="<?= e(url('/personnel/import/template')) ?>" class="btn btn-sm btn-outline-success mb-2"><i class="bi bi-download me-1"></i>ดาวน์โหลดไฟล์ต้นแบบ</a>
          <ul class="mb-2 ps-3">
            <li>จำเป็น: <code>first_name</code>, <code>last_name</code>, <code>department_code</code></li>
            <li><code>employee_code</code>: <strong>เว้นว่าง</strong> = บุคลากรใหม่ (ระบบสร้างรหัสให้อัตโนมัติ) / ใส่รหัสเดิม = แก้ไขบุคลากรรายนั้น</li>
            <li>วันที่: <code>2568-05-16</code> หรือ <code>16/05/2568</code> (พ.ศ. หรือ ค.ศ.)</li>
            <li>ประเภท: <?= e(implode(', ', UserService::EMPLOYMENT_TYPES)) ?></li>
            <li>สถานะ: <?= e(implode(', ', PersonnelService::STATUSES)) ?> (ว่าง = ปฏิบัติงาน)</li>
            <li>Excel: บันทึกเป็น "CSV UTF-8 (Comma delimited)"</li>
          </ul>
          <div class="fw-semibold">รหัสฝ่าย/งาน (department_code)</div>
          <ul class="mb-0 ps-3">
            <?php foreach ($departments as $d): ?>
              <li><code><?= e($d['code']) ?></code> — <?= e($d['name_th']) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php $hasErrors = $preview['errors'] !== []; ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-2-circle me-1"></i>ผลการตรวจสอบ</div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge text-bg-success fs-6">เพิ่มใหม่ <?= (int) $preview['creates'] ?></span>
        <span class="badge text-bg-primary fs-6">แก้ไข <?= (int) $preview['updates'] ?></span>
        <span class="badge text-bg-<?= $hasErrors ? 'danger' : 'secondary' ?> fs-6">ผิดพลาด <?= count($preview['errors']) ?> แถว</span>
      </div>

      <?php if ($hasErrors): ?>
        <div class="alert alert-danger">
          <div class="fw-semibold mb-1">พบข้อผิดพลาด — ยังไม่มีการบันทึกข้อมูล กรุณาแก้ไขไฟล์แล้วอัปโหลดใหม่</div>
          <ul class="mb-0 small">
            <?php foreach ($preview['errors'] as $line => $messages): ?>
              <li>บรรทัด <?= (int) $line ?>: <?= e(implode(', ', $messages)) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <a href="<?= e(url('/personnel/import')) ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>อัปโหลดใหม่</a>
      <?php else: ?>
        <div class="table-responsive mb-3" style="max-height:60vh">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light sticky-top">
              <tr><th>บรรทัด</th><th>การทำงาน</th><th>รหัส</th><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>สถานะ</th><th>เลขบัตร</th></tr>
            </thead>
            <tbody>
              <?php foreach ($preview['rows'] as $row): $d = $row['data']; ?>
                <tr>
                  <td><?= (int) $row['line'] ?></td>
                  <td><span class="badge text-bg-<?= $row['action'] === 'create' ? 'success' : 'primary' ?>"><?= $row['action'] === 'create' ? 'เพิ่ม' : 'แก้ไข' ?></span></td>
                  <td class="font-monospace small"><?= !empty($d['employee_code']) ? e($d['employee_code']) : '<span class="text-muted fst-italic">อัตโนมัติ</span>' ?></td>
                  <td><?= e(PersonnelService::displayName($d)) ?></td>
                  <td class="small"><?= e($d['position'] ?? '-') ?></td>
                  <td class="small"><?= e(PersonnelService::STATUSES[$d['status']] ?? $d['status']) ?></td>
                  <td class="small font-monospace"><?= !empty($d['national_id']) ? '*********' . e(substr((string) preg_replace('/\D/', '', (string) $d['national_id']), -4)) : '-' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <form method="post" action="<?= e(url('/personnel/import/confirm')) ?>"
                data-confirm="ยืนยันบันทึกข้อมูล <?= count($preview['rows']) ?> รายการ?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>ยืนยันนำเข้า</button>
          </form>
          <form method="post" action="<?= e(url('/personnel/import/confirm')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="cancel" value="1">
            <button type="submit" class="btn btn-outline-secondary">ยกเลิก</button>
          </form>
          <span class="small text-muted align-self-center">ข้อมูลรอยืนยันมีอายุ 15 นาที</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
