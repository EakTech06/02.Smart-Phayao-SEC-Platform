-- Phase 2: Personnel Base & Account Sync (รันซ้ำได้ / เพิ่มอย่างเดียว ไม่ลบข้อมูลเดิม)
-- ใช้กับฐานข้อมูลที่ติดตั้งไว้ก่อนแล้ว — การติดตั้งใหม่ใช้ schema.sql + seed.sql ได้เลย
SET NAMES utf8mb4;

ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS gender ENUM('male','female','unspecified') NULL AFTER nickname,
  ADD COLUMN IF NOT EXISTS birth_date DATE NULL AFTER gender,
  ADD COLUMN IF NOT EXISTS position_number VARCHAR(30) NULL AFTER position,
  ADD COLUMN IF NOT EXISTS education_level VARCHAR(100) NULL AFTER employment_type,
  ADD COLUMN IF NOT EXISTS education_major VARCHAR(150) NULL AFTER education_level,
  ADD COLUMN IF NOT EXISTS address VARCHAR(500) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS national_id_encrypted TEXT NULL COMMENT 'AES-256-GCM (Core\\Crypto)' AFTER national_id_hash,
  ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER start_date,
  ADD COLUMN IF NOT EXISTS note VARCHAR(500) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL AFTER note,
  ADD COLUMN IF NOT EXISTS updated_by INT UNSIGNED NULL AFTER created_by,
  ADD INDEX IF NOT EXISTS idx_employees_status (status);

START TRANSACTION;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.employees.create_all',          'core', 'employees', 'create',         'all',        'เพิ่มข้อมูลบุคลากร'),
('core.employees.update_department',   'core', 'employees', 'update',         'department', 'แก้ไขข้อมูลบุคลากรในฝ่าย'),
('core.employees.delete_all',          'core', 'employees', 'delete',         'all',        'ลบข้อมูลบุคลากร'),
('core.employees.import_all',          'core', 'employees', 'import',         'all',        'นำเข้าข้อมูลบุคลากรจากไฟล์ CSV'),
('core.employees.read_sensitive_all',  'core', 'employees', 'read_sensitive', 'all',        'ดูเลขประจำตัวประชาชนเต็ม (บันทึก Audit)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- super_admin ได้ทุกสิทธิ์, admin ได้ทุกสิทธิ์ยกเว้นจัดการบทบาท/สิทธิ์รายบุคคล (ตาม seed.sql)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'core.employees.create_all','core.employees.update_department','core.employees.delete_all',
  'core.employees.import_all','core.employees.read_sensitive_all')
WHERE r.code IN ('super_admin', 'admin');

COMMIT;
