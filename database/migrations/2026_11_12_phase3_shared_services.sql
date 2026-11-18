-- Phase 3: Shared Services & Google Integration
-- ไฟล์ (Google Shared Drive), ปฏิทินกลาง (+ซิงก์ Google Calendar), การแจ้งเตือน, คำขอใช้สิทธิ์ PDPA
-- นำเข้าผ่าน phpMyAdmin ได้ (รันซ้ำได้)
SET NAMES utf8mb4;

-- ---------- ไฟล์ ----------
ALTER TABLE files
  ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL AFTER original_name,
  ADD INDEX IF NOT EXISTS idx_files_module (module, deleted_at);

-- ---------- ปฏิทินกลาง ----------
ALTER TABLE calendar_events
  ADD COLUMN IF NOT EXISTS updated_by INT UNSIGNED NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS google_event_id VARCHAR(255) NULL AFTER color,
  ADD COLUMN IF NOT EXISTS google_synced_at DATETIME NULL AFTER google_event_id,
  ADD INDEX IF NOT EXISTS idx_calendar_range (start_at, end_at);

-- ---------- การแจ้งเตือน ----------
ALTER TABLE notifications
  ADD INDEX IF NOT EXISTS idx_notifications_created (user_id, created_at);

-- ---------- คำขอใช้สิทธิ์ของเจ้าของข้อมูล (PDPA มาตรา 30-36) ----------
CREATE TABLE IF NOT EXISTS pdpa_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_no VARCHAR(30) NOT NULL,
  user_id INT UNSIGNED NOT NULL COMMENT 'เจ้าของข้อมูลที่ยื่นคำขอ',
  request_type ENUM('access','portability','rectification','erasure','restriction','objection','withdraw_consent') NOT NULL,
  details TEXT NOT NULL,
  status ENUM('pending','in_progress','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  due_date DATE NOT NULL COMMENT 'ภายใน 30 วันนับแต่วันที่ได้รับคำขอ',
  response TEXT NULL,
  handled_by INT UNSIGNED NULL,
  handled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pdpa_requests_no (request_no),
  KEY idx_pdpa_requests_user (user_id),
  KEY idx_pdpa_requests_status (status, due_date),
  CONSTRAINT fk_pdpa_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pdpa_requests_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- สิทธิ์ ----------
INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.files.delete_all',    'core', 'files', 'delete',  'all', 'ลบไฟล์ของผู้อื่น'),
('core.pdpa.request_own',    'core', 'pdpa',  'request', 'own', 'ยื่นคำขอใช้สิทธิ์ PDPA ของตนเอง'),
('core.pdpa.manage_all',     'core', 'pdpa',  'manage',  'all', 'จัดการคำขอใช้สิทธิ์ PDPA (DPO)')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.pdpa.request_own';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('core.files.delete_all', 'core.pdpa.manage_all')
WHERE r.code IN ('super_admin', 'admin');
