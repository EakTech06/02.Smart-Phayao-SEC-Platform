-- ทะเบียนนักเรียน: สิทธิ์แก้ไขข้อมูลนักเรียนตามขอบเขต
-- 1) ครูผู้รับผิดชอบแก้ไขข้อมูลนักเรียนได้เฉพาะคนที่ตนรับผิดชอบ (update_own / update_sensitive_own)
--    "รับผิดชอบ" = เป็นครูผู้รับผิดชอบในการรับบริการ หรือเป็นครูของห้องบริการ ในปีการศึกษาปัจจุบัน (เหมือน read_own)
-- 2) บทบาทใหม่ "เจ้าหน้าที่งานทะเบียนนักเรียน" (student_registrar): เพิ่ม ลบ แก้ไขข้อมูลนักเรียนได้ทั้งหมด
--    มอบหมายให้ครูที่รับผิดชอบงานทะเบียนนักเรียนโดยเพิ่มบทบาทนี้ในหน้าผู้ใช้งาน (ใช้ร่วมกับบทบาทครูได้)
-- รันซ้ำได้
SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('academic.students.update_own',           'academic', 'students', 'update',           'own', 'แก้ไขข้อมูลนักเรียนที่ตนเป็นครูผู้รับผิดชอบ/ครูประจำห้อง'),
('academic.students.update_sensitive_own', 'academic', 'students', 'update_sensitive', 'own', 'แก้ไขข้อมูลความพิการ/สุขภาพของนักเรียนที่ตนรับผิดชอบ')
ON DUPLICATE KEY UPDATE description = VALUES(description);

UPDATE permissions SET description = 'แก้ไขข้อมูลนักเรียนทั้งหมดและการรับบริการ (งานทะเบียนนักเรียน)'
WHERE code = 'academic.students.update_all';
UPDATE permissions SET description = 'แก้ไขข้อมูลความพิการ/สุขภาพของนักเรียนทั้งหมด'
WHERE code = 'academic.students.update_sensitive_all';

-- super_admin ได้สิทธิ์ทุกข้อของทะเบียนนักเรียน
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code LIKE 'academic.students.%'
WHERE r.code = 'super_admin';

-- ครู: แก้ไขได้เฉพาะนักเรียนที่ตนรับผิดชอบ (ยกเลิกสิทธิ์แก้ไขระดับทั้งหมด ถ้าเคยกำหนดไว้)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'academic.students.read_own','academic.students.read_sensitive_own',
  'academic.students.update_own','academic.students.update_sensitive_own')
WHERE r.code = 'teacher';
DELETE rp FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.code = 'teacher' AND p.code IN ('academic.students.update_all', 'academic.students.update_sensitive_all');

-- บทบาทเจ้าหน้าที่งานทะเบียนนักเรียน
INSERT INTO roles (code, name_th, description, level, is_system) VALUES
('student_registrar', 'เจ้าหน้าที่งานทะเบียนนักเรียน', 'ครูที่ได้รับมอบหมายงานทะเบียนนักเรียน: เพิ่ม ลบ แก้ไขข้อมูลนักเรียนได้ทั้งหมด', 7, 1)
ON DUPLICATE KEY UPDATE name_th = VALUES(name_th), description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  -- สิทธิ์พื้นฐานเหมือนผู้ใช้ทั่วไป (ใช้บทบาทนี้บทบาทเดียวก็เข้าใช้งานได้)
  'core.dashboard.view','core.users.read_own','core.users.update_own','core.employees.read_own','core.employees.update_own',
  'core.files.read_own','core.files.upload_own','core.notifications.read_own','core.calendar.read_all',
  'core.service_rooms.read_all','core.pdpa.request_own',
  -- ทะเบียนนักเรียนทั้งหมด
  'academic.students.read_all','academic.students.create_all','academic.students.update_all','academic.students.delete_all',
  'academic.students.export_all','academic.students.read_sensitive_all','academic.students.update_sensitive_all',
  'academic.students.change_status_all')
WHERE r.code = 'student_registrar';

COMMIT;
