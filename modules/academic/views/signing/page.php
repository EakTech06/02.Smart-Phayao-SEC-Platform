<?php

use Modules\Academic\Services\SigningLookup;

/**
 * หน้ากำหนดตำแหน่งลายเซ็นและลงนาม
 * @var string $kind research|media
 * @var array $ctx บริบทเอกสาร (SigningService)
 * @var bool $isPdf
 * @var array<int, array> $boxes
 * @var list<int> $editable ลำดับที่ผู้ใช้แก้ตำแหน่งได้
 * @var array<int, array> $signed ลายเซ็นที่มีผลในไฟล์ฉบับนี้
 * @var string $mode submit|sign|view
 * @var int|null $order ลำดับที่ผู้ใช้จะลงนาม
 * @var bool $hasSignature
 * @var string|null $problem
 * @var string $docLabel
 * @var string $backUrl
 * @var string $signUrl
 */
$json = static fn (mixed $v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$listUrl = $kind === 'research' ? '/academic/research/works' : '/academic/innovation/media';
$canAct = $mode !== 'view' && $problem === null && $isPdf;
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
    <li class="breadcrumb-item"><a href="<?= e(url($listUrl)) ?>"><?= $kind === 'research' ? 'รายการงานวิจัย' : 'สื่อนวัตกรรม' ?></a></li>
    <li class="breadcrumb-item"><a href="<?= e(url($backUrl)) ?>">รายละเอียด</a></li>
    <li class="breadcrumb-item active" aria-current="page">ลงนาม</li>
  </ol>
</nav>

<?php foreach (['status', 'file', 'boxes', 'password', 'note', 'to_status', 'action'] as $k): ?>
  <?php if ($e = error_for($k)): ?><div class="alert alert-danger"><?= e($e) ?></div><?php break; endif; ?>
<?php endforeach; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div class="min-w-0">
    <h1 class="h4 mb-1"><i class="bi bi-pen me-1"></i>ลงนาม<?= e($docLabel) ?></h1>
    <div class="small text-body-secondary text-truncate"><?= e($ctx['title']) ?></div>
    <div class="d-flex flex-wrap gap-2 small text-body-secondary mt-1">
      <span class="badge text-bg-light border"><?= e($ctx['status_label']) ?></span>
      <span><i class="bi bi-person me-1"></i><?= e($ctx['owner_name']) ?></span>
      <span><i class="bi bi-file-earmark-pdf me-1"></i>ไฟล์เวอร์ชันที่ <?= (int) $ctx['version']['version_no'] ?></span>
    </div>
  </div>
  <a href="<?= e(url($backUrl)) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>กลับหน้ารายละเอียด</a>
</div>

<?php if (!$isPdf): ?>
  <div class="alert alert-warning"><i class="bi bi-file-earmark-word me-1"></i>ไฟล์ฉบับล่าสุดเป็น .docx ซึ่งใช้เป็นฉบับร่างเท่านั้น กรุณาอัปโหลดไฟล์ PDF ฉบับสมบูรณ์ที่หน้ารายละเอียดก่อนกำหนดตำแหน่งลายเซ็นและลงนาม</div>
<?php else: ?>

<?php if ($mode === 'submit'): ?>
  <div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle me-1"></i>ลากกรอบลายเซ็นทั้ง 4 ลำดับไปวางในตำแหน่งที่ต้องการ (เลือกหน้าได้) ปรับขนาดที่มุมขวาล่างของกรอบ
    แล้วกด <strong>ดูตัวอย่าง</strong> เพื่อตรวจก่อน จากนั้นใส่รหัสผ่านเพื่อ <strong>ลงนามลำดับที่ 1 และส่ง</strong>
  </div>
<?php elseif ($mode === 'sign'): ?>
  <div class="alert alert-success small mb-3">
    <i class="bi bi-pen me-1"></i>คุณลงนามลำดับที่ <?= (int) $order ?> ในฐานะ<strong><?= e(SigningLookup::ORDERS[$order]) ?></strong>
    ตรวจเอกสารและตำแหน่งกรอบ กด <strong>ดูตัวอย่าง</strong> ก่อนยืนยันลงนาม หรือส่งกลับแก้ไขพร้อมเหตุผล
  </div>
<?php elseif ($editable !== []): ?>
  <div class="alert alert-secondary small mb-3"><i class="bi bi-arrows-move me-1"></i>ผู้ตรวจปรับตำแหน่งกรอบลายเซ็นของลำดับที่ยังไม่ลงนามได้ แล้วกดบันทึกตำแหน่ง</div>
<?php endif; ?>
<?php if ($problem !== null && $mode !== 'view'): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($problem) ?>
    <?php if (!$hasSignature): ?><a href="<?= e(url('/profile#signature')) ?>" class="alert-link ms-1">ไปบันทึกลายเซ็น</a><?php endif; ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card shadow-sm">
      <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="เลื่อนหน้า">
          <button type="button" class="btn btn-outline-secondary" data-sign-prev aria-label="หน้าก่อนหน้า"><i class="bi bi-chevron-left"></i></button>
          <button type="button" class="btn btn-outline-secondary" data-sign-next aria-label="หน้าถัดไป"><i class="bi bi-chevron-right"></i></button>
        </div>
        <label class="small d-flex align-items-center gap-1 mb-0">หน้า
          <input type="number" min="1" value="1" class="form-control form-control-sm" style="width: 5rem" data-sign-page-input aria-label="เลขหน้า">
          <span class="text-body-secondary">/ <span data-sign-page-count>-</span></span>
        </label>
        <span class="badge text-bg-warning d-none ms-auto" data-sign-preview-badge><i class="bi bi-eye me-1"></i>ตัวอย่างหลังลงนาม (ยังไม่บันทึก)</span>
        <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-sign-preview-exit><i class="bi bi-x-lg me-1"></i>ปิดตัวอย่าง</button>
      </div>
      <div class="card-body bg-body-tertiary p-2 p-md-3">
        <div class="sign-stage mx-auto" data-sign-stage>
          <canvas data-sign-canvas></canvas>
          <div class="sign-layer" data-sign-layer></div>
          <div class="sign-loading text-center text-body-secondary py-5" data-sign-loading><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดเอกสาร</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header"><i class="bi bi-bounding-box me-1"></i>ตำแหน่งลายเซ็น 4 ลำดับ</div>
      <ul class="list-group list-group-flush small" data-sign-orders>
        <?php foreach (SigningLookup::ORDERS as $o => $label): ?>
          <?php $isSigned = isset($signed[$o]); $canEdit = in_array($o, $editable, true); ?>
          <li class="list-group-item d-flex align-items-center gap-2" data-sign-order="<?= (int) $o ?>">
            <span class="sign-chip" style="--sign-color: <?= e(SigningLookup::COLORS[$o]) ?>"><?= (int) $o ?></span>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?= e($label) ?></div>
              <div class="text-body-secondary">
                <?php if ($isSigned): ?>
                  <i class="bi bi-check-circle-fill text-success me-1"></i>ลงนามแล้ว · <?= e($signed[$o]['signer_name']) ?>
                <?php else: ?>
                  หน้า <span data-sign-order-page>-</span><?= $boxes[$o]['saved'] ? '' : ' · <span class="text-warning-emphasis">ยังไม่บันทึก</span>' ?>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($canEdit && !$isSigned): ?>
              <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-sign-here="<?= (int) $o ?>" title="ย้ายกรอบมาหน้าที่แสดงอยู่"><i class="bi bi-box-arrow-in-down"></i><span class="visually-hidden">ย้ายกรอบลำดับที่ <?= (int) $o ?> มาหน้านี้</span></button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-link text-nowrap" data-sign-goto="<?= (int) $o ?>">ไปที่กรอบ</button>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($editable !== []): ?>
        <form method="post" action="<?= e(url($signUrl . '/boxes')) ?>" class="card-body border-top" data-sign-boxes-form>
          <?= csrf_field() ?>
          <input type="hidden" name="boxes" value="">
          <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-save me-1"></i>บันทึกตำแหน่ง</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($mode === 'submit' || $mode === 'sign'): ?>
      <div class="card shadow-sm mb-3 border-primary">
        <div class="card-header fw-semibold"><i class="bi bi-shield-lock me-1"></i><?= $mode === 'submit' ? 'ลงนามลำดับที่ 1 และส่ง' : 'ลงนามลำดับที่ ' . (int) $order ?></div>
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 small mb-3">
            <?php if ($hasSignature): ?>
              <i class="bi bi-check-circle-fill text-success"></i><span>มีภาพลายเซ็นในโปรไฟล์แล้ว</span>
            <?php else: ?>
              <i class="bi bi-x-circle-fill text-danger"></i><span>ยังไม่มีภาพลายเซ็น</span>
            <?php endif; ?>
            <a href="<?= e(url('/profile#signature')) ?>" class="ms-auto">จัดการลายเซ็น</a>
          </div>
          <button type="button" class="btn btn-outline-secondary w-100 mb-3" data-sign-preview<?= $canAct ? '' : ' disabled' ?>
                  data-url="<?= e(url($signUrl . '/preview')) ?>"><i class="bi bi-eye me-1"></i>ดูตัวอย่างหลังลงนาม</button>
          <form method="post" action="<?= e(url($signUrl)) ?>" data-sign-form
                data-confirm="<?= $mode === 'submit' ? 'ยืนยันลงนามลำดับที่ 1 และส่งเอกสาร?' : 'ยืนยันลงนามลำดับที่ ' . (int) $order . '?' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="boxes" value="">
            <label class="form-label small" for="sign-password">ยืนยันตัวตนด้วยรหัสผ่าน (หรือรหัส OTP 6 หลัก หากเปิดใช้ 2FA)</label>
            <input type="password" id="sign-password" name="password" required autocomplete="current-password" class="form-control mb-2<?= error_for('password') ? ' is-invalid' : '' ?>"<?= $canAct ? '' : ' disabled' ?>>
            <button type="submit" class="btn btn-primary w-100"<?= $canAct ? '' : ' disabled' ?>><i class="bi bi-pen me-1"></i><?= $mode === 'submit' ? 'ลงนามและส่ง' : 'ยืนยันลงนาม' ?></button>
            <div class="form-text">ระบบประทับภาพลายเซ็น ชื่อ-สกุล ตำแหน่ง และวันเวลาลงในกรอบของคุณ แล้วบันทึกเป็นไฟล์ฉบับใหม่ (ไฟล์ต้นฉบับยังเก็บไว้)</div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($mode === 'sign'): ?>
      <form method="post" action="<?= e(url($signUrl . '/return')) ?>" class="card card-body shadow-sm mb-3" data-confirm="ยืนยันส่งกลับแก้ไข? ลายเซ็นที่ลงแล้วของฉบับนี้จะถูกยกเลิก">
        <?= csrf_field() ?>
        <div class="fw-semibold mb-2"><i class="bi bi-arrow-return-left me-1"></i>ส่งกลับแก้ไข</div>
        <label class="form-label small" for="return-note">เหตุผล / สิ่งที่ต้องแก้ไข</label>
        <textarea id="return-note" name="note" rows="3" maxlength="1000" required class="form-control mb-2"></textarea>
        <div><button type="submit" class="btn btn-outline-warning">ส่งกลับแก้ไข</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script type="application/json" id="sign-data"><?= $json([
    'file' => url($signUrl . '/file'),
    'boxes' => $boxes,
    'editable' => $editable,
    'signed' => array_map('intval', array_keys($signed)),
    'colors' => SigningLookup::COLORS,
    'labels' => SigningLookup::ORDERS,
    'minW' => SigningLookup::MIN_W,
    'minH' => SigningLookup::MIN_H,
    'csrf' => csrf_token(),
]) ?></script>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js" defer></script>
<script src="<?= e(asset('js/sign-placement.js')) ?>" defer></script>
<?php endif; ?>
