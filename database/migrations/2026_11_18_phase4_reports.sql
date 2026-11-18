-- Phase 4: Dashboard & General Reports
-- สิทธิ์ออกรายงานภาพรวม: ทั้งหน่วยงาน (ผู้บริหาร/ผู้ดูแลระบบ) หรือเฉพาะฝ่ายที่สังกัด (หัวหน้าฝ่าย/หน่วยบริการ)
-- นำเข้าผ่าน phpMyAdmin ได้ (รันซ้ำได้) ไม่มีการเปลี่ยนโครงสร้างตาราง
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.reports.view_all',        'core', 'reports', 'view', 'all',        'ดู/ส่งออกรายงานภาพรวมทั้งหน่วยงาน'),
('core.reports.view_department', 'core', 'reports', 'view', 'department', 'ดู/ส่งออกรายงานภาพรวมเฉพาะฝ่ายที่สังกัด')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.reports.view_all'
WHERE r.code IN ('super_admin', 'admin', 'director', 'deputy_director');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.reports.view_department'
WHERE r.code IN ('department_head', 'service_unit_head');

COMMIT;
