<?php

use Core\Session;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\StudentLookup;

/** @var array|null $work */
/** @var array $types */
/** @var array $years */
/** @var int|null $currentYearId */

$isEdit = $work !== null;
$oldInput = Session::getFlash('old', []);
$oldInput = is_array($oldInput) ? $oldInput : [];
$val = static function (string $key, mixed $default = '') use ($oldInput, $work): string {
    if (array_key_exists($key, $oldInput) && is_scalar($oldInput[$key])) {
        return (string) $oldInput[$key];
    }

    return (string) ($work[$key] ?? $default);
};
$selectedDisabilities = isset($oldInput['disabilities']) && is_array($oldInput['disabilities'])
    ? array_map('strval', $oldInput['disabilities'])
    : ($work['disabilities'] ?? []);
$field = static function (string $name): array {
    $err = error_for($name);

    return [$err !== null ? ' is-invalid' : '', $err !== null ? '<div class="invalid-feedback d-block">' . e($err) . '</div>' : ''];
};
$maxMb = (int) (ResearchLookup::MAX_BYTES / 1024 / 1024);
$action = $isEdit ? url('/academic/research/works/' . (int) $work['id']) : url('/academic/research/works');
?>
<nav aria-label="breadcrumb">
  <ol class="breadcrumb small mb-2">
    <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
    <?php if ($isEdit): ?><li class="breadcrumb-item"><a href="<?= e(url('/academic/research/works/' . (int) $work['id'])) ?>"><?= e(mb_strimwidth((string) $work['title'], 0, 60, '…')) ?></a></li><?php endif; ?>
    <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'แก้ไขข้อมูล' : 'ส่งงานวิจัย' ?></li>
  </ol>
</nav>
<h1 class="h4 mb-3"><?= $isEdit ? 'แก้ไขข้อมูลงานวิจัย' : 'ส่งงานวิจัย' ?></h1>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card shadow-sm mb-4">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
  <div class="card-body row g-3">
    <?php if ($e = error_for('status')): ?><div class="col-12"><div class="alert alert-danger mb-0"><?= e($e) ?></div></div><?php endif; ?>
    <div class="col-12">
      <?php [$cls, $msg] = $field('title'); ?>
      <label class="form-label fw-semibold" for="r-title">ชื่องานวิจัย <span class="text-danger">*</span></label>
      <input type="text" id="r-title" name="title" maxlength="300" required class="form-control<?= $cls ?>" value="<?= e($val('title')) ?>">
      <?= $msg ?>
    </div>
    <div class="col-12 col-md-6">
      <?php [$cls, $msg] = $field('type_id'); ?>
      <label class="form-label fw-semibold" for="r-type">ประเภทงานวิจัย <span class="text-danger">*</span></label>
      <select id="r-type" name="type_id" required class="form-select<?= $cls ?>">
        <option value="">-- เลือกประเภท --</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $val('type_id') === (string) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('academic_year_id'); ?>
      <label class="form-label fw-semibold" for="r-year">ปีการศึกษา <span class="text-danger">*</span></label>
      <select id="r-year" name="academic_year_id" required class="form-select<?= $cls ?>">
        <?php foreach ($years as $y): ?>
          <option value="<?= (int) $y['id'] ?>"<?= $val('academic_year_id', (string) $currentYearId) === (string) $y['id'] ? ' selected' : '' ?>><?= e($y['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $msg ?>
    </div>
    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('semester'); ?>
      <label class="form-label fw-semibold" for="r-semester">ภาคเรียน <span class="text-danger">*</span></label>
      <select id="r-semester" name="semester" required class="form-select<?= $cls ?>">
        <?php foreach (ResearchLookup::SEMESTERS as $k => $label): ?>
          <option value="<?= $k ?>"<?= $val('semester', '1') === (string) $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $msg ?>
    </div>

    <fieldset class="col-12">
      <?php [$cls, $msg] = $field('disabilities'); ?>
      <legend class="form-label fw-semibold fs-6 mb-1">ประเภทความพิการของกลุ่มเป้าหมาย <span class="text-danger">*</span> <span class="fw-normal small text-body-secondary">(เลือกได้หลายประเภท)</span></legend>
      <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-1">
        <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
          <div class="col"><div class="form-check">
            <input class="form-check-input<?= $cls ?>" type="checkbox" name="disabilities[]" value="<?= e($k) ?>" id="r-dis-<?= e($k) ?>"<?= in_array($k, $selectedDisabilities, true) ? ' checked' : '' ?>>
            <label class="form-check-label" for="r-dis-<?= e($k) ?>"><?= e($label) ?></label>
          </div></div>
        <?php endforeach; ?>
      </div>
      <?= $msg ?>
    </fieldset>

    <div class="col-6 col-md-3">
      <?php [$cls, $msg] = $field('target_count'); ?>
      <label class="form-label fw-semibold" for="r-count">จำนวนกลุ่มเป้าหมาย (คน) <span class="text-danger">*</span></label>
      <input type="number" id="r-count" name="target_count" min="1" max="9999" required inputmode="numeric" class="form-control<?= $cls ?>" value="<?= e($val('target_count')) ?>">
      <?= $msg ?>
    </div>
    <div class="col-12 col-md-9">
      <?php [$cls, $msg] = $field('keywords'); ?>
      <label class="form-label fw-semibold" for="r-keywords">คำสำคัญ</label>
      <input type="text" id="r-keywords" name="keywords" maxlength="500" class="form-control<?= $cls ?>" value="<?= e($val('keywords')) ?>" placeholder="เช่น การอ่าน, เด็กออทิสติก, สื่อการสอน" aria-describedby="r-keywords-hint">
      <div class="form-text" id="r-keywords-hint">คั่นแต่ละคำด้วยเครื่องหมายจุลภาค (,) ไม่เกิน <?= ResearchLookup::MAX_KEYWORDS ?> คำ ใช้สำหรับค้นหา</div>
      <?= $msg ?>
    </div>

    <?php if (!$isEdit): ?>
      <div class="col-12">
        <div class="alert alert-warning d-flex gap-2 mb-2" role="note">
          <i class="bi bi-shield-exclamation fs-5"></i>
          <div><strong>คุ้มครองข้อมูลส่วนบุคคลของผู้เรียน (PDPA):</strong> ก่อนส่ง กรุณาปิดหรือลบชื่อ-นามสกุล รูปภาพ และข้อมูลที่ระบุตัวผู้เรียนได้ออกจากไฟล์
            ระบบเก็บข้อมูลกลุ่มเป้าหมายเป็นจำนวนเท่านั้น</div>
        </div>
        <?php [$cls, $msg] = $field('file'); ?>
        <label class="form-label fw-semibold" for="r-file">ไฟล์งานวิจัย <span class="text-danger">*</span></label>
        <input type="file" id="r-file" name="file" required accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="form-control<?= $cls ?>" aria-describedby="r-file-hint">
        <div class="form-text" id="r-file-hint">PDF หรือ DOCX ขนาดไม่เกิน <?= $maxMb ?> MB (ใช้งานได้ทั้งคอมพิวเตอร์และมือถือ)</div>
        <?= $msg ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
    <a href="<?= e(url($isEdit ? '/academic/research/works/' . (int) $work['id'] : '/academic/research')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
    <?php if ($isEdit): ?>
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
    <?php else: ?>
      <button type="submit" name="action" value="draft" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>บันทึกร่าง</button>
      <button type="submit" name="action" value="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>ส่งงาน</button>
    <?php endif; ?>
  </div>
</form>
