<?php

use Modules\CoreAdmin\Services\ServiceRoomService;

/** @var array $rooms */
/** @var array $years */
/** @var array{year: int, q: string} $filters */
/** @var bool $canManage */

$teacherCell = static function (array $room): string {
    if ($room['homeroom_employee_id'] === null) {
        return '<span class="text-muted">- ยังไม่กำหนด -</span>';
    }
    $html = e(ServiceRoomService::teacherName($room));
    if ($room['homeroom_deleted_at'] !== null || $room['homeroom_status'] !== 'active') {
        $html .= ' <span class="badge text-bg-warning" title="ควรเปลี่ยนครูประจำชั้น">พ้นสภาพ/ไม่ปฏิบัติงาน</span>';
    }

    return $html;
};
$deleteForm = static function (array $room): string {
    return '<form method="post" action="' . e(url('/service-rooms/' . (int) $room['id'])) . '" class="d-inline"'
        . ' data-confirm="ลบห้องบริการ ' . e($room['room_code'] . ' ' . $room['name_th']) . ' (ปีการศึกษา ' . (int) $room['year_be'] . ')?">'
        . csrf_field() . '<input type="hidden" name="_method" value="DELETE">'
        . '<button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">ข้อมูลห้องบริการ</h1>
  <?php if ($canManage): ?>
    <a href="<?= e(url('/service-rooms/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มห้องบริการ</a>
  <?php endif; ?>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/service-rooms')) ?>" class="row g-2 align-items-end">
      <div class="col-6 col-md-3 col-xl-2">
        <label class="form-label small mb-1" for="year">ปีการศึกษา</label>
        <select id="year" name="year" class="form-select">
          <option value="all" <?= $filters['year'] === 0 ? 'selected' : '' ?>>ทุกปี</option>
          <?php foreach ($years as $y): ?>
            <option value="<?= (int) $y['id'] ?>" <?= $filters['year'] === (int) $y['id'] ? 'selected' : '' ?>>
              <?= (int) $y['year_be'] ?><?= (int) $y['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-5 col-xl-4">
        <label class="form-label small mb-1" for="q">ค้นหา</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="รหัส, ชื่อห้อง, ชื่อครู">
      </div>
      <div class="col-12 col-md-4 col-xl-2">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>กรอง</button>
      </div>
    </form>
  </div>
</div>

<?php if ($rooms === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-door-closed fs-1 d-block mb-2"></i>ไม่พบข้อมูลห้องบริการ
  </div></div>
<?php else: ?>
  <!-- Desktop: ตาราง -->
  <div class="card shadow-sm d-none d-md-block">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 8rem;">รหัสห้องบริการ</th>
            <th style="width: 7rem;">ปีการศึกษา</th>
            <th>ชื่อห้องบริการ</th>
            <th>ครูประจำชั้น</th>
            <?php if ($canManage): ?><th class="text-end" style="width: 7rem;">จัดการ</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $room): ?>
            <tr>
              <td class="font-monospace fw-semibold"><?= e($room['room_code']) ?></td>
              <td><?= (int) $room['year_be'] ?></td>
              <td><?= e($room['name_th']) ?></td>
              <td><?= $teacherCell($room) ?></td>
              <?php if ($canManage): ?>
                <td class="text-end text-nowrap">
                  <a href="<?= e(url('/service-rooms/' . (int) $room['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                  <?= $deleteForm($room) ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white small text-muted">ทั้งหมด <?= count($rooms) ?> ห้อง</div>
  </div>

  <!-- Mobile: Card List -->
  <div class="d-md-none d-grid gap-2">
    <?php foreach ($rooms as $room): ?>
      <div class="card shadow-sm">
        <div class="card-body py-2">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="min-w-0">
              <div><span class="badge text-bg-primary font-monospace"><?= e($room['room_code']) ?></span>
                <span class="small text-muted ms-1">ปีการศึกษา <?= (int) $room['year_be'] ?></span></div>
              <div class="fw-semibold mt-1"><?= e($room['name_th']) ?></div>
              <div class="small mt-1"><i class="bi bi-person-badge me-1 text-muted"></i><?= $teacherCell($room) ?></div>
            </div>
            <?php if ($canManage): ?>
              <div class="d-flex flex-column gap-1">
                <a href="<?= e(url('/service-rooms/' . (int) $room['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                <?= $deleteForm($room) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="small text-muted text-center">ทั้งหมด <?= count($rooms) ?> ห้อง</div>
  </div>
<?php endif; ?>
