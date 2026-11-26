-- ทะเบียนนักเรียน: แจ้งเตือนบัตรประจำตัวคนพิการหมดอายุ/ใกล้หมดอายุเข้ากระดิ่ง
-- card_alert_key = SHA-256 ของ "วันหมดอายุ|ระดับ" ที่แจ้งไปแล้ว (ไม่เก็บวันหมดอายุแบบไม่เข้ารหัส) ใช้กันแจ้งซ้ำ
-- รันซ้ำได้
SET NAMES utf8mb4;

ALTER TABLE academic_student_sensitive
  ADD COLUMN IF NOT EXISTS card_alert_key CHAR(64) NULL COMMENT 'แฮชของระดับแจ้งเตือนบัตรคนพิการที่แจ้งแล้ว' AFTER data_encrypted;

-- วันที่ตรวจบัตรคนพิการครั้งล่าสุด (ตรวจวันละครั้ง: เปิดแดชบอร์ดหรือ tools/card_reminder.php)
INSERT IGNORE INTO settings (setting_group, setting_key, setting_value, value_type, description)
VALUES ('academic', 'academic.card_checked_on', NULL, 'string', 'วันที่ตรวจแจ้งเตือนบัตรประจำตัวคนพิการครั้งล่าสุด (ระบบบันทึกเอง)');
