<?php

use Modules\Academic\Services\InnovationLookup;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\StudentLookup;

/** @var array $media */
/** @var bool $isOwner */
/** @var bool $canReview */
/** @var array{manual: list<array>, report: list<array>} $versions */
/** @var array $logs */
/** @var array $threads */
/** @var string $reportDue */

$id = (int) $media['id'];
$base = '/academic/innovation/media/' . $id;
$status = (string) $media['status'];
$editable = $isOwner && in_array($status, InnovationLookup::OWNER_EDITABLE, true);
$revisionDoc = $status === 'report_revision' ? 'report' : 'manual';
$reviewOptions = [];
if ($canReview) {
    foreach (InnovationLookup::REVIEW_ACTIONS as $action => [$label, $needNote]) {
        $to = InnovationLookup::reviewTarget($status, $action);
        if ($to !== null) {
            $reviewOptions[$action] = $label . ($action === 'pass' ? ' → ' . InnovationLookup::statusLabel($to) : '') . ($needNote ? ' (ต้องระบุเหตุผล)' : '');
        }
    }
}
// ส่งฉบับแก้ไขได้เมื่อมีไฟล์ใหม่กว่าเวอร์ชันที่ถูกส่งกลับ (logs เรียงล่าสุดก่อน)
$revisionAt = 0;
foreach ($logs as $l) {
    if ($l['to_status'] === $status) {
        $revisionAt = (int) $l['version_no'];
        break;
    }
}
$canResubmit = in_array($status, ['manual_revision', 'report_revision'], true) && (int) $media[$revisionDoc . '_version'] > $revisionAt;
$canComment = $status !== 'draft' && ($canReview || ($isOwner && $threads !== []));
$maxMb = (int) (ResearchLookup::MAX_BYTES / 1024 / 1024);
$size = static fn (int $b): string => $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format(max(1, (int) round($b / 1024))) . ' KB';
$planned = $media['targets']['planned'];
$actual = $media['targets']['actual'];
$hasActual = $actual !== [] || $media['actual_start'] !== null;
$reportOverdue = $status === 'in_use' && $reportDue < date('Y-m-d');
// ขั้นตอนภาพรวม
$steps = [
    ['คู่มือการใช้สื่อ', in_array($status, ['draft', 'manual_submitted', 'manual_reviewing', 'manual_revision', 'manual_resubmitted'], true)],
    ['อยู่ระหว่างใช้สื่อ', $status === 'in_use'],
    ['รายงานการใช้สื่อ', str_starts_with($status, 'report_')],
    ['เสร็จสมบูรณ์', $status === 'completed'],
];
$reached = match (true) {
    $status === 'completed' => 3,
    str_starts_with($status, 'report_') => 2,
    $status === 'in_use' => 1,
    default => 0,
};
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/innovation/media' . ($isOwner ? '?view=mine' : ''))) ?>">สื่อนวัตกรรม</a></li>
    <li class="breadcrumb-item active" aria-current="page">รายละเอียด</li>
  </ol>
</nav>

<?php foreach (['status', 'file', 'action', 'note', 'body'] as $k): ?>
  <?php if ($e = error_for($k)): ?><div class="alert alert-danger"><?= e($e) ?></div><?php break; endif; ?>
<?php endforeach; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div class="min-w-0">
    <h1 class="h4 mb-1"><?= e($media['title']) ?></h1>
    <div class="d-flex flex-wrap align-items-center gap-2 small text-body-secondary">
      <?= InnovationLookup::statusBadge($status) ?>
      <span><i class="bi bi-person me-1"></i><?= e($media['owner_name']) ?></span>
      <span><i class="bi bi-tag me-1"></i><?= e($media['type_name']) ?></span>
    </div>
  </div>
  <?php if ($isOwner): ?>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($status === 'in_use'): ?>
        <a href="<?= e(url($base . '/report')) ?>" class="btn btn-primary"><i class="bi bi-file-earmark-arrow-up me-1"></i>ส่งรายงานการใช้สื่อ</a>
      <?php endif; ?>
      <?php if ($editable): ?>
        <a href="<?= e(url($base . '/edit')) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i><?= $status === 'report_revision' ? 'แก้ไขข้อมูลการใช้สื่อ' : 'แก้ไขข้อมูล' ?></a>
      <?php endif; ?>
      <?php if ($status === 'draft'): ?>
        <form method="post" action="<?= e(url($base . '/submit')) ?>" data-confirm="ยืนยันส่งคู่มือการใช้สื่อให้ผู้ตรวจ?">
          <?= csrf_field() ?><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>ส่งคู่มือ</button>
        </form>
      <?php endif; ?>
      <?php if ($status === 'draft' && $media['submitted_at'] === null): ?>
        <form method="post" action="<?= e(url($base)) ?>" data-confirm="ลบร่างสื่อนวัตกรรมนี้?">
          <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>ลบร่าง</button>
        </form>
      <?php elseif (in_array($status, InnovationLookup::OWNER_CANCELLABLE, true)): ?>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancel-box" aria-expanded="false" aria-controls="cancel-box"><i class="bi bi-slash-circle me-1"></i>ยกเลิก</button>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($isOwner && in_array($status, InnovationLookup::OWNER_CANCELLABLE, true) && !($status === 'draft' && $media['submitted_at'] === null)): ?>
  <form method="post" action="<?= e(url($base . '/cancel')) ?>" class="collapse card card-body shadow-sm mb-3" id="cancel-box" data-confirm="ยืนยันยกเลิกสื่อนวัตกรรมนี้? (ยกเลิกแล้วส่งต่อไม่ได้)">
    <?= csrf_field() ?>
    <label class="form-label fw-semibold" for="cancel-note">เหตุผลที่ยกเลิก</label>
    <textarea id="cancel-note" name="note" rows="2" maxlength="1000" class="form-control mb-2"></textarea>
    <div><button type="submit" class="btn btn-danger">ยืนยันยกเลิก</button></div>
  </form>
