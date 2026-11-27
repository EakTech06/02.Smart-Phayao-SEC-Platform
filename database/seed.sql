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
(8, 'staff',             'บุคลากร/เจ้าหน้าที่',  'ผู้ใช้งานทั่วไป',                  8, 1),
(9, 'student_registrar', 'เจ้าหน้าที่งานทะเบียนนักเรียน', 'ครูที่ได้รับมอบหมายงานทะเบียนนักเรียน: เพิ่ม ลบ แก้ไขข้อมูลนักเรียนได้ทั้งหมด', 7, 1)
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
('core.employees.update_own',      'core', 'employees',     'update', 'own',          'แก้ไขข้อมูลส่วนบุคคลของตนเอง'),
('core.employees.create_all',      'core', 'employees',     'create', 'all',          'เพิ่มข้อมูลบุคลากร'),
('core.employees.update_department','core','employees',     'update', 'department',   'แก้ไขข้อมูลบุคลากรในฝ่าย'),
('core.employees.delete_all',      'core', 'employees',     'delete', 'all',          'ลบข้อมูลบุคลากร'),
('core.employees.import_all',      'core', 'employees',     'import', 'all',          'นำเข้าข้อมูลบุคลากรจากไฟล์ CSV'),
('core.employees.read_sensitive_all','core','employees',    'read_sensitive', 'all',  'ดูเลขประจำตัวประชาชนเต็ม (บันทึก Audit)'),
('core.roles.read_all',            'core', 'roles',         'read',   'all',          'ดูบทบาท'),
('core.roles.update_all',          'core', 'roles',         'update', 'all',          'จัดการบทบาท'),
('core.permissions.assign_all',    'core', 'permissions',   'assign', 'all',          'กำหนดสิทธิ์รายบุคคล'),
('core.departments.read_all',      'core', 'departments',   'read',   'all',          'ดูโครงสร้างองค์กร'),
('core.departments.update_all',    'core', 'departments',   'update', 'all',          'จัดการโครงสร้างองค์กร'),
('core.audit.read_all',            'core', 'audit',         'read',   'all',          'ดูประวัติการใช้งานระบบทั้งหมด'),
('core.settings.manage',           'core', 'settings',      'manage', 'none',         'จัดการตั้งค่าระบบ'),
('core.sessions.read_all',         'core', 'sessions',      'read',   'all',          'ดูเซสชันการใช้งานทั้งหมด'),
('core.sessions.manage_all',       'core', 'sessions',      'manage', 'all',          'จัดการและยุติเซสชันผู้อื่น'),
('core.files.read_own',            'core', 'files',         'read',   'own',          'ดูไฟล์ของตนเอง'),
('core.files.read_department',     'core', 'files',         'read',   'department',   'ดูไฟล์ในฝ่าย'),
('core.files.read_all',            'core', 'files',         'read',   'all',          'ดูไฟล์ทั้งหมด'),
('core.files.upload_own',          'core', 'files',         'upload', 'own',          'อัปโหลดไฟล์'),
('core.notifications.read_own',    'core', 'notifications', 'read',   'own',          'ดูการแจ้งเตือนของตนเอง'),
('core.calendar.read_all',         'core', 'calendar',      'read',   'all',          'ดูปฏิทินกลาง'),
('core.calendar.create_department','core', 'calendar',      'create', 'department',   'สร้างกิจกรรมระดับฝ่าย'),
('core.calendar.create_all',       'core', 'calendar',      'create', 'all',          'สร้างกิจกรรมระดับองค์กร'),
('core.service_rooms.read_all',    'core', 'service_rooms', 'read',   'all',          'ดูข้อมูลห้องบริการ'),
('core.service_rooms.manage_all',  'core', 'service_rooms', 'manage', 'all',          'เพิ่ม/แก้ไข/ลบข้อมูลห้องบริการ'),
('core.files.delete_all',          'core', 'files',         'delete', 'all',          'ลบไฟล์ของผู้อื่น'),
('core.pdpa.request_own',          'core', 'pdpa',          'request','own',          'ยื่นคำขอใช้สิทธิ์ PDPA ของตนเอง'),
('core.pdpa.manage_all',           'core', 'pdpa',          'manage', 'all',          'จัดการคำขอใช้สิทธิ์ PDPA (DPO)'),
('core.reports.view_all',          'core', 'reports',       'view',   'all',          'ดู/ส่งออกรายงานภาพรวมทั้งหน่วยงาน'),
('core.reports.view_department',   'core', 'reports',       'view',   'department',   'ดู/ส่งออกรายงานภาพรวมเฉพาะฝ่ายที่สังกัด')
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
  'core.dashboard.view','core.users.read_own','core.users.update_own','core.employees.read_own','core.employees.update_own',
  'core.files.read_own','core.files.upload_own','core.notifications.read_own','core.calendar.read_all',
  'core.service_rooms.read_all','core.pdpa.request_own')
