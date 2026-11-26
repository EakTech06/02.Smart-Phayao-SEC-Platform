<?php

/** @var array{title:string, student:array, name:string, sensitive:bool, blocks:list<array>} $report */
/** @var string $orgName */
/** @var array|null $photo */

$student = $report['student'];
$base = url('/academic/students/' . (int) $student['id']);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
  <a href="<?= e($base) ?>" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i> กลับ</a>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-primary" data-action="print"><i class="bi bi-printer me-1"></i>พิมพ์ / PDF</button>
    <a href="<?= e($base . '/excel') ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>
  </div>
</div>
<div class="alert alert-info small d-print-none"><i class="bi bi-info-circle me-1"></i>กด "พิมพ์ / PDF" แล้วเลือกเครื่องพิมพ์ หรือเลือก "บันทึกเป็น PDF" (Save as PDF) เพื่อได้ไฟล์ PDF</div>

<article class="card shadow-sm mx-auto student-print" style="max-width: 210mm;">
  <div class="card-body p-4">
    <header class="d-flex justify-content-between align-items-start gap-3 border-bottom pb-2 mb-3">
      <div>
        <div class="small text-muted"><?= e($orgName) ?></div>
        <h1 class="h5 fw-bold mb-1"><?= e($report['title']) ?></h1>
        <div class="fw-semibold"><?= e($report['name']) ?><?= $student['nickname'] ? ' (' . e($student['nickname']) . ')' : '' ?></div>
        <div class="small">เลขประจำตัวนักเรียน <span class="font-monospace"><?= e($student['student_code']) ?></span></div>
        <div class="small text-muted">ข้อมูล ณ <?= e(thai_date(date('Y-m-d H:i'), true, true)) ?></div>
      </div>
      <?php if ($photo !== null): ?>
        <img src="<?= e(url('/files/' . $photo['uuid'] . '/download?inline=1')) ?>" alt="รูปถ่ายนักเรียน" class="border" style="width: 30mm; height: 40mm; object-fit: cover;">
      <?php else: ?>
        <div class="border d-flex align-items-center justify-content-center text-muted small" style="width: 30mm; height: 40mm;">รูปถ่าย</div>
      <?php endif; ?>
    </header>

    <?php foreach ($report['blocks'] as $block): ?>
      <section class="report-section mb-3">
        <h2 class="h6 fw-bold bg-body-tertiary border px-2 py-1 mb-0"><?= e($block['title']) ?></h2>
        <?php if ($block['type'] === 'fields'): ?>
          <table class="table table-sm table-bordered mb-0 profile-table">
            <tbody>
              <?php foreach ($block['rows'] as [$label, $value]): ?>
                <tr><th scope="row" class="fw-normal text-muted" style="width: 35%;"><?= e($label) ?></th><td><?= nl2br(e($value)) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php elseif ($block['rows'] === []): ?>
          <div class="border border-top-0 px-2 py-1 small text-muted"><?= e($block['empty']) ?></div>
        <?php else: ?>
          <table class="table table-sm table-bordered mb-0 profile-table">
            <thead class="table-light"><tr><?php foreach ($block['head'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($block['rows'] as $r): ?>
                <tr><?php foreach ($r as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?php if ($report['sensitive']): ?>
      <p class="small text-muted mb-0"><i class="bi bi-shield-lock me-1"></i>เอกสารนี้มีข้อมูลอ่อนไหวตาม PDPA มาตรา 26 ใช้เฉพาะในการจัดการศึกษาและการให้บริการนักเรียนเท่านั้น</p>
    <?php endif; ?>
    <?= Core\View::template('print_footer') ?>
  </div>
</article>
<script src="<?= e(asset('js/reports.js')) ?>" defer></script>
