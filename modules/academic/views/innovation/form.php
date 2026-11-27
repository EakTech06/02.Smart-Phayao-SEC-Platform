<?php

use Core\Session;
use Modules\Academic\Services\ResearchLookup;

/** @var array|null $media */
/** @var array $types */
/** @var array $years */
/** @var int|null $currentYearId */
/** @var int $dueDays */

$isEdit = $media !== null;
$oldInput = Session::getFlash('old', []);
$oldInput = is_array($oldInput) ? $oldInput : [];
$val = static function (string $key, mixed $default = '') use ($oldInput, $media): string {
    if (array_key_exists($key, $oldInput) && is_scalar($oldInput[$key])) {
        return (string) $oldInput[$key];
    }

    return (string) ($media[$key] ?? $default);
};
$planned = isset($oldInput['planned']) && is_array($oldInput['planned']) ? $oldInput['planned'] : ($media['targets']['planned'] ?? []);
$field = static function (string $name): array {
    $err = error_for($name);

    return [$err !== null ? ' is-invalid' : '', $err !== null ? '<div class="invalid-feedback d-block">' . e($err) . '</div>' : ''];
};
$maxMb = (int) (ResearchLookup::MAX_BYTES / 1024 / 1024);
$action = $isEdit ? url('/academic/innovation/media/' . (int) $media['id']) : url('/academic/innovation/media');
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/innovation/media?view=mine')) ?>">สื่อนวัตกรรม</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'แก้ไขข้อมูล' : 'ส่งคู่มือการใช้สื่อ' ?></li>
  </ol>
</nav>
<h1 class="h4 mb-1"><?= $isEdit ? 'แก้ไขข้อมูลสื่อนวัตกรรม' : 'สร้างสื่อนวัตกรรมใหม่ · ส่งคู่มือการใช้สื่อ' ?></h1>
<p class="small text-body-secondary mb-3">ระยะที่ 1 คู่มือการใช้สื่อ → ใช้สื่อกับกลุ่มเป้าหมาย → ระยะที่ 2 รายงานการใช้สื่อ (ส่งภายใน <?= (int) $dueDays ?> วันหลังวันสิ้นสุดการใช้สื่อตามแผน)</p>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card shadow-sm mb-4">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
  <div class="card-body row g-3">
    <?php if ($e = error_for('status')): ?><div class="col-12"><div class="alert alert-danger mb-0"><?= e($e) ?></div></div><?php endif; ?>
    <div class="col-12">
      <?php [$cls, $msg] = $field('title'); ?>
      <label class="form-label fw-semibold" for="m-title">ชื่อสื่อนวัตกรรม <span class="text-danger">*</span></label>
      <input type="text" id="m-title" name="title" maxlength="300" required class="form-control<?= $cls ?>" value="<?= e($val('title')) ?>">
      <?= $msg ?>
    </div>
    <div class="col-12 col-md-6">
      <?php [$cls, $msg] = $field('type_id'); ?>
      <label class="form-label fw-semibold" for="m-type">ประเภทสื่อนวัตกรรม <span class="text-danger">*</span></label>
      <select id="m-type" name="type_id" required class="form-select<?= $cls ?>">
        <option value="">-- เลือกประเภท --</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $val('type_id') === (string) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('academic_year_id'); ?>
      <label class="form-label fw-semibold" for="m-year">ปีการศึกษา <span class="text-danger">*</span></label>
      <select id="m-year" name="academic_year_id" required class="form-select<?= $cls ?>">
        <?php foreach ($years as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= $val('academic_year_id', (string) $currentYearId) === (string) $y['id'] ? ' selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('semester'); ?>
      <label class="form-label fw-semibold" for="m-semester">ภาคเรียน <span class="text-danger">*</span></label>
      <select id="m-semester" name="semester" required class="form-select<?= $cls ?>">
        <?php foreach (ResearchLookup::SEMESTERS as $k => $label): ?>
          <option value="<?= $k ?>"<?= $val('semester', '1') === (string) $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('plan_start'); ?>
      <label class="form-label fw-semibold" for="m-start">วันเริ่มใช้สื่อตามแผน <span class="text-danger">*</span></label>
      <input type="date" id="m-start" name="plan_start" required class="form-control<?= $cls ?>" value="<?= e($val('plan_start')) ?>">
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('plan_end'); ?>
      <label class="form-label fw-semibold" for="m-end">วันสิ้นสุดการใช้สื่อตามแผน <span class="text-danger">*</span></label>
      <input type="date" id="m-end" name="plan_end" required class="form-control<?= $cls ?>" value="<?= e($val('plan_end')) ?>">
      <?= $msg ?>
    </div>

    <div class="col-12 col-lg-8">
      <div class="form-label fw-semibold">จำนวนกลุ่มเป้าหมายที่วางแผนไว้ แยกตาม 9 ประเภทความพิการ <span class="text-danger">*</span></div>
      <?php $name = 'planned'; $values = $planned; $compare = null; require __DIR__ . '/_targets.php'; ?>
      <div class="form-text">กรอกเฉพาะประเภทที่มีกลุ่มเป้าหมาย · เก็บเป็นจำนวนเท่านั้น ไม่เก็บชื่อผู้เรียน</div>
    </div>

    <?php if (!$isEdit): ?>
      <div class="col-12">
        <div class="alert alert-warning d-flex gap-2 mb-2" role="note">
          <i class="bi bi-shield-exclamation fs-5"></i>
          <div><strong>คุ้มครองข้อมูลส่วนบุคคลของผู้เรียน (PDPA):</strong> ก่อนส่ง กรุณาปิดหรือลบชื่อ-นามสกุล รูปภาพ และข้อมูลที่ระบุตัวผู้เรียนได้ออกจากไฟล์</div>
        </div>
        <?php [$cls, $msg] = $field('file'); ?>
        <label class="form-label fw-semibold" for="m-file">คู่มือการใช้สื่อนวัตกรรม (PDF) <span class="text-danger">*</span></label>
        <input type="file" id="m-file" name="file" required accept=".pdf,application/pdf" class="form-control<?= $cls ?>" aria-describedby="m-file-hint">
        <div class="form-text" id="m-file-hint">PDF ขนาดไม่เกิน <?= $maxMb ?> MB (ใช้งานได้ทั้งคอมพิวเตอร์และมือถือ)</div>
        <?= $msg ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
    <a href="<?= e(url($isEdit ? '/academic/innovation/media/' . (int) $media['id'] : '/academic/innovation/media?view=mine')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <?php if ($isEdit): ?>
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
    <?php else: ?>
      <button type="submit" name="action" value="draft" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>บันทึกร่าง</button>
      <button type="submit" name="action" value="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>ส่งคู่มือ</button>
    <?php endif; ?>
  </div>
</form>
<script src="<?= e(asset('js/innovation-form.js')) ?>" defer></script>
