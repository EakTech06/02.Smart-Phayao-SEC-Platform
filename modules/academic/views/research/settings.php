<?php

use Modules\Academic\Services\ResearchLookup;

/** @var array $types */
/** @var array $rounds */
/** @var array $years */
/** @var int|null $currentYearId */

$errs = errors();
$firstError = $errs !== [] ? (string) (array_values($errs)[0][0] ?? '') : '';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small mb-2">
  <li class="breadcrumb-item"><a href="<?= e(url('/academic/research')) ?>">งานวิจัยและพัฒนานวัตกรรม</a></li>
  <li class="breadcrumb-item active" aria-current="page">ตั้งค่า</li>
</ol></nav>
<h1 class="h4 mb-3">ตั้งค่างานวิจัยและนวัตกรรม</h1>
<?php if ($firstError !== ''): ?><div class="alert alert-danger"><?= e($firstError) ?></div><?php endif; ?>

<div class="row g-3 mb-3">
  <?php foreach (ResearchLookup::KINDS as $kind => $kindLabel): ?>
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100" id="types-<?= e($kind) ?>">
        <div class="card-header fw-semibold"><i class="bi bi-tags me-1"></i><?= e($kindLabel) ?></div>
        <div class="list-group list-group-flush">
          <?php foreach ($types[$kind] as $t): ?>
            <form method="post" action="<?= e(url('/academic/research/settings/types/' . $kind . '/' . (int) $t['id'])) ?>" class="list-group-item">
              <?= csrf_field() ?><input type="hidden" name="_method" value="PUT">
              <div class="row g-2 align-items-center">
                <div class="col-12 col-md">
                  <label class="visually-hidden" for="t-name-<?= (int) $t['id'] ?>">ชื่อประเภท</label>
                  <input type="text" id="t-name-<?= (int) $t['id'] ?>" name="name" maxlength="150" required class="form-control form-control-sm" value="<?= e($t['name']) ?>">
                </div>
                <div class="col-4 col-md-2">
                  <label class="visually-hidden" for="t-sort-<?= (int) $t['id'] ?>">ลำดับ</label>
                  <input type="number" id="t-sort-<?= (int) $t['id'] ?>" name="sort_order" min="0" max="999" class="form-control form-control-sm" value="<?= (int) $t['sort_order'] ?>" title="ลำดับ">
                </div>
                <div class="col-auto"><div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="t-active-<?= (int) $t['id'] ?>"<?= $t['is_active'] ? ' checked' : '' ?>>
                  <label class="form-check-label small" for="t-active-<?= (int) $t['id'] ?>">ใช้งาน</label>
                </div></div>
                <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">บันทึก</button></div>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
        <form method="post" action="<?= e(url('/academic/research/settings/types/' . $kind)) ?>" class="card-body border-top">
          <?= csrf_field() ?><input type="hidden" name="is_active" value="1">
          <label class="form-label small fw-semibold" for="t-new-<?= e($kind) ?>">เพิ่ม<?= e($kindLabel) ?></label>
          <div class="input-group input-group-sm">
            <input type="text" id="t-new-<?= e($kind) ?>" name="name" maxlength="150" required class="form-control" placeholder="ชื่อประเภทใหม่">
            <input type="number" name="sort_order" min="0" max="999" class="form-control" style="max-width: 6rem" value="<?= count($types[$kind]) + 1 ?>" aria-label="ลำดับ">
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่ม</button>
          </div>
          <div class="form-text">ปิดใช้งานแทนการลบ งานเดิมที่ใช้ประเภทนั้นยังแสดงตามเดิม</div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('/academic/research/settings/innovation')) ?>" class="card shadow-sm mb-3" id="innovation-settings">
  <?= csrf_field() ?><input type="hidden" name="_method" value="PUT">
  <div class="card-header fw-semibold"><i class="bi bi-hourglass-split me-1"></i>กำหนดส่งรายงานการใช้สื่อนวัตกรรม</div>
  <div class="card-body row g-2 align-items-end">
    <div class="col-12 col-md-5 col-lg-4">
      <label class="form-label small" for="report-due-days">ส่งรายงานภายใน (วัน) หลังวันสิ้นสุดการใช้สื่อตามแผน</label>
      <div class="input-group">
        <input type="number" id="report-due-days" name="report_due_days" min="1" max="365" required class="form-control<?= error_for('report_due_days') ? ' is-invalid' : '' ?>" value="<?= (int) $reportDueDays ?>">
        <span class="input-group-text">วัน</span>
        <button type="submit" class="btn btn-primary">บันทึก</button>
      </div>
    </div>
    <div class="col-12 col-md form-text mb-1">กำหนดวันส่งรายงานของแต่ละสื่อเมื่อคู่มือได้รับอนุมัติ (สื่อที่อนุมัติไปแล้วใช้วันเดิม) · แจ้งเตือนครูเมื่อถึงวันสิ้นสุดการใช้สื่อ ก่อนครบกำหนด <?= e(implode('/', ResearchLookup::REMIND_DAYS)) ?> วัน และเมื่อเลยกำหนด (แจ้งผู้ตรวจด้วย)</div>
  </div>
</form>

