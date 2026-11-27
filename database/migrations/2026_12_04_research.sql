-- ฝ่ายบริหารวิชาการ: งานวิจัยและพัฒนานวัตกรรม (ระยะที่ 1: ประเภท รอบการส่ง งานวิจัย เวอร์ชัน ข้อเสนอแนะ ประวัติสถานะ)
-- บทบาทใหม่: research_head (ผู้ตรวจ), academic_head, academic_deputy
-- รันซ้ำได้
SET NAMES utf8mb4;

-- งานวิจัยและพัฒนานวัตกรรม (ฝ่ายบริหารวิชาการ)
-- ประเภทงานวิจัย (kind = research) และประเภทสื่อนวัตกรรม (kind = innovation) ผู้ดูแลเพิ่ม/แก้ไขได้
CREATE TABLE IF NOT EXISTS research_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('research','innovation') NOT NULL,
  name VARCHAR(150) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_research_types_name (kind, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- รอบการส่งงาน: 1 รอบต่อ ประเภท + ปีการศึกษา + ภาคเรียน
CREATE TABLE IF NOT EXISTS research_rounds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('research','innovation') NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  opens_on DATE NULL,
  due_on DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_research_rounds_term (kind, academic_year_id, semester),
  CONSTRAINT fk_research_rounds_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- งานวิจัย (ข้อมูลผู้เรียนเก็บเป็นจำนวนเท่านั้น ตามหลัก PDPA)
CREATE TABLE IF NOT EXISTS research_works (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(300) NOT NULL,
  type_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  target_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนกลุ่มเป้าหมาย (คน)',
  keywords VARCHAR(500) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  current_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL COMMENT 'ส่งครั้งแรก',
  status_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_research_works_owner (owner_user_id, deleted_at),
  KEY idx_research_works_term (academic_year_id, semester),
  KEY idx_research_works_status (status),
  CONSTRAINT fk_research_works_owner FOREIGN KEY (owner_user_id) REFERENCES users (id),
  CONSTRAINT fk_research_works_type FOREIGN KEY (type_id) REFERENCES research_types (id),
  CONSTRAINT fk_research_works_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประเภทความพิการของกลุ่มเป้าหมาย (เลือกได้หลายประเภท) ค่าตาม StudentLookup::DISABILITY_TYPES
CREATE TABLE IF NOT EXISTS research_work_disabilities (
  work_id INT UNSIGNED NOT NULL,
  disability_type VARCHAR(30) NOT NULL,
  PRIMARY KEY (work_id, disability_type),
  CONSTRAINT fk_research_work_disabilities_work FOREIGN KEY (work_id) REFERENCES research_works (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ไฟล์ทุกเวอร์ชัน (เก็บใน storage/research นอก public ไม่ลบจริง)
CREATE TABLE IF NOT EXISTS research_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id INT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  storage_path VARCHAR(300) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  note VARCHAR(500) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_research_versions_no (work_id, version_no),
  CONSTRAINT fk_research_versions_work FOREIGN KEY (work_id) REFERENCES research_works (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อเสนอแนะ (ผู้ตรวจเปิดเรื่อง) และการตอบกลับในเรื่องเดียวกัน (parent_id)
CREATE TABLE IF NOT EXISTS research_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  version_no SMALLINT UNSIGNED NULL COMMENT 'เวอร์ชันที่แสดงความเห็น',
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_research_comments_work (work_id, parent_id),
  CONSTRAINT fk_research_comments_work FOREIGN KEY (work_id) REFERENCES research_works (id),
  CONSTRAINT fk_research_comments_parent FOREIGN KEY (parent_id) REFERENCES research_comments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประวัติการเปลี่ยนสถานะ: ผู้ดำเนินการ วันเวลา หมายเหตุ
CREATE TABLE IF NOT EXISTS research_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  version_no SMALLINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_research_status_logs_work (work_id, id),
  CONSTRAINT fk_research_status_logs_work FOREIGN KEY (work_id) REFERENCES research_works (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- กันแจ้งเตือนกำหนดส่งซ้ำ (alert_key: d7, d3, d1, overdue)
CREATE TABLE IF NOT EXISTS research_round_alerts (
  round_id INT UNSIGNED NOT NULL,
  alert_key VARCHAR(20) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recipients INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (round_id, alert_key),
  CONSTRAINT fk_research_round_alerts_round FOREIGN KEY (round_id) REFERENCES research_rounds (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

-- งานวิจัยและพัฒนานวัตกรรม: สิทธิ์ บทบาท และประเภทเริ่มต้น
INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('academic.research.submit_own', 'academic', 'research', 'submit', 'own', 'ส่งงานวิจัย/สื่อนวัตกรรมของตนเอง ดูได้เฉพาะงานของตน'),
('academic.research.read_all',   'academic', 'research', 'read',   'all', 'ดูรายละเอียดและไฟล์งานวิจัย/สื่อนวัตกรรมทั้งหมด และรายงาน'),
('academic.research.review_all', 'academic', 'research', 'review', 'all', 'ตรวจงานวิจัย/สื่อนวัตกรรม ให้ข้อเสนอแนะ และเปลี่ยนสถานะ'),
('academic.research.manage_all', 'academic', 'research', 'manage', 'all', 'จัดการประเภทงานวิจัย ประเภทสื่อนวัตกรรม และรอบการส่ง')
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

INSERT INTO research_types (kind, name, sort_order) VALUES
('research', 'การวิจัยเชิงปฏิบัติการในชั้นเรียน (Classroom Action Research)', 1),
('research', 'วิจัยและพัฒนา (R&D)', 2),
('innovation', 'สิ่งประดิษฐ์', 1),
('innovation', 'ICT', 2),
('innovation', 'ภูมิปัญญาท้องถิ่น', 3)
ON DUPLICATE KEY UPDATE sort_order = sort_order;

COMMIT;
