<?php

use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\StudentLookup;

/** @var array $work */
/** @var bool $isOwner */
/** @var bool $canReview */
/** @var array $versions */
/** @var array $logs */
/** @var array $threads */
/** @var array|null $round */
/** @var list<array> $signing */
/** @var int|null $awaiting */

$id = (int) $work['id'];
$base = '/academic/research/works/' . $id;
$status = (string) $work['status'];
$editable = $isOwner && in_array($status, ResearchLookup::OWNER_EDITABLE, true);
$reviewOptions = [];
if ($canReview) {
    foreach (ResearchLookup::REVIEW_TRANSITIONS as $to => $from) {
        if (in_array($status, $from, true)) {
            $reviewOptions[$to] = ResearchLookup::statusLabel($to);
        }
    }
}
$maxMb = (int) (ResearchLookup::MAX_BYTES / 1024 / 1024);
$canComment = $status !== 'draft' && ($canReview || ($isOwner && $threads !== []));
$size = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format(max(1, (int) round($b / 1024))) . ' KB';
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research/works' . ($isOwner ? '?view=mine' : ''))) ?>">รายการงานวิจัย</a></li>
    <li class="breadcrumb-item active" aria-current="page">รายละเอียด</li>
  </ol>
</nav>

<?php foreach (['status', 'file', 'to_status', 'note', 'body', 'boxes', 'reason', 'password'] as $k): ?>
  <?php if ($e = error_for($k)): ?><div class="alert alert-danger"><?= e($e) ?></div><?php break; endif; ?>
<?php endforeach; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div class="min-w-0">
    <h1 class="h4 mb-1"><?= e($work['title']) ?></h1>
    <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary">
      <?= ResearchLookup::statusBadge($status) ?>
      <span><i class="bi bi-person me-1"></i><?= e($work['owner_name']) ?></span>
      <span><i class="bi bi-file-earmark me-1"></i>เวอร์ชันล่าสุด <?= (int) $work['current_version'] ?></span>
    </div>
  </div>
  <?php if ($isOwner): ?>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($editable): ?>
        <a href="<?= e(url($base . '/edit')) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>แก้ไขข้อมูล</a>
      <?php endif; ?>
      <?php if ($status === 'draft'): ?>
        <a href="<?= e(url($base . '/sign')) ?>" class="btn btn-primary"><i class="bi bi-pen me-1"></i>ลงนามและส่งงาน</a>
      <?php endif; ?>
      <?php if (in_array($status, ResearchLookup::OWNER_CANCELLABLE, true)): ?>
        <?php if ($status === 'draft' && $work['submitted_at'] === null): ?>
          <form method="post" action="<?= e(url($base)) ?>" data-confirm="ลบร่างงานวิจัยนี้?">
            <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>ลบร่าง</button>
          </form>
        <?php else: ?>
          <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancel-box" aria-expanded="false" aria-controls="cancel-box"><i class="bi bi-slash-circle me-1"></i>ยกเลิกงาน</button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($isOwner && in_array($status, ResearchLookup::OWNER_CANCELLABLE, true) && !($status === 'draft' && $work['submitted_at'] === null)): ?>
  <form method="post" action="<?= e(url($base . '/cancel')) ?>" class="collapse card card-body shadow-sm mb-3" id="cancel-box" data-confirm="ยืนยันยกเลิกงานวิจัยนี้? (ยกเลิกแล้วส่งต่อไม่ได้)">
    <?= csrf_field() ?>
    <label class="form-label fw-semibold" for="cancel-note">เหตุผลที่ยกเลิก</label>
    <textarea id="cancel-note" name="note" rows="2" maxlength="1000" class="form-control mb-2"></textarea>
    <div><button type="submit" class="btn btn-danger">ยืนยันยกเลิกงาน</button></div>
  </form>
<?php endif; ?>

