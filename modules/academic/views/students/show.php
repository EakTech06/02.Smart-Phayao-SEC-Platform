<?php

use Modules\Academic\Services\StudentCompleteness;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;
use Modules\CoreAdmin\Services\FileService;

/** @var array $student */
/** @var array $guardians */
/** @var array $enrollments */
/** @var array|null $health */
/** @var bool $canSensitive */
/** @var string|null $nationalId */
/** @var array $documents */
/** @var array|null $photo */
/** @var bool $canUpdate */
/** @var bool $canUpdateHealth */
/** @var bool $canDelete */
/** @var bool $driveReady */
/** @var string|null $screenerName */
/** @var array $completeness ผล StudentCompleteness::evaluate */
/** @var list<array> $statusLogs */
/** @var array<string,string> $statusTargets สถานะที่เปลี่ยนได้ (ว่าง = ไม่มีสิทธิ์/สถานะสิ้นสุด) */
/** @var list<array> $identityHistory */

$id = (int) $student['id'];
$base = url('/academic/students/' . $id);
[$statusLabel, $statusColor] = StudentLookup::STATUSES[$student['status']];
$name = StudentService::displayName($student);
$dash = static fn (mixed $v): string => $v === null || $v === '' ? '-' : e((string) $v);
$date = static fn (?string $d): string => $d ? e(thai_date($d, false, true)) : '-';
$row = static fn (string $label, string $html): string => '<dt class="col-sm-5 col-xxl-4 fw-normal text-muted">' . e($label) . '</dt><dd class="col-sm-7 col-xxl-8">' . $html . '</dd>';
$idLabel = StudentLookup::ID_TYPES[$student['id_type']] ?? $student['id_type'];
$score = (int) $completeness['score'];
$scoreColor = $score >= 100 ? 'success' : ($score >= 70 ? 'warning' : 'danger');
$code = static fn (array $list, mixed $k): string => $k === null || $k === '' ? '-' : e($list[(string) $k] ?? (string) $k);
?>
<a href="<?= e(url('/academic/students')) ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> ทะเบียนนักเรียน</a>