<?php endif; ?>

<ol class="list-unstyled d-flex flex-wrap gap-2 mb-3" aria-label="ขั้นตอนของสื่อนวัตกรรม">
  <?php foreach ($steps as $i => [$label, $current]): ?>
    <?php $done = !in_array($status, ['failed', 'cancelled'], true) && ($i < $reached || $status === 'completed'); ?>
    <li class="badge rounded-pill <?= $current ? 'text-bg-primary' : ($done ? 'text-bg-success' : 'text-bg-light border') ?> px-3 py-2"<?= $current ? ' aria-current="step"' : '' ?>>
      <i class="bi bi-<?= $done ? 'check-circle' : ($current ? 'arrow-right-circle' : 'circle') ?> me-1"></i><?= $i + 1 ?>. <?= e($label) ?>
    </li>
  <?php endforeach; ?>
</ol>

<?php if ($isOwner && $status === 'in_use'): ?>
  <div class="alert alert-<?= $reportOverdue ? 'danger' : 'info' ?>"><i class="bi bi-info-circle me-1"></i>คู่มือได้รับอนุมัติแล้ว ใช้สื่อกับกลุ่มเป้าหมายตามแผน แล้วส่งรายงานการใช้สื่อภายใน <strong><?= e(thai_date($reportDue, false, true)) ?></strong><?= $reportOverdue ? ' (เลยกำหนดแล้ว)' : '' ?></div>
