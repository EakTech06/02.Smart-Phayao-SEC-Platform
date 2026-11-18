<?php

use Modules\CoreAdmin\Services\CalendarService;

/** @var string $month Y-m */
/** @var int $first timestamp วันที่ 1 ของเดือน */
/** @var int $gridStart */
/** @var array $events */
/** @var bool $canCreate */

$prev = date('Y-m', strtotime('-1 month', $first));
$next = date('Y-m', strtotime('+1 month', $first));
$today = date('Y-m-d');
$monthTitle = THAI_MONTHS[(int) date('n', $first)] . ' ' . ((int) date('Y', $first) + 543);

// จัดกิจกรรมลงแต่ละวัน (กิจกรรมหลายวันแสดงทุกวันที่คาบเกี่ยว)
$byDay = [];
foreach ($events as $ev) {
    $d = strtotime(substr((string) $ev['start_at'], 0, 10));
    $last = strtotime(substr((string) ($ev['end_at'] ?? $ev['start_at']), 0, 10));
    for ($guard = 0; $d <= $last && $guard < 62; $guard++, $d = strtotime('+1 day', $d)) {
        $byDay[date('Y-m-d', $d)][] = $ev;
    }
}
$timeLabel = static fn (array $ev): string => (int) $ev['is_all_day'] === 1 ? 'ทั้งวัน' : substr((string) $ev['start_at'], 11, 5);
$weekdays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
$monthEvents = array_filter($byDay, static fn (string $day): bool => substr($day, 0, 7) === $month, ARRAY_FILTER_USE_KEY);
ksort($monthEvents);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">ปฏิทินกลาง</h1>
  <?php if ($canCreate): ?>
    <a href="<?= e(url('/calendar/create')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่มกิจกรรม</a>
  <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <a href="<?= e(url('/calendar?month=' . $prev)) ?>" class="btn btn-outline-secondary" aria-label="เดือนก่อน"><i class="bi bi-chevron-left"></i></a>
  <div class="text-center">
    <div class="h5 mb-0"><?= e($monthTitle) ?></div>
    <?php if ($month !== date('Y-m')): ?><a href="<?= e(url('/calendar')) ?>" class="small">กลับไปเดือนปัจจุบัน</a><?php endif; ?>
  </div>
  <a href="<?= e(url('/calendar?month=' . $next)) ?>" class="btn btn-outline-secondary" aria-label="เดือนถัดไป"><i class="bi bi-chevron-right"></i></a>
</div>

<!-- Desktop/Tablet: ตารางรายเดือน -->
<div class="card shadow-sm d-none d-md-block">
  <table class="table table-bordered mb-0 calendar-grid">
    <thead class="table-light">
      <tr><?php foreach ($weekdays as $i => $w): ?><th class="text-center small<?= $i === 0 ? ' text-danger' : '' ?>"><?= $w ?></th><?php endforeach; ?></tr>
    </thead>
    <tbody>
      <?php for ($week = 0; $week < 6; $week++): ?>
        <tr>
          <?php for ($dow = 0; $dow < 7; $dow++): ?>
            <?php
            $ts = strtotime('+' . ($week * 7 + $dow) . ' days', $gridStart);
            $day = date('Y-m-d', $ts);
            $classes = trim((date('Y-m', $ts) !== $month ? 'other-month ' : '') . ($day === $today ? 'today' : ''));
            $items = $byDay[$day] ?? [];
            ?>
            <td class="<?= $classes ?>">
              <div class="d-flex justify-content-between">
                <span class="day-number<?= $dow === 0 ? ' text-danger' : '' ?>"><?= (int) date('j', $ts) ?></span>
                <?php if ($canCreate): ?>
                  <a href="<?= e(url('/calendar/create?date=' . $day)) ?>" class="text-muted small" title="เพิ่มกิจกรรมวันนี้" aria-label="เพิ่มกิจกรรม <?= e(thai_date($day, false)) ?>"><i class="bi bi-plus"></i></a>
                <?php endif; ?>
              </div>
              <?php foreach (array_slice($items, 0, 3) as $ev): ?>
                <a href="<?= e(url('/calendar/' . (int) $ev['id'])) ?>" class="calendar-event" style="background: <?= e($ev['color'] ?: '#0d6efd') ?>;"
                   title="<?= e($timeLabel($ev) . ' ' . $ev['title']) ?>">
                  <?= (int) $ev['is_all_day'] === 1 ? '' : e(substr((string) $ev['start_at'], 11, 5)) . ' ' ?><?= e($ev['title']) ?>
                </a>
              <?php endforeach; ?>
              <?php if (count($items) > 3): ?>
                <div class="small text-muted mt-1">+<?= count($items) - 3 ?> รายการ</div>
              <?php endif; ?>
            </td>
          <?php endfor; ?>
        </tr>
      <?php endfor; ?>
    </tbody>
  </table>
</div>

<!-- Mobile: รายการกิจกรรมของเดือน -->
<div class="d-md-none">
  <?php if ($monthEvents === []): ?>
    <div class="card shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 d-block mb-2"></i>ไม่มีกิจกรรมในเดือนนี้</div></div>
  <?php endif; ?>
  <?php foreach ($monthEvents as $day => $items): ?>
    <div class="fw-semibold small mt-3 mb-1<?= $day === $today ? ' text-primary' : '' ?>">
      <?= e($weekdays[(int) date('w', strtotime($day))] . ' ' . thai_date($day, false)) ?><?= $day === $today ? ' (วันนี้)' : '' ?>
    </div>
    <div class="list-group shadow-sm">
      <?php foreach ($items as $ev): ?>
        <a href="<?= e(url('/calendar/' . (int) $ev['id'])) ?>" class="list-group-item list-group-item-action d-flex gap-2 py-2">
          <span class="rounded flex-shrink-0" style="width: .4rem; background: <?= e($ev['color'] ?: '#0d6efd') ?>;"></span>
          <span class="min-w-0">
            <span class="d-block fw-semibold text-break"><?= e($ev['title']) ?></span>
            <span class="d-block small text-muted"><?= e($timeLabel($ev)) ?><?= $ev['location'] ? ' · ' . e($ev['location']) : '' ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="small text-muted mt-3 d-flex flex-wrap gap-3">
  <?php foreach (CalendarService::VISIBILITIES as $key => $label): ?>
    <span><i class="bi bi-<?= $key === 'public' ? 'globe' : ($key === 'department' ? 'people' : 'lock') ?> me-1"></i><?= e($label) ?></span>
  <?php endforeach; ?>
  <span><i class="bi bi-google me-1"></i>กิจกรรมทั้งองค์กรซิงก์กับ Google Calendar ขององค์กร</span>
</div>
