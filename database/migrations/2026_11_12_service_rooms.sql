-- ข้อมูลห้องบริการ: รหัสห้อง (R001) + ปีการศึกษา + ชื่อห้อง + ครูประจำชั้น (จากข้อมูลบุคลากร)
-- นำเข้าผ่าน phpMyAdmin ได้ (รันซ้ำได้)
SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS service_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(10) NOT NULL COMMENT 'รูปแบบ R + ตัวเลข 3 หลัก เช่น R001',
  academic_year_id INT UNSIGNED NOT NULL,
  name_th VARCHAR(255) NOT NULL,
  homeroom_employee_id INT UNSIGNED NULL COMMENT 'ครูประจำชั้น (employees.id)',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_rooms_year_code (academic_year_id, room_code),
  KEY idx_service_rooms_homeroom (homeroom_employee_id),
  CONSTRAINT fk_service_rooms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
  CONSTRAINT fk_service_rooms_homeroom FOREIGN KEY (homeroom_employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.service_rooms.read_all',   'core', 'service_rooms', 'read',   'all', 'ดูข้อมูลห้องบริการ'),
('core.service_rooms.manage_all', 'core', 'service_rooms', 'manage', 'all', 'เพิ่ม/แก้ไข/ลบข้อมูลห้องบริการ')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ดูได้ทุกบทบาท / จัดการได้เฉพาะ super_admin, admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.service_rooms.read_all';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'core.service_rooms.manage_all'
WHERE r.code IN ('super_admin', 'admin');

COMMIT;
