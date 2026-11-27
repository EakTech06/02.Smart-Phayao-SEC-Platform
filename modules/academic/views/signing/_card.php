<?php

use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\SigningLookup;

/**
 * การ์ดสถานะการลงนาม 4 ลำดับ (ใช้ในหน้ารายละเอียดงานวิจัย/สื่อนวัตกรรม)
 * @var list<array{type: string, signatures: array, document: ?array, history: array}> $signing
 * @var string $signBase URL หน้างาน
 * @var bool $revokable ผู้ดูแลยกเลิกการอนุมัติได้ในสถานะนี้
 */
$canManage = can(ResearchLookup::PERM_MANAGE);
?>
<?php foreach ($signing as $i => $sum): ?>
  <?php $doc = $sum['document']; $sigs = $sum['signatures']; ?>
  <div class="card shadow-sm mb-3<?= $doc !== null ? ' border-success' : '' ?>" id="signing-<?= e($sum['type']) ?>">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
      <span class="me-auto"><i class="bi bi-pen me-1"></i>การลงนาม · <?= e(SigningLookup::DOC_TYPES[$sum['type']]) ?></span>
      <?php if ($doc !== null): ?><span class="badge text-bg-success"><i class="bi bi-patch-check me-1"></i>ลงนามครบ 4 ลำดับ</span><?php endif; ?>
    </div>
    <?php if ($doc !== null): ?>
      <div class="card-body border-bottom bg-success-subtle">
        <div class="small text-body-secondary">เลขที่เอกสาร</div>
        <div class="fs-5 fw-semibold font-monospace"><?= e($doc['doc_no']) ?></div>
        <div class="small text-body-secondary mb-2">อนุมัติ <?= e(thai_date((string) $doc['approved_at'])) ?> · ไฟล์ถูกล็อก มี QR Code ตรวจสอบท้ายหน้าสุดท้าย</div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= e(url($signBase . '/signed/' . (int) $doc['signature_id'])) ?>" class="btn btn-sm btn-success"><i class="bi bi-download me-1"></i>ดาวน์โหลดฉบับลงนามครบ</a>
          <a href="<?= e(url($signBase . '/signed/' . (int) $doc['signature_id'] . '?inline=1')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success"><i class="bi bi-printer me-1"></i>เปิดเพื่อพิมพ์</a>
        </div>
      </div>
    <?php endif; ?>
    <ul class="list-group list-group-flush small">
      <?php foreach (SigningLookup::ORDERS as $order => $label): ?>
        <?php $s = $sigs[$order] ?? null; ?>
        <li class="list-group-item d-flex align-items-start gap-2">
          <span class="badge rounded-pill mt-1" style="background: <?= e(SigningLookup::COLORS[$order]) ?>"><?= (int) $order ?></span>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold"><?= e($label) ?></div>
            <?php if ($s !== null): ?>
              <div class="text-body-secondary"><i class="bi bi-check-circle-fill text-success me-1"></i><?= e($s['signer_name']) ?> · <?= e(thai_date((string) $s['signed_at'])) ?> · ไฟล์เวอร์ชันที่ <?= (int) $s['version_no'] ?></div>
            <?php else: ?>
              <div class="text-body-secondary"><i class="bi bi-hourglass me-1"></i>ยังไม่ลงนาม</div>
            <?php endif; ?>
          </div>
          <?php if ($s !== null): ?>
            <a href="<?= e(url($signBase . '/signed/' . (int) $s['id'])) ?>" class="btn btn-sm btn-outline-secondary text-nowrap" title="ไฟล์หลังลงนามลำดับนี้"><i class="bi bi-file-earmark-pdf"></i><span class="visually-hidden">ดาวน์โหลดไฟล์หลังลงนามลำดับที่ <?= (int) $order ?></span></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($sum['history'] !== []): ?>
      <div class="card-footer bg-transparent">
        <button class="btn btn-link btn-sm px-0" type="button" data-bs-toggle="collapse" data-bs-target="#sign-history-<?= e($sum['type']) ?>" aria-expanded="false" aria-controls="sign-history-<?= e($sum['type']) ?>">
          <i class="bi bi-clock-history me-1"></i>ประวัติการลงนามทั้งหมด (<?= count($sum['history']) ?>)
        </button>
        <ul class="collapse list-unstyled small mb-0 mt-2" id="sign-history-<?= e($sum['type']) ?>">
          <?php foreach ($sum['history'] as $h): ?>
            <li class="border-top py-2<?= $h['revoked_at'] !== null ? ' text-body-secondary' : '' ?>">
              <div>ลำดับที่ <?= (int) $h['sign_order'] ?> · <?= e($h['signer_name']) ?> · <?= e(thai_date((string) $h['signed_at'])) ?> · เวอร์ชันที่ <?= (int) $h['version_no'] ?></div>
              <?php if ($h['revoked_at'] !== null): ?>
                <div><span class="badge text-bg-secondary">ยกเลิก</span> <?= e(thai_date((string) $h['revoked_at'])) ?><?= $h['revoke_reason'] ? ' · ' . e($h['revoke_reason']) : '' ?></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if ($canManage && $revokable): ?>
  <form method="post" action="<?= e(url($signBase . '/revoke')) ?>" class="card card-body shadow-sm mb-3 border-danger" data-confirm="ยืนยันยกเลิกการอนุมัติ? ลายเซ็นทั้งหมดและเลขที่เอกสารของฉบับนี้จะถูกยกเลิก">
    <?= csrf_field() ?>
    <div class="fw-semibold text-danger mb-2"><i class="bi bi-x-octagon me-1"></i>ผู้ดูแล: ยกเลิกการอนุมัติ</div>
    <label class="form-label small" for="revoke-reason">เหตุผล (บันทึกในประวัติ)</label>
    <textarea id="revoke-reason" name="reason" rows="2" maxlength="500" required class="form-control mb-2"></textarea>
    <div><button type="submit" class="btn btn-outline-danger btn-sm">ยกเลิกการอนุมัติ</button></div>
  </form>
<?php endif; ?>
