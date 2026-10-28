   <?php
   $avatarFile = $user['avatar'] ?? $avatar ?? null;
   $avatarPath = $avatarFile ? '/uploads/avatars/' . e($avatarFile) : null;
   $initials   = mb_substr($user['first_name'] ?? $user['name'] ?? 'U', 0, 1);
   ?>

   <div class="card shadow-sm mb-4">
     <div class="card-body d-flex align-items-center">
       <div class="me-3">
         <?php if (!empty($avatarPath)): ?>
           <img src="<?= e($avatarPath) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 64px; height: 64px; object-fit: cover;">
         <?php else: ?>
           <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm fw-bold fs-4" style="width: 64px; height: 64px;">
             <?= e($initials) ?>
           </div>
         <?php endif; ?>
       </div>
       <div>
         <h4 class="mb-1 fw-bold">
           <?= e(trim(($user['title_name'] ?? '') . ' ' . ($user['first_name'] ?? $user['name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
         </h4>
         <p class="text-muted mb-0"><?= e($user['position'] ?? 'ผู้ใช้งานระบบ') ?></p>
       </div>
     </div>
   </div>