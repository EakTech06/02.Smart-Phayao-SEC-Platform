-- Smart Phayao SEC Platform : Seed (Phase 1)  รันซ้ำได้ (idempotent)
SET NAMES utf8mb4;
START TRANSACTION;

-- ---------- โครงสร้างองค์กร ----------
INSERT INTO departments (id, parent_id, code, name_th, name_en, type, sort_order) VALUES
(1, NULL, 'DIRECTOR',     'สำนักงานผู้อำนวยการ', 'Director Office',   'administration', 1),
(2, 1,    'ACADEMIC',     'ฝ่ายบริหารวิชาการ',    'Academic Affairs',  'division',       2),
(3, 1,    'BUDGET',       'ฝ่ายบริหารงบประมาณ',   'Budget Affairs',    'division',       3),
(4, 1,    'PERSONNEL',    'ฝ่ายบริหารงานบุคคล',   'Personnel Affairs', 'division',       4),
(5, 1,    'GENERAL',      'ฝ่ายบริหารทั่วไป',      'General Affairs',   'division',       5),
(6, 1,    'SERVICE_UNIT', 'งานหน่วยบริการ',        'Service Units',     'service_unit',   6)
ON DUPLICATE KEY UPDATE name_th = VALUES(name_th), type = VALUES(type);

-- ---------- Roles ----------
INSERT INTO roles (id, code, name_th, description, level, is_system) VALUES
(1, 'super_admin',       'ผู้ดูแลระบบสูงสุด',   'สิทธิ์ทั้งหมด (บังคับ 2FA)',      1, 1),
(2, 'admin',             'ผู้ดูแลระบบ',         'จัดการผู้ใช้และตั้งค่าระบบ',       2, 1),
(3, 'director',          'ผู้อำนวยการ',          'ดูภาพรวมทั้งองค์กร',              3, 1),
(4, 'deputy_director',   'รองผู้อำนวยการ',       'กำกับดูแลฝ่ายที่รับผิดชอบ',        4, 1),
(5, 'department_head',   'หัวหน้าฝ่าย/งาน',      'ดูแลบุคลากรภายในฝ่าย',            5, 1),
(6, 'service_unit_head', 'หัวหน้าหน่วยบริการ',   'ดูแลบุคลากรในหน่วยบริการ',         6, 1),
(7, 'teacher',           'ครู',                  'ผู้ใช้งานทั่วไป (ครู)',             7, 1),
(8, 'staff',             'บุคลากร/เจ้าหน้าที่',  'ผู้ใช้งานทั่วไป',                  8, 1)
ON DUPLICATE KEY UPDATE name_th = VALUES(name_th), description = VALUES(description);

