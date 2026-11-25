-- Phase 5A: Academic Module — ทะเบียนนักเรียน (ฝ่ายบริหารวิชาการ)
-- ตารางของโมดูลใช้ prefix academic_ | นำเข้าผ่าน phpMyAdmin ได้ (รันซ้ำได้)
-- ข้อมูลอ่อนไหว (เลขบัตร, ความพิการ/การวินิจฉัย, สุขภาพ) เข้ารหัส AES-256-GCM ที่ฝั่ง PHP ก่อนบันทึก
SET NAMES utf8mb4;
START TRANSACTION;

-- ---------------------------------------------------------------- นักเรียน
CREATE TABLE IF NOT EXISTS academic_students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_code VARCHAR(20) NOT NULL COMMENT 'เลขประจำตัวนักเรียน (ตัวเลข)',
  id_type ENUM('citizen','g_code','passport','none') NOT NULL DEFAULT 'citizen' COMMENT 'ประเภทเอกสารแสดงตน',
  national_id_hash CHAR(64) NULL COMMENT 'Blind Index ของเลขบัตร/พาสปอร์ต ใช้ตรวจซ้ำ',
  national_id_encrypted TEXT NULL COMMENT 'AES-256-GCM (Core\\Crypto)',
  national_id_last4 CHAR(4) NULL,
  g_code VARCHAR(20) NULL COMMENT 'เลข G (นักเรียนที่ไม่มีเลขประจำตัวประชาชน)',
  title_name VARCHAR(30) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  nickname VARCHAR(50) NULL,
  first_name_en VARCHAR(100) NULL,
  last_name_en VARCHAR(100) NULL,
  gender ENUM('male','female') NULL,
  birth_date DATE NULL,
  nationality VARCHAR(50) NULL,
  ethnicity VARCHAR(50) NULL,
  religion VARCHAR(50) NULL,
  tribe VARCHAR(50) NULL,
  address_registered TEXT NULL COMMENT 'JSON: ที่อยู่ตามทะเบียนบ้าน',
  address_current TEXT NULL COMMENT 'JSON: ที่อยู่ปัจจุบัน (NULL = ตามทะเบียนบ้าน)',
  phone VARCHAR(30) NULL,
  parents_status ENUM('together','separated','divorced','father_remarried','mother_remarried','father_deceased','mother_deceased','other') NULL,
  lives_with ENUM('parents','father','mother','guardian') NULL,
  applied_at DATE NULL COMMENT 'วันที่มาสมัคร',
  admitted_at DATE NULL COMMENT 'วันที่เริ่มรับบริการ',
  status ENUM('applicant','active','graduated','transferred','discharged','deceased') NOT NULL DEFAULT 'active',
  left_at DATE NULL COMMENT 'วันที่จำหน่าย/จบ/ย้าย',
  left_reason VARCHAR(255) NULL,
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_academic_students_code (student_code),
  UNIQUE KEY uq_academic_students_nid (national_id_hash),
  UNIQUE KEY uq_academic_students_gcode (g_code),
  KEY idx_academic_students_status (status, deleted_at),
  KEY idx_academic_students_name (first_name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- บิดา มารดา ผู้ปกครอง
CREATE TABLE IF NOT EXISTS academic_student_guardians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  relation ENUM('father','mother','guardian') NOT NULL,
  relation_note VARCHAR(50) NULL COMMENT 'ความสัมพันธ์กับนักเรียน (กรณีผู้ปกครอง)',
  title_name VARCHAR(30) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NULL,
  birth_date DATE NULL,
  national_id_encrypted TEXT NULL COMMENT 'AES-256-GCM',
  national_id_last4 CHAR(4) NULL,
  occupation VARCHAR(100) NULL,
  income_per_year INT UNSIGNED NULL,
  phone VARCHAR(30) NULL,
  line_id VARCHAR(100) NULL,
  life_status ENUM('alive','deceased') NOT NULL DEFAULT 'alive',
  is_primary_contact TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_guardians_relation (student_id, relation),
  CONSTRAINT fk_academic_guardians_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- การรับบริการรายปีการศึกษา
CREATE TABLE IF NOT EXISTS academic_student_enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  service_type ENUM('center_boarding','center_day','service_unit','home','hospital','learning_center') NOT NULL,
  department_id INT UNSIGNED NULL COMMENT 'หน่วยบริการ (กรณีรับบริการนอกศูนย์)',
  service_room_id INT UNSIGNED NULL COMMENT 'ห้องบริการของปีการศึกษานั้น',
  grade_level VARCHAR(50) NULL COMMENT 'ระดับชั้น เช่น เตรียมความพร้อม',
  responsible_employee_id INT UNSIGNED NULL COMMENT 'ครูผู้รับผิดชอบนักเรียน',
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_enrollments_year (student_id, academic_year_id),
  KEY idx_academic_enrollments_scope (academic_year_id, department_id),
  KEY idx_academic_enrollments_teacher (academic_year_id, responsible_employee_id),
  KEY idx_academic_enrollments_room (service_room_id),
  CONSTRAINT fk_academic_enrollments_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE,
  CONSTRAINT fk_academic_enrollments_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
  CONSTRAINT fk_academic_enrollments_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_academic_enrollments_room FOREIGN KEY (service_room_id) REFERENCES service_rooms (id) ON DELETE SET NULL,
  CONSTRAINT fk_academic_enrollments_teacher FOREIGN KEY (responsible_employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ข้อมูลความพิการและสุขภาพ (เข้ารหัสทั้งก้อน)
CREATE TABLE IF NOT EXISTS academic_student_sensitive (
  student_id INT UNSIGNED NOT NULL PRIMARY KEY,
  data_encrypted MEDIUMTEXT NOT NULL COMMENT 'JSON เข้ารหัส AES-256-GCM: ประเภท/ระดับความพิการ, การคัดกรอง, บัตรคนพิการ, สุขภาพ',
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_academic_sensitive_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('academic.students.update_sensitive_all',    'academic', 'students', 'update_sensitive','all',         'แก้ไขข้อมูลความพิการ/สุขภาพของนักเรียน')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code LIKE 'academic.students.%'
WHERE r.code = 'super_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'academic.students.read_all','academic.students.create_all','academic.students.update_all','academic.students.delete_all',
  'academic.students.export_all','academic.students.read_sensitive_all','academic.students.update_sensitive_all')
WHERE r.code = 'admin';

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
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('academic.students.read_own','academic.students.read_sensitive_own')
WHERE r.code = 'teacher';

COMMIT;
