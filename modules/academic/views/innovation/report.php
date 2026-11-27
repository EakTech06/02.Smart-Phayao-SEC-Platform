<?php

use Core\Session;
use Modules\Academic\Services\ResearchLookup;

/** @var array $media */
/** @var bool $isRevision */
/** @var string $reportDue */

$oldInput = Session::getFlash('old', []);
$oldInput = is_array($oldInput) ? $oldInput : [];
$val = static fn (string $key): string => array_key_exists($key, $oldInput) && is_scalar($oldInput[$key]) ? (string) $oldInput[$key] : (string) ($media[$key] ?? '');
$actual = isset($oldInput['actual']) && is_array($oldInput['actual']) ? $oldInput['actual'] : $media['targets']['actual'];
$field = static function (string $name): array {
    $err = error_for($name);

    return [$err !== null ? ' is-invalid' : '', $err !== null ? '<div class="invalid-feedback d-block">' . e($err) . '</div>' : ''];
};
$base = '/academic/innovation/media/' . (int) $media['id'];
$maxMb = (int) (ResearchLookup::MAX_BYTES / 1024 / 1024);
$overdue = !$isRevision && $reportDue < date('Y-m-d');
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
    <li class="breadcrumb-item"><a href="<?= e(url($base)) ?>"><?= e(mb_strimwidth((string) $media['title'], 0, 60, '…')) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= $isRevision ? 'แก้ไขข้อมูลการใช้สื่อ' : 'ส่งรายงานการใช้สื่อ' ?></li>
  </ol>
</nav>
<h1 class="h4 mb-3"><?= $isRevision ? 'แก้ไขข้อมูลการใช้สื่อนวัตกรรม' : 'ระยะที่ 2 · ส่งรายงานการใช้สื่อนวัตกรรม' ?></h1>

<div class="card shadow-sm mb-3">
  <div class="card-body small">
    <div class="fw-semibold fs-6 mb-1"><?= e($media['title']) ?></div>
    <div class="text-body-secondary">
      <?= e($media['type_name']) ?> · <?= e($media['year_name']) ?> ภาคเรียนที่ <?= (int) $media['semester'] ?>
      · ใช้สื่อตามแผน <?= e(thai_date((string) $media['plan_start'], false)) ?> - <?= e(thai_date((string) $media['plan_end'], false)) ?>
    </div>
    <div class="mt-1<?= $overdue ? ' text-danger fw-semibold' : '' ?>"><i class="bi bi-calendar-check me-1"></i>กำหนดส่งรายงาน <?= e(thai_date($reportDue, false, true)) ?><?= $overdue ? ' (เลยกำหนดแล้ว)' : '' ?></div>
  </div>
</div>

<form method="post" action="<?= e(url($isRevision ? $base : $base . '/report')) ?>" enctype="multipart/form-data" class="card shadow-sm mb-4">
  <?= csrf_field() ?>
  <?php if ($isRevision): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
  <div class="card-body row g-3">
    <?php if ($e = error_for('status')): ?><div class="col-12"><div class="alert alert-danger mb-0"><?= e($e) ?></div></div><?php endif; ?>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('actual_start'); ?>
      <label class="form-label fw-semibold" for="r-start">วันเริ่มใช้สื่อจริง <span class="text-danger">*</span></label>
      <input type="date" id="r-start" name="actual_start" required class="form-control<?= $cls ?>" value="<?= e($val('actual_start') ?: (string) $media['plan_start']) ?>">
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('actual_end'); ?>
      <label class="form-label fw-semibold" for="r-end">วันสิ้นสุดการใช้สื่อจริง <span class="text-danger">*</span></label>
      <input type="date" id="r-end" name="actual_end" required class="form-control<?= $cls ?>" value="<?= e($val('actual_end') ?: (string) $media['plan_end']) ?>">
      <?= $msg ?>
    </div>
    <div class="col-12 col-lg-8">
      <div class="form-label fw-semibold">จำนวนกลุ่มเป้าหมายที่ใช้จริง แยกตาม 9 ประเภทความพิการ <span class="text-danger">*</span></div>
      <?php $name = 'actual'; $values = $actual; $compare = $media['targets']['planned']; require __DIR__ . '/_targets.php'; ?>
    </div>
    <?php if (!$isRevision): ?>
      <div class="col-12">
        <div class="alert alert-warning d-flex gap-2 mb-2" role="note">
          <i class="bi bi-shield-exclamation fs-5"></i>
          <div><strong>คุ้มครองข้อมูลส่วนบุคคลของผู้เรียน (PDPA):</strong> ก่อนส่ง กรุณาปิดหรือลบชื่อ-นามสกุล รูปภาพ และข้อมูลที่ระบุตัวผู้เรียนได้ออกจากไฟล์</div>
        </div>
        <?php [$cls, $msg] = $field('file'); ?>
        <label class="form-label fw-semibold" for="r-file">รายงานการใช้สื่อนวัตกรรม (PDF) <span class="text-danger">*</span></label>
        <input type="file" id="r-file" name="file" required accept=".pdf,application/pdf" class="form-control<?= $cls ?>">
        <div class="form-text">PDF ขนาดไม่เกิน <?= $maxMb ?> MB</div>
        <?= $msg ?>
      </div>
    <?php else: ?>
      <div class="col-12 form-text">ไฟล์รายงานฉบับแก้ไข อัปโหลดได้ที่หน้ารายละเอียดสื่อ</div>
    <?php endif; ?>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2">
    <a href="<?= e(url($base)) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-<?= $isRevision ? 'save' : 'send' ?> me-1"></i><?= $isRevision ? 'บันทึก' : 'ส่งรายงาน' ?></button>
  </div>
</form>
<script src="<?= e(asset('js/innovation-form.js')) ?>" defer></script>
