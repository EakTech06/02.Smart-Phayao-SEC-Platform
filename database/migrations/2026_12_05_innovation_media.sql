-- ฝ่ายบริหารวิชาการ: งานวิจัยและพัฒนานวัตกรรม ระยะที่ 2 สื่อนวัตกรรม (คู่มือการใช้สื่อ → ใช้สื่อ → รายงานการใช้สื่อ)
-- ต้องรัน 2026_12_04_research.sql ก่อน · รันซ้ำได้
SET NAMES utf8mb4;

-- สื่อนวัตกรรม: 1 ชิ้นส่งเอกสาร 2 ระยะ (คู่มือการใช้สื่อ → ใช้สื่อ → รายงานการใช้สื่อ)
CREATE TABLE IF NOT EXISTS innovation_media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(300) NOT NULL,
  type_id INT UNSIGNED NOT NULL COMMENT 'research_types kind = innovation',
  academic_year_id INT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  plan_start DATE NOT NULL COMMENT 'ช่วงเวลาที่วางแผนใช้สื่อ',
  plan_end DATE NOT NULL,
  actual_start DATE NULL COMMENT 'วันที่ใช้สื่อจริง (จากรายงาน)',
  actual_end DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  manual_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  report_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  report_due_on DATE NULL COMMENT 'กำหนดส่งรายงาน = วันสิ้นสุดตามแผน + จำนวนวันที่ตั้งค่า (กำหนดเมื่อคู่มืออนุมัติ)',
  submitted_at DATETIME NULL COMMENT 'ส่งคู่มือครั้งแรก',
  manual_approved_at DATETIME NULL,
  report_submitted_at DATETIME NULL,
  completed_at DATETIME NULL,
  status_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_innovation_media_owner (owner_user_id, deleted_at),
  KEY idx_innovation_media_term (academic_year_id, semester),
  KEY idx_innovation_media_status (status),
  CONSTRAINT fk_innovation_media_owner FOREIGN KEY (owner_user_id) REFERENCES users (id),
  CONSTRAINT fk_innovation_media_type FOREIGN KEY (type_id) REFERENCES research_types (id),
  CONSTRAINT fk_innovation_media_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จำนวนกลุ่มเป้าหมายแยกตาม 9 ประเภทความพิการ: planned = ตามแผน (คู่มือ), actual = ใช้จริง (รายงาน) เก็บเป็นจำนวนเท่านั้น
CREATE TABLE IF NOT EXISTS innovation_targets (
  media_id INT UNSIGNED NOT NULL,
  phase ENUM('planned','actual') NOT NULL,
  disability_type VARCHAR(30) NOT NULL,
  target_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (media_id, phase, disability_type),
  CONSTRAINT fk_innovation_targets_media FOREIGN KEY (media_id) REFERENCES innovation_media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ไฟล์ทุกเวอร์ชันแยกตามฉบับ (doc: manual = คู่มือ, report = รายงาน) ไม่ลบจริง
CREATE TABLE IF NOT EXISTS innovation_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  doc ENUM('manual','report') NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  storage_path VARCHAR(300) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  note VARCHAR(500) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_innovation_versions_no (media_id, doc, version_no),
  CONSTRAINT fk_innovation_versions_media FOREIGN KEY (media_id) REFERENCES innovation_media (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS innovation_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  doc ENUM('manual','report') NOT NULL,
  version_no SMALLINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_innovation_comments_media (media_id, parent_id),
  CONSTRAINT fk_innovation_comments_media FOREIGN KEY (media_id) REFERENCES innovation_media (id),
  CONSTRAINT fk_innovation_comments_parent FOREIGN KEY (parent_id) REFERENCES innovation_comments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS innovation_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  doc ENUM('manual','report') NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  version_no SMALLINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_innovation_status_logs_media (media_id, id),
  CONSTRAINT fk_innovation_status_logs_media FOREIGN KEY (media_id) REFERENCES innovation_media (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- กันแจ้งเตือนซ้ำต่อสื่อ (plan_end, d7, d3, d1, overdue)
CREATE TABLE IF NOT EXISTS innovation_alerts (
  media_id INT UNSIGNED NOT NULL,
  alert_key VARCHAR(20) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (media_id, alert_key),
  CONSTRAINT fk_innovation_alerts_media FOREIGN KEY (media_id) REFERENCES innovation_media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สื่อนวัตกรรม: กำหนดส่งรายงานการใช้สื่อ (วันหลังวันสิ้นสุดการใช้สื่อตามแผน) ผู้ดูแลแก้ไขได้ในหน้าตั้งค่างานวิจัย/นวัตกรรม
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description) VALUES
('research', 'research.innovation_report_due_days', '30', 'integer', 'ส่งรายงานการใช้สื่อนวัตกรรมภายใน (วัน) หลังวันสิ้นสุดการใช้สื่อตามแผน')
ON DUPLICATE KEY UPDATE description = VALUES(description);
