-- ตั้งค่าชื่อระบบที่แสดงในจุดต่าง ๆ (แก้ไขได้ที่ ตั้งค่าระบบ > ชื่อระบบ) รันซ้ำได้: ไม่ทับค่าที่ผู้ดูแลแก้ไขแล้ว
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description, is_public) VALUES
('branding', 'app.name',       'Smart Phayao SEC Platform',          'string', 'ชื่อระบบ (เต็ม): แท็บเบราว์เซอร์ หน้าเข้าสู่ระบบ อีเมล แอปยืนยันตัวตน ท้ายกระดาษรายงาน', 1),
('branding', 'app.brand_main', 'Smart Phayao SEC',                   'string', 'ชื่อระบบบรรทัดหลัก: โลโก้เมนูด้านข้าง แถบบน หน้าแรก', 1),
('branding', 'app.brand_sub',  'Platform',                           'string', 'ชื่อระบบบรรทัดรอง (ใต้ชื่อหลัก)', 1),
('branding', 'app.short_name', 'Smart SEC',                          'string', 'ชื่อย่อ: แถบบนบนมือถือ', 1),
('branding', 'app.tagline',    'ระบบสารสนเทศเพื่อการบริหารจัดการ', 'string', 'คำอธิบายระบบ: หน้าแรกสาธารณะ', 1)
ON DUPLICATE KEY UPDATE setting_group = VALUES(setting_group), description = VALUES(description);
INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description, is_public) VALUES
('branding', 'app.logo', '', 'string', 'โลโก้ระบบ (ชื่อไฟล์ใน public/uploads/branding ว่าง = ไอคอนเริ่มต้น)', 1)
ON DUPLICATE KEY UPDATE setting_group = VALUES(setting_group), description = VALUES(description);
