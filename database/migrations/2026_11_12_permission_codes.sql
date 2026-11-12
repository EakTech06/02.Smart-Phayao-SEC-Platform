-- 2026-11-12: ย้ายรหัสสิทธิ์ให้ตรงกับที่โค้ดใช้จริง (เพิ่มอย่างเดียว ไม่ลบของเดิม รันซ้ำได้)
--   core.audit_logs.read_all          -> core.audit.read_all
--   core.settings.read_all/update_all -> core.settings.manage
--   core.sessions.revoke_all          -> core.sessions.read_all + core.sessions.manage_all
SET NAMES utf8mb4;
START TRANSACTION;

ALTER TABLE employees ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) NULL AFTER email;

INSERT INTO permissions (code, module, resource, action, scope, description) VALUES
('core.audit.read_all',      'core', 'audit',    'read',   'all',  'ดูประวัติการใช้งานระบบทั้งหมด'),
('core.settings.manage',     'core', 'settings', 'manage', 'none', 'จัดการตั้งค่าระบบ'),
('core.sessions.read_all',   'core', 'sessions', 'read',   'all',  'ดูเซสชันการใช้งานทั้งหมด'),
('core.sessions.manage_all', 'core', 'sessions', 'manage', 'all',  'จัดการและยุติเซสชันผู้อื่น')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT rp.role_id, pn.id
FROM role_permissions rp
JOIN permissions po ON po.id = rp.permission_id
JOIN permissions pn ON (po.code, pn.code) IN (
  ('core.audit_logs.read_all', 'core.audit.read_all'),
  ('core.settings.update_all', 'core.settings.manage'),
  ('core.sessions.revoke_all', 'core.sessions.read_all'),
  ('core.sessions.revoke_all', 'core.sessions.manage_all')
);

INSERT IGNORE INTO user_permissions (user_id, permission_id, effect, reason, expires_at, granted_by)
SELECT up.user_id, pn.id, up.effect, up.reason, up.expires_at, up.granted_by
FROM user_permissions up
JOIN permissions po ON po.id = up.permission_id
JOIN permissions pn ON (po.code, pn.code) IN (
  ('core.audit_logs.read_all', 'core.audit.read_all'),
  ('core.settings.update_all', 'core.settings.manage'),
  ('core.sessions.revoke_all', 'core.sessions.read_all'),
  ('core.sessions.revoke_all', 'core.sessions.manage_all')
);

COMMIT;