-- ---------- Permissions (module.resource.action_scope) ----------
INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.dashboard.view',            'core', 'dashboard',     'view',   'none',         'ดูแดชบอร์ด'),
('core.users.read_own',            'core', 'users',         'read',   'own',          'ดูบัญชีของตนเอง'),
('core.users.read_department',     'core', 'users',         'read',   'department',   'ดูบัญชีผู้ใช้ในฝ่าย'),
('core.users.read_service_unit',   'core', 'users',         'read',   'service_unit', 'ดูบัญชีผู้ใช้ในหน่วยบริการ'),
('core.users.read_all',            'core', 'users',         'read',   'all',          'ดูบัญชีผู้ใช้ทั้งหมด'),
('core.users.create_all',          'core', 'users',         'create', 'all',          'สร้างบัญชีผู้ใช้'),
('core.users.update_own',          'core', 'users',         'update', 'own',          'แก้ไขบัญชีตนเอง'),
('core.users.update_department',   'core', 'users',         'update', 'department',   'แก้ไขบัญชีผู้ใช้ในฝ่าย'),
('core.users.update_all',          'core', 'users',         'update', 'all',          'แก้ไขบัญชีผู้ใช้ทั้งหมด'),
('core.users.delete_all',          'core', 'users',         'delete', 'all',          'ระงับ/ลบบัญชีผู้ใช้'),
('core.employees.read_own',        'core', 'employees',     'read',   'own',          'ดูข้อมูลบุคลากรของตนเอง'),
('core.employees.read_department', 'core', 'employees',     'read',   'department',   'ดูข้อมูลบุคลากรในฝ่าย'),
('core.employees.read_service_unit','core','employees',     'read',   'service_unit', 'ดูข้อมูลบุคลากรในหน่วยบริการ'),
('core.employees.read_all',        'core', 'employees',     'read',   'all',          'ดูข้อมูลบุคลากรทั้งหมด'),
('core.employees.update_all',      'core', 'employees',     'update', 'all',          'แก้ไขข้อมูลบุคลากร'),
('core.roles.read_all',            'core', 'roles',         'read',   'all',          'ดูบทบาท'),
('core.roles.update_all',          'core', 'roles',         'update', 'all',          'จัดการบทบาท'),
('core.permissions.assign_all',    'core', 'permissions',   'assign', 'all',          'กำหนดสิทธิ์รายบุคคล'),
('core.departments.read_all',      'core', 'departments',   'read',   'all',          'ดูโครงสร้างองค์กร'),
('core.departments.update_all',    'core', 'departments',   'update', 'all',          'จัดการโครงสร้างองค์กร'),
('core.audit_logs.read_all',       'core', 'audit_logs',    'read',   'all',          'ดู Audit Log'),
('core.settings.read_all',         'core', 'settings',      'read',   'all',          'ดูการตั้งค่าระบบ'),
('core.settings.update_all',       'core', 'settings',      'update', 'all',          'แก้ไขการตั้งค่าระบบ'),
('core.sessions.revoke_all',       'core', 'sessions',      'revoke', 'all',          'บังคับออกจากระบบ'),
('core.files.read_own',            'core', 'files',         'read',   'own',          'ดูไฟล์ของตนเอง'),
('core.files.read_department',     'core', 'files',         'read',   'department',   'ดูไฟล์ในฝ่าย'),
('core.files.read_all',            'core', 'files',         'read',   'all',          'ดูไฟล์ทั้งหมด'),
('core.files.upload_own',          'core', 'files',         'upload', 'own',          'อัปโหลดไฟล์'),
('core.notifications.read_own',    'core', 'notifications', 'read',   'own',          'ดูการแจ้งเตือนของตนเอง'),
('core.calendar.read_all',         'core', 'calendar',      'read',   'all',          'ดูปฏิทินกลาง'),
('core.calendar.create_department','core', 'calendar',      'create', 'department',   'สร้างกิจกรรมระดับฝ่าย'),
('core.calendar.create_all',       'core', 'calendar',      'create', 'all',          'สร้างกิจกรรมระดับองค์กร')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ---------- Role → Permission ----------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'admin' AND p.code NOT IN ('core.roles.update_all', 'core.permissions.assign_all');

-- สิทธิ์พื้นฐานของทุกคน
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.dashboard.view','core.users.read_own','core.users.update_own','core.employees.read_own',
  'core.files.read_own','core.files.upload_own','core.notifications.read_own','core.calendar.read_all')
WHERE r.code IN ('director','deputy_director','department_head','service_unit_head','teacher','staff');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.users.read_all','core.employees.read_all','core.departments.read_all',
  'core.files.read_all','core.calendar.create_all')
WHERE r.code IN ('director','deputy_director');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.audit_logs.read_all'
WHERE r.code = 'director';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.users.read_department','core.employees.read_department','core.departments.read_all',
  'core.files.read_department','core.calendar.create_department')
WHERE r.code = 'department_head';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.users.read_service_unit','core.employees.read_service_unit','core.calendar.create_department')
WHERE r.code = 'service_unit_head';

-- ---------- Super Admin ----------
INSERT INTO employees (id, employee_code, title_name, first_name, last_name, position, employment_type, department_id)
VALUES (1, 'SYS-0001', '', 'ผู้ดูแลระบบ', 'สูงสุด', 'ผู้ดูแลระบบ', 'other', 1)
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name);

