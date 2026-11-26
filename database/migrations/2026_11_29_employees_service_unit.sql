-- ข้อมูลบุคลากร: สังกัดหลัก = ฝ่าย (employees.department_id), สังกัดรอง = หน่วยบริการ 1 หน่วย (employees.service_unit_id)
-- บัญชีผู้ใช้ที่ผูกกับบุคลากรได้หน่วยบริการเป็นฝ่ายรอง (user_departments.is_primary = 0)
-- รันซ้ำได้
SET NAMES utf8mb4;

ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS service_unit_id INT UNSIGNED NULL COMMENT 'สังกัดรอง: หน่วยบริการ (departments.id)' AFTER department_id;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND CONSTRAINT_NAME = 'fk_employees_service_unit');
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE employees ADD KEY idx_employees_service_unit (service_unit_id),
     ADD CONSTRAINT fk_employees_service_unit FOREIGN KEY (service_unit_id) REFERENCES departments (id) ON DELETE SET NULL',
  'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
