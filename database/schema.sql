-- Smart Phayao SEC Platform : Core Schema (Phase 1)
-- MySQL 8 / MariaDB 10.4+  |  utf8mb4_unicode_ci
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  code VARCHAR(50) NOT NULL,
  name_th VARCHAR(191) NOT NULL,
  name_en VARCHAR(191) NULL,
  type ENUM('administration','division','service_unit','unit') NOT NULL DEFAULT 'division',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_departments_code (code),
  KEY idx_departments_parent (parent_id),
  CONSTRAINT fk_departments_parent FOREIGN KEY (parent_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(30) NOT NULL,
  title_name VARCHAR(50) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  nickname VARCHAR(50) NULL,
  gender ENUM('male','female','unspecified') NULL,
  birth_date DATE NULL,
  position VARCHAR(150) NULL,
  position_number VARCHAR(30) NULL,
  academic_rank VARCHAR(100) NULL,
  employment_type ENUM('civil_servant','government_employee','permanent_employee','temporary_employee',
    'contract_9000','contract_15000','other') NOT NULL DEFAULT 'civil_servant' COMMENT 'other = ข้อมูลเดิม (ไม่เป็นตัวเลือกแล้ว)',
  education_level VARCHAR(100) NULL COMMENT 'วุฒิสูงสุดจาก employee_educations',
  education_major VARCHAR(150) NULL COMMENT 'สาขาของวุฒิสูงสุด',
  screener_reg_no VARCHAR(50) NULL COMMENT 'เลขทะเบียนผู้คัดกรองคนพิการ',
  license_no VARCHAR(50) NULL COMMENT 'เลขที่ใบประกอบวิชาชีพครู',
  license_expiry_date DATE NULL COMMENT 'วันหมดอายุใบประกอบวิชาชีพครู',
  license_alert_key VARCHAR(30) NULL COMMENT 'แจ้งเตือนใบประกอบล่าสุด: วันหมดอายุ|ระดับ (กันแจ้งซ้ำ)',
  first_position VARCHAR(150) NULL COMMENT 'ตำแหน่ง (เริ่มต้นที่แห่งนี้)',
  appointment_date DATE NULL COMMENT 'วันที่บรรจุเข้ารับราชการ',
  department_id INT UNSIGNED NULL COMMENT 'สังกัดหลัก: ฝ่าย',
  service_unit_id INT UNSIGNED NULL COMMENT 'สังกัดรอง: หน่วยบริการ (departments.id)',
  phone VARCHAR(30) NULL,
  email VARCHAR(191) NULL,
  address VARCHAR(500) NULL,
  avatar VARCHAR(255) NULL COMMENT 'ชื่อไฟล์ใน public/uploads/avatars',
  national_id_hash CHAR(64) NULL COMMENT 'Blind Index: HMAC-SHA256 ใช้ค้นหา/ตรวจซ้ำ',
  national_id_encrypted TEXT NULL COMMENT 'AES-256-GCM (Core\\Crypto)',
  national_id_last4 CHAR(4) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('active','inactive','retired','resigned') NOT NULL DEFAULT 'active',
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_employees_code (employee_code),
  UNIQUE KEY uq_employees_nid (national_id_hash),
  KEY idx_employees_department (department_id),
  KEY idx_employees_service_unit (service_unit_id),
  KEY idx_employees_status (status),
  CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_employees_service_unit FOREIGN KEY (service_unit_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NULL,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(191) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  password_changed_at DATETIME NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_employee (employee_id),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  name_th VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 100,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(150) NOT NULL COMMENT 'module.resource.action_scope',
  module VARCHAR(50) NOT NULL,
  resource VARCHAR(50) NOT NULL,
  action VARCHAR(50) NOT NULL,
  scope ENUM('none','own','department','service_unit','all') NOT NULL DEFAULT 'none',
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_permissions_code (code),
  KEY idx_permissions_module (module, resource)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  KEY idx_ur_role (role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
  user_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  effect ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  reason VARCHAR(255) NULL,
  expires_at DATETIME NULL,
  granted_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_id),
  KEY idx_up_permission (permission_id),
  CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_departments (
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  is_head TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, department_id),
  KEY idx_ud_department (department_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  owner_user_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  module VARCHAR(50) NOT NULL,
  ref_type VARCHAR(50) NULL,
  ref_id BIGINT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  title VARCHAR(255) NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  storage ENUM('google_drive','local') NOT NULL DEFAULT 'google_drive',
  drive_file_id VARCHAR(191) NULL,
  storage_path VARCHAR(500) NULL,
  checksum_sha256 CHAR(64) NULL,
  visibility ENUM('private','department','public') NOT NULL DEFAULT 'private',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_files_uuid (uuid),
  KEY idx_files_ref (ref_type, ref_id),
  KEY idx_files_owner (owner_user_id),
  KEY idx_files_module (module, deleted_at),
  CONSTRAINT fk_files_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_files_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NULL,
  link VARCHAR(500) NULL,
  data JSON NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user (user_id, read_at),
  KEY idx_notifications_created (user_id, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  module VARCHAR(50) NULL,
  entity_type VARCHAR(100) NULL,
  entity_id VARCHAR(64) NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  request_method VARCHAR(10) NULL,
  request_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_user (user_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_action (action),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  value_type ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  description VARCHAR(255) NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  location VARCHAR(255) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  is_all_day TINYINT(1) NOT NULL DEFAULT 0,
  visibility ENUM('public','department','private') NOT NULL DEFAULT 'public',
  department_id INT UNSIGNED NULL,
  color VARCHAR(20) NULL,
  google_event_id VARCHAR(255) NULL,
  google_synced_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_calendar_start (start_at),
  KEY idx_calendar_range (start_at, end_at),
  CONSTRAINT fk_calendar_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_calendar_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  id VARCHAR(128) NOT NULL PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  payload MEDIUMBLOB NOT NULL,
  last_activity INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(191) NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(50) NOT NULL,
  user_agent VARCHAR(255) NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_la_username (username, attempted_at),
  KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pr_token (token_hash),
  KEY idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ph_user (user_id),
  CONSTRAINT fk_ph_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_2fa (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  secret_encrypted TEXT NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  confirmed_at DATETIME NULL,
  last_used_step BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recovery_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rc_user (user_id, code_hash),
  CONSTRAINT fk_rc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_years (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_be SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_years (year_be)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_years (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_be SMALLINT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fiscal_years (year_be)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NULL,
  fiscal_year_id INT UNSIGNED NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  round TINYINT UNSIGNED NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_eval_code (code),
  CONSTRAINT fk_eval_academic FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
  CONSTRAINT fk_eval_fiscal FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลห้องบริการ (รหัสห้องไม่ซ้ำภายในปีการศึกษาเดียวกัน)
CREATE TABLE IF NOT EXISTS service_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(10) NOT NULL COMMENT 'รูปแบบ R + ตัวเลข 3 หลัก เช่น R001',
  academic_year_id INT UNSIGNED NOT NULL,
  name_th VARCHAR(255) NOT NULL,
  grade_level VARCHAR(50) NULL COMMENT 'ระดับชั้นของห้อง เช่น เตรียมความพร้อม',
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_rooms_year_code (academic_year_id, room_code),
  CONSTRAINT fk_service_rooms_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ครูประจำชั้นของห้องบริการ (มีได้มากกว่า 1 คน, sort_order 1 = ครูหลัก)
CREATE TABLE IF NOT EXISTS service_room_teachers (
  service_room_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (service_room_id, employee_id),
  KEY idx_service_room_teachers_employee (employee_id),
  CONSTRAINT fk_service_room_teachers_room FOREIGN KEY (service_room_id) REFERENCES service_rooms (id) ON DELETE CASCADE,
  CONSTRAINT fk_service_room_teachers_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- คำขอใช้สิทธิ์ของเจ้าของข้อมูล (PDPA มาตรา 30-36) ต้องดำเนินการภายใน 30 วัน
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

-- ================================================================ Phase 5A: Academic Module (ทะเบียนนักเรียน)
CREATE TABLE IF NOT EXISTS academic_students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_code VARCHAR(20) NOT NULL COMMENT 'เลขประจำตัวนักเรียน (ตัวเลข)',
  id_type ENUM('citizen','g_code','passport','none') NOT NULL DEFAULT 'citizen' COMMENT 'ประเภทเอกสารแสดงตน',
  national_id_hash CHAR(64) NULL COMMENT 'Blind Index ของเลขบัตร/พาสปอร์ต ใช้ตรวจซ้ำ',
  national_id_encrypted TEXT NULL COMMENT 'AES-256-GCM (Core\\Crypto)',
  national_id_last4 CHAR(4) NULL,
  g_code VARCHAR(20) NULL COMMENT 'เลข G (นักเรียนที่ไม่มีเลขประจำตัวประชาชน)',
  title_name VARCHAR(30) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  nickname VARCHAR(50) NULL,
  first_name_en VARCHAR(100) NULL,
  last_name_en VARCHAR(100) NULL,
  gender ENUM('male','female') NULL,
  birth_date DATE NULL,
  nationality VARCHAR(50) NULL,
  ethnicity VARCHAR(50) NULL,
  religion VARCHAR(50) NULL,
  tribe VARCHAR(50) NULL,
  address_registered TEXT NULL COMMENT 'JSON: ที่อยู่ตามทะเบียนบ้าน',
  address_current TEXT NULL COMMENT 'JSON: ที่อยู่ปัจจุบัน (NULL = ตามทะเบียนบ้าน)',
  travel_km_unpaved DECIMAL(5,1) UNSIGNED NULL COMMENT 'ระยะทางถนนลูกรัง (กม.)',
  travel_km_paved DECIMAL(5,1) UNSIGNED NULL COMMENT 'ระยะทางถนนลาดยาง (กม.)',
  travel_km_waterway DECIMAL(5,1) UNSIGNED NULL COMMENT 'ระยะทางทางน้ำ (กม.)',
  phone VARCHAR(30) NULL,
  parents_status ENUM('together','separated','divorced','father_remarried','mother_remarried','father_deceased','mother_deceased','other') NULL,
  lives_with ENUM('parents','father','mother','guardian') NULL,
  older_brothers TINYINT UNSIGNED NULL COMMENT 'จำนวนพี่ชาย',
  younger_brothers TINYINT UNSIGNED NULL COMMENT 'จำนวนน้องชาย',
  older_sisters TINYINT UNSIGNED NULL COMMENT 'จำนวนพี่สาว',
  younger_sisters TINYINT UNSIGNED NULL COMMENT 'จำนวนน้องสาว',
  child_order TINYINT UNSIGNED NULL COMMENT 'เป็นบุตรคนที่',
  siblings_studying TINYINT UNSIGNED NULL COMMENT 'จำนวนพี่น้องที่กำลังศึกษา',
  scout_level CHAR(2) NULL COMMENT 'ประเภทลูกเสือ (รหัส SET Stu_Scout)',
  scout_group CHAR(1) NULL COMMENT 'เหล่าลูกเสือ (รหัส SET Stu_Groupscout)',
  redcross_status CHAR(1) NULL COMMENT 'ยุวกาชาด (รหัส SET Stu_Redcrossyouth)',
  applied_at DATE NULL COMMENT 'วันที่มาสมัคร',
  admitted_at DATE NULL COMMENT 'วันที่เริ่มรับบริการ',
  status ENUM('applicant','active','suspended','graduated','transferred','discharged','deceased') NOT NULL DEFAULT 'active',
  left_at DATE NULL COMMENT 'วันที่จำหน่าย/จบ/ย้าย',
  left_reason VARCHAR(255) NULL,
  note VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_academic_students_code (student_code),
  UNIQUE KEY uq_academic_students_nid (national_id_hash),
  UNIQUE KEY uq_academic_students_gcode (g_code),
  KEY idx_academic_students_status (status, deleted_at),
  KEY idx_academic_students_name (first_name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_student_guardians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  relation ENUM('father','mother','guardian') NOT NULL,
  relation_note VARCHAR(50) NULL COMMENT 'ความสัมพันธ์กับนักเรียน (กรณีผู้ปกครอง)',
  title_name VARCHAR(30) NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NULL,
  birth_date DATE NULL,
  national_id_encrypted TEXT NULL COMMENT 'AES-256-GCM',
  national_id_last4 CHAR(4) NULL,
  occupation VARCHAR(100) NULL,
  income_per_year INT UNSIGNED NULL,
  phone VARCHAR(30) NULL,
  line_id VARCHAR(100) NULL,
  life_status ENUM('alive','deceased') NOT NULL DEFAULT 'alive',
  is_primary_contact TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_guardians_relation (student_id, relation),
  CONSTRAINT fk_academic_guardians_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_student_enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  service_type ENUM('center_boarding','center_day','service_unit','home','hospital','learning_center') NOT NULL,
  department_id INT UNSIGNED NULL COMMENT 'หน่วยบริการ (กรณีรับบริการนอกศูนย์)',
  service_room_id INT UNSIGNED NULL COMMENT 'ห้องบริการของปีการศึกษานั้น',
  grade_level VARCHAR(50) NULL COMMENT 'ระดับชั้น เช่น เตรียมความพร้อม',
  responsible_employee_id INT UNSIGNED NULL COMMENT 'ครูผู้รับผิดชอบนักเรียน',
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_enrollments_year (student_id, academic_year_id),
  KEY idx_academic_enrollments_scope (academic_year_id, department_id),
  KEY idx_academic_enrollments_teacher (academic_year_id, responsible_employee_id),
  KEY idx_academic_enrollments_room (service_room_id),
  CONSTRAINT fk_academic_enrollments_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE,
  CONSTRAINT fk_academic_enrollments_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE RESTRICT,
  CONSTRAINT fk_academic_enrollments_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_academic_enrollments_room FOREIGN KEY (service_room_id) REFERENCES service_rooms (id) ON DELETE SET NULL,
  CONSTRAINT fk_academic_enrollments_teacher FOREIGN KEY (responsible_employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_student_sensitive (
  student_id INT UNSIGNED NOT NULL PRIMARY KEY,
  data_encrypted MEDIUMTEXT NOT NULL COMMENT 'JSON เข้ารหัส AES-256-GCM: ประเภท/ระดับความพิการ, การคัดกรอง, บัตรคนพิการ, สุขภาพ',
  card_alert_key CHAR(64) NULL COMMENT 'แฮชของระดับแจ้งเตือนบัตรคนพิการที่แจ้งแล้ว',
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_academic_sensitive_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 5B: ประวัติสถานะ และประวัติเลขประจำตัว
CREATE TABLE IF NOT EXISTS academic_student_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(20) NULL COMMENT 'NULL = สถานะแรกเมื่อเพิ่มนักเรียน',
  to_status VARCHAR(20) NOT NULL,
  effective_on DATE NOT NULL COMMENT 'วันที่มีผล',
  reason VARCHAR(255) NULL,
  reference_no VARCHAR(100) NULL COMMENT 'เลขที่หนังสือ/คำสั่งอ้างอิง',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_academic_status_logs_student (student_id, effective_on),
  CONSTRAINT fk_academic_status_logs_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_student_identity_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  id_type VARCHAR(20) NOT NULL COMMENT 'ประเภทเอกสารเดิม',
  national_id_encrypted TEXT NULL COMMENT 'เลขบัตร/พาสปอร์ตเดิม AES-256-GCM',
  national_id_last4 CHAR(4) NULL,
  g_code VARCHAR(20) NULL COMMENT 'เลข G เดิม',
  reason VARCHAR(255) NULL,
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_academic_identity_history_student (student_id, changed_at),
  CONSTRAINT fk_academic_identity_history_student FOREIGN KEY (student_id) REFERENCES academic_students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- งานวิจัยและพัฒนานวัตกรรม (ฝ่ายบริหารวิชาการ)
-- ประเภทงานวิจัย (kind = research) และประเภทสื่อนวัตกรรม (kind = innovation) ผู้ดูแลเพิ่ม/แก้ไขได้
CREATE TABLE IF NOT EXISTS research_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('research','innovation') NOT NULL,
  name VARCHAR(150) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_research_types_name (kind, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- รอบการส่งงาน: 1 รอบต่อ ประเภท + ปีการศึกษา + ภาคเรียน
CREATE TABLE IF NOT EXISTS research_rounds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('research','innovation') NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  opens_on DATE NULL,
  due_on DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_research_rounds_term (kind, academic_year_id, semester),
  CONSTRAINT fk_research_rounds_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- งานวิจัย (ข้อมูลผู้เรียนเก็บเป็นจำนวนเท่านั้น ตามหลัก PDPA)
CREATE TABLE IF NOT EXISTS research_works (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(300) NOT NULL,
  type_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  target_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนกลุ่มเป้าหมาย (คน)',
  keywords VARCHAR(500) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  current_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME NULL COMMENT 'ส่งครั้งแรก',
  status_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_research_works_owner (owner_user_id, deleted_at),
  KEY idx_research_works_term (academic_year_id, semester),
  KEY idx_research_works_status (status),
  CONSTRAINT fk_research_works_owner FOREIGN KEY (owner_user_id) REFERENCES users (id),
  CONSTRAINT fk_research_works_type FOREIGN KEY (type_id) REFERENCES research_types (id),
  CONSTRAINT fk_research_works_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประเภทความพิการของกลุ่มเป้าหมาย (เลือกได้หลายประเภท) ค่าตาม StudentLookup::DISABILITY_TYPES
CREATE TABLE IF NOT EXISTS research_work_disabilities (
  work_id INT UNSIGNED NOT NULL,
  disability_type VARCHAR(30) NOT NULL,
  PRIMARY KEY (work_id, disability_type),
  CONSTRAINT fk_research_work_disabilities_work FOREIGN KEY (work_id) REFERENCES research_works (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ไฟล์ทุกเวอร์ชัน (เก็บใน storage/research นอก public ไม่ลบจริง)
CREATE TABLE IF NOT EXISTS research_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id INT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  storage_path VARCHAR(300) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  note VARCHAR(500) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_research_versions_no (work_id, version_no),
  CONSTRAINT fk_research_versions_work FOREIGN KEY (work_id) REFERENCES research_works (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อเสนอแนะ (ผู้ตรวจเปิดเรื่อง) และการตอบกลับในเรื่องเดียวกัน (parent_id)
CREATE TABLE IF NOT EXISTS research_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  version_no SMALLINT UNSIGNED NULL COMMENT 'เวอร์ชันที่แสดงความเห็น',
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_research_comments_work (work_id, parent_id),
  CONSTRAINT fk_research_comments_work FOREIGN KEY (work_id) REFERENCES research_works (id),
  CONSTRAINT fk_research_comments_parent FOREIGN KEY (parent_id) REFERENCES research_comments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ประวัติการเปลี่ยนสถานะ: ผู้ดำเนินการ วันเวลา หมายเหตุ
CREATE TABLE IF NOT EXISTS research_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id INT UNSIGNED NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  version_no SMALLINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_research_status_logs_work (work_id, id),
  CONSTRAINT fk_research_status_logs_work FOREIGN KEY (work_id) REFERENCES research_works (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- กันแจ้งเตือนกำหนดส่งซ้ำ (alert_key: d7, d3, d1, overdue)
CREATE TABLE IF NOT EXISTS research_round_alerts (
  round_id INT UNSIGNED NOT NULL,
  alert_key VARCHAR(20) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  recipients INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (round_id, alert_key),
  CONSTRAINT fk_research_round_alerts_round FOREIGN KEY (round_id) REFERENCES research_rounds (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สื่อนวัตกรรม: 1 ชิ้นส่งเอกสาร 2 ระยะ (คู่มือการใช้สื่อ → ใช้สื่อ → รายงานการใช้สื่อ)
CREATE TABLE IF NOT EXISTS innovation_media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT UNSIGNED NOT NULL,
  title VARCHAR(300) NOT NULL,
  type_id INT UNSIGNED NOT NULL COMMENT 'research_types kind = innovation',
  academic_year_id INT UNSIGNED NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  plan_start DATE NOT NULL COMMENT 'ช่วงเวลาที่วางแผนใช้สื่อ',
  plan_end DATE NOT NULL,
  actual_start DATE NULL COMMENT 'วันที่ใช้สื่อจริง (จากรายงาน)',
  actual_end DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  manual_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  report_version SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  report_due_on DATE NULL COMMENT 'กำหนดส่งรายงาน = วันสิ้นสุดตามแผน + จำนวนวันที่ตั้งค่า (กำหนดเมื่อคู่มืออนุมัติ)',
  submitted_at DATETIME NULL COMMENT 'ส่งคู่มือครั้งแรก',
  manual_approved_at DATETIME NULL,
  report_submitted_at DATETIME NULL,
  completed_at DATETIME NULL,
  status_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY idx_innovation_media_owner (owner_user_id, deleted_at),
  KEY idx_innovation_media_term (academic_year_id, semester),
  KEY idx_innovation_media_status (status),
  CONSTRAINT fk_innovation_media_owner FOREIGN KEY (owner_user_id) REFERENCES users (id),
  CONSTRAINT fk_innovation_media_type FOREIGN KEY (type_id) REFERENCES research_types (id),
  CONSTRAINT fk_innovation_media_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- จำนวนกลุ่มเป้าหมายแยกตาม 9 ประเภทความพิการ: planned = ตามแผน (คู่มือ), actual = ใช้จริง (รายงาน) เก็บเป็นจำนวนเท่านั้น
CREATE TABLE IF NOT EXISTS innovation_targets (
  media_id INT UNSIGNED NOT NULL,
  phase ENUM('planned','actual') NOT NULL,
  disability_type VARCHAR(30) NOT NULL,
  target_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (media_id, phase, disability_type),
  CONSTRAINT fk_innovation_targets_media FOREIGN KEY (media_id) REFERENCES innovation_media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ไฟล์ทุกเวอร์ชันแยกตามฉบับ (doc: manual = คู่มือ, report = รายงาน) ไม่ลบจริง
CREATE TABLE IF NOT EXISTS innovation_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  doc ENUM('manual','report') NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(150) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  storage_path VARCHAR(300) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  note VARCHAR(500) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_innovation_versions_no (media_id, doc, version_no),
  CONSTRAINT fk_innovation_versions_media FOREIGN KEY (media_id) REFERENCES innovation_media (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS innovation_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  doc ENUM('manual','report') NOT NULL,
  version_no SMALLINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_innovation_comments_media (media_id, parent_id),
  CONSTRAINT fk_innovation_comments_media FOREIGN KEY (media_id) REFERENCES innovation_media (id),
  CONSTRAINT fk_innovation_comments_parent FOREIGN KEY (parent_id) REFERENCES innovation_comments (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS innovation_status_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  doc ENUM('manual','report') NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  version_no SMALLINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_innovation_status_logs_media (media_id, id),
  CONSTRAINT fk_innovation_status_logs_media FOREIGN KEY (media_id) REFERENCES innovation_media (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- กันแจ้งเตือนซ้ำต่อสื่อ (plan_end, d7, d3, d1, overdue)
CREATE TABLE IF NOT EXISTS innovation_alerts (
  media_id INT UNSIGNED NOT NULL,
  alert_key VARCHAR(20) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (media_id, alert_key),
  CONSTRAINT fk_innovation_alerts_media FOREIGN KEY (media_id) REFERENCES innovation_media (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