WHERE r.code IN ('director','deputy_director','department_head','service_unit_head','teacher','staff','student_registrar');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.users.read_all','core.employees.read_all','core.departments.read_all',
  'core.files.read_all','core.calendar.create_all','core.reports.view_all')
WHERE r.code IN ('director','deputy_director');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.audit.read_all'
WHERE r.code = 'director';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.users.read_department','core.employees.read_department','core.departments.read_all',
  'core.files.read_department','core.calendar.create_department','core.reports.view_department')
WHERE r.code = 'department_head';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.users.read_service_unit','core.employees.read_service_unit','core.calendar.create_department','core.reports.view_department')
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
('security', 'force_2fa_roles',  '["super_admin"]',                      'json',   'Role ที่บังคับ 2FA', 0),
('personnel', 'personnel.code_prefix', 'T-', 'string', 'คำนำหน้ารหัสบุคลากรอัตโนมัติ (A-Z 0-9 _ . -)', 0),
('personnel', 'personnel.code_digits', '4', 'int', 'จำนวนหลักของเลขลำดับรหัสบุคลากร (3-8)', 0)
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

-- ================================================================ Phase 5A: Academic Module (ทะเบียนนักเรียน)
-- ---------------------------------------------------------------- หน่วยบริการ (ฝ่ายย่อยภายใต้งานหน่วยบริการ)
INSERT IGNORE INTO departments (parent_id, code, name_th, type, sort_order)
SELECT p.id, v.code, v.name_th, 'unit', v.sort_order
FROM departments p
JOIN (
  SELECT 'SU_CHUN' AS code, 'หน่วยบริการจุน' AS name_th, 61 AS sort_order UNION ALL
  SELECT 'SU_DOKKHAMTAI', 'หน่วยบริการดอกคำใต้', 62 UNION ALL
  SELECT 'SU_PONG', 'หน่วยบริการปง', 63 UNION ALL
  SELECT 'SU_PHUKAMYAO', 'หน่วยบริการภูกามยาว', 64 UNION ALL
  SELECT 'SU_PHUSANG', 'หน่วยบริการภูซาง', 65 UNION ALL
  SELECT 'SU_CHIANGKHAM', 'หน่วยบริการเชียงคำ', 66 UNION ALL
  SELECT 'SU_CHIANGMUAN', 'หน่วยบริการเชียงม่วน', 67 UNION ALL
  SELECT 'SU_MAECHAI', 'หน่วยบริการแม่ใจ', 68
) v
WHERE p.code = 'SERVICE_UNIT';

-- ---------------------------------------------------------------- ชื่อระบบ (ตั้งค่าระบบ > ชื่อระบบ)
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description, is_public) VALUES
('branding', 'app.name',       'Smart Phayao SEC Platform',          'string', 'ชื่อระบบ (เต็ม): แท็บเบราว์เซอร์ หน้าเข้าสู่ระบบ อีเมล แอปยืนยันตัวตน ท้ายกระดาษรายงาน', 1),
('branding', 'app.brand_main', 'Smart Phayao SEC',                   'string', 'ชื่อระบบบรรทัดหลัก: โลโก้เมนูด้านข้าง แถบบน หน้าแรก', 1),
('branding', 'app.brand_sub',  'Platform',                           'string', 'ชื่อระบบบรรทัดรอง (ใต้ชื่อหลัก)', 1),
('branding', 'app.short_name', 'Smart SEC',                          'string', 'ชื่อย่อ: แถบบนบนมือถือ', 1),
('branding', 'app.tagline',    'ระบบสารสนเทศเพื่อการบริหารจัดการ', 'string', 'คำอธิบายระบบ: หน้าแรกสาธารณะ', 1),
('branding', 'app.logo',       '',                                   'string', 'โลโก้ระบบ (ชื่อไฟล์ใน public/uploads/branding ว่าง = ไอคอนเริ่มต้น)', 1)
ON DUPLICATE KEY UPDATE setting_group = VALUES(setting_group), description = VALUES(description);

