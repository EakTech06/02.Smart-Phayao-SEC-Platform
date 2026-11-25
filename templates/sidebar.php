<?php

use Core\Auth;
use Core\Rbac;

$currentPath = current_path();
$academicOn = in_array('academic', (array) config('app.modules', []), true);
// สิทธิ์ระดับ own (เห็นเฉพาะข้อมูลตนเอง) ไม่ถือเป็นเมนูของฝ่าย: แสดงใต้ "บัญชีของฉัน" แทน
$beyondOwn = static fn (string $resource): bool => !in_array(Rbac::scope($resource, 'read'), [null, 'own'], true);
$myEmployeeId = (int) (Auth::user()['employee_id'] ?? 0);

/*
 * เมนูจัดเป็นหมวดตามโครงสร้างการบริหาร 4 ฝ่าย + งานหน่วยบริการ
 * หมวดที่ยังไม่มีเมนู (หรือผู้ใช้ไม่มีสิทธิ์เมนูใดเลย) จะถูกซ่อนอัตโนมัติ
 * โมดูลใหม่เพิ่มรายการลงในหมวดของฝ่ายนั้น ๆ ได้ทันที
 * label = null: หมวดไม่มีหัวข้อ (เมนูหลักด้านบน)
 */
$groups = [
    'main' => ['label' => null, 'icon' => null, 'items' => [
        ['url' => '/dashboard', 'icon' => 'speedometer2', 'label' => 'แดชบอร์ด', 'show' => can('core.dashboard.view')],
        ['url' => '/notifications', 'icon' => 'bell', 'label' => 'การแจ้งเตือน', 'show' => true],
        ['url' => '/reports/overview', 'icon' => 'file-earmark-bar-graph', 'label' => 'รายงานภาพรวม', 'show' => Rbac::canAny(['core.reports.view_all', 'core.reports.view_department'])],
    ]],
    'shared' => ['label' => 'บริการกลาง', 'icon' => 'grid', 'items' => [
        ['url' => '/calendar', 'icon' => 'calendar3', 'label' => 'ปฏิทินกลาง', 'show' => can('core.calendar.read_all')],
        ['url' => '/files', 'icon' => 'folder2-open', 'label' => 'ไฟล์/เอกสาร', 'show' => can('core.files.read_own')],
        ['url' => '/pdpa', 'icon' => 'shield-lock', 'label' => 'คำขอใช้สิทธิ์ PDPA', 'show' => can('core.pdpa.request_own')],
    ]],
    'academic' => ['label' => 'ฝ่ายบริหารวิชาการ', 'icon' => 'mortarboard', 'items' => [
        ['url' => '/academic/students', 'icon' => 'person-lines-fill', 'label' => 'ทะเบียนนักเรียน', 'show' => $academicOn && Rbac::scope('academic.students', 'read') !== null],
    ]],
    'budget' => ['label' => 'ฝ่ายบริหารงบประมาณ', 'icon' => 'cash-coin', 'items' => []],
    'personnel' => ['label' => 'ฝ่ายบริหารงานบุคคล', 'icon' => 'person-vcard', 'items' => [
        ['url' => '/personnel', 'icon' => 'people', 'label' => 'ข้อมูลบุคลากร', 'show' => $beyondOwn('core.employees')],
    ]],
    'general' => ['label' => 'ฝ่ายบริหารทั่วไป', 'icon' => 'building', 'items' => []],
    'service_unit' => ['label' => 'งานหน่วยบริการ', 'icon' => 'geo-alt', 'items' => []],
    'master' => ['label' => 'ข้อมูลพื้นฐาน', 'icon' => 'database', 'items' => [
        ['url' => '/service-rooms', 'icon' => 'door-open', 'label' => 'ข้อมูลห้องบริการ', 'show' => can('core.service_rooms.read_all')],
    ]],
    'system' => ['label' => 'ผู้ดูแลระบบ', 'icon' => 'sliders', 'items' => [
        ['url' => '/users', 'icon' => 'person-gear', 'label' => 'ผู้ใช้งาน', 'show' => $beyondOwn('core.users')],
        ['url' => '/roles', 'icon' => 'shield-shaded', 'label' => 'จัดการสิทธิ์บทบาท', 'show' => can('core.roles.read_all')],
        ['url' => '/audit-logs', 'icon' => 'clock-history', 'label' => 'ประวัติการใช้งาน', 'show' => can('core.audit.read_all')],
        ['url' => '/sessions', 'icon' => 'display', 'label' => 'จัดการเซสชัน', 'show' => can('core.sessions.read_all')],
        ['url' => '/settings', 'icon' => 'gear', 'label' => 'ตั้งค่าระบบ', 'show' => can('core.settings.manage')],
    ]],
    'account' => ['label' => 'บัญชีของฉัน', 'icon' => 'person-circle', 'items' => [
        ['url' => '/profile', 'icon' => 'person-badge', 'label' => 'โปรไฟล์ของฉัน', 'show' => true],
        ['url' => '/personnel/' . $myEmployeeId, 'icon' => 'person-vcard', 'label' => 'ข้อมูลบุคลากรของฉัน',
            'show' => $myEmployeeId > 0 && Rbac::scope('core.employees', 'read') !== null],
        ['url' => '/password/change', 'icon' => 'key', 'label' => 'ความปลอดภัยบัญชี', 'show' => true],
    ]],
];

