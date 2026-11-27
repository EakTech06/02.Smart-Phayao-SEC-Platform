<?php

use Modules\Academic\Services\SigningLookup;

/** @var array|null $doc เอกสารจากโทเคน QR Code (null = ไม่พบ) */
$valid = $doc !== null && $doc['revoked_at'] === null;
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8 col-xl-6">
    <h1 class="h4 mb-3"><i class="bi bi-qr-code-scan me-1"></i>ตรวจสอบเอกสาร</h1>
    <?php if ($doc === null): ?>
      <div class="card shadow-sm border-danger">
        <div class="card-body text-center py-5">
          <i class="bi bi-x-octagon text-danger display-4" aria-hidden="true"></i>
          <h2 class="h5 mt-3">ไม่พบเอกสารในระบบ</h2>
          <p class="text-body-secondary mb-0">QR Code นี้ไม่ใช่ของเอกสารที่ออกโดยระบบ หรือรหัสไม่ถูกต้อง</p>
        </div>
      </div>
    <?php else: ?>
      <div class="card shadow-sm <?= $valid ? 'border-success' : 'border-danger' ?>">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <i class="bi bi-<?= $valid ? 'patch-check-fill text-success' : 'exclamation-octagon-fill text-danger' ?> display-6" aria-hidden="true"></i>
            <div>
              <div class="h5 mb-0"><?= $valid ? 'เอกสารถูกต้อง ลงนามครบ 4 ลำดับ' : 'เอกสารนี้ถูกยกเลิกการอนุมัติแล้ว' ?></div>
              <?php if (!$valid): ?>
                <div class="small text-danger">ยกเลิกเมื่อ <?= e(thai_date((string) $doc['revoked_at'])) ?> · ไม่สามารถใช้อ้างอิงได้</div>
              <?php endif; ?>
            </div>
          </div>
          <dl class="row small mb-0">
            <dt class="col-sm-4">เลขที่เอกสาร</dt><dd class="col-sm-8 font-monospace fw-semibold"><?= e($doc['doc_no']) ?></dd>
            <dt class="col-sm-4">ประเภทเอกสาร</dt><dd class="col-sm-8"><?= e(SigningLookup::DOC_TYPES[$doc['doc_type']] ?? $doc['doc_type']) ?></dd>
            <dt class="col-sm-4">ชื่อเรื่อง</dt><dd class="col-sm-8"><?= e($doc['title']) ?></dd>
            <dt class="col-sm-4">ผู้จัดทำ</dt><dd class="col-sm-8"><?= e($doc['owner_name']) ?></dd>
            <dt class="col-sm-4">อนุมัติเมื่อ</dt><dd class="col-sm-8"><?= e(thai_date((string) $doc['approved_at'])) ?></dd>
            <dt class="col-sm-4">รหัสตรวจสอบไฟล์ (SHA-256)</dt><dd class="col-sm-8 font-monospace text-break" style="font-size: .75rem"><?= e($doc['checksum_sha256']) ?></dd>
          </dl>
        </div>
        <ul class="list-group list-group-flush small">
          <?php foreach ($doc['signatures'] as $s): ?>
            <li class="list-group-item d-flex gap-2">
              <span class="sign-chip" style="--sign-color: <?= e(SigningLookup::COLORS[(int) $s['sign_order']] ?? '#6c757d') ?>"><?= (int) $s['sign_order'] ?></span>
              <div>
                <div class="fw-semibold"><?= e($s['signer_name']) ?></div>
                <div class="text-body-secondary"><?= e($s['signer_position']) ?> · ลงนาม <?= e(thai_date((string) $s['signed_at'])) ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <p class="small text-body-secondary mt-3">เทียบเลขที่เอกสารและรายชื่อผู้ลงนามกับเอกสารที่ได้รับ หากไม่ตรงกันแสดงว่าเอกสารอาจถูกแก้ไข</p>
    <?php endif; ?>
  </div>
</div>
