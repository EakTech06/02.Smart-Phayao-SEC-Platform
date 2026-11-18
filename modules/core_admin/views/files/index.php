<?php

use Modules\CoreAdmin\Services\FileService;

/** @var array $files */
/** @var array{q: string, scope: string} $filters */
/** @var bool $driveReady */

$visibilityBadge = ['private' => 'secondary', 'department' => 'info', 'public' => 'success'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0">ไฟล์/เอกสาร</h1>
  <span class="small text-muted"><i class="bi bi-google me-1"></i>จัดเก็บบน Google Shared Drive ขององค์กร</span>
</div>

<?php if (!$driveReady): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>ยังไม่ได้เชื่อมต่อ Google Workspace จึงยังอัปโหลดไฟล์ไม่ได้
    <?php if (can('core.settings.manage')): ?>— ดูวิธีตั้งค่าที่ <code>tools/google-apps-script/README.md</code><?php else: ?>กรุณาแจ้งผู้ดูแลระบบ<?php endif; ?>
  </div>
<?php endif; ?>

<?php if (can('core.files.upload_own')): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-cloud-arrow-up me-1"></i>อัปโหลดไฟล์</div>
    <div class="card-body">
      <form method="post" action="<?= e(url('/files')) ?>" enctype="multipart/form-data" class="row g-2 align-items-end" novalidate>
        <?= csrf_field() ?>
        <div class="col-12 col-lg-4">
          <label class="form-label small mb-1" for="f-file">ไฟล์ <span class="text-danger">*</span></label>
          <input type="file" id="f-file" name="file" class="form-control<?= error_for('file') ? ' is-invalid' : '' ?>" accept="<?= e(FileService::ACCEPT) ?>" required <?= $driveReady ? '' : 'disabled' ?>>
          <?php if (error_for('file')): ?><div class="invalid-feedback"><?= e(error_for('file')) ?></div><?php endif; ?>
          <div class="form-text">pdf, jpg, png, docx, xlsx ไม่เกิน 20 MB</div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label small mb-1" for="f-title">ชื่อเอกสาร</label>
          <input type="text" id="f-title" name="title" class="form-control" maxlength="255" value="<?= e(old('title')) ?>" placeholder="ไม่ระบุ = ใช้ชื่อไฟล์">
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <label class="form-label small mb-1" for="f-visibility">ใครเห็นได้บ้าง</label>
          <select id="f-visibility" name="visibility" class="form-select<?= error_for('visibility') ? ' is-invalid' : '' ?>">
            <?php foreach (FileService::VISIBILITIES as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= old('visibility', 'private') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (error_for('visibility')): ?><div class="invalid-feedback"><?= e(error_for('visibility')) ?></div><?php endif; ?>
        </div>
        <div class="col-12 col-lg-2">
          <button type="submit" class="btn btn-primary w-100" <?= $driveReady ? '' : 'disabled' ?>><i class="bi bi-upload me-1"></i>อัปโหลด</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" action="<?= e(url('/files')) ?>" class="row g-2 align-items-end">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="scope">แสดง</label>
        <select id="scope" name="scope" class="form-select">
          <option value="all" <?= $filters['scope'] === 'all' ? 'selected' : '' ?>>ทุกไฟล์ที่ฉันเห็นได้</option>
          <option value="mine" <?= $filters['scope'] === 'mine' ? 'selected' : '' ?>>ไฟล์ของฉัน</option>
        </select>
      </div>
      <div class="col-6 col-md-5">
        <label class="form-label small mb-1" for="q">ค้นหา</label>
        <input type="search" id="q" name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="ชื่อไฟล์ / ชื่อเอกสาร">
      </div>
      <div class="col-12 col-md-4 col-lg-2">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>กรอง</button>
      </div>
    </form>
  </div>
</div>

<?php if ($files === []): ?>
  <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
    <i class="bi bi-folder2-open fs-1 d-block mb-2"></i>ยังไม่มีไฟล์
  </div></div>
<?php else: ?>
  <div class="list-group shadow-sm">
    <?php foreach ($files as $f): ?>
      <?php $download = url('/files/' . $f['uuid'] . '/download'); ?>
      <div class="list-group-item d-flex flex-wrap flex-md-nowrap align-items-center gap-2 gap-md-3 py-3">
        <i class="bi bi-<?= e(FileService::icon((string) $f['mime_type'])) ?> fs-3 flex-shrink-0"></i>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-semibold text-break"><?= e($f['title'] ?? $f['original_name']) ?></div>
          <div class="small text-muted text-break">
            <?php if ($f['title'] !== null): ?><?= e($f['original_name']) ?> · <?php endif; ?>
            <?= e(FileService::humanSize((int) $f['size_bytes'])) ?> · <?= e(trim((string) $f['owner_name']) ?: '-') ?> · <?= e(thai_date($f['created_at'])) ?>
          </div>
          <span class="badge text-bg-<?= e($visibilityBadge[$f['visibility']] ?? 'secondary') ?>">
            <?= e(FileService::VISIBILITIES[$f['visibility']] ?? $f['visibility']) ?><?= $f['visibility'] === 'department' && $f['department_name'] ? ': ' . e($f['department_name']) : '' ?>
          </span>
        </div>
        <div class="d-flex gap-1 ms-auto">
          <?php if (FileService::isInline((string) $f['mime_type'])): ?>
            <a href="<?= e($download . '?inline=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="เปิดดู"><i class="bi bi-eye"></i></a>
          <?php endif; ?>
          <a href="<?= e($download) ?>" class="btn btn-sm btn-outline-primary" title="ดาวน์โหลด"><i class="bi bi-download"></i></a>
          <?php if (FileService::canDelete($f)): ?>
            <form method="post" action="<?= e(url('/files/' . $f['uuid'])) ?>" data-confirm="ลบไฟล์ <?= e($f['original_name']) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="small text-muted text-center mt-2"><?= count($files) ?> ไฟล์</div>
<?php endif; ?>
