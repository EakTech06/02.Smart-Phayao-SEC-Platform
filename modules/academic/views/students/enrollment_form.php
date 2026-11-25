<?php

use Core\Session;
use Modules\Academic\Services\StudentService;

/** @var array $student */
/** @var array|null $enrollment */
/** @var array $options */
/** @var int|null $currentYearId */

$isEdit = $enrollment !== null;
$oldInput = Session::getFlash('old', []);
$hasOld = is_array($oldInput) && $oldInput !== [];
$labels = StudentService::labels();
$current = ['enroll_academic_year_id' => (string) ($currentYearId ?? '')];
if ($isEdit) {
    foreach ($enrollment as $k => $v) {
        $current['enroll_' . $k] = (string) $v;
    }
}
$val = static fn (string $key): string => $hasOld ? old($key) : ($current[$key] ?? '');
$field = require __DIR__ . '/_field.php';
$enrollRequired = true;
$back = url('/academic/students/' . (int) $student['id']);
$action = url('/academic/students/' . (int) $student['id'] . '/enrollments' . ($isEdit ? '/' . (int) $enrollment['id'] : ''));
?>
<a href="<?= e($back) ?>#enrollments" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับ</a>
<h1 class="h4 mb-1"><?= $isEdit ? 'แก้ไขการรับบริการ' : 'เพิ่มการรับบริการ' ?></h1>
<p class="text-muted"><?= e($student['student_code'] . ' ' . StudentService::displayName($student)) ?></p>

<form method="post" action="<?= e($action) ?>" novalidate class="card shadow-sm">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
  <div class="card-body">
    <?php require __DIR__ . '/_enrollment_fields.php'; ?>
  </div>
  <div class="card-footer bg-white text-end">
    <a href="<?= e($back) ?>#enrollments" class="btn btn-outline-secondary">ยกเลิก</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
  </div>
</form>
<script src="<?= e(asset('js/academic-students.js')) ?>" defer></script>
