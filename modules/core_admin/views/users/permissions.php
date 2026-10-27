<?php

use Modules\CoreAdmin\Services\RolePermissionService;

/** @var array $user */
/** @var array $overrides */
/** @var array $grouped */
$id = (int) $user['id'];
?>
<a href="<?= e(url('/users/' . $id)) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับไปหน้าข้อมูลผู้ใช้</a>
<h1 class="h4 mb-3">จัดการสิทธิ์รายบุคคล: <?= e($user['username']) ?></h1>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">เพิ่ม/กำหนดสิทธิ์เฉพาะตัว Override</div>
      <div class="card-body">
        <form method="post" action="<?= e(url('/users/' . $id . '/permissions')) ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="permission_id">เลือกสิทธิ์</label>
            <select name="permission_id" id="permission_id" class="form-select" required>
              <option value="">-- เลือกสิทธิ์ --</option>
              <?php foreach ($grouped as $mod => $resList): ?>
                <optgroup label="[<?= strtoupper($mod) ?>]">
                  <?php foreach ($resList as $res => $perms): ?>
                    <?php foreach ($perms as $p): ?>
                      <option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?> (<?= e($p['description']) ?>)</option>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="effect">ผลลัพธ์ (Effect)</label>
            <select name="effect" id="effect" class="form-select" required>
              <option value="allow">ALLOW (อนุญาต)</option>
              <option value="deny">DENY (ระงับ/ห้าม)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="expires_at">วันหมดอายุ (ถ้ามี)</label>
            <input type="date" name="expires_at" id="expires_at" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label" for="reason">เหตุผล</label>
            <input type="text" name="reason" id="reason" class="form-control" placeholder="เช่น ได้รับมอบหมายพิเศษชั่วคราว">
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>บันทึกสิทธิ์</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">สิทธิ์เฉพาะตัวที่มีอยู่ปัจจุบัน (Overrides)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr><th>สิทธิ์</th><th>ผล</th><th>หมดอายุ</th><th></th></tr>
            </thead>
            <tbody>
              <?php if (empty($overrides)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">ยังไม่มีการกำหนดสิทธิ์เฉพาะตัว</td></tr>
              <?php else: ?>
                <?php foreach ($overrides as $o): ?>
                  <tr>
                    <td><code><?= e($o['code']) ?></code><br><small class="text-muted"><?= e($o['description']) ?></small></td>
                    <td>
                      <span class="badge text-bg-<?= $o['effect'] === 'allow' ? 'success' : 'danger' ?>">
                        <?= strtoupper($o['effect']) ?>
                      </span>
                    </td>
                    <td class="small"><?= e($o['expires_at'] ?? 'ถาวร') ?></td>
                    <td class="text-end">

                    <form method="post" action="<?= e(url('/users/' . $id . '/permissions/delete')) ?>" class="d-inline" data-confirm="ลบการกำหนดสิทธิ์นี้?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="permission_id" value="<?= (int) $o['permission_id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                      </form>


                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>