-- ---------------------------------------------------------------- ตั้งค่าเลขประจำตัวนักเรียน
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description, is_public) VALUES
('academic', 'academic.student_code_digits', '5', 'int', 'จำนวนหลักของเลขประจำตัวนักเรียนที่ระบบเสนอให้อัตโนมัติ (3-10)', 0)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ---------------------------------------------------------------- สิทธิ์
INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('academic.students.read_all',                'academic', 'students', 'read',           'all',          'ดูทะเบียนนักเรียนทั้งหมด'),
('academic.students.read_service_unit',       'academic', 'students', 'read',           'service_unit', 'ดูนักเรียนในหน่วยบริการที่สังกัด'),
('academic.students.read_department',         'academic', 'students', 'read',           'department',   'ดูนักเรียนที่รับบริการในฝ่าย/งานที่สังกัด'),
('academic.students.read_own',                'academic', 'students', 'read',           'own',          'ดูนักเรียนที่ตนเป็นครูผู้รับผิดชอบ/ครูประจำชั้น'),
('academic.students.create_all',              'academic', 'students', 'create',         'all',          'เพิ่มนักเรียน'),
('academic.students.update_all',              'academic', 'students', 'update',         'all',          'แก้ไขข้อมูลนักเรียนและการรับบริการ'),
('academic.students.delete_all',              'academic', 'students', 'delete',         'all',          'ลบข้อมูลนักเรียน'),
('academic.students.export_all',              'academic', 'students', 'export',         'all',          'ส่งออกทะเบียนนักเรียน (Excel)'),
('academic.students.read_sensitive_all',      'academic', 'students', 'read_sensitive', 'all',          'ดูข้อมูลความพิการ/สุขภาพ/เลขบัตรของนักเรียนทั้งหมด'),
('academic.students.read_sensitive_service_unit', 'academic', 'students', 'read_sensitive', 'service_unit', 'ดูข้อมูลความพิการ/สุขภาพของนักเรียนในหน่วยบริการ'),
('academic.students.read_sensitive_own',      'academic', 'students', 'read_sensitive', 'own',          'ดูข้อมูลความพิการ/สุขภาพของนักเรียนที่ตนรับผิดชอบ'),
('academic.students.update_sensitive_all',    'academic', 'students', 'update_sensitive','all',         'แก้ไขข้อมูลความพิการ/สุขภาพของนักเรียน'),
('academic.students.change_status_all',      'academic', 'students', 'change_status', 'all',         'เปลี่ยนสถานะนักเรียน (พักการเรียน จบ ย้าย จำหน่าย)'),
('academic.students.update_own',           'academic', 'students', 'update',           'own', 'แก้ไขข้อมูลนักเรียนที่ตนเป็นครูผู้รับผิดชอบ/ครูประจำห้อง'),
('academic.students.update_sensitive_own', 'academic', 'students', 'update_sensitive', 'own', 'แก้ไขข้อมูลความพิการ/สุขภาพของนักเรียนที่ตนรับผิดชอบ')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code LIKE 'academic.students.%'
WHERE r.code = 'super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'academic.students.read_all','academic.students.create_all','academic.students.update_all','academic.students.delete_all',
  'academic.students.export_all','academic.students.read_sensitive_all','academic.students.update_sensitive_all',
  'academic.students.change_status_all')
WHERE r.code IN ('admin', 'student_registrar');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'academic.students.read_all','academic.students.export_all','academic.students.read_sensitive_all')
WHERE r.code IN ('director','deputy_director');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'academic.students.read_service_unit','academic.students.read_sensitive_service_unit','academic.students.read_own','academic.students.read_sensitive_own')
WHERE r.code = 'service_unit_head';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'academic.students.read_department','academic.students.read_own','academic.students.read_sensitive_own')
WHERE r.code = 'department_head';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('academic.students.read_own','academic.students.read_sensitive_own',
  'academic.students.update_own','academic.students.update_sensitive_own')
WHERE r.code = 'teacher';