-- password_hash เป็น placeholder ซึ่ง login ไม่ได้จนกว่าจะรัน:
--   php tools/set_password.php superadmin "SuperAdmin@123"
-- (tool จะพิมพ์ Hash ออกมา สามารถนำมาแทนค่า placeholder ด้านล่างเพื่อฝังใน seed ได้)
INSERT INTO users (id, employee_id, username, email, password_hash, status, must_change_password)
VALUES (1, 1, 'superadmin', 'superadmin@smartsec.local', '!SET_WITH_TOOLS_SET_PASSWORD!', 'active', 1)
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (1, 1);
INSERT IGNORE INTO user_departments (user_id, department_id, is_primary) VALUES (1, 1, 1);

-- ---------- DEMO users สำหรับทดสอบ RBAC/IDOR (ลบก่อนขึ้น Production) ----------
INSERT INTO employees (id, employee_code, title_name, first_name, last_name, position, employment_type, department_id) VALUES
(2, 'T-0001', 'นาย', 'หัวหน้า', 'วิชาการ', 'ครู ชำนาญการพิเศษ', 'civil_servant', 2),
(3, 'T-0002', 'นางสาว', 'ครูทดสอบ', 'ใจดี', 'ครู', 'civil_servant', 2)
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name);

INSERT INTO users (id, employee_id, username, email, password_hash, status, must_change_password) VALUES
(2, 2, 'head01',    NULL, '!SET_WITH_TOOLS_SET_PASSWORD!', 'active', 1),
(3, 3, 'teacher01', NULL, '!SET_WITH_TOOLS_SET_PASSWORD!', 'active', 1)
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (2, 5), (3, 7);
INSERT IGNORE INTO user_departments (user_id, department_id, is_primary, is_head) VALUES (2, 2, 1, 1), (3, 2, 1, 0);

-- ---------- Settings ----------
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description, is_public) VALUES
('general',  'org.name',         'ศูนย์การศึกษาพิเศษ ประจำจังหวัดพะเยา', 'string', 'ชื่อหน่วยงาน', 1),
('general',  'org.short_name',   'Smart Phayao SEC',                     'string', 'ชื่อย่อ', 1),
('general',  'maintenance_mode', '0',                                    'bool',   'โหมดปิดปรับปรุง', 0),
('security', 'force_2fa_roles',  '["super_admin"]',                      'json',   'Role ที่บังคับ 2FA', 0)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ---------- ปีการศึกษา / ปีงบประมาณ / รอบประเมิน ----------
INSERT INTO academic_years (id, year_be, name, start_date, end_date, is_current) VALUES
(1, 2568, 'ปีการศึกษา 2568', '2025-05-16', '2026-05-15', 0),
(2, 2569, 'ปีการศึกษา 2569', '2026-05-16', '2027-05-15', 1)
ON DUPLICATE KEY UPDATE is_current = VALUES(is_current);

INSERT INTO fiscal_years (id, year_be, name, start_date, end_date, is_current) VALUES
(1, 2569, 'ปีงบประมาณ 2569', '2025-10-01', '2026-09-30', 0),
(2, 2570, 'ปีงบประมาณ 2570', '2026-10-01', '2027-09-30', 1)
ON DUPLICATE KEY UPDATE is_current = VALUES(is_current);

INSERT INTO evaluation_periods (academic_year_id, fiscal_year_id, code, name, round, start_date, end_date, is_current) VALUES
(2, 2, 'FY2570-R1', 'รอบที่ 1 (1 ต.ค. 2569 - 31 มี.ค. 2570)', 1, '2026-10-01', '2027-03-31', 1),
(2, 2, 'FY2570-R2', 'รอบที่ 2 (1 เม.ย. 2570 - 30 ก.ย. 2570)', 2, '2027-04-01', '2027-09-30', 0)
ON DUPLICATE KEY UPDATE is_current = VALUES(is_current);

COMMIT;