<?php /** @var array $user */ /** @var array|null $signature */ ?>
<h1 class="h4 mb-3">โปรไฟล์ของฉัน</h1>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-info" type="button">ข้อมูลส่วนตัว</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-password" type="button">เปลี่ยนรหัสผ่าน</button></li>
  <li class="nav-item"><button class="nav-link" id="signature-tab" data-bs-toggle="tab" data-bs-target="#tab-signature" type="button">ลายเซ็น</button></li>
</ul>

<div class="tab-content">

  <!-- ===== แท็บ 1: ข้อมูลส่วนตัว + รูป ===== -->
  <div class="tab-pane fade show active" id="tab-info">
    <form method="post" action="<?= e(url('/profile')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="row g-3">

        <div class="col-12 col-md-4">
          <div class="card shadow-sm text-center h-100">
            <div class="card-body">
              <?php if (!empty($user['avatar'])): ?>
                <img id="avatarPreview" src="<?= e(url('uploads/avatars/' . $user['avatar'])) ?>"
                     class="rounded-circle shadow-sm mb-3" width="120" height="120" style="object-fit:cover;" alt="Avatar">
              <?php else: ?>
                <img id="avatarPreview" src="" class="rounded-circle shadow-sm mb-3 d-none" width="120" height="120" style="object-fit:cover;" alt="Avatar">
                <div id="avatarFallback" class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center shadow-sm mb-3"
                     style="width:120px;height:120px;font-size:3rem;">
                  <?= e(mb_substr($user['first_name'] ?? 'U', 0, 1)) ?>
                </div>
              <?php endif; ?>

              <input type="file" name="avatar" id="avatarInput" class="form-control form-control-sm"
                     accept="image/jpeg,image/png,image/webp">
              <div class="form-text">JPG, PNG, WebP ไม่เกิน 2MB</div>

              <?php if (!empty($user['avatar'])): ?>
                <div class="form-check mt-2 text-start d-inline-block">
                  <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="removeAvatar">
                  <label class="form-check-label small" for="removeAvatar">ลบรูปโปรไฟล์</label>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-8">
          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">ข้อมูลที่แก้ไขได้</div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label class="form-label fw-semibold">คำนำหน้า</label>
                  <input type="text" name="title_name" class="form-control" value="<?= e($user['title_name'] ?? '') ?>" maxlength="50">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">ชื่อ</label>
                  <input type="text" name="first_name" class="form-control" value="<?= e($user['first_name'] ?? '') ?>" required maxlength="100">
                </div>
                <div class="col-md-5">
                  <label class="form-label fw-semibold">นามสกุล</label>
                  <input type="text" name="last_name" class="form-control" value="<?= e($user['last_name'] ?? '') ?>" required maxlength="100">
                </div>
                <div class="col-md-7">
                  <label class="form-label fw-semibold">อีเมล</label>
                  <input type="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-5">
                  <label class="form-label fw-semibold">เบอร์โทรศัพท์</label>
                  <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" maxlength="30">
                </div>
              </div>
            </div>
          </div>

          <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">ข้อมูลที่แก้ไขไม่ได้ (ติดต่อผู้ดูแลระบบ)</div>
            <div class="card-body small">
              <div class="row mb-1"><div class="col-4 fw-semibold">ชื่อผู้ใช้</div><div class="col-8"><?= e($user['username'] ?? '-') ?></div></div>
              <div class="row mb-1"><div class="col-4 fw-semibold">ฝ่าย/งาน</div><div class="col-8"><?= e($user['department_name'] ?? '-') ?></div></div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= e(url('/dashboard')) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึก</button>
          </div>
        </div>
      </div>
    </form>
  </div>
           
  <!-- ===== แท็บ 2: เปลี่ยนรหัสผ่าน ===== -->
  <div class="tab-pane fade" id="tab-password">
    <div class="card shadow-sm" style="max-width:520px;">
      <div class="card-body">
        <h5 class="card-title mb-3"><i class="bi bi-shield-lock me-1"></i> เปลี่ยนรหัสผ่าน</h5>
        <form method="post" action="<?= e(url('/profile/password')) ?>">
          <?= csrf_field() ?>
          
          <!-- รหัสผ่านปัจจุบัน -->
          <div class="mb-3">
            <label class="form-label fw-semibold">รหัสผ่านปัจจุบัน</label>
            <div class="input-group">
              <input type="password" name="current_password" id="current_password" class="form-control" required>
              <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <!-- รหัสผ่านใหม่ -->
          <div class="mb-3">
            <label class="form-label fw-semibold">รหัสผ่านใหม่</label>
            <div class="input-group">
              <input type="password" name="new_password" id="new_password" class="form-control" required>
              <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <!-- ยืนยันรหัสผ่านใหม่ -->
          <div class="mb-3">
            <label class="form-label fw-semibold">ยืนยันรหัสผ่านใหม่</label>
            <div class="input-group">
              <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" required>
              <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password_confirmation">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <!-- ข้อความคำแนะนำนโยบายรหัสผ่าน -->
          <ul class="text-muted small ps-3 mb-4">
            <li>อย่างน้อย 8 ตัวอักษร มีพิมพ์ใหญ่ พิมพ์เล็ก ตัวเลข และอักขระพิเศษ</li>
            <li>ห้ามใช้เลขประจำตัวประชาชน และห้ามซ้ำกับรหัสผ่านเดิม</li>
          </ul>

          <button type="submit" class="btn btn-primary w-100">บันทึกรหัสผ่านใหม่</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ===== แท็บ 3: ลายเซ็นสำหรับลงนามเอกสาร ===== -->
  <div class="tab-pane fade" id="tab-signature">
    <div class="card shadow-sm" style="max-width:720px;">
      <div class="card-body">
        <h5 class="card-title mb-1"><i class="bi bi-pen me-1"></i> ลายเซ็นสำหรับลงนามเอกสาร</h5>
        <p class="small text-body-secondary">ใช้ประทับในเอกสารงานวิจัยและสื่อนวัตกรรมเมื่อคุณลงนาม เก็บไว้นอกพื้นที่สาธารณะ ผู้ใช้อื่นดาวน์โหลดภาพนี้ไม่ได้</p>
        <?php foreach (['signature', 'signature_file'] as $k): ?>
          <?php if ($err = error_for($k)): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endif; ?>
        <?php endforeach; ?>
        <div class="mb-3">
          <div class="small fw-semibold mb-1">ลายเซ็นปัจจุบัน</div>
          <?php if ($signature !== null): ?>
            <img src="<?= e(url('/profile/signature/image?v=' . substr((string) $signature['checksum_sha256'], 0, 12))) ?>" alt="ภาพลายเซ็นปัจจุบัน" class="signature-preview">
            <div class="small text-body-secondary mt-1"><?= $signature['source'] === 'draw' ? 'วาดบนจอ' : 'อัปโหลดภาพ' ?> · บันทึกเมื่อ <?= e(thai_date((string) $signature['updated_at'])) ?></div>
          <?php else: ?>
            <div class="alert alert-warning py-2 mb-0 small"><i class="bi bi-exclamation-triangle me-1"></i>ยังไม่มีลายเซ็น ต้องบันทึกก่อนจึงจะลงนามเอกสารได้</div>
          <?php endif; ?>
        </div>

        <form method="post" action="<?= e(url('/profile/signature')) ?>" class="border rounded-3 p-3 mb-3" data-signature-form>
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="draw">
          <input type="hidden" name="signature" value="" data-signature-value>
          <div class="fw-semibold mb-2">วาดลายเซ็น</div>
          <canvas class="signature-pad" data-signature-pad aria-label="พื้นที่วาดลายเซ็น ใช้เมาส์ นิ้ว หรือปากกา"></canvas>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-signature-clear><i class="bi bi-eraser me-1"></i>ล้าง</button>
            <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-save me-1"></i>บันทึกลายเซ็นที่วาด</button>
          </div>
        </form>

        <form method="post" action="<?= e(url('/profile/signature')) ?>" enctype="multipart/form-data" class="border rounded-3 p-3 mb-3">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="upload">
          <label class="form-label fw-semibold" for="signature-file">หรืออัปโหลดภาพลายเซ็น</label>
          <input type="file" id="signature-file" name="signature_file" required accept="image/png,image/jpeg,image/webp" class="form-control">
          <div class="form-text">PNG, JPG หรือ WEBP ไม่เกิน 2 MB · เซ็นด้วยปากกาสีเข้มบนกระดาษขาว ระบบทำพื้นหลังสีขาวให้โปร่งใส</div>
          <button type="submit" class="btn btn-outline-primary btn-sm mt-2"><i class="bi bi-upload me-1"></i>อัปโหลดลายเซ็น</button>
        </form>

        <?php if ($signature !== null): ?>
          <form method="post" action="<?= e(url('/profile/signature')) ?>" data-confirm="ลบภาพลายเซ็น? (ลายเซ็นที่ประทับในเอกสารแล้วไม่เปลี่ยนแปลง)">
            <?= csrf_field() ?><input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="btn btn-link text-danger px-0 btn-sm"><i class="bi bi-trash me-1"></i>ลบภาพลายเซ็น</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="<?= e(asset('js/signature-pad.js')) ?>" defer></script>
<script>
document.getElementById('avatarInput')?.addEventListener('change', function () {
  const file = this.files[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) { alert('ไฟล์ต้องไม่เกิน 2MB'); this.value = ''; return; }
  const img = document.getElementById('avatarPreview');
  img.src = URL.createObjectURL(file);
  img.classList.remove('d-none');
  document.getElementById('avatarFallback')?.classList.add('d-none');
});
</script>