<div class="card shadow-sm mb-3">
  <div class="card-body d-flex flex-wrap gap-3 align-items-center">
    <?php if ($photo !== null): ?>
      <img src="<?= e(url('/files/' . $photo['uuid'] . '/download?inline=1')) ?>" alt="รูปถ่ายนักเรียน" class="rounded border student-photo" loading="lazy">
    <?php else: ?>
      <div class="rounded border bg-body-tertiary d-flex align-items-center justify-content-center student-photo text-muted"><i class="bi bi-person fs-1"></i></div>
    <?php endif; ?>
    <div class="flex-grow-1">
      <h1 class="h4 mb-1"><?= e($name) ?><?php if ($student['nickname']): ?> <span class="text-muted fs-6">(<?= e($student['nickname']) ?>)</span><?php endif; ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="font-monospace">เลขประจำตัว <?= e($student['student_code']) ?></span>
        <span class="badge text-bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
        <span class="text-muted small">อายุ <?= e(StudentLookup::age($student['birth_date'])) ?></span>
        <a href="#completeness" class="badge text-bg-<?= e($scoreColor) ?> text-decoration-none" title="ความครบถ้วนของข้อมูล"><i class="bi bi-clipboard-check me-1"></i>ข้อมูลครบ <?= $score ?>%</a>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= e($base . '/print') ?>" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>พิมพ์/PDF</a>
      <a href="<?= e($base . '/excel') ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>
      <?php if ($canUpdate): ?>
        <a href="<?= e($base . '/edit') ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>แก้ไข</a>
      <?php endif; ?>
      <?php if ($canDelete): ?>
        <form method="post" action="<?= e($base) ?>" data-confirm="ลบข้อมูลนักเรียน <?= e($name) ?>? (ลบแบบ Soft Delete)">
          <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i><span class="visually-hidden">ลบ</span></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <nav class="card-footer bg-white small d-flex flex-wrap gap-3">
    <a href="#general">ข้อมูลทั่วไป</a><a href="#family">ครอบครัว</a><a href="#completeness">ความครบถ้วน</a><a href="#status">สถานะ</a><a href="#enrollments">การรับบริการ</a>
    <?php if ($canSensitive): ?><a href="#health">ความพิการ/สุขภาพ</a><?php endif; ?>
    <a href="#documents">เอกสาร</a>
  </nav>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-6">
    <section class="card shadow-sm h-100" id="general">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>ข้อมูลทั่วไป</div>
      <div class="card-body">
        <dl class="row mb-0">
          <?php
          $idHtml = '-';
          if ($student['id_type'] === 'g_code') {
              $idHtml = '<span class="font-monospace">' . $dash($student['g_code']) . '</span>';
          } elseif ((int) $student['has_national_id'] === 1) {
              $idHtml = $nationalId !== null
                  ? '<span class="font-monospace user-select-all">' . e($nationalId) . '</span> <span class="badge text-bg-warning">แสดงครั้งเดียว · บันทึกประวัติแล้ว</span>'
                  : '<span class="font-monospace">*********' . e((string) $student['national_id_last4']) . '</span>';
          }
          echo $row($idLabel, $idHtml);
          ?>
          <?php if ($canSensitive && $nationalId === null && (int) $student['has_national_id'] === 1): ?>
            <dt class="col-sm-5 col-xxl-4"></dt>
            <dd class="col-sm-7 col-xxl-8">
              <form method="post" action="<?= e($base . '/reveal-id') ?>" data-confirm="แสดงเลขเต็ม? ระบบจะบันทึกประวัติการเปิดดู">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>แสดงเลขเต็ม</button>
              </form>
            </dd>
          <?php endif; ?>
          <?= $row('ชื่อภาษาอังกฤษ', $dash(trim(($student['first_name_en'] ?? '') . ' ' . ($student['last_name_en'] ?? '')))) ?>
          <?= $row('เพศ', $dash(StudentLookup::GENDERS[$student['gender'] ?? ''] ?? null)) ?>
          <?= $row('วันเกิด', $date($student['birth_date'])) ?>
          <?= $row('สัญชาติ / เชื้อชาติ', $dash($student['nationality']) . ' / ' . $dash($student['ethnicity'])) ?>
          <?= $row('ศาสนา / ชนเผ่า', $dash($student['religion']) . ' / ' . $dash($student['tribe'])) ?>
          <?= $row('เบอร์โทรศัพท์', $dash($student['phone'])) ?>
          <?= $row('ที่อยู่ตามทะเบียนบ้าน', e(StudentLookup::formatAddress($student['address_registered'])) . (!empty($student['address_registered']['house_code']) ? '<div class="small text-muted">รหัสประจำบ้าน ' . e($student['address_registered']['house_code']) . '</div>' : '')) ?>
          <?= $row('ที่อยู่ปัจจุบัน', $student['address_current'] === null ? 'ตามทะเบียนบ้าน' : e(StudentLookup::formatAddress($student['address_current']))) ?>
          <?php
          $travel = [];
          foreach (StudentLookup::TRAVEL_FIELDS as $k => $label) {
              if ($student[$k] !== null) {
                  $travel[] = $label . ' ' . rtrim(rtrim((string) $student[$k], '0'), '.') . ' กม.';
              }
          }
          ?>
          <?= $row('ระยะทางเดินทาง', $travel === [] ? '-' : e(implode(' · ', $travel))) ?>
          <?= $row('ลูกเสือ / เหล่า', $code(StudentLookup::SCOUT_LEVELS, $student['scout_level']) . ' / ' . $code(StudentLookup::SCOUT_GROUPS, $student['scout_group'])) ?>
          <?= $row('ยุวกาชาด', $code(StudentLookup::REDCROSS_STATUSES, $student['redcross_status'])) ?>
          <?= $row('วันที่มาสมัคร', $date($student['applied_at'])) ?>
          <?= $row('วันที่เริ่มรับบริการ', $date($student['admitted_at'])) ?>
          <?php if (in_array($student['status'], StudentLookup::LEFT_STATUSES, true)): ?>
            <?= $row('ออกจากการรับบริการ', $date($student['left_at']) . ($student['left_reason'] ? ' · ' . e($student['left_reason']) : '')) ?>
          <?php endif; ?>
          <?php if ($student['note']): ?><?= $row('หมายเหตุ', nl2br(e($student['note']))) ?><?php endif; ?>
        </dl>
        <?php if ($identityHistory !== []): ?>
          <div class="border-top pt-2 mt-2">
            <div class="small fw-semibold mb-1"><i class="bi bi-clock-history me-1"></i>เลขประจำตัวเดิม <span class="badge text-bg-warning">ข้อมูลอ่อนไหว</span></div>
            <ul class="list-unstyled small mb-0">
              <?php foreach ($identityHistory as $h): ?>
                <li class="mb-1">
                  <?= e(StudentLookup::ID_TYPES[$h['id_type']] ?? $h['id_type']) ?>:
                  <span class="font-monospace"><?= $h['g_code'] ? e($h['g_code']) : '*********' . e((string) $h['national_id_last4']) ?></span>
                  <span class="text-muted">· เปลี่ยนเมื่อ <?= e(thai_date((string) $h['changed_at'])) ?><?= $h['changed_by_name'] ? ' โดย ' . e($h['changed_by_name']) : '' ?><?= $h['reason'] ? ' · ' . e($h['reason']) : '' ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="col-12 col-xl-6">
    <section class="card shadow-sm h-100" id="family">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i>บิดา มารดา และผู้ปกครอง</div>
      <div class="card-body">
        <dl class="row mb-2">
          <?= $row('สถานภาพบิดา-มารดา', $dash(StudentLookup::PARENTS_STATUSES[$student['parents_status'] ?? ''] ?? null)) ?>
          <?= $row('อาศัยอยู่กับ', $dash(StudentLookup::LIVES_WITH[$student['lives_with'] ?? ''] ?? null)) ?>
          <?php
          $sib = [];
          foreach (['older_brothers' => 'พี่ชาย', 'older_sisters' => 'พี่สาว', 'younger_brothers' => 'น้องชาย', 'younger_sisters' => 'น้องสาว'] as $k => $label) {
              if ($student[$k] !== null) {
                  $sib[] = $label . ' ' . (int) $student[$k];
              }
          }
          ?>
          <?= $row('พี่น้อง', ($sib === [] ? '-' : e(implode(' · ', $sib)))
              . ($student['child_order'] !== null ? '<div class="small text-muted">เป็นบุตรคนที่ ' . (int) $student['child_order'] . '</div>' : '')
              . ($student['siblings_studying'] !== null ? '<div class="small text-muted">พี่น้องที่กำลังศึกษา ' . (int) $student['siblings_studying'] . ' คน</div>' : '')) ?>
        </dl>
        <?php if ($guardians === []): ?>
          <p class="text-muted mb-0">ยังไม่มีข้อมูล</p>
        <?php endif; ?>
        <?php foreach (StudentLookup::RELATIONS as $rel => $relLabel): ?>
          <?php if (!isset($guardians[$rel])) { continue; } $g = $guardians[$rel]; ?>
          <div class="border rounded p-2 mb-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
              <div class="fw-semibold">
                <?= e($relLabel) ?><?= $g['relation_note'] ? ' (' . e($g['relation_note']) . ')' : '' ?>:
                <?= e(trim(($g['title_name'] ?? '') . $g['first_name'] . ' ' . ($g['last_name'] ?? ''))) ?>
              </div>
              <div>
                <?php if ((int) $g['is_primary_contact'] === 1): ?><span class="badge text-bg-primary">ผู้ติดต่อหลัก</span><?php endif; ?>
                <?php if ($g['life_status'] === 'deceased'): ?><span class="badge text-bg-secondary">เสียชีวิต</span><?php endif; ?>
              </div>
            </div>
            <div class="small text-muted">
              <?php if ($g['phone']): ?><i class="bi bi-telephone"></i> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $g['phone'])) ?>"><?= e($g['phone']) ?></a> · <?php endif; ?>
              <?= $g['line_id'] ? 'Line: ' . e($g['line_id']) . ' · ' : '' ?>
              <?= $g['occupation'] ? 'อาชีพ ' . e($g['occupation']) . ' · ' : '' ?>
              <?= $g['income_per_year'] !== null ? 'รายได้ ' . e(number_format((int) $g['income_per_year'])) . ' บาท/ปี · ' : '' ?>
              <?= $g['national_id_last4'] ? 'เลขบัตร *********' . e($g['national_id_last4']) : '' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <div class="col-12 col-xl-6">
    <section class="card shadow-sm h-100" id="completeness">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard-check me-1"></i>ความครบถ้วนของข้อมูล</span>
        <span class="badge text-bg-<?= e($scoreColor) ?>"><?= $score ?>%</span>
      </div>
      <div class="card-body">
        <div class="progress mb-3 completeness-bar" role="progressbar" aria-label="ความครบถ้วน" aria-valuenow="<?= $score ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar bg-<?= e($scoreColor) ?>" style="width: <?= $score ?>%"></div>
        </div>
        <ul class="list-unstyled mb-0 small">
          <?php foreach ($completeness['categories'] as $c): ?>
            <li class="d-flex gap-2 mb-2">
              <?php if ($c['skipped']): ?>
                <i class="bi bi-lock text-muted"></i>
                <div class="text-muted"><?= e($c['label']) ?> · ไม่มีสิทธิ์ตรวจ (ข้อมูลอ่อนไหว)</div>
              <?php elseif ($c['missing'] === []): ?>
                <i class="bi bi-check-circle-fill text-success"></i>
                <div><?= e($c['label']) ?> <span class="text-muted">(<?= (int) $c['done'] ?>/<?= (int) $c['total'] ?>)</span></div>
              <?php else: ?>
                <i class="bi bi-exclamation-circle-fill text-danger"></i>
                <div><?= e($c['label']) ?> <span class="text-muted">(<?= (int) $c['done'] ?>/<?= (int) $c['total'] ?>)</span>
                  <div class="text-danger">ยังขาด: <?= e(implode(', ', $c['missing'])) ?></div></div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($completeness['warnings'] !== []): ?>
          <div class="alert alert-warning small py-2 mb-0 mt-2">
            <div class="fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>ข้อควรตรวจสอบ</div>
            <ul class="mb-0 ps-3"><?php foreach ($completeness['warnings'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="col-12 col-xl-6">
    <section class="card shadow-sm h-100" id="status">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-flag me-1"></i>สถานะและประวัติการเปลี่ยนสถานะ</div>
      <div class="card-body">
        <?php if ($statusTargets !== []): ?>
          <form method="post" action="<?= e($base . '/status') ?>" class="row g-2 mb-3 border rounded p-2 mx-0" novalidate
                data-confirm="เปลี่ยนสถานะของ <?= e($name) ?>?">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
              <label class="form-label small" for="st-to">เปลี่ยนเป็น <span class="text-danger">*</span></label>
              <select id="st-to" name="to_status" class="form-select form-select-sm<?= error_for('to_status') ? ' is-invalid' : '' ?>" required>
                <?php foreach ($statusTargets as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= old('to_status') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (error_for('to_status')): ?><div class="invalid-feedback"><?= e(error_for('to_status')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small" for="st-date">วันที่มีผล <span class="text-danger">*</span></label>
              <input type="date" id="st-date" name="effective_on" value="<?= e(old('effective_on', date('Y-m-d'))) ?>" required
                     class="form-control form-control-sm<?= error_for('effective_on') ? ' is-invalid' : '' ?>">
              <?php if (error_for('effective_on')): ?><div class="invalid-feedback"><?= e(error_for('effective_on')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-7">
              <label class="form-label small" for="st-reason">เหตุผล</label>
              <input type="text" id="st-reason" name="reason" maxlength="255" value="<?= e(old('reason')) ?>" placeholder="เช่น ส่งต่อเรียนร่วม, ย้ายตามผู้ปกครอง"
                     class="form-control form-control-sm<?= error_for('reason') ? ' is-invalid' : '' ?>">
              <?php if (error_for('reason')): ?><div class="invalid-feedback"><?= e(error_for('reason')) ?></div><?php endif; ?>
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label small" for="st-ref">เลขที่หนังสือ/คำสั่ง</label>
              <input type="text" id="st-ref" name="reference_no" maxlength="100" value="<?= e(old('reference_no')) ?>" class="form-control form-control-sm">
            </div>
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <span class="form-text m-0">แนบหนังสือ/คำสั่งได้ที่ส่วนเอกสารหลักฐาน</span>
              <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>เปลี่ยนสถานะ</button>
            </div>
          </form>
        <?php elseif ($student['status'] === 'deceased'): ?>
          <p class="small text-muted">สถานะสิ้นสุดแล้ว ไม่สามารถเปลี่ยนสถานะได้</p>
        <?php endif; ?>
        <?php if ($statusLogs === []): ?>
          <p class="text-muted small mb-0">ยังไม่มีประวัติ</p>
        <?php else: ?>
          <ol class="list-unstyled small mb-0">
            <?php foreach ($statusLogs as $log): ?>
              <?php [$toLabel, $toColor] = StudentLookup::STATUSES[$log['to_status']] ?? [$log['to_status'], 'secondary']; ?>
              <li class="mb-2 border-start border-3 ps-2">
                <span class="badge text-bg-<?= e($toColor) ?>"><?= e($toLabel) ?></span>
                <span class="text-muted">มีผล <?= $date($log['effective_on']) ?></span>
                <?php if ($log['from_status']): ?><span class="text-muted">· จาก<?= e(StudentLookup::statusLabel((string) $log['from_status'])) ?></span><?php endif; ?>
                <?php if ($log['reason'] || $log['reference_no']): ?>
                  <div><?= e((string) $log['reason']) ?><?= $log['reference_no'] ? ' <span class="text-muted">(อ้างอิง ' . e($log['reference_no']) . ')</span>' : '' ?></div>
                <?php endif; ?>
                <div class="text-muted">บันทึก <?= e(thai_date((string) $log['created_at'])) ?><?= $log['created_by_name'] ? ' โดย ' . e($log['created_by_name']) : '' ?></div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="col-12">
    <section class="card shadow-sm" id="enrollments">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-door-open me-1"></i>การรับบริการรายปีการศึกษา</span>
        <?php if ($canUpdate): ?><a href="<?= e($base . '/enrollments/create') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>เพิ่ม</a><?php endif; ?>
      </div>
      <?php if ($enrollments === []): ?>
        <div class="card-body text-muted">ยังไม่มีข้อมูลการรับบริการ</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>ปีการศึกษา</th><th>ประเภท</th><th>หน่วยบริการ</th><th>ห้อง / ระดับชั้น</th><th>ครูผู้รับผิดชอบ</th><?php if ($canUpdate): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($enrollments as $en): ?>
                <tr>
                  <td class="text-nowrap"><?= e($en['year_name']) ?><?= (int) $en['is_current'] === 1 ? ' <span class="badge text-bg-success">ปัจจุบัน</span>' : '' ?></td>
                  <td><?= e(StudentLookup::serviceLabel($en['service_type'])) ?></td>
                  <td><?= $dash($en['department_name']) ?></td>
                  <td><?= $en['room_code'] ? e($en['room_code'] . ' ' . $en['room_name']) : '-' ?><?= $en['grade_level'] ? '<div class="small text-muted">' . e($en['grade_level']) . '</div>' : '' ?></td>
                  <td>
                    <?= $dash($en['teacher_name']) ?>
                    <?php if ($en['teacher_name'] && $en['teacher_status'] !== 'active'): ?><span class="badge text-bg-warning">ไม่ได้ปฏิบัติงานแล้ว</span><?php endif; ?>
                    <?= $en['homeroom_name'] ? '<div class="small text-muted">ครูประจำชั้น ' . e($en['homeroom_name']) . '</div>' : '' ?>
                  </td>
                  <?php if ($canUpdate): ?>
                    <td class="text-end text-nowrap">
                      <a href="<?= e($base . '/enrollments/' . (int) $en['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" aria-label="แก้ไข"><i class="bi bi-pencil"></i></a>
                      <form method="post" action="<?= e($base . '/enrollments/' . (int) $en['id']) ?>" class="d-inline" data-confirm="ลบข้อมูลการรับบริการ <?= e($en['year_name']) ?>?">
                        <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="ลบ"><i class="bi bi-trash"></i></button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
                <?php if ($en['note']): ?><tr><td colspan="<?= $canUpdate ? 6 : 5 ?>" class="small text-muted border-top-0 pt-0">หมายเหตุ: <?= e($en['note']) ?></td></tr><?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <?php if ($canSensitive): ?>
    <div class="col-12">
      <section class="card shadow-sm border-warning-subtle" id="health">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-shield-lock me-1"></i>ข้อมูลความพิการและสุขภาพ <span class="badge text-bg-warning">ข้อมูลอ่อนไหว</span></span>
          <?php if ($canUpdateHealth): ?><a href="<?= e($base . '/health/edit') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>แก้ไข</a><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($health === null): ?>
            <p class="text-muted mb-0">ยังไม่มีข้อมูล</p>
          <?php else: ?>
            <div class="row">
              <dl class="row col-12 col-xl-6 mb-0">
                <?= $row('ประเภทความพิการ', $dash(StudentLookup::DISABILITY_TYPES[$health['disability_type'] ?? ''] ?? null)
                    . (!empty($health['disability_level']) ? ' (' . e(StudentLookup::DISABILITY_LEVELS[$health['disability_level']][0] ?? '') . ')' : '')) ?>
                <?php if (!empty($health['multiple_disabilities'])): ?>
                  <?= $row('ความพิการซ้อน', e(implode(', ', array_map(static fn (string $k): string => str_replace('บกพร่องทาง', '', StudentLookup::DISABILITY_TYPES[$k] ?? $k), $health['multiple_disabilities'])))) ?>
                <?php endif; ?>
                <?= $row('ระดับเชาวน์ปัญญา (IQ)', $dash($health['iq'] ?? null)) ?>
                <?= $row('ลักษณะความพิการ', !empty($health['disability_description']) ? nl2br(e($health['disability_description'])) : '-') ?>
                <?php $screening = (string) ($health['screening_type'] ?? ''); ?>
                <?= $row('ประเภทผู้คัดกรอง', $dash(StudentLookup::SCREENING_TYPES[$screening] ?? null)) ?>
                <?php if ($screening === 'none' || ($screening === '' && $screenerName)): ?>
                  <?= $row('ครูผู้คัดกรอง', $dash($screenerName)) ?>
                <?php endif; ?>
                <?php if ($screening === 'medical_cert' || ($screening === '' && (!empty($health['hospital']) || !empty($health['doctor'])))): ?>
                  <?= $row('แพทย์/โรงพยาบาล', $dash(trim(($health['doctor'] ?? '') . ' ' . ($health['hospital'] ?? ''))) . (!empty($health['examined_at']) ? '<div class="small text-muted">ตรวจเมื่อ ' . $date($health['examined_at']) . '</div>' : '')) ?>
                <?php endif; ?>
              </dl>
              <dl class="row col-12 col-xl-6 mb-0">
                <?php if ($screening === 'disability_card' || ($screening === '' && (!empty($health['card_types']) || !empty($health['card_expires_at'])))): ?>
                  <?php
                  $cardStatus = StudentCompleteness::cardStatus($health);
                  $cardText = !empty($health['card_types'])
                      ? e(implode(', ', array_map(static fn (string $k): string => StudentLookup::CARD_TYPES[$k] ?? $k, $health['card_types'])))
                      : '-';
                  if (!empty($health['card_issued_at']) || !empty($health['card_expires_at'])) {
                      $cardText .= '<div class="small text-muted">ออกบัตร ' . $date($health['card_issued_at'] ?? null)
                          . ' · หมดอายุ ' . $date($health['card_expires_at'] ?? null) . '</div>';
                  }
                  if ($cardStatus !== null && $cardStatus['state'] !== 'valid') {
                      $cardText .= '<div class="small text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>' . e($cardStatus['text']) . '</div>';
                  }
                  ?>
                  <?= $row('บัตรประจำตัวคนพิการ', $cardText) ?>
                <?php endif; ?>
                <?= $row('กลุ่มเลือด', $dash(StudentLookup::BLOOD_GROUPS[$health['blood_group'] ?? ''] ?? null)) ?>
                <?= $row('โรคประจำตัว', !empty($health['chronic_conditions']) ? nl2br(e($health['chronic_conditions'])) : '-') ?>
                <?= $row('ประวัติการแพ้', !empty($health['allergies']) ? '<span class="text-danger">' . nl2br(e($health['allergies'])) . '</span>' : '-') ?>
                <?= $row('ยาที่ใช้ประจำ', !empty($health['medications']) ? nl2br(e($health['medications'])) : '-') ?>
                <?php if (!empty($health['health_note'])): ?><?= $row('หมายเหตุ', nl2br(e($health['health_note']))) ?><?php endif; ?>
              </dl>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <div class="col-12">
    <section class="card shadow-sm mb-3" id="documents">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-paperclip me-1"></i>เอกสารหลักฐาน (เก็บใน Google Shared Drive)</div>
      <?php if ($documents === []): ?>
        <div class="card-body text-muted">ยังไม่มีเอกสาร</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($documents as $doc): ?>
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
              <i class="bi bi-<?= e(FileService::icon((string) $doc['mime_type'])) ?> text-primary"></i>
              <div class="flex-grow-1">
                <a href="<?= e(url('/files/' . $doc['uuid'] . '/download' . (FileService::isInline((string) $doc['mime_type']) ? '?inline=1' : ''))) ?>" target="_blank" rel="noopener"><?= e($doc['title'] ?: $doc['original_name']) ?></a>
                <?php if ($doc['ref_type'] === StudentLookup::REF_TYPE_SENSITIVE): ?><span class="badge text-bg-warning">อ่อนไหว</span><?php endif; ?>
                <div class="small text-muted"><?= e($doc['original_name']) ?> · <?= e(FileService::humanSize((int) $doc['size_bytes'])) ?> · <?= e(thai_date((string) $doc['created_at'])) ?></div>
              </div>
              <?php if ($canUpdate): ?>
                <form method="post" action="<?= e($base . '/documents/' . (int) $doc['id']) ?>" data-confirm="ลบเอกสาร <?= e($doc['original_name']) ?>?">
                  <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
                  <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="ลบ"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if (!$canSensitive): ?>
        <div class="card-body border-top small text-muted py-2"><i class="bi bi-lock me-1"></i>เอกสารทางการแพทย์/ความพิการแสดงเฉพาะผู้มีสิทธิ์ข้อมูลอ่อนไหว</div>
      <?php endif; ?>
      <?php if ($canUpdate): ?>
        <div class="card-footer bg-white">
          <?php if (!$driveReady): ?>
            <div class="small text-muted"><i class="bi bi-cloud-slash me-1"></i>ยังไม่ได้เชื่อมต่อ Google Workspace จึงยังแนบเอกสารไม่ได้</div>
          <?php else: ?>
            <form method="post" action="<?= e($base . '/documents') ?>" enctype="multipart/form-data" class="row g-2 align-items-end" novalidate>
              <?= csrf_field() ?>
              <div class="col-12 col-md-4">
                <label class="form-label small" for="d-type">ประเภทเอกสาร</label>
                <select id="d-type" name="document_type" class="form-select<?= error_for('document_type') ? ' is-invalid' : '' ?>">
                  <option value="">-- เลือก --</option>
                  <?php foreach (StudentLookup::DOCUMENT_TYPES as $k => [$label, $sensitive]): ?>
                    <?php if ($sensitive && !$canSensitive) { continue; } ?>
                    <option value="<?= e($k) ?>"><?= e($label) ?><?= $sensitive ? ' (อ่อนไหว)' : '' ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (error_for('document_type')): ?><div class="invalid-feedback"><?= e(error_for('document_type')) ?></div><?php endif; ?>
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small" for="d-note">หมายเหตุ</label>
                <input type="text" id="d-note" name="note" maxlength="150" class="form-control">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small" for="d-file">ไฟล์ (PDF, JPG, PNG, DOCX ไม่เกิน 20 MB)</label>
                <input type="file" id="d-file" name="file" accept="<?= e(FileService::ACCEPT) ?>" class="form-control<?= error_for('file') ? ' is-invalid' : '' ?>">
                <?php if (error_for('file')): ?><div class="invalid-feedback"><?= e(error_for('file')) ?></div><?php endif; ?>
              </div>
              <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>อัปโหลด</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
