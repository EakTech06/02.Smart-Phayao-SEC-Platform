<?php

use Modules\CoreAdmin\Services\DepartmentService;

/** @var array $departments */
/** @var bool $canManage */
/** @var array<int, string> $usage id => ข้อมูลที่อ้างถึง (ว่าง = ลบได้) */

// หน่วยย่อยแสดงเยื้องใต้หน่วยแม่ (เรียงตามลำดับที่)
$byId = array_column($departments, null, 'id');
$level = static function (array $d) use (&$level, $byId): int {
    $p = $d['parent_id'] !== null ? ($byId[$d['parent_id']] ?? null) : null;

    return $p === null || $p['parent_id'] === null ? 0 : 1 + $level($p);
};
$active = count(array_filter($departments, static fn (array $d): bool => (int) $d['is_active'] === 1));
$switch = static function (array $d) use ($canManage): string {
    $on = (int) $d['is_active'] === 1;
    if (!$canManage) {
        return $on ? '<span class="badge text-bg-success">เปิด</span>' : '<span class="badge text-bg-secondary">ปิด</span>';
    }

    return '<form method="post" action="' . e(url('/departments/' . (int) $d['id'] . '/active')) . '" class="d-inline" data-department-toggle'
        . ' data-name="' . e($d['name_th']) . '" data-employees="' . (int) $d['employee_count'] . '">'
        . csrf_field() . '<input type="hidden" name="is_active" value="' . ($on ? '0' : '1') . '">'
        . '<div class="form-check form-switch m-0 d-inline-flex align-items-center gap-2">'
        . '<input class="form-check-input" type="checkbox" role="switch" id="dep-active-' . (int) $d['id'] . '"' . ($on ? ' checked' : '')
        . ' aria-label="สถานะการใช้งาน ' . e($d['name_th']) . '">'
        . '<label class="form-check-label small" for="dep-active-' . (int) $d['id'] . '">' . ($on ? 'เปิด' : 'ปิด') . '</label></div></form>';
};
// ปุ่มแก้ไข + ลบ (ลบได้เฉพาะที่ไม่มีข้อมูลอ้างถึง ไม่เช่นนั้นแสดงเหตุผลที่ปุ่ม)
$actions = static function (array $d) use ($usage): string {
    $html = '<a href="' . e(url('/departments/' . (int) $d['id'] . '/edit')) . '" class="btn btn-sm btn-outline-primary" title="แก้ไข"><i class="bi bi-pencil"></i></a> ';
    $used = $usage[(int) $d['id']] ?? '';
    if ($used !== '') {
        return $html . '<span class="d-inline-block" tabindex="0" title="' . e('ลบไม่ได้: ยังมี' . $used . ' (ใช้การปิดการใช้งานแทน)') . '">'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="ลบไม่ได้"><i class="bi bi-trash"></i></button></span>';
    }

    return $html . '<form method="post" action="' . e(url('/departments/' . (int) $d['id'])) . '" class="d-inline"'
        . ' data-confirm="' . e('ลบ ' . $d['name_th'] . ' ออกจากระบบ? (ลบแล้วกู้คืนไม่ได้)') . '">'
        . csrf_field() . '<input type="hidden" name="_method" value="DELETE">'
        . '<button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">ข้อมูลฝ่าย/งาน</h1>
    <div class="small text-muted">ใช้เป็นตัวเลือกฝ่าย/งานของข้อมูลบุคลากร ผู้ใช้งาน และหน่วยบริการ · ปิดการใช้งานแล้วจะไม่แสดงในตัวเลือก (ข้อมูลเดิมยังอยู่) · ลบได้เฉพาะฝ่าย/งานที่ยังไม่มีข้อมูลอ้างถึง</div>
  </div>
  <?php if ($canManage): ?>
    <a href="<?= e(url('/departments/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มฝ่าย/งาน</a>
  <?php endif; ?>
</div>

<!-- Desktop: ตาราง -->
<div class="card shadow-sm d-none d-md-block">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="text-center text-nowrap">ลำดับที่</th>
          <th style="min-width: 14rem;">ชื่อฝ่าย/งาน</th>
          <th>รหัส</th>
          <th>ประเภท</th>
          <th class="text-end">บุคลากร</th>
          <th class="text-nowrap">สถานะการใช้งาน</th>
          <?php if ($canManage): ?><th style="width: 7rem;" class="text-end">จัดการ</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($departments as $d): ?>
          <?php $indent = $level($d); ?>
          <tr class="<?= (int) $d['is_active'] === 1 ? '' : 'text-muted' ?>">
            <td class="text-center"><?= (int) $d['sort_order'] ?></td>
            <td>
              <span style="padding-left: <?= $indent * 1.5 ?>rem;"><?= $indent > 0 ? '<i class="bi bi-arrow-return-right text-muted me-1"></i>' : '' ?><?= e($d['name_th']) ?></span>
              <?php if ($d['parent_name'] && $indent === 0 && $d['type'] !== 'division'): ?><span class="small text-muted ms-1">(สังกัด <?= e($d['parent_name']) ?>)</span><?php endif; ?>
            </td>
            <td class="font-monospace small text-nowrap"><?= e($d['code']) ?></td>
            <td class="small text-nowrap"><?= e(DepartmentService::TYPES[$d['type']] ?? $d['type']) ?></td>
            <td class="text-end"><?= (int) $d['employee_count'] ?></td>
            <td><?= $switch($d) ?></td>
            <?php if ($canManage): ?>
              <td class="text-end text-nowrap"><?= $actions($d) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white small text-muted">ทั้งหมด <?= count($departments) ?> รายการ · เปิดใช้งาน <?= $active ?> รายการ</div>
</div>

<!-- Mobile: Card List -->
<div class="d-md-none d-grid gap-2">
  <?php foreach ($departments as $d): ?>
    <div class="card shadow-sm<?= (int) $d['is_active'] === 1 ? '' : ' opacity-75' ?>">
      <div class="card-body py-2 d-flex justify-content-between align-items-start gap-2">
        <div class="min-w-0" style="padding-left: <?= $level($d) * 1 ?>rem;">
          <div class="fw-semibold"><span class="badge text-bg-light border me-1"><?= (int) $d['sort_order'] ?></span><?= e($d['name_th']) ?></div>
          <div class="small text-muted"><?= e(DepartmentService::TYPES[$d['type']] ?? $d['type']) ?> · <span class="font-monospace"><?= e($d['code']) ?></span> · บุคลากร <?= (int) $d['employee_count'] ?> คน</div>
          <div class="mt-1"><?= $switch($d) ?></div>
        </div>
        <?php if ($canManage): ?>
          <div class="text-nowrap"><?= $actions($d) ?></div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<script src="<?= e(asset('js/departments.js')) ?>" defer></script>
