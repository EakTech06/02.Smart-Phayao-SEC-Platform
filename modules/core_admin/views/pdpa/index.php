<?php

use Modules\CoreAdmin\Services\FileService;
use Modules\CoreAdmin\Services\PdpaService;

/** @var array $requests */
/** @var bool $driveReady */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">คำขอใช้สิทธิ์ PDPA</h1>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('/pdpa/export')) ?>" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>ดาวน์โหลดข้อมูลของฉัน (JSON)</a>
    <?php if (PdpaService::canManage()): ?>
      <a href="<?= e(url('/pdpa/manage')) ?>" class="btn btn-outline-secondary"><i class="bi bi-inboxes me-1"></i>จัดการคำขอ (DPO)</a>
    <?php endif; ?>
  </div>
</div>

<div class="alert alert-info small">
  <i class="bi bi-shield-lock me-1"></i>ตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 คุณมีสิทธิ์ขอเข้าถึง ขอสำเนา ขอแก้ไข ขอลบ ขอระงับการใช้
  คัดค้าน หรือขอโอนย้ายข้อมูลส่วนบุคคลของตนเอง หน่วยงานจะดำเนินการภายใน <?= PdpaService::DUE_DAYS ?> วันนับแต่วันที่ได้รับคำขอ
  และแจ้งผลทางการแจ้งเตือนในระบบและอีเมล
</div>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square me-1"></i>ยื่นคำขอใหม่</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/pdpa')) ?>" enctype="multipart/form-data" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="f-request_type">ประเภทคำขอ <span class="text-danger">*</span></label>
            <select id="f-request_type" name="request_type" class="form-select<?= error_for('request_type') ? ' is-invalid' : '' ?>" required>
              <option value="">-- เลือก --</option>
              <?php foreach (PdpaService::TYPES as $key => [$label, $section]): ?>
                <option value="<?= e($key) ?>" <?= old('request_type') === $key ? 'selected' : '' ?>><?= e($label . ' (' . $section . ')') ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (error_for('request_type')): ?><div class="invalid-feedback"><?= e(error_for('request_type')) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="f-details">รายละเอียด <span class="text-danger">*</span></label>
            <textarea id="f-details" name="details" rows="5" maxlength="5000" class="form-control<?= error_for('details') ? ' is-invalid' : '' ?>"
                      placeholder="ระบุข้อมูลที่ต้องการ และเหตุผล เช่น ขอแก้ไขนามสกุลจาก ... เป็น ..." required><?= e(old('details')) ?></textarea>
            <?php if (error_for('details')): ?><div class="invalid-feedback"><?= e(error_for('details')) ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label" for="f-attachment">เอกสารประกอบ (ถ้ามี)</label>
            <input type="file" id="f-attachment" name="attachment" class="form-control<?= error_for('file') ? ' is-invalid' : '' ?>" accept="<?= e(FileService::ACCEPT) ?>" <?= $driveReady ? '' : 'disabled' ?>>
            <?php if (error_for('file')): ?><div class="invalid-feedback"><?= e(error_for('file')) ?></div><?php endif; ?>
            <div class="form-text"><?= $driveReady ? 'pdf, jpg, png, docx, xlsx ไม่เกิน 20 MB เห็นเฉพาะคุณและผู้ดูแลคำขอ' : 'ยังแนบไฟล์ไม่ได้ (ยังไม่ได้เชื่อมต่อ Google Drive)' ?></div>
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>ยื่นคำขอ</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-1"></i>คำขอของฉัน</div>
      <?php if ($requests === []): ?>
        <div class="card-body text-center text-muted py-5">ยังไม่มีคำขอ</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($requests as $r): ?>
            <?php [$statusLabel, $statusColor] = PdpaService::STATUSES[$r['status']]; ?>
            <a href="<?= e(url('/pdpa/' . (int) $r['id'])) ?>" class="list-group-item list-group-item-action py-3">
              <div class="d-flex justify-content-between gap-2">
                <span class="fw-semibold font-monospace"><?= e($r['request_no']) ?></span>
                <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
              </div>
              <div class="small"><?= e(PdpaService::TYPES[$r['request_type']][0] ?? $r['request_type']) ?></div>
              <div class="small text-muted">ยื่นเมื่อ <?= e(thai_date((string) $r['created_at'])) ?> · กำหนดเสร็จ <?= e(thai_date((string) $r['due_date'], false)) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
