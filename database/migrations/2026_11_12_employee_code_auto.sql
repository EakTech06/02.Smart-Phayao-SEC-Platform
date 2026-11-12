-- รหัสบุคลากรสร้างอัตโนมัติ: <คำนำหน้า><เลขลำดับ> เช่น T-0005 (เลขลำดับต่อจากค่าสูงสุดที่เคยใช้ รวมบุคลากรที่ถูกลบ)
-- นำเข้าผ่าน phpMyAdmin ได้ (รันซ้ำได้ ไม่เขียนทับค่าที่ตั้งไว้แล้ว)
SET NAMES utf8mb4;

INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description, is_public) VALUES
('personnel', 'personnel.code_prefix', 'T-', 'string', 'คำนำหน้ารหัสบุคลากรอัตโนมัติ (A-Z 0-9 _ . -)', 0),
('personnel', 'personnel.code_digits', '4',  'int',    'จำนวนหลักของเลขลำดับรหัสบุคลากร (3-8)', 0)
ON DUPLICATE KEY UPDATE description = VALUES(description);
