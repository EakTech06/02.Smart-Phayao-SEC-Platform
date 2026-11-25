<?php

use Modules\Academic\Services\StudentImportService;

/** @var array|null $preview */
/** @var list<array> $units */
/** @var int|null $currentYearId */

$labels = StudentImportService::columnLabels();
$choices = StudentImportService::choices();
?>
<a href="<?= e(url('/academic/students')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> ทะเบียนนักเรียน</a>
<h1 class="h4 mb-3">นำเข้าข้อมูลนักเรียน (CSV)</h1>

<?php if ($msg = error_for('file')): ?>
  <div class="alert alert-danger"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($preview === null): ?>
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-1-circle me-1"></i>อัปโหลดไฟล์</div>
        <div class="card-body">
          <form method="post" action="<?= e(url('/academic/students/import')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label" for="file">ไฟล์ CSV (UTF-8, ไม่เกิน 2 MB / <?= StudentImportService::MAX_ROWS ?> แถว)</label>
              <input type="file" id="file" name="file" accept=".csv,text/csv" class="form-control" required>
            </div>
            <?php if (can('academic.students.update_all')): ?>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="update_existing" value="1" id="update_existing">
                <label class="form-check-label" for="update_existing">อัปเดตข้อมูลเดิม เมื่อพบเลขประจำตัวนักเรียนที่มีอยู่แล้ว
                  <span class="d-block small text-muted">ช่องที่เว้นว่างในไฟล์ = คงค่าเดิม · ไม่เปลี่ยนสถานะและการรับบริการ</span></label>
              </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>ตรวจสอบข้อมูล</button>
            <span class="small text-muted ms-2">ยังไม่บันทึก จนกว่าจะกดยืนยันในขั้นถัดไป</span>
          </form>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i>วิธีเตรียมไฟล์</div>
        <div class="card-body small">
          <a href="<?= e(url('/academic/students/import/template')) ?>" class="btn btn-sm btn-outline-success mb-2"><i class="bi bi-download me-1"></i>ดาวน์โหลดไฟล์ต้นแบบ</a>
          <ul class="mb-0 ps-3">
            <li>จำเป็น: <code>first_name</code>, <code>last_name</code> ใส่เฉพาะคอลัมน์ที่มีข้อมูลก็ได้ ลำดับคอลัมน์ไม่สำคัญ</li>
            <li>หัวคอลัมน์ใช้ชื่ออังกฤษตามไฟล์ต้นแบบ หรือชื่อภาษาไทยตามตารางด้านขวาก็ได้</li>
            <li><code>student_code</code> เว้นว่าง = ระบบออกเลขให้ / ใส่เลขเดิมจากระบบ SET ได้</li>
            <li>วันที่: <code>16/05/2568</code> หรือ <code>2568-05-16</code> (พ.ศ. หรือ ค.ศ.)</li>
            <li>ช่องตัวเลือกใส่ได้ทั้งรหัสหรือชื่อภาษาไทย เช่น <code>male</code> หรือ <code>ชาย</code></li>
            <li>เลขบัตร 13 หลักใน Excel: ตั้งรูปแบบคอลัมน์เป็น <strong>ข้อความ</strong> ก่อนวาง ไม่เช่นนั้นจะกลายเป็น 1.1E+12</li>
            <li>ไม่กรอกที่อยู่ปัจจุบัน (<code>cur_*</code>) = ใช้ที่อยู่ตามทะเบียนบ้าน</li>
            <li>การรับบริการ (<code>enroll_*</code>) ใช้กับนักเรียนใหม่ · <code>enroll_year</code> ว่าง = ปีการศึกษาปัจจุบัน ·
              <code>enroll_unit</code>: <?= e(implode(', ', array_column($units, 'name_th'))) ?></li>
            <li>ไม่รับข้อมูลความพิการ/สุขภาพทาง CSV (ข้อมูลอ่อนไหว บันทึกผ่านหน้าข้อมูลนักเรียน)</li>
            <li>Excel: บันทึกเป็น "CSV UTF-8 (Comma delimited)"</li>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-table me-1"></i>คอลัมน์ที่รองรับ (<?= count($labels) ?>)</div>
        <div class="table-responsive import-columns">
          <table class="table table-sm mb-0 small">
            <thead class="table-light sticky-top"><tr><th>คอลัมน์</th><th>ชื่อภาษาไทย</th><th>ค่าที่ใช้ได้</th></tr></thead>
            <tbody>
              <?php foreach ($labels as $col => $label): ?>
                <?php $opt = $choices[$col] ?? (preg_match('/_life_status$/', $col) ? $choices['*_life_status'] : null); ?>
                <tr>
                  <td><code><?= e($col) ?></code><?= in_array($col, ['first_name', 'last_name'], true) ? ' <span class="text-danger">*</span>' : '' ?></td>
                  <td><?= e($label) ?></td>
                  <td class="text-muted"><?= $opt !== null ? e(implode(' · ', array_map(static fn ($k, $v): string => $k . '=' . $v, array_keys($opt), $opt))) : '' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php $hasErrors = $preview['errors'] !== []; ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-2-circle me-1"></i>ผลการตรวจสอบ</div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="badge text-bg-success fs-6">เพิ่มใหม่ <?= (int) $preview['creates'] ?></span>
        <span class="badge text-bg-primary fs-6">แก้ไข <?= (int) $preview['updates'] ?></span>
        <span class="badge text-bg-<?= $hasErrors ? 'danger' : 'secondary' ?> fs-6">ผิดพลาด <?= count($preview['errors']) ?> แถว</span>
      </div>
      <p class="small text-muted">คอลัมน์ที่อ่านได้ <?= count($preview['columns']) ?> คอลัมน์: <?= e(implode(', ', $preview['columns'])) ?></p>

      <?php if ($hasErrors): ?>
        <div class="alert alert-danger">
          <div class="fw-semibold mb-1">พบข้อผิดพลาด ยังไม่มีการบันทึกข้อมูล กรุณาแก้ไขไฟล์แล้วอัปโหลดใหม่</div>
          <ul class="mb-0 small">
            <?php foreach ($preview['errors'] as $line => $messages): ?>
              <li>บรรทัด <?= (int) $line ?>: <?= e(implode(', ', $messages)) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <a href="<?= e(url('/academic/students/import')) ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>อัปโหลดใหม่</a>
      <?php else: ?>
        <div class="table-responsive mb-3 import-preview">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light sticky-top">
              <tr><th>บรรทัด</th><th>การทำงาน</th><th>เลขประจำตัว</th><th>ชื่อ-สกุล</th><th>เอกสารแสดงตน</th><th>การรับบริการ</th><th>ข้อควรตรวจสอบ</th></tr>
            </thead>
            <tbody>
              <?php foreach ($preview['rows'] as $row): ?>
                <tr>
                  <td><?= (int) $row['line'] ?></td>
                  <td><span class="badge text-bg-<?= $row['action'] === 'create' ? 'success' : 'primary' ?>"><?= $row['action'] === 'create' ? 'เพิ่ม' : 'แก้ไข' ?></span></td>
                  <td class="font-monospace small"><?= e($row['student_code']) ?></td>
                  <td><?= e($row['name']) ?></td>
                  <td class="small font-monospace"><?= e($row['id_masked']) ?></td>
                  <td class="small"><?= $row['enrollment'] !== null ? e($row['enrollment']) : '<span class="text-muted">-</span>' ?></td>
                  <td class="small text-warning-emphasis"><?= e(implode(' · ', $row['warnings'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="small text-muted">เลขประจำตัวที่ระบบออกให้อาจเปลี่ยนได้หากมีผู้อื่นเพิ่มนักเรียนก่อนกดยืนยัน · บันทึกทั้งไฟล์ในครั้งเดียว หากแถวใดไม่ผ่านจะไม่บันทึกเลย</p>
        <div class="d-flex flex-wrap gap-2">
          <form method="post" action="<?= e(url('/academic/students/import/confirm')) ?>"
                data-confirm="ยืนยันบันทึกข้อมูลนักเรียน <?= count($preview['rows']) ?> รายการ?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>ยืนยันนำเข้า</button>
          </form>
          <form method="post" action="<?= e(url('/academic/students/import/confirm')) ?>">
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
