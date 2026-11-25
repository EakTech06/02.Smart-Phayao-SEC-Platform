-- Phase 5B: ทะเบียนนักเรียน (ต่อจาก Phase 5A) ตามเอกสาร Phase5_academic_students_analysis
--   1. สถานะ "พักการเรียน" + ประวัติการเปลี่ยนสถานะ (academic_student_status_logs)
--   2. ประวัติการเปลี่ยนเลขประจำตัว (academic_student_identity_history) เลขเดิมเข้ารหัส ไม่เก็บในช่องหมายเหตุ
--   3. ข้อมูลพี่น้อง กิจกรรม (ลูกเสือ/ยุวกาชาด) และระยะทางเดินทาง ตามแบบฟอร์ม SET
--   4. สิทธิ์เปลี่ยนสถานะนักเรียน
-- ต้องรัน 2026_11_18_phase5a_students.sql ก่อน | รันซ้ำได้ (MariaDB 10.3+ / phpMyAdmin)
SET NAMES utf8mb4;

-- ---------------------------------------------------------------- นักเรียน: สถานะใหม่ + ฟิลด์ตาม SET
ALTER TABLE academic_students
  MODIFY status ENUM('applicant','active','suspended','graduated','transferred','discharged','deceased') NOT NULL DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS older_brothers TINYINT UNSIGNED NULL COMMENT 'จำนวนพี่ชาย' AFTER lives_with,
  ADD COLUMN IF NOT EXISTS younger_brothers TINYINT UNSIGNED NULL COMMENT 'จำนวนน้องชาย' AFTER older_brothers,
  ADD COLUMN IF NOT EXISTS older_sisters TINYINT UNSIGNED NULL COMMENT 'จำนวนพี่สาว' AFTER younger_brothers,
  ADD COLUMN IF NOT EXISTS younger_sisters TINYINT UNSIGNED NULL COMMENT 'จำนวนน้องสาว' AFTER older_sisters,
  ADD COLUMN IF NOT EXISTS child_order TINYINT UNSIGNED NULL COMMENT 'เป็นบุตรคนที่' AFTER younger_sisters,
  ADD COLUMN IF NOT EXISTS siblings_studying TINYINT UNSIGNED NULL COMMENT 'จำนวนพี่น้องที่กำลังศึกษา' AFTER child_order,
  ADD COLUMN IF NOT EXISTS scout_level CHAR(2) NULL COMMENT 'ประเภทลูกเสือ (รหัส SET Stu_Scout)' AFTER siblings_studying,
  ADD COLUMN IF NOT EXISTS scout_group CHAR(1) NULL COMMENT 'เหล่าลูกเสือ (รหัส SET Stu_Groupscout)' AFTER scout_level,
  ADD COLUMN IF NOT EXISTS redcross_status CHAR(1) NULL COMMENT 'ยุวกาชาด (รหัส SET Stu_Redcrossyouth)' AFTER scout_group,
  ADD COLUMN IF NOT EXISTS travel_km_unpaved DECIMAL(5,1) UNSIGNED NULL COMMENT 'ระยะทางถนนลูกรัง (กม.)' AFTER address_current,
  ADD COLUMN IF NOT EXISTS travel_km_paved DECIMAL(5,1) UNSIGNED NULL COMMENT 'ระยะทางถนนลาดยาง (กม.)' AFTER travel_km_unpaved,
  ADD COLUMN IF NOT EXISTS travel_km_waterway DECIMAL(5,1) UNSIGNED NULL COMMENT 'ระยะทางทางน้ำ (กม.)' AFTER travel_km_paved;

-- ---------------------------------------------------------------- ประวัติการเปลี่ยนสถานะ
CREATE TABLE IF NOT EXISTS academic_student_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL COMMENT 'NULL = สถานะแรกเมื่อเพิ่มนักเรียน',
  to_status VARCHAR(20) NOT NULL,
  effective_on DATE NOT NULL COMMENT 'วันที่มีผล',
  reason VARCHAR(255) NULL,
  reference_no VARCHAR(100) NULL COMMENT 'เลขที่หนังสือ/คำสั่งอ้างอิง',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_academic_status_logs_student (student_id, effective_on),
  CONSTRAINT fk_academic_status_logs_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ยกยอดสถานะปัจจุบันของนักเรียนที่มีอยู่แล้ว เป็นรายการแรกของประวัติ
INSERT INTO academic_student_status_logs (student_id, from_status, to_status, effective_on, reason, created_by, created_at)
SELECT s.id, NULL, s.status, COALESCE(s.left_at, s.admitted_at, DATE(s.created_at)),
       CASE WHEN s.left_reason IS NOT NULL THEN s.left_reason ELSE 'ยกยอดจากทะเบียนเดิม (Phase 5A)' END,
       s.created_by, s.created_at
FROM academic_students s
WHERE NOT EXISTS (SELECT 1 FROM academic_student_status_logs l WHERE l.student_id = s.id);

-- ---------------------------------------------------------------- ประวัติเลขประจำตัว (เลขเดิมเข้ารหัส)
CREATE TABLE IF NOT EXISTS academic_student_identity_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  id_type VARCHAR(20) NOT NULL COMMENT 'ประเภทเอกสารเดิม',
  national_id_encrypted TEXT NULL COMMENT 'เลขบัตร/พาสปอร์ตเดิม AES-256-GCM',
  national_id_last4 CHAR(4) NULL,
  g_code VARCHAR(20) NULL COMMENT 'เลข G เดิม',
  reason VARCHAR(255) NULL,
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_academic_identity_history_student (student_id, changed_at),
  CONSTRAINT fk_academic_identity_history_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- สิทธิ์
INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('academic.students.change_status_all', 'academic', 'students', 'change_status', 'all', 'เปลี่ยนสถานะนักเรียน (พักการเรียน จบ ย้าย จำหน่าย)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.students.change_status_all'
WHERE r.code IN ('super_admin', 'admin');