<div class="card shadow-sm mb-4" id="rounds">
  <div class="card-header fw-semibold"><i class="bi bi-calendar-range me-1"></i>รอบและกำหนดการส่ง</div>
  <div class="card-body small text-body-secondary pb-0">
    ระบบแจ้งเตือนครูที่ยังไม่ส่งงานล่วงหน้า <?= e(implode(' / ', ResearchLookup::REMIND_DAYS)) ?> วันก่อนครบกำหนด และเมื่อเลยกำหนด (แจ้งผู้ตรวจด้วย) ผ่านกระดิ่งและอีเมล
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr>
        <th scope="col">ประเภทงาน</th><th scope="col">ปีการศึกษา</th><th scope="col">ภาคเรียน</th><th scope="col">ชื่อรอบ</th>
        <th scope="col">วันเปิดรับ</th><th scope="col">ครบกำหนดส่ง</th><th scope="col">ใช้งาน</th><th scope="col"><span class="visually-hidden">จัดการ</span></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rounds as $r): ?>
          <?php $fid = 'round-' . (int) $r['id']; ?>
          <tr>
            <td>
              <form method="post" action="<?= e(url('/academic/research/settings/rounds/' . (int) $r['id'])) ?>" id="<?= e($fid) ?>">
                <?= csrf_field() ?><input type="hidden" name="_method" value="PUT">
              </form>
              <select name="kind" form="<?= e($fid) ?>" class="form-select form-select-sm" aria-label="ประเภทงาน">
                <?php foreach (['research' => 'งานวิจัย', 'innovation' => 'สื่อนวัตกรรม'] as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $r['kind'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><select name="academic_year_id" form="<?= e($fid) ?>" class="form-select form-select-sm" aria-label="ปีการศึกษา">
              <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === (int) $r['academic_year_id'] ? ' selected' : '' ?>><?= e((string) $y['year_be']) ?></option><?php endforeach; ?>
            </select></td>
            <td><select name="semester" form="<?= e($fid) ?>" class="form-select form-select-sm" aria-label="ภาคเรียน">
              <?php foreach (ResearchLookup::SEMESTERS as $k => $label): ?><option value="<?= $k ?>"<?= (int) $r['semester'] === $k ? ' selected' : '' ?>><?= $k ?></option><?php endforeach; ?>
            </select></td>
            <td><input type="text" name="title" form="<?= e($fid) ?>" maxlength="200" required class="form-control form-control-sm" value="<?= e($r['title']) ?>" aria-label="ชื่อรอบ"></td>
            <td><input type="date" name="opens_on" form="<?= e($fid) ?>" class="form-control form-control-sm" value="<?= e((string) $r['opens_on']) ?>" aria-label="วันเปิดรับ"></td>
            <td><input type="date" name="due_on" form="<?= e($fid) ?>" required class="form-control form-control-sm" value="<?= e((string) $r['due_on']) ?>" aria-label="ครบกำหนดส่ง"></td>
            <td><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" form="<?= e($fid) ?>"<?= $r['is_active'] ? ' checked' : '' ?> aria-label="ใช้งาน"></div></td>
            <td class="text-nowrap">
              <button type="submit" form="<?= e($fid) ?>" class="btn btn-sm btn-outline-primary">บันทึก</button>
              <form method="post" action="<?= e(url('/academic/research/settings/rounds/' . (int) $r['id'])) ?>" class="d-inline" data-confirm="ลบรอบการส่งนี้?">
                <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="ลบรอบ"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rounds === []): ?>
          <tr><td colspan="8" class="text-center text-body-secondary py-3">ยังไม่มีรอบการส่ง</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <form method="post" action="<?= e(url('/academic/research/settings/rounds')) ?>" class="card-body border-top">
    <?= csrf_field() ?><input type="hidden" name="is_active" value="1">
    <div class="fw-semibold small mb-2"><i class="bi bi-plus-circle me-1"></i>เพิ่มรอบการส่ง</div>
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2"><label class="form-label small" for="nr-kind">ประเภทงาน</label>
        <select id="nr-kind" name="kind" class="form-select form-select-sm"><option value="research">งานวิจัย</option><option value="innovation">สื่อนวัตกรรม</option></select></div>
      <div class="col-6 col-md-2"><label class="form-label small" for="nr-year">ปีการศึกษา</label>
        <select id="nr-year" name="academic_year_id" class="form-select form-select-sm">
          <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"<?= (int) $y['id'] === (int) $currentYearId ? ' selected' : '' ?>><?= e((string) $y['year_be']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-6 col-md-1"><label class="form-label small" for="nr-sem">ภาคเรียน</label>
        <select id="nr-sem" name="semester" class="form-select form-select-sm"><option value="1">1</option><option value="2">2</option></select></div>
      <div class="col-6 col-md-3"><label class="form-label small" for="nr-title">ชื่อรอบ</label>
        <input type="text" id="nr-title" name="title" maxlength="200" required class="form-control form-control-sm" placeholder="เช่น ส่งงานวิจัย ภาคเรียนที่ 1/2569"></div>
      <div class="col-6 col-md-2"><label class="form-label small" for="nr-open">วันเปิดรับ</label>
        <input type="date" id="nr-open" name="opens_on" class="form-control form-control-sm"></div>
      <div class="col-6 col-md-2"><label class="form-label small" for="nr-due">ครบกำหนดส่ง</label>
        <input type="date" id="nr-due" name="due_on" required class="form-control form-control-sm"></div>
      <div class="col-12"><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มรอบ</button></div>
    </div>
  </form>
</div>
