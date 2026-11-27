<?php

use Modules\Academic\Services\StudentLookup;

/**
 * ตารางกรอกจำนวนกลุ่มเป้าหมายแยกตาม 9 ประเภทความพิการ (เก็บเป็นจำนวนเท่านั้น)
 * @var string $name ชื่อฟิลด์ (planned/actual)
 * @var array<string, int|string> $values
 * @var array<string, int>|null $compare จำนวนตามแผนแสดงคู่ (ฟอร์มรายงาน)
 */
$compare ??= null;
$err = error_for($name);
?>
<div class="table-responsive">
  <table class="table table-sm align-middle mb-1">
    <thead><tr>
      <th scope="col">ประเภทความพิการ</th>
      <?php if ($compare !== null): ?><th scope="col" class="text-end text-nowrap">ตามแผน (คน)</th><?php endif; ?>
      <th scope="col" class="text-end text-nowrap" style="width: 9rem"><?= $compare !== null ? 'ใช้จริง (คน)' : 'จำนวน (คน)' ?></th>
    </tr></thead>
    <tbody>
      <?php foreach (StudentLookup::DISABILITY_TYPES as $k => $label): ?>
        <?php $id = $name . '-' . $k; ?>
        <tr>
          <td><label for="<?= e($id) ?>" class="mb-0"><?= e($label) ?></label></td>
          <?php if ($compare !== null): ?><td class="text-end text-body-secondary"><?= (int) ($compare[$k] ?? 0) ?: '-' ?></td><?php endif; ?>
          <td><input type="number" id="<?= e($id) ?>" name="<?= e($name) ?>[<?= e($k) ?>]" min="0" max="999" inputmode="numeric"
                     class="form-control form-control-sm text-end<?= $err ? ' is-invalid' : '' ?>" value="<?= e((string) ($values[$k] ?? '')) ?>" placeholder="0" data-target-input="<?= e($name) ?>"></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="fw-semibold">
      <td>รวม</td>
      <?php if ($compare !== null): ?><td class="text-end"><?= array_sum(array_map('intval', $compare)) ?></td><?php endif; ?>
      <td class="text-end"><span data-target-total="<?= e($name) ?>"><?= array_sum(array_map('intval', $values)) ?></span> คน</td>
    </tr></tfoot>
  </table>
</div>
<?php if ($err): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