<?php if ($isOwner && $status === 'revision'): ?>
  <div class="alert alert-warning"><i class="bi bi-arrow-return-left me-1"></i>ถูกส่งกลับให้แก้ไข ดูข้อเสนอแนะ/ประวัติสถานะ แล้วอัปโหลดไฟล์ฉบับแก้ไขเพื่อลงนามและส่งอีกครั้ง (ลายเซ็นเดิมถูกยกเลิกแล้ว)</div>
<?php endif; ?>
<?php if ($awaiting !== null && \Modules\Academic\Services\SigningLookup::canSignOrder($awaiting, (int) $work['owner_user_id'], (int) \Core\Auth::id())): ?>
  <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
    <span class="me-auto"><i class="bi bi-pen me-1"></i>เอกสารนี้รอคุณลงนามลำดับที่ <?= (int) $awaiting ?> (<?= e(\Modules\Academic\Services\SigningLookup::ORDERS[$awaiting]) ?>)</span>
    <a href="<?= e(url($base . '/sign')) ?>" class="btn btn-success"><i class="bi bi-pen me-1"></i>ไปหน้าลงนาม</a>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-xl-7">
    <div class="card shadow-sm mb-3">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i>ข้อมูลงานวิจัย</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-sm-4">ประเภทงานวิจัย</dt><dd class="col-sm-8"><?= e($work['type_name']) ?></dd>
          <dt class="col-sm-4">ปีการศึกษา / ภาคเรียน</dt><dd class="col-sm-8"><?= e($work['year_name']) ?> · <?= e(ResearchLookup::semesterLabel((int) $work['semester'])) ?></dd>
          <dt class="col-sm-4">ประเภทความพิการของกลุ่มเป้าหมาย</dt>
          <dd class="col-sm-8"><?php foreach ($work['disabilities'] as $d): ?><span class="badge text-bg-light border me-1 mb-1"><?= e(StudentLookup::DISABILITY_TYPES[$d] ?? $d) ?></span><?php endforeach; ?></dd>
          <dt class="col-sm-4">จำนวนกลุ่มเป้าหมาย</dt><dd class="col-sm-8"><?= number_format((int) $work['target_count']) ?> คน</dd>
          <dt class="col-sm-4">คำสำคัญ</dt>
          <dd class="col-sm-8"><?php if ($work['keywords']): foreach (explode(', ', (string) $work['keywords']) as $k): ?><span class="badge rounded-pill text-bg-secondary me-1 mb-1"><?= e($k) ?></span><?php endforeach; else: ?>-<?php endif; ?></dd>
          <dt class="col-sm-4">ผู้จัดทำ</dt><dd class="col-sm-8"><?= e($work['owner_name']) ?><?= $work['owner_position'] ? ' · ' . e($work['owner_position']) : '' ?></dd>
          <dt class="col-sm-4">ส่งครั้งแรก</dt><dd class="col-sm-8"><?= $work['submitted_at'] ? e(thai_date((string) $work['submitted_at'])) : 'ยังไม่ส่ง' ?></dd>
          <?php if ($round !== null): ?>
            <dt class="col-sm-4">กำหนดส่งของรอบ</dt><dd class="col-sm-8"><?= e($round['title']) ?> · <?= e(thai_date((string) $round['due_on'], false, true)) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mb-3" id="versions">
      <div class="card-header"><i class="bi bi-files me-1"></i>ไฟล์งานวิจัย (ทุกเวอร์ชัน)</div>
      <div class="list-group list-group-flush">
        <?php foreach ($versions as $v): ?>
          <?php $file = $base . '/versions/' . (int) $v['id']; $isPdf = $v['mime_type'] === 'application/pdf'; ?>
          <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-<?= $isPdf ? 'file-earmark-pdf text-danger' : 'file-earmark-word text-primary' ?> fs-4" aria-hidden="true"></i>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate">เวอร์ชันที่ <?= (int) $v['version_no'] ?><?= (int) $v['version_no'] === (int) $work['current_version'] ? ' <span class="badge text-bg-primary">ล่าสุด</span>' : '' ?> · <?= e($v['original_name']) ?></div>
              <div class="small text-body-secondary"><?= e(thai_date((string) $v['created_at'])) ?> · <?= e($size((int) $v['size_bytes'])) ?> · <?= e($v['uploader_name'] ?? '-') ?><?= $v['note'] ? ' · ' . e($v['note']) : '' ?></div>
            </div>
            <div class="d-flex gap-1 ms-auto">
              <?php if ($isPdf): ?>
                <a href="<?= e(url($file . '?inline=1')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-eye me-1"></i>เปิดดู</a>
              <?php endif; ?>
              <a href="<?= e(url($file)) ?>" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-download me-1"></i>ดาวน์โหลด</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($editable): ?>
        <form method="post" action="<?= e(url($base . '/versions')) ?>" enctype="multipart/form-data" class="card-body border-top">
          <?= csrf_field() ?>
          <div class="fw-semibold mb-2"><i class="bi bi-cloud-upload me-1"></i><?= $status === 'revision' ? 'อัปโหลดไฟล์ฉบับแก้ไข' : 'อัปโหลดไฟล์ฉบับใหม่' ?></div>
          <div class="small text-warning-emphasis mb-2"><i class="bi bi-shield-exclamation me-1"></i>ปิดชื่อหรือภาพที่ระบุตัวผู้เรียนในไฟล์ก่อนส่ง</div>
          <div class="row g-2">
            <div class="col-12 col-md-6">
              <label class="visually-hidden" for="v-file">ไฟล์</label>
              <input type="file" id="v-file" name="file" required accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="form-control">
              <div class="form-text">PDF หรือ DOCX ไม่เกิน <?= $maxMb ?> MB · DOCX ใช้เป็นฉบับร่างเท่านั้น ฉบับที่ส่งลงนามต้องเป็น PDF · ไฟล์เดิมทุกเวอร์ชันยังเก็บไว้</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="visually-hidden" for="v-note">สิ่งที่แก้ไข</label>
              <input type="text" id="v-note" name="note" maxlength="500" class="form-control" placeholder="สรุปสิ่งที่แก้ไข (ไม่บังคับ)">
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if ($status === 'revision'): ?>
              <button type="submit" name="submit" value="1" class="btn btn-primary"><i class="bi bi-pen me-1"></i>อัปโหลดแล้วลงนามส่งฉบับแก้ไข</button>
              <button type="submit" class="btn btn-outline-primary">อัปโหลดอย่างเดียว</button>
            <?php else: ?>
              <button type="submit" class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i>อัปโหลด</button>
            <?php endif; ?>
          </div>
        </form>
        <?php
        // ส่งฉบับแก้ไขได้เมื่อมีไฟล์ใหม่กว่าเวอร์ชันที่ถูกส่งกลับ (logs เรียงล่าสุดก่อน)
        $revisionAt = 0;
        foreach ($logs as $l) {
            if ($l['to_status'] === 'revision') {
                $revisionAt = (int) $l['version_no'];
                break;
            }
        }
        ?>
        <?php if ($status === 'revision' && (int) $work['current_version'] > $revisionAt): ?>
          <div class="card-body border-top pt-2">
            <a href="<?= e(url($base . '/sign')) ?>" class="btn btn-link px-0">อัปโหลดไว้แล้ว? ลงนามและส่งฉบับแก้ไขจากเวอร์ชันล่าสุด</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card shadow-sm mb-3" id="feedback">
      <div class="card-header"><i class="bi bi-chat-left-text me-1"></i>ข้อเสนอแนะ</div>
      <div class="card-body">
        <?php if ($threads === []): ?>
          <p class="text-body-secondary small mb-0">ยังไม่มีข้อเสนอแนะ</p>
        <?php endif; ?>
        <?php foreach ($threads as $t): ?>
          <div class="border rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between gap-2 small text-body-secondary mb-1">
              <span><i class="bi bi-person-check me-1"></i><strong><?= e($t['user_name'] ?? '-') ?></strong><?= $t['version_no'] ? ' · เวอร์ชันที่ ' . (int) $t['version_no'] : '' ?></span>
              <span><?= e(thai_date((string) $t['created_at'])) ?></span>
            </div>
            <div class="text-break" style="white-space: pre-line"><?= e($t['body']) ?></div>
            <?php foreach ($t['replies'] as $r): ?>
              <div class="border-start border-3 ps-3 mt-3 ms-2">
                <div class="d-flex justify-content-between gap-2 small text-body-secondary mb-1">
                  <span><i class="bi bi-reply me-1"></i><strong><?= e($r['user_name'] ?? '-') ?></strong></span>
                  <span><?= e(thai_date((string) $r['created_at'])) ?></span>
                </div>
                <div class="text-break" style="white-space: pre-line"><?= e($r['body']) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if ($canComment): ?>
              <form method="post" action="<?= e(url($base . '/comments')) ?>" class="mt-3 d-flex gap-2">
                <?= csrf_field() ?><input type="hidden" name="parent_id" value="<?= (int) $t['id'] ?>">
                <label class="visually-hidden" for="reply-<?= (int) $t['id'] ?>">ตอบกลับ</label>
                <textarea id="reply-<?= (int) $t['id'] ?>" name="body" rows="1" maxlength="3000" required class="form-control form-control-sm" placeholder="ตอบกลับในเรื่องนี้"></textarea>
                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-reply me-1"></i>ตอบกลับ</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($canReview && $status !== 'draft'): ?>
          <form method="post" action="<?= e(url($base . '/comments')) ?>">
            <?= csrf_field() ?>
            <label class="form-label fw-semibold" for="new-comment">เขียนข้อเสนอแนะใหม่</label>
            <textarea id="new-comment" name="body" rows="3" maxlength="3000" required class="form-control mb-2"></textarea>
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-chat-left-text me-1"></i>บันทึกข้อเสนอแนะ</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-5">
    <?php if ($reviewOptions !== []): ?>
      <form method="post" action="<?= e(url($base . '/review')) ?>" class="card shadow-sm mb-3 border-primary">
        <?= csrf_field() ?>
        <div class="card-header fw-semibold"><i class="bi bi-clipboard-check me-1"></i>ผลการตรวจ / เปลี่ยนสถานะ</div>
        <div class="card-body">
          <label class="form-label" for="to-status">สถานะใหม่</label>
          <select id="to-status" name="to_status" required class="form-select mb-2">
            <option value="">-- เลือก --</option>
            <?php foreach ($reviewOptions as $k => $label): ?>
              <option value="<?= e($k) ?>"><?= e($label) ?><?= in_array($k, ResearchLookup::NOTE_REQUIRED, true) ? ' (ต้องระบุเหตุผล)' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label" for="review-note">หมายเหตุ / เหตุผล</label>
          <textarea id="review-note" name="note" rows="3" maxlength="1000" class="form-control mb-2" placeholder="จำเป็นเมื่อส่งกลับแก้ไข ไม่ผ่าน หรือยกเลิก"></textarea>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-square me-1"></i>บันทึกผลการตรวจ</button>
          <div class="form-text">ระบบแจ้งครูเจ้าของงานทันที</div>
        </div>
      </form>
    <?php endif; ?>

    <?php $signBase = $base; $revokable = in_array($status, ['approved', 'passed', 'sign_3', 'sign_4'], true); require __DIR__ . '/../signing/_card.php'; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header"><i class="bi bi-clock-history me-1"></i>ประวัติสถานะ</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($logs as $l): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between gap-2">
              <span><?= $l['from_status'] ? e(ResearchLookup::statusLabel((string) $l['from_status'])) . ' <i class="bi bi-arrow-right"></i> ' : '' ?><strong><?= e(ResearchLookup::statusLabel((string) $l['to_status'])) ?></strong></span>
              <span class="text-body-secondary text-nowrap"><?= e(thai_date((string) $l['created_at'])) ?></span>
            </div>
            <div class="text-body-secondary"><?= e($l['user_name'] ?? 'ระบบ') ?><?= $l['version_no'] ? ' · เวอร์ชันที่ ' . (int) $l['version_no'] : '' ?></div>
            <?php if ($l['note']): ?><div class="mt-1 text-break" style="white-space: pre-line"><?= e($l['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
