<?php

use Modules\CoreAdmin\Services\FileService;
use Modules\CoreAdmin\Services\PdpaService;

/** @var array $req */
/** @var array $attachments */
/** @var bool $canHandle */
/** @var bool $isOwner */
/** @var bool $driveReady */

[$statusLabel, $statusColor] = PdpaService::STATUSES[$req['status']];
$overdue = PdpaService::isOverdue($req);
// ข้อผิดพลาดที่ไม่มีช่องกรอกให้แสดง (เช่น คำขอถูกปิดไปแล้วระหว่างที่อีกคนกำลังบันทึก)
$orphanErrors = $canHandle ? [] : array_merge(...array_values(errors() ?: [[]]));
?>
<?php if ($orphanErrors !== []): ?>
  <div class="alert alert-danger"><?= implode('<br>', array_map('e', $orphanErrors)) ?></div>
<?php endif; ?>
<a href="<?= e(url(PdpaService::canManage() && !$isOwner ? '/pdpa/manage' : '/pdpa')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
      <h1 class="h4 mb-0">คำขอ <span class="font-monospace"><?= e($req['request_no']) ?></span></h1>
      <div>
        <span class="badge text-bg-<?= e($statusColor) ?> fs-6"><?= e($statusLabel) ?></span>
        <?php if ($overdue): ?><span class="badge text-bg-danger fs-6">เกินกำหนด</span><?php endif; ?>
      </div>
    </div>
    <dl class="row mb-0">
      <dt class="col-sm-3">ประเภทคำขอ</dt>
      <dd class="col-sm-9"><?= e(PdpaService::TYPES[$req['request_type']][0] ?? $req['request_type']) ?> <span class="text-muted small">(<?= e(PdpaService::TYPES[$req['request_type']][1] ?? '') ?>)</span></dd>
      <dt class="col-sm-3">ผู้ยื่นคำขอ</dt>
      <dd class="col-sm-9"><?= e(trim((string) $req['requester_name']) ?: $req['username']) ?><?= $req['employee_code'] ? ' (' . e($req['employee_code']) . ')' : '' ?></dd>
      <dt class="col-sm-3">วันที่ยื่น</dt><dd class="col-sm-9"><?= e(thai_date((string) $req['created_at'])) ?></dd>
      <dt class="col-sm-3">กำหนดดำเนินการ</dt>
      <dd class="col-sm-9<?= $overdue ? ' text-danger fw-semibold' : '' ?>"><?= e(thai_date((string) $req['due_date'], false, true)) ?> (<?= PdpaService::DUE_DAYS ?> วันตามกฎหมาย)</dd>
      <dt class="col-sm-3">รายละเอียด</dt><dd class="col-sm-9 text-break"><?= nl2br(e($req['details'])) ?></dd>
      <?php if ($req['response'] !== null): ?>
        <dt class="col-sm-3">ผลการดำเนินการ</dt>
        <dd class="col-sm-9 text-break">
          <?= nl2br(e($req['response'])) ?>
          <div class="small text-muted mt-1">โดย <?= e(trim((string) $req['handler_name']) ?: '-') ?> · <?= e(thai_date((string) $req['handled_at'])) ?></div>
        </dd>
      <?php endif; ?>
      <?php if ($attachments !== []): ?>
        <dt class="col-sm-3">ไฟล์แนบ</dt>
        <dd class="col-sm-9">
          <?php foreach ($attachments as $f): ?>
            <div>
              <a href="<?= e(url('/files/' . $f['uuid'] . '/download')) ?>"><i class="bi bi-<?= e(FileService::icon((string) $f['mime_type'])) ?> me-1"></i><?= e($f['original_name']) ?></a>
              <span class="small text-muted">(<?= e(FileService::humanSize((int) $f['size_bytes'])) ?><?= (int) $f['owner_user_id'] === (int) $req['user_id'] ? ' · ผู้ยื่น' : ' · ผู้ดูแล' ?>)</span>
            </div>
          <?php endforeach; ?>
        </dd>
      <?php endif; ?>
    </dl>

    <?php if ($isOwner && $req['status'] === 'pending'): ?>
      <form method="post" action="<?= e(url('/pdpa/' . (int) $req['id'] . '/cancel')) ?>" class="mt-3" data-confirm="ยกเลิกคำขอ <?= e($req['request_no']) ?>?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>ยกเลิกคำขอ</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($canHandle): ?>
  <div class="card shadow-sm border-primary-subtle">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-check me-1"></i>บันทึกผลการดำเนินการ (DPO)</div>
    <div class="card-body">
      <?php if ($req['request_type'] === 'access' || $req['request_type'] === 'portability'): ?>
        <div class="alert alert-light border small">
          <i class="bi bi-lightbulb me-1"></i>ผู้ยื่นสามารถดาวน์โหลดสำเนาข้อมูลของตนเองได้ที่เมนู "คำขอใช้สิทธิ์ PDPA → ดาวน์โหลดข้อมูลของฉัน"
          หากต้องการส่งเอกสารเพิ่มเติม ให้แนบไฟล์ด้านล่าง
        </div>
      <?php elseif ($req['request_type'] === 'rectification'): ?>
        <div class="alert alert-light border small"><i class="bi bi-lightbulb me-1"></i>แก้ไขข้อมูลที่เมนู "ข้อมูลบุคลากร" แล้วกลับมาบันทึกผลที่นี่</div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/pdpa/' . (int) $req['id'] . '/handle')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label" for="f-status">สถานะ <span class="text-danger">*</span></label>
            <select id="f-status" name="status" class="form-select<?= error_for('status') ? ' is-invalid' : '' ?>">
              <?php foreach (PdpaService::HANDLER_STATUSES as $s): ?>
                <option value="<?= e($s) ?>" <?= old('status', $req['status'] === 'pending' ? 'in_progress' : 'completed') === $s ? 'selected' : '' ?>><?= e(PdpaService::STATUSES[$s][0]) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (error_for('status')): ?><div class="invalid-feedback"><?= e(error_for('status')) ?></div><?php endif; ?>
          </div>
          <div class="col-12 col-md-8">
            <label class="form-label" for="f-attachment">แนบไฟล์ผลการดำเนินการ</label>
            <input type="file" id="f-attachment" name="attachment" class="form-control<?= error_for('file') ? ' is-invalid' : '' ?>" accept="<?= e(FileService::ACCEPT) ?>" <?= $driveReady ? '' : 'disabled' ?>>
            <?php if (error_for('file')): ?><div class="invalid-feedback"><?= e(error_for('file')) ?></div><?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="f-response">ผลการดำเนินการ / เหตุผล (แจ้งผู้ยื่นทางอีเมลและในระบบ)</label>
            <textarea id="f-response" name="response" rows="4" maxlength="5000" class="form-control<?= error_for('response') ? ' is-invalid' : '' ?>"><?= e(old('response')) ?></textarea>
            <?php if (error_for('response')): ?><div class="invalid-feedback"><?= e(error_for('response')) ?></div><?php endif; ?>
            <div class="form-text">จำเป็นเมื่อเลือก "ดำเนินการเสร็จสิ้น" หรือ "ปฏิเสธคำขอ" (กรณีปฏิเสธต้องระบุเหตุผลตามกฎหมาย)</div>
          </div>
        </div>
        <div class="text-end mt-3">
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึกและแจ้งผู้ยื่น</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
