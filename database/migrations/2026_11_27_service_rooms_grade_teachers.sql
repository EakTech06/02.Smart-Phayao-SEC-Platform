-- ห้องบริการ: เพิ่มระดับชั้นของห้อง + ครูประจำชั้นได้มากกว่า 1 คน (ตาราง service_room_teachers)
-- ย้ายครูประจำชั้นเดิม (service_rooms.homeroom_employee_id) มาไว้ในตารางใหม่แล้วลบคอลัมน์เดิม
-- นำเข้าผ่าน phpMyAdmin ได้ (รันซ้ำได้)
SET NAMES utf8mb4;

ALTER TABLE service_rooms
  ADD COLUMN IF NOT EXISTS grade_level VARCHAR(50) NULL COMMENT 'ระดับชั้นของห้อง เช่น เตรียมความพร้อม' AFTER name_th;

CREATE TABLE IF NOT EXISTS service_room_teachers (
  service_room_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = ครูประจำชั้นหลัก',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (service_room_id, employee_id),
  KEY idx_service_room_teachers_employee (employee_id),
  CONSTRAINT fk_service_room_teachers_room FOREIGN KEY (service_room_id) REFERENCES service_rooms (id) ON DELETE CASCADE,
  CONSTRAINT fk_service_room_teachers_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ย้ายข้อมูลเดิม (ทำเฉพาะเมื่อยังมีคอลัมน์ homeroom_employee_id)
SET @has_old := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_rooms' AND COLUMN_NAME = 'homeroom_employee_id');
SET @sql := IF(@has_old > 0,
  'INSERT IGNORE INTO service_room_teachers (service_room_id, employee_id, sort_order)
   SELECT id, homeroom_employee_id, 1 FROM service_rooms WHERE homeroom_employee_id IS NOT NULL',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE service_rooms DROP FOREIGN KEY IF EXISTS fk_service_rooms_homeroom;
ALTER TABLE service_rooms DROP INDEX IF EXISTS idx_service_rooms_homeroom;
ALTER TABLE service_rooms DROP COLUMN IF EXISTS homeroom_employee_id;
