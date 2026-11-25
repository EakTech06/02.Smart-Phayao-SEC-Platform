-- ข้อมูลบุคลากร: เลขทะเบียนผู้คัดกรองคนพิการ
-- รันซ้ำได้
SET NAMES utf8mb4;

ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS screener_reg_no VARCHAR(50) NULL COMMENT 'เลขทะเบียนผู้คัดกรองคนพิการ' AFTER education_major;