// เมนูที่ active = url ที่ตรงกับ path ยาวที่สุด (เช่น /academic/students/import ไม่ทำให้ทะเบียนนักเรียน active ด้วย)
$activeUrl = null;
foreach ($groups as $g) {
    foreach ($g['items'] as $item) {
        if ($item['show'] && ($currentPath === $item['url'] || str_starts_with($currentPath, $item['url'] . '/'))
            && strlen($item['url']) > strlen((string) $activeUrl)) {
            $activeUrl = $item['url'];
        }
    }
}
?>
<nav class="col-lg-3 col-xl-2 offcanvas-lg offcanvas-start bg-white border-end sidebar p-0" tabindex="-1" id="sidebar" aria-label="เมนูหลัก">
  <div class="offcanvas-header d-lg-none border-bottom">
    <h5 class="offcanvas-title">เมนู</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="ปิด"></button>
  </div>
  <div class="offcanvas-body d-block p-2">
    <?php foreach ($groups as $key => $group): ?>
      <?php
      $items = array_values(array_filter($group['items'], static fn (array $i): bool => $i['show']));
      if ($items === []) {
          continue;
      }
      $hasActive = in_array($activeUrl, array_column($items, 'url'), true);
      $id = 'menu-' . $key;
      ?>
      <?php if ($group['label'] !== null): ?>
        <button type="button" class="sidebar-group btn w-100 d-flex align-items-center<?= $hasActive ? ' has-active' : '' ?>"
                data-bs-toggle="collapse" data-bs-target="#<?= e($id) ?>" aria-expanded="true" aria-controls="<?= e($id) ?>"
                data-sidebar-group="<?= e($key) ?>"<?= $hasActive ? ' data-keep-open' : '' ?>>
          <i class="bi bi-<?= e($group['icon']) ?> me-2"></i><span class="flex-grow-1 text-start"><?= e($group['label']) ?></span>
          <i class="bi bi-chevron-down sidebar-chevron"></i>
        </button>
      <?php endif; ?>
      <ul class="nav nav-pills flex-column gap-1 mb-2<?= $group['label'] !== null ? ' collapse show' : '' ?>" id="<?= e($id) ?>">
        <?php foreach ($items as $item): ?>
          <?php $active = $item['url'] === $activeUrl; ?>
          <li class="nav-item">
            <a class="nav-link <?= $active ? 'active' : 'link-body-emphasis' ?><?= $group['label'] !== null ? ' ps-4' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
              <i class="bi bi-<?= e($item['icon']) ?> me-2"></i><?= e($item['label']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </div>
</nav>