<?php elseif ($isOwner && in_array($status, ['manual_revision', 'report_revision'], true)): ?>
  <div class="alert alert-warning"><i class="bi bi-arrow-return-left me-1"></i>ผู้ตรวจส่งกลับให้แก้ไข<?= e(InnovationLookup::DOCS[$revisionDoc]) ?> ดูข้อเสนอแนะด้านล่าง แล้วอัปโหลดไฟล์ฉบับแก้ไขเพื่อส่งอีกครั้ง</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-xl-7">
    <div class="card shadow-sm mb-3">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i>ข้อมูลสื่อนวัตกรรม</div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-sm-4">ประเภทสื่อนวัตกรรม</dt><dd class="col-sm-8"><?= e($media['type_name']) ?></dd>
          <dt class="col-sm-4">ปีการศึกษา / ภาคเรียน</dt><dd class="col-sm-8"><?= e($media['year_name']) ?> · <?= e(ResearchLookup::semesterLabel((int) $media['semester'])) ?></dd>
          <dt class="col-sm-4">ช่วงเวลาใช้สื่อตามแผน</dt><dd class="col-sm-8"><?= e(thai_date((string) $media['plan_start'], false)) ?> - <?= e(thai_date((string) $media['plan_end'], false)) ?></dd>
          <dt class="col-sm-4">กำหนดส่งรายงาน</dt>
          <dd class="col-sm-8<?= $reportOverdue ? ' text-danger fw-semibold' : '' ?>"><?= e(thai_date($reportDue, false, true)) ?><?= $media['report_due_on'] === null ? ' <span class="text-body-secondary">(โดยประมาณ กำหนดจริงเมื่อคู่มืออนุมัติ)</span>' : '' ?><?= $reportOverdue ? ' (เลยกำหนด)' : '' ?></dd>
          <?php if ($media['actual_start'] !== null): ?>
            <dt class="col-sm-4">วันที่ใช้สื่อจริง</dt><dd class="col-sm-8"><?= e(thai_date((string) $media['actual_start'], false)) ?> - <?= e(thai_date((string) $media['actual_end'], false)) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4">ผู้จัดทำ</dt><dd class="col-sm-8"><?= e($media['owner_name']) ?><?= $media['owner_position'] ? ' · ' . e($media['owner_position']) : '' ?></dd>
          <dt class="col-sm-4">ส่งคู่มือครั้งแรก</dt><dd class="col-sm-8"><?= $media['submitted_at'] ? e(thai_date((string) $media['submitted_at'])) : 'ยังไม่ส่ง' ?></dd>
          <?php if ($media['manual_approved_at']): ?><dt class="col-sm-4">คู่มืออนุมัติเมื่อ</dt><dd class="col-sm-8"><?= e(thai_date((string) $media['manual_approved_at'])) ?></dd><?php endif; ?>
          <?php if ($media['completed_at']): ?><dt class="col-sm-4">เสร็จสมบูรณ์เมื่อ</dt><dd class="col-sm-8"><?= e(thai_date((string) $media['completed_at'])) ?></dd><?php endif; ?>
        </dl>
      </div>
      <div class="table-responsive border-top">
        <table class="table table-sm mb-0 align-middle small">
          <caption class="px-3 small">จำนวนกลุ่มเป้าหมาย (คน) แยกตามประเภทความพิการ</caption>
          <thead><tr><th scope="col" class="ps-3">ประเภทความพิการ</th><th scope="col" class="text-end">ตามแผน</th><th scope="col" class="text-end pe-3">ใช้จริง</th></tr></thead>
          <tbody>
            <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
              <?php if (!isset($planned[$k]) && !isset($actual[$k])) { continue; } ?>
              <tr><td class="ps-3"><?= e($label) ?></td><td class="text-end"><?= (int) ($planned[$k] ?? 0) ?></td><td class="text-end pe-3"><?= $hasActual ? (int) ($actual[$k] ?? 0) : '-' ?></td></tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot><tr class="fw-semibold"><td class="ps-3">รวม</td><td class="text-end"><?= array_sum($planned) ?></td><td class="text-end pe-3"><?= $hasActual ? array_sum($actual) : '-' ?></td></tr></tfoot>
        </table>
      </div>
    </div>

    <div class="card shadow-sm mb-3" id="versions">
      <div class="card-header"><i class="bi bi-files me-1"></i>เอกสาร (ทุกเวอร์ชัน)</div>
      <?php foreach (InnovationLookup::DOCS as $doc => $docLabel): ?>
        <?php if ($versions[$doc] === [] && $doc === 'report') { continue; } ?>
        <div class="px-3 pt-2 small fw-semibold text-body-secondary"><?= $doc === 'manual' ? 'ระยะที่ 1' : 'ระยะที่ 2' ?> · <?= e($docLabel) ?></div>
        <div class="list-group list-group-flush">
          <?php foreach ($versions[$doc] as $v): ?>
            <?php $file = $base . '/versions/' . (int) $v['id']; ?>
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
              <i class="bi bi-file-earmark-pdf text-danger fs-4" aria-hidden="true"></i>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate">เวอร์ชันที่ <?= (int) $v['version_no'] ?><?= (int) $v['version_no'] === (int) $media[$doc . '_version'] ? ' <span class="badge text-bg-primary">ล่าสุด</span>' : '' ?> · <?= e($v['original_name']) ?></div>
                <div class="small text-body-secondary"><?= e(thai_date((string) $v['created_at'])) ?> · <?= e($size((int) $v['size_bytes'])) ?> · <?= e($v['uploader_name'] ?? '-') ?><?= $v['note'] ? ' · ' . e($v['note']) : '' ?></div>
              </div>
              <div class="d-flex gap-1 ms-auto">
                <a href="<?= e(url($file . '?inline=1')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-eye me-1"></i>เปิดดู</a>
                <a href="<?= e(url($file)) ?>" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-download me-1"></i>ดาวน์โหลด</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($editable): ?>
        <form method="post" action="<?= e(url($base . '/versions')) ?>" enctype="multipart/form-data" class="card-body border-top">
          <?= csrf_field() ?>
          <div class="fw-semibold mb-2"><i class="bi bi-cloud-upload me-1"></i>อัปโหลด<?= e(InnovationLookup::DOCS[$revisionDoc]) ?><?= $status === 'draft' ? 'ฉบับใหม่' : 'ฉบับแก้ไข' ?></div>
          <div class="small text-warning-emphasis mb-2"><i class="bi bi-shield-exclamation me-1"></i>ปิดชื่อหรือภาพที่ระบุตัวผู้เรียนในไฟล์ก่อนส่ง</div>
          <div class="row g-2">
            <div class="col-12 col-md-6">
              <label class="visually-hidden" for="v-file">ไฟล์ PDF</label>
              <input type="file" id="v-file" name="file" required accept=".pdf,application/pdf" class="form-control">
              <div class="form-text">PDF ไม่เกิน <?= $maxMb ?> MB · ไฟล์เดิมทุกเวอร์ชันยังเก็บไว้</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="visually-hidden" for="v-note">สิ่งที่แก้ไข</label>
              <input type="text" id="v-note" name="note" maxlength="500" class="form-control" placeholder="สรุปสิ่งที่แก้ไข (ไม่บังคับ)">
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if ($status !== 'draft'): ?>
              <button type="submit" name="submit" value="1" class="btn btn-primary"><i class="bi bi-send me-1"></i>อัปโหลดและส่งฉบับแก้ไข</button>
              <button type="submit" class="btn btn-outline-primary">อัปโหลดอย่างเดียว</button>
            <?php else: ?>
              <button type="submit" class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i>อัปโหลด</button>
            <?php endif; ?>
          </div>
        </form>
        <?php if ($canResubmit): ?>
          <form method="post" action="<?= e(url($base . '/submit')) ?>" class="card-body border-top pt-2" data-confirm="ส่งฉบับแก้ไขจากไฟล์เวอร์ชันล่าสุด?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link px-0">อัปโหลดไว้แล้ว? ส่งฉบับแก้ไขจากเวอร์ชันล่าสุด</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card shadow-sm mb-3" id="feedback">
      <div class="card-header"><i class="bi bi-chat-left-text me-1"></i>ข้อเสนอแนะ</div>
      <div class="card-body">
        <?php if ($threads === []): ?><p class="text-body-secondary small mb-0">ยังไม่มีข้อเสนอแนะ</p><?php endif; ?>
        <?php foreach ($threads as $t): ?>
          <div class="border rounded-3 p-3 mb-3">
            <div class="d-flex justify-content-between gap-2 small text-body-secondary mb-1">
              <span><i class="bi bi-person-check me-1"></i><strong><?= e($t['user_name'] ?? '-') ?></strong> · <?= e($t['doc'] === 'manual' ? 'คู่มือ' : 'รายงาน') ?><?= $t['version_no'] ? ' เวอร์ชันที่ ' . (int) $t['version_no'] : '' ?></span>
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
          <form method="post" action="<?= e(url($base . '/comments')) ?>" class="mt-3">
            <?= csrf_field() ?>
            <label class="form-label fw-semibold" for="new-comment">เขียนข้อเสนอแนะใหม่ (<?= e(InnovationLookup::DOCS[InnovationLookup::stage($status)]) ?>)</label>
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
        <div class="card-header fw-semibold"><i class="bi bi-clipboard-check me-1"></i>ผลการตรวจ<?= e(InnovationLookup::DOCS[InnovationLookup::stage($status)]) ?></div>
        <div class="card-body">
          <label class="form-label" for="review-action">ผลการตรวจ / สถานะใหม่</label>
          <select id="review-action" name="action" required class="form-select mb-2">
            <option value="">-- เลือก --</option>
            <?php foreach ($reviewOptions as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="review-note">หมายเหตุ / เหตุผล</label>
          <textarea id="review-note" name="note" rows="3" maxlength="1000" class="form-control mb-2" placeholder="จำเป็นเมื่อส่งกลับแก้ไข ไม่ผ่าน หรือยกเลิก"></textarea>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-square me-1"></i>บันทึกผลการตรวจ</button>
          <div class="form-text">ระบบแจ้งครูเจ้าของงานทันที<?= InnovationLookup::stage($status) === 'manual' ? ' · ผ่านการตรวจคู่มือแล้ว สถานะเป็น "อยู่ระหว่างใช้สื่อ" และกำหนดวันส่งรายงาน' : '' ?></div>
        </div>
      </form>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header"><i class="bi bi-clock-history me-1"></i>ประวัติสถานะ</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($logs as $l): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between gap-2">
              <span><?= $l['from_status'] ? e(InnovationLookup::statusLabel((string) $l['from_status'])) . ' <i class="bi bi-arrow-right"></i> ' : '' ?><strong><?= e(InnovationLookup::statusLabel((string) $l['to_status'])) ?></strong></span>
              <span class="text-body-secondary text-nowrap"><?= e(thai_date((string) $l['created_at'])) ?></span>
            </div>
            <div class="text-body-secondary"><?= e($l['user_name'] ?? 'ระบบ') ?> · <?= e($l['doc'] === 'manual' ? 'คู่มือ' : 'รายงาน') ?><?= $l['version_no'] ? ' เวอร์ชันที่ ' . (int) $l['version_no'] : '' ?></div>
            <?php if ($l['note']): ?><div class="mt-1 text-break" style="white-space: pre-line"><?= e($l['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
