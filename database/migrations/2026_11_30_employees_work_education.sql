-- ข้อมูลบุคลากร: ข้อมูลการปฏิบัติงานเพิ่มเติม + ประเภทบุคลากรจ้างเหมา + ข้อมูลการศึกษาหลายระดับ (employee_educations)
-- employees.education_level / education_major เก็บวุฒิสูงสุดจาก employee_educations (ใช้ในรายงานและการค้นหาเดิม)
-- รันซ้ำได้
SET NAMES utf8mb4;

ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS license_no VARCHAR(50) NULL COMMENT 'เลขที่ใบประกอบวิชาชีพครู' AFTER screener_reg_no,
  ADD COLUMN IF NOT EXISTS license_expiry_date DATE NULL COMMENT 'วันหมดอายุใบประกอบวิชาชีพครู' AFTER license_no,
  ADD COLUMN IF NOT EXISTS first_position VARCHAR(150) NULL COMMENT 'ตำแหน่ง (เริ่มต้นที่แห่งนี้)' AFTER license_expiry_date,
  ADD COLUMN IF NOT EXISTS appointment_date DATE NULL COMMENT 'วันที่บรรจุเข้ารับราชการ' AFTER first_position,
  ADD COLUMN IF NOT EXISTS license_alert_key VARCHAR(30) NULL COMMENT 'แจ้งเตือนใบประกอบล่าสุด: วันหมดอายุ|ระดับ (กันแจ้งซ้ำ)' AFTER license_expiry_date;

-- วันที่ตรวจใบประกอบวิชาชีพครูครั้งล่าสุด (ตรวจวันละครั้ง: เปิดแดชบอร์ดหรือ tools/license_reminder.php)
INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type, description)
VALUES ('personnel', 'personnel.license_checked_on', NULL, 'string', 'วันที่ตรวจแจ้งเตือนใบประกอบวิชาชีพครูครั้งล่าสุด (ระบบบันทึกเอง)');

-- 'other' คงไว้เพื่อข้อมูลเดิม (ไม่แสดงเป็นตัวเลือกแล้ว)
ALTER TABLE employees
  MODIFY employment_type ENUM('civil_servant','government_employee','permanent_employee','temporary_employee',
    'contract_9000','contract_15000','other') NOT NULL DEFAULT 'civil_servant';

CREATE TABLE IF NOT EXISTS employee_educations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  level VARCHAR(30) NOT NULL COMMENT 'primary, lower_secondary, upper_secondary, graduate_diploma, bachelor, master, other',
  institution VARCHAR(191) NULL,
  degree VARCHAR(150) NULL COMMENT 'วุฒิการศึกษา เช่น ค.บ., ศษ.ม.',
  major VARCHAR(150) NULL COMMENT 'สาขาวิชา (ต่ำกว่าปริญญาตรีไม่มี)',
  graduated_year SMALLINT UNSIGNED NULL COMMENT 'ปีที่สำเร็จการศึกษา (พ.ศ.)',
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employee_educations_employee (employee_id),
  CONSTRAINT fk_employee_educations_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ย้ายวุฒิการศึกษาเดิม (ข้อความอิสระ) เป็นรายการแรก เฉพาะบุคลากรที่ยังไม่มีข้อมูลการศึกษา
INSERT INTO employee_educations (employee_id, level, degree, major, sort_order)
SELECT e.id,
       CASE
         WHEN e.education_level LIKE '%ปริญญาโท%' THEN 'master'
         WHEN e.education_level LIKE '%ปริญญาตรี%' THEN 'bachelor'
         WHEN e.education_level LIKE '%บัณฑิต%' AND e.education_level LIKE '%ประกาศนียบัตร%' THEN 'graduate_diploma'
         WHEN e.education_level LIKE '%มัธยมศึกษาตอนปลาย%' THEN 'upper_secondary'
         WHEN e.education_level LIKE '%มัธยมศึกษาตอนต้น%' THEN 'lower_secondary'
         WHEN e.education_level LIKE '%ประถม%' THEN 'primary'
         ELSE 'other'
       END,
       -- ข้อความเดิมที่เป็นชื่อระดับพอดี (เช่น "ปริญญาโท") ไม่ต้องซ้ำในช่องวุฒิการศึกษา
       NULLIF(NULLIF(NULLIF(NULLIF(TRIM(e.education_level), 'ปริญญาโท'), 'ปริญญาตรี'), 'มัธยมศึกษาตอนปลาย'), 'มัธยมศึกษาตอนต้น'),
       NULLIF(e.education_major, ''), 0
FROM employees e
WHERE e.education_level IS NOT NULL AND e.education_level <> ''
  AND NOT EXISTS (SELECT 1 FROM employee_educations x WHERE x.employee_id = e.id);