-- งานวิจัยและพัฒนานวัตกรรม: สิทธิ์ บทบาท และประเภทเริ่มต้น
INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('academic.research.submit_own', 'academic', 'research', 'submit', 'own', 'ส่งงานวิจัย/สื่อนวัตกรรมของตนเอง ดูได้เฉพาะงานของตน'),
('academic.research.read_all',   'academic', 'research', 'read',   'all', 'ดูรายละเอียดและไฟล์งานวิจัย/สื่อนวัตกรรมทั้งหมด และรายงาน'),
('academic.research.review_all', 'academic', 'research', 'review', 'all', 'ตรวจงานวิจัย/สื่อนวัตกรรม ให้ข้อเสนอแนะ และเปลี่ยนสถานะ'),
('academic.research.manage_all', 'academic', 'research', 'manage', 'all', 'จัดการประเภทงานวิจัย ประเภทสื่อนวัตกรรม และรอบการส่ง'),
('academic.research.sign_2', 'academic', 'research', 'sign_2', 'all', 'ลงนามลำดับที่ 2 (หัวหน้างานวิจัยและพัฒนานวัตกรรม) ในงานวิจัย/สื่อนวัตกรรม'),
('academic.research.sign_3', 'academic', 'research', 'sign_3', 'all', 'ลงนามลำดับที่ 3 (หัวหน้าฝ่ายบริหารวิชาการ) ในงานวิจัย/สื่อนวัตกรรม'),
('academic.research.sign_4', 'academic', 'research', 'sign_4', 'all', 'ลงนามลำดับที่ 4 (รองผู้อำนวยการฝ่ายบริหารวิชาการ อนุมัติขั้นสุดท้าย)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO roles (code, name_th, description, level, is_system) VALUES
('research_head',   'หัวหน้างานวิจัยและพัฒนานวัตกรรม', 'ผู้ตรวจงานวิจัย/สื่อนวัตกรรม: ตรวจ ให้ข้อเสนอแนะ เปลี่ยนสถานะ (ลงนามลำดับที่ 2)', 6, 1),
('academic_head',   'หัวหน้าฝ่ายบริหารวิชาการ', 'ดูภาพรวมและรายงานงานวิจัย/สื่อนวัตกรรม (ลงนามลำดับที่ 3)', 5, 1),
('academic_deputy', 'รองผู้อำนวยการฝ่ายบริหารวิชาการ', 'ดูภาพรวมและรายงานงานวิจัย/สื่อนวัตกรรม (ลงนามลำดับที่ 4 อนุมัติขั้นสุดท้าย)', 4, 1)
ON DUPLICATE KEY UPDATE name_th = VALUES(name_th), description = VALUES(description);

-- สิทธิ์พื้นฐานเหมือนผู้ใช้ทั่วไป (ใช้บทบาทใหม่บทบาทเดียวก็เข้าใช้งานได้)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.dashboard.view','core.users.read_own','core.users.update_own','core.employees.read_own','core.employees.update_own',
  'core.files.read_own','core.files.upload_own','core.notifications.read_own','core.calendar.read_all',
  'core.service_rooms.read_all','core.pdpa.request_own')
WHERE r.code IN ('research_head', 'academic_head', 'academic_deputy');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code LIKE 'academic.research.%'
WHERE r.code = 'super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('academic.research.read_all', 'academic.research.manage_all')
WHERE r.code = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.submit_own'
WHERE r.code IN ('teacher', 'research_head', 'academic_head');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.review_all'
WHERE r.code = 'research_head';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.read_all'
WHERE r.code IN ('research_head', 'academic_head', 'academic_deputy', 'director');

-- ลงนาม 4 ลำดับ: ลำดับ 1 = ครูเจ้าของงาน, 2-4 ตามบทบาท
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.sign_2' WHERE r.code = 'research_head';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.sign_3' WHERE r.code = 'academic_head';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.sign_4' WHERE r.code = 'academic_deputy';

INSERT INTO research_types (kind, name, sort_order) VALUES
('research', 'การวิจัยเชิงปฏิบัติการในชั้นเรียน (Classroom Action Research)', 1),
('research', 'วิจัยและพัฒนา (R&D)', 2),
('innovation', 'สิ่งประดิษฐ์', 1),
('innovation', 'ICT', 2),
('innovation', 'ภูมิปัญญาท้องถิ่น', 3)
ON DUPLICATE KEY UPDATE sort_order = sort_order;

-- สื่อนวัตกรรม: กำหนดส่งรายงานการใช้สื่อ (วันหลังวันสิ้นสุดการใช้สื่อตามแผน) ผู้ดูแลแก้ไขได้ในหน้าตั้งค่างานวิจัย/นวัตกรรม
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description) VALUES
('research', 'research.innovation_report_due_days', '30', 'integer', 'ส่งรายงานการใช้สื่อนวัตกรรมภายใน (วัน) หลังวันสิ้นสุดการใช้สื่อตามแผน')
ON DUPLICATE KEY UPDATE description = VALUES(description);
