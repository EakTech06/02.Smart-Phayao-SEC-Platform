-- ผู้ใช้งานทั่วไปแก้ไขข้อมูลบุคลากรของตนเองได้ (เฉพาะข้อมูลส่วนบุคคล/วุฒิการศึกษา ดู PersonnelService::SELF_EDIT_FIELDS)
-- รันซ้ำได้
SET NAMES utf8mb4;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.employees.update_own', 'core', 'employees', 'update', 'own', 'แก้ไขข้อมูลส่วนบุคคลของตนเอง')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.employees.update_own'
WHERE r.code IN ('super_admin', 'admin', 'director', 'deputy_director', 'department_head', 'service_unit_head', 'teacher', 'staff');
