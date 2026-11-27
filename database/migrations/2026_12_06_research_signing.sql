-- งานวิจัยและพัฒนานวัตกรรม ระยะที่ 3: ลงนาม 4 ลำดับใน PDF + ตำแหน่งลายเซ็น + เลขที่เอกสารและ QR Code
-- ใช้ร่วมกันทั้งงานวิจัย (doc_type = research) คู่มือ (manual) และรายงานการใช้สื่อ (report)

-- ภาพลายเซ็นของผู้ใช้ (ไฟล์อยู่ใน storage/signatures นอก public ระบบอ่านเฉพาะตอนประทับ)
CREATE TABLE IF NOT EXISTS user_signatures (
  user_id INT UNSIGNED NOT NULL,
  storage_path VARCHAR(200) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  width SMALLINT UNSIGNED NOT NULL,
  height SMALLINT UNSIGNED NOT NULL,
  source ENUM('draw','upload') NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_user_signatures_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตำแหน่งกรอบลายเซ็นของแต่ละลำดับ ต่อไฟล์ต้นฉบับ (สัดส่วนของหน้าจากมุมซ้ายบน)
CREATE TABLE IF NOT EXISTS research_sign_boxes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type ENUM('research','manual','report') NOT NULL,
  doc_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  sign_order TINYINT UNSIGNED NOT NULL,
  page SMALLINT UNSIGNED NOT NULL,
  x DECIMAL(6,5) NOT NULL,
  y DECIMAL(6,5) NOT NULL,
  w DECIMAL(6,5) NOT NULL,
  h DECIMAL(6,5) NOT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_research_sign_boxes (doc_type, doc_id, version_id, sign_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประวัติการลงนาม: ไฟล์ที่ลงนามแล้วของแต่ละลำดับเป็นเวอร์ชันใหม่ (ไฟล์ต้นฉบับเก็บไว้เสมอ)
CREATE TABLE IF NOT EXISTS research_signatures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type ENUM('research','manual','report') NOT NULL,
  doc_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  sign_order TINYINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  signer_name VARCHAR(200) NOT NULL,
  signer_position VARCHAR(200) NOT NULL,
  signed_at DATETIME NOT NULL,
  storage_path VARCHAR(300) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  revoked_by INT UNSIGNED DEFAULT NULL,
  revoke_reason VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_research_signatures_doc (doc_type, doc_id, revoked_at),
  KEY idx_research_signatures_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- เอกสารที่ลงนามครบ 4 ลำดับ: เลขที่เอกสาร + โทเคนตรวจสอบ (QR Code)
CREATE TABLE IF NOT EXISTS research_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type ENUM('research','manual','report') NOT NULL,
  doc_id INT UNSIGNED NOT NULL,
  version_id INT UNSIGNED NOT NULL,
  signature_id INT UNSIGNED NOT NULL,
  doc_no VARCHAR(40) NOT NULL,
  verify_token CHAR(32) NOT NULL,
  title VARCHAR(300) NOT NULL,
  owner_name VARCHAR(200) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  approved_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  revoked_by INT UNSIGNED DEFAULT NULL,
  revoke_reason VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_research_documents_no (doc_no),
  UNIQUE KEY uq_research_documents_token (verify_token),
  KEY idx_research_documents_doc (doc_type, doc_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('academic.research.sign_2', 'academic', 'research', 'sign_2', 'all', 'ลงนามลำดับที่ 2 (หัวหน้างานวิจัยและพัฒนานวัตกรรม) ในงานวิจัย/สื่อนวัตกรรม'),
('academic.research.sign_3', 'academic', 'research', 'sign_3', 'all', 'ลงนามลำดับที่ 3 (หัวหน้าฝ่ายบริหารวิชาการ) ในงานวิจัย/สื่อนวัตกรรม'),
('academic.research.sign_4', 'academic', 'research', 'sign_4', 'all', 'ลงนามลำดับที่ 4 (รองผู้อำนวยการฝ่ายบริหารวิชาการ อนุมัติขั้นสุดท้าย)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.sign_2' WHERE r.code = 'research_head';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.sign_3' WHERE r.code = 'academic_head';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'academic.research.sign_4' WHERE r.code = 'academic_deputy';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code LIKE 'academic.research.sign_%' WHERE r.code = 'super_admin';

COMMIT;
