<?php

use Modules\CoreAdmin\Services\CalendarService;

/** @var array $event */
/** @var bool $canEdit */

$allDay = (int) $event['is_all_day'] === 1;
$start = thai_date((string) $event['start_at'], !$allDay, true);
$end = $event['end_at'] !== null ? thai_date((string) $event['end_at'], !$allDay, true) : null;
$visIcon = ['public' => 'globe', 'department' => 'people', 'private' => 'lock'][$event['visibility']] ?? 'globe';
?>
<a href="<?= e(url('/calendar?month=' . substr((string) $event['start_at'], 0, 7))) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> กลับไปปฏิทิน</a>

<div class="card shadow-sm" style="border-top: 4px solid <?= e($event['color'] ?: '#0d6efd') ?>;">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2">
      <h1 class="h4 mb-2 text-break"><?= e($event['title']) ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-start">
        <a href="<?= e(CalendarService::googleTemplateUrl($event)) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-google me-1"></i>เพิ่มลง Google Calendar ของฉัน
        </a>
        <?php if ($canEdit): ?>
          <a href="<?= e(url('/calendar/' . (int) $event['id'] . '/edit')) ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>แก้ไข</a>
          <form method="post" action="<?= e(url('/calendar/' . (int) $event['id'])) ?>" data-confirm="ลบกิจกรรม <?= e($event['title']) ?>?">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>ลบ</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <dl class="row mb-0">
      <dt class="col-sm-3"><i class="bi bi-clock me-1"></i>วันเวลา</dt>
      <dd class="col-sm-9"><?= e($start) ?><?= $end !== null && $end !== $start ? ' – ' . e($end) : '' ?><?= $allDay ? ' (ทั้งวัน)' : '' ?></dd>
      <?php if (!empty($event['location'])): ?>
        <dt class="col-sm-3"><i class="bi bi-geo-alt me-1"></i>สถานที่</dt><dd class="col-sm-9"><?= e($event['location']) ?></dd>
      <?php endif; ?>
      <dt class="col-sm-3"><i class="bi bi-<?= $visIcon ?> me-1"></i>การมองเห็น</dt>
      <dd class="col-sm-9">
        <?= e(CalendarService::VISIBILITIES[$event['visibility']] ?? $event['visibility']) ?><?= $event['department_name'] ? ': ' . e($event['department_name']) : '' ?>
        <?php if ($event['visibility'] === 'public'): ?>
          <span class="badge text-bg-<?= $event['google_event_id'] ? 'success' : 'secondary' ?> ms-1">
            <i class="bi bi-google me-1"></i><?= $event['google_event_id'] ? 'ซิงก์ Google Calendar แล้ว' : 'ยังไม่ซิงก์ Google Calendar' ?>
          </span>
        <?php endif; ?>
      </dd>
      <dt class="col-sm-3"><i class="bi bi-person me-1"></i>ผู้สร้าง</dt>
      <dd class="col-sm-9"><?= e(trim((string) $event['creator_name']) ?: '-') ?> · <?= e(thai_date((string) $event['created_at'])) ?></dd>
    </dl>
    <?php if (!empty($event['description'])): ?>
      <hr>
      <div class="text-break"><?= nl2br(e($event['description'])) ?></div>
    <?php endif; ?>
  </div>
</div>
