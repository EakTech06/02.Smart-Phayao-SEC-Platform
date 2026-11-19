# ระบบฝ่ายบริหารงานบุคคล (Phase 7 — `personnel_*`) : ผลการตรวจสอบข้อมูลและแบบออกแบบระบบ

> สถานะ: **เอกสารออกแบบ (ยังไม่มีโค้ด)** — ใช้เป็นฐานตกลงขอบเขตก่อนเริ่มพัฒนา Phase 7
> อ้างอิง: เอกสารข้อกำหนด `Smart Phayao SEC Platform V.1-4.pdf` (ข้อ 4, 5, 7.4, 8 และ Roadmap Phase 7)

---

## 1. ผลการตรวจสอบแหล่งข้อมูล

### 1.1 ตำแหน่งไฟล์

- ไม่พบโฟลเดอร์ `ข้อมูล/ฝ่ายบริหารงานบุคคล` ในทุก branch (`main`, `claude/*`)
- โฟลเดอร์ `ข้อมูล/` เคยอยู่ใน repository (commit `ab867dc`, `c7ae321`, `814d310`) และถูกลบออกใน commit `53d59e1`
- ไฟล์ในโฟลเดอร์เดิมเป็นข้อมูลของฝ่ายบริหารงานบุคคล จึงใช้ชุดนี้ในการวิเคราะห์

| ไฟล์ | เนื้อหา | ความสัมพันธ์กับระบบ |
|---|---|---|
| `รายการอบรมของครูปี2568.docx` | รายการอบรมรายบุคคล (~60 คน): ชื่อการอบรม, ว/ด/ป, สถานที่/ผู้จัด, ชั่วโมง, รวมครั้ง/ชั่วโมง | บันทึกการพัฒนาตนเอง (อบรม/ประชุม/สัมมนา) |
| `การพัฒนาตนเองด้านการเป็นวิทยากร.docx` | รายการเป็นวิทยากร/กรรมการ/ให้คำปรึกษา + ชั่วโมง | บันทึกการพัฒนาตนเอง (ด้านวิทยากร/นิเทศ) |
| `จำนวนชั่วโมงการพัฒนาตนเอง จำนวนชั่วโมงขอ.docx` | ตารางที่ 6 (SAR 2.6.4): ชื่อ, ตำแหน่ง, ชั่วโมงพัฒนาตนเอง, ชั่วโมง PLC, นวัตกรรม PLC | รายงานสรุปชั่วโมงพัฒนาตนเอง + PLC |
| `แสดงรางวัลที่ครูได้รับ ปีการศึกษา 2568.docx` | ตารางที่ 45: รางวัลรายบุคคล (ครูดีในดวงใจ, ประเมินสื่อ, วิจัยในชั้นเรียน, ครูผู้ฝึกสอนนักเรียน ฯลฯ) | ทะเบียนผลงาน/รางวัล |
| `ผลงานรางวัลครูย้อนหลัง 3ปี.docx` | ตารางที่ 20: เมทริกซ์ รางวัล × บุคลากร × ปีการศึกษา 2566-2568 × ระดับ (สถานศึกษา/จังหวัด, ภาค, ชาติ) | รายงานรางวัลย้อนหลัง 3 ปี |
| `ตัวอย่าง IDP อรนุช ...ปี 2569.pdf` | แผนพัฒนาตนเองรายบุคคล (ID Plan) 4 ตอน + ลงนามรอง ผอ.ฝ่ายบุคคล / ผอ. | ระบบ ID Plan |
| `Smart Phayao SEC Platform V.1-4.pdf` | ข้อกำหนดทั้งโครงการ | ขอบเขต Phase 7 |

### 1.2 ข้อสังเกตด้านคุณภาพข้อมูล (ต้องจัดการก่อน/ระหว่างนำเข้า)

1. **ชั่วโมงเป็นข้อความอิสระ** เช่น `90 ชั่วโมง30นาที`, `2.30 ชั่วโมง`, `6ชั่วโมง`, `12 ชั่งโมง` → ระบบต้องเก็บเป็น **นาที (INT)** และแสดงผลเป็น "ชม./นาที"
2. **วันที่ไม่เป็นรูปแบบเดียวกัน** (`13สิงหาคม2568`, `ระหว่างวันที่ 6-8 พฤษภาคม 2568`, วัน/เดือน/ปีแยกบรรทัด, เลขไทย `๒๕๖๘`) → เก็บ `start_date`/`end_date` (ค.ศ.) แสดงผลเป็น พ.ศ.
3. **อ้างอิงบุคคลด้วยชื่อ-สกุล** ไม่มีรหัส → ต้องจับคู่กับ `employees.employee_code` ตอนนำเข้า (มีหน้าตรวจทานก่อนยืนยัน)
4. **ตำแหน่ง/วิทยฐานะพิมพ์ผิดหรือไม่ตรงกัน** (`ครูชำนาญการรู`, ลำดับที่ซ้ำ `19`) → ตำแหน่งต้องดึงจาก `employees` ไม่พิมพ์ใหม่ในแต่ละรายการ
5. **ยอดรวมคำนวณด้วยมือ** (`11 ครั้ง 102 ชั่วโมง`) → ระบบคำนวณอัตโนมัติ ไม่ให้กรอกยอดรวม
6. **รางวัลปนหลายประเภท**: รางวัลของครูเอง, ครูผู้ฝึกสอนนักเรียน, ผลประเมินสื่อ/วิจัย/ห้องเรียนคุณภาพ → ต้องมีประเภทรางวัลและระดับรางวัลเป็น master data
7. **ช่วงเวลารายงานไม่ตรงปีการศึกษา**: ID Plan นับการพัฒนาตนเอง `1 เม.ย. 2568 – 31 มี.ค. 2569` ขณะที่ PA/เลื่อนเงินเดือนใช้ปีงบประมาณ (ต.ค.–ก.ย.) → ต้องรองรับ "รอบรายงาน" ที่กำหนดช่วงวันที่เองได้
8. **ข้อความสำคัญซ้ำซ้อนกันหลายเอกสาร** (อบรมรายการเดียวปรากฏทั้งในรายการอบรม, ID Plan, ตารางที่ 6) → บันทึกครั้งเดียว แล้วรายงานทุกฉบับดึงจากแหล่งเดียวกัน

### 1.3 สิ่งที่มีอยู่แล้วในระบบและนำกลับมาใช้ได้

| ของเดิม (Core) | ใช้ใน Phase 7 อย่างไร |
|---|---|
| `employees`, `users` (1:1), `departments` (มี `PERSONNEL` id=4) | ผู้บันทึก/เจ้าของข้อมูลทุกรายการอ้าง `employee_id` |
| `academic_years`, `fiscal_years`, `evaluation_periods` | ปีการศึกษา (รางวัล, PLC, ID Plan), ปีงบประมาณ (การลา, PA, เลื่อนเงินเดือน), รอบประเมิน |
| `files` + `DriveUploader` (Google Shared Drive) | เกียรติบัตร/หลักฐาน/คำสั่ง แนบผ่าน `module='personnel'`, `ref_type`, `ref_id` |
| `notifications`, `Mailer` | แจ้งผลอนุมัติ/ตีกลับ, เตือนส่ง ID Plan/PA |
| `calendar_events` | แสดงวันลา/ไปราชการ/นัด PLC บนปฏิทินกลาง |
| `Rbac` (scope own/department/service_unit/all, deny ชนะ) | สิทธิ์ `personnel.*` ทั้งหมด |
| `Audit` + Redaction | บันทึกการอนุมัติ, การเปิดดูข้อมูลอ่อนไหว (PA/เงินเดือน) |
| `XlsxWriter`, `mpdf/mpdf 8.2`, `print.css` | ส่งออกรายงาน Excel / PDF ภาษาไทย / พิมพ์ A4 |
| รูปแบบนำเข้า preview → confirm ของ `PersonnelImportService` | นำเข้าข้อมูลย้อนหลังปี 2566-2568 |

### 1.4 ข้อสังเกตต่อระบบเดิมที่กระทบ Phase 7

1. **ฟิลด์บุคลากรยังไม่พอสำหรับ ID Plan/PA**: ขาด วันเข้ารับราชการ (ใช้ `start_date` ได้แต่ควรระบุความหมายให้ชัด), อันดับเงินเดือน (คศ.1-คศ.5), วุฒิการศึกษาหลายระดับ (ตอนนี้มี `education_level`/`education_major` ค่าเดียว) → เสนอเพิ่มตาราง `personnel_educations` และคอลัมน์ `salary_grade` (ไม่เก็บอัตราเงินเดือนใน employees)
2. **Soft delete ไม่ตรงข้อกำหนด**: `employees.deleted_at` เป็น `NULL` ได้ ขณะที่ข้อกำหนดข้อ 5/8 ให้ใช้ค่าเริ่มต้น `'9999-12-31 00:00:00'` เพื่อไม่ให้ UNIQUE ชนกัน → ตาราง `personnel_*` ใหม่จะใช้ตามข้อกำหนด ส่วนตารางเดิมควรตัดสินใจแยก
3. `config/app.php` เปิดเฉพาะ `modules => ['core_admin']` → Phase 7 จะเป็นโมดูลใหม่ `modules/personnel/` และเพิ่มลงในรายการ
4. ยังไม่มีบทบาท **เจ้าหน้าที่งานบุคคล** (ผู้ตรวจสอบ/รับรองข้อมูลทั้งองค์กรแต่ไม่ใช่ admin) → เสนอเพิ่ม role `personnel_officer`

---

## 2. ขอบเขตระบบ (ตามข้อกำหนดข้อ 7.4)

| # | ระบบย่อย | ข้อมูลต้นทาง | ลำดับความสำคัญ |
|---|---|---|---|
| P1 | **บันทึกการพัฒนาตนเอง** (อบรม/ประชุม/สัมมนา, วิทยากร/นิเทศ, ศึกษาดูงาน) | มีครบ (ปี 2568) | สูง — 7A |
| P2 | **ทะเบียนผลงาน/รางวัล** | มีครบ (ปี 2566-2568) | สูง — 7A |
| P3 | **PLC** (กลุ่ม, การประชุม, ชั่วโมง, นวัตกรรม) | มีชั่วโมงรวม + ชื่อนวัตกรรม | สูง — 7A |
| P4 | **รายงานฝ่ายบุคคล** (ตาราง SAR 6, 20, 45 และรายบุคคล) | เทียบกับเอกสารเดิมได้ทันที | สูง — 7A |
| P5 | **ID Plan** (ประเมินตนเองตามกรอบ ก.ค.ศ. + แผนพัฒนา + อนุมัติ) | มีตัวอย่าง 1 ฉบับ | กลาง — 7B |
| P6 | **ระบบการลา** | ยังไม่มีข้อมูล | กลาง — 7C |
| P7 | **ขออนุญาตไปราชการ** | ยังไม่มีข้อมูล | กลาง — 7C |
| P8 | **PA (ข้าราชการครู)** / ประเมินผลการปฏิบัติงาน (ลูกจ้าง/พนักงานราชการ) | ยังไม่มีข้อมูล | ต่ำ — 7D |
| P9 | **รายงานเลื่อนเงินเดือน** (ข้อมูลอ่อนไหว) | ยังไม่มีข้อมูล | ต่ำ — 7D |

แนวคิดหลัก: **บันทึกครั้งเดียว ใช้ได้ทุกเอกสาร** — การอบรมหนึ่งรายการถูกใช้ทั้งในรายการอบรม, ตาราง SAR, ID Plan ตอนที่ 2 และหลักฐาน PA โดยไม่ต้องพิมพ์ซ้ำ

---

## 3. กระบวนการทำงาน (Workflow)

### 3.1 บันทึกการพัฒนาตนเอง / รางวัล / PLC

```
ครูบันทึก (draft) ──ส่ง──▶ submitted ──เจ้าหน้าที่งานบุคคล/หัวหน้าฝ่ายตรวจ──▶ verified
                                  └────────── ตีกลับ (ระบุเหตุผล) ──▶ returned ──แก้ไข──▶ submitted
```

- ครูแก้ไข/ลบได้เฉพาะสถานะ `draft` และ `returned` ของตนเอง
- รายงานทางการ (SAR) นับเฉพาะสถานะ `verified`; รายงานส่วนตัวแสดงทุกสถานะพร้อมป้ายสถานะ
- ต้องแนบหลักฐาน (เกียรติบัตร/คำสั่ง) อย่างน้อย 1 ไฟล์ก่อนส่ง (ตั้งค่าได้ใน `settings`)
- เจ้าหน้าที่บันทึกแทนเป็นกลุ่มได้ (อบรมภายในที่ทุกคนเข้าร่วม เช่น "การรักษาวินัยข้าราชการครู" 23 พ.ค. 2568) → สร้าง 1 กิจกรรม + เลือกผู้เข้าร่วมหลายคน สถานะ `verified` ทันที

### 3.2 ID Plan

```
ครูประเมินตนเอง (ตอน 3) → เลือกหลักสูตร/วิธีพัฒนา (ตอน 4) → ระบบดึงข้อมูลตอน 1-2 อัตโนมัติ
→ ส่ง → รอง ผอ.ฝ่ายบริหารงานบุคคล ให้ความเห็น → ผอ. ให้ความเห็น/อนุมัติ → พิมพ์ PDF ฉบับสมบูรณ์
```

### 3.3 การลา / ไปราชการ (7C)

```
ผู้ขอ → หัวหน้าฝ่าย/หน่วยบริการ (ความเห็น) → รอง ผอ. → ผอ. (อนุมัติ) → ลงปฏิทินกลาง + ตัดวันลาคงเหลือ
ไปราชการ: เมื่อกลับมาให้ "รายงานผล" → สร้างบันทึกการพัฒนาตนเองอัตโนมัติ (ถ้าเป็นการอบรม/ประชุม)
```

- สายอนุมัติกำหนดเป็นข้อมูล (ไม่ hardcode) เพื่อรองรับการปฏิบัติหน้าที่แทน

---

## 4. แบบจำลองข้อมูล (ร่าง — ยังไม่ใช่ SQL)

ทุกตาราง: `utf8mb4_unicode_ci`, มี `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at DEFAULT '9999-12-31 00:00:00'`

### 4.1 Master data

| ตาราง | ฟิลด์หลัก | หมายเหตุ |
|---|---|---|
| `personnel_development_types` | code, name_th, counts_as_self_dev, sort_order | `training` (อบรม/ประชุม/สัมมนา), `speaker` (วิทยากร/นิเทศ/ให้คำปรึกษา/กรรมการ), `study_visit` (ศึกษาดูงาน/แลกเปลี่ยนเรียนรู้), `self_study`, `other` — ตรงกับตารางที่ 1-4 ใน ID Plan |
| `personnel_award_levels` | code, name_th, rank_order | `school`, `area` (เขตพื้นที่/จังหวัด), `bureau` (สำนักบริหารงานการศึกษาพิเศษ), `region` (ภาค), `national`, `international` |
| `personnel_award_categories` | code, name_th | `teacher_award`, `student_coach` (ครูผู้ฝึกสอนนักเรียน), `media_evaluation`, `classroom_research`, `quality_classroom`, `innovation`, `other` |
| `personnel_award_programs` | name_th, organizer | ชื่อโครงการรางวัลที่ใช้จัดกลุ่มในรายงาน 3 ปี เช่น "รางวัลทรงคุณค่า สพฐ. (OBEC AWARDS)", "งานศิลปหัตถกรรมนักเรียน", "ครูดีในดวงใจ", "คุรุชนคนคุณธรรม" |
| `personnel_report_periods` | code, name_th, start_date, end_date, academic_year_id, fiscal_year_id | รอบรายงานกำหนดเอง เช่น "ID Plan 2569: 2025-04-01 – 2026-03-31" |
| `personnel_competency_frameworks` / `personnel_competency_items` | framework: code, name, version; item: domain_no (1-4), item_no, name_th | กรอบหลักสูตร ก.ค.ศ./สถาบันคุรุพัฒนา 4 ด้าน (ความรู้, ทักษะ, ความเป็นครู, Pedagogy) — แยก version เพราะกรอบเปลี่ยนได้ |

### 4.2 การพัฒนาตนเอง

| ตาราง | ฟิลด์หลัก |
|---|---|
| `personnel_development_activities` | type_id, title, organizer, location, mode (`onsite`/`online`/`hybrid`), start_date, end_date, minutes, is_group (บันทึกแทนเป็นกลุ่ม), academic_year_id (คำนวณจากวันที่) |
| `personnel_development_records` | activity_id, employee_id, role_in_activity (`participant`/`speaker`/`committee`/`organizer`), minutes (override ได้ถ้าไม่เท่ากันทุกคน), status, submitted_at, verified_by, verified_at, return_reason |
| (ไฟล์แนบ) | ใช้ตาราง `files` กลาง: `module='personnel'`, `ref_type='development_record'` |

แยก activity กับ record เพื่อให้อบรมเดียวกันที่ครูหลายคนไปร่วมไม่ถูกพิมพ์ซ้ำ 60 ครั้ง และสรุปได้ว่า "อบรมเรื่องนี้มีใครเข้าร่วมบ้าง"

### 4.3 ผลงาน/รางวัล

| ตาราง | ฟิลด์หลัก |
|---|---|
| `personnel_awards` | employee_id, program_id (NULL ได้), category_id, level_id, title (ชื่อรางวัลเต็ม), result (เช่น เหรียญทอง ชนะเลิศ / ดีเยี่ยม), issuer, award_date, academic_year_id, semester (1/2/NULL), student_count (กรณีครูผู้ฝึกสอน), status, verified_by |

### 4.4 PLC

| ตาราง | ฟิลด์หลัก |
|---|---|
| `personnel_plc_groups` | academic_year_id, name, topic, leader_employee_id, innovation_title (นวัตกรรม PLC) |
| `personnel_plc_members` | group_id, employee_id, role (`leader`/`member`/`buddy`/`mentor`) |
| `personnel_plc_sessions` | group_id, session_date, minutes, step (Identify/Plan/Do/See/Reflect), summary |
| `personnel_plc_attendances` | session_id, employee_id, attended |

ชั่วโมง PLC ต่อปี = ผลรวม `minutes` ของ session ที่เข้าร่วม → ตรงกับคอลัมน์ "การเข้าร่วม PLC ต่อปี" ในตารางที่ 6 ส่วน "นวัตกรรม PLC" มาจาก `innovation_title` ของกลุ่ม

### 4.5 ID Plan (7B)

| ตาราง | ฟิลด์หลัก |
|---|---|
| `personnel_idp_plans` | employee_id, academic_year_id (ปีที่วางแผน), report_period_id (ช่วงนับผลการพัฒนาปีก่อน), framework_id, snapshot_json (ข้อมูลตอนที่ 1 ณ วันส่ง: ตำแหน่ง, วิทยฐานะ, วุฒิ, อายุราชการ), duties_text (2.2 งานที่ได้รับมอบหมาย), status, deputy_comment, director_comment, approved_at |
| `personnel_idp_workloads` | plan_id, semester, workload_type, hours_per_week (ตารางชั่วโมงสอนตามที่ ก.ค.ศ. กำหนด) |
| `personnel_idp_assessments` | plan_id, item_id, score (1-5), priority_order |
| `personnel_idp_goals` | plan_id, domain_no, course_name, reason, expected_outcome, method, period, budget |

ตอนที่ 2.3 (ผลการพัฒนาตนเองตามตารางที่ 1-4) **ไม่เก็บซ้ำ** ดึงจาก `personnel_development_records` + `personnel_awards` ตามช่วง `report_period_id`

### 4.6 การลา / ไปราชการ (7C)

| ตาราง | ฟิลด์หลัก |
|---|---|
| `personnel_leave_types` | code (`sick`, `personal`, `vacation`, `maternity`, `paternity`, `ordination`, `study` ...), name_th, max_days_per_year, needs_medical_cert_after_days, applies_to_employment_types |
| `personnel_leave_balances` | employee_id, fiscal_year_id, leave_type_id, entitled_days, carried_over_days, used_days |
| `personnel_leave_requests` | request_no, employee_id, leave_type_id, start_date, end_date, half_day (`am`/`pm`/NULL), working_days, reason, contact_info, substitute_employee_id, status |
| `personnel_travel_requests` | request_no, employee_id, purpose, destination, start_datetime, end_datetime, vehicle, companions (ตารางลูก), budget_source, status, report_text (รายงานผลหลังกลับ), development_record_id |
| `personnel_approval_steps` | ref_type (`leave`/`travel`/`idp`), ref_id, step_no, approver_role/approver_user_id, decision, comment, decided_at |
| `personnel_holidays` | holiday_date, name_th (ใช้คำนวณวันทำการ) |

### 4.7 PA / ประเมิน / เลื่อนเงินเดือน (7D — ข้อมูลอ่อนไหว)

| ตาราง | ฟิลด์หลัก |
|---|---|
| `personnel_pa_agreements` | employee_id, fiscal_year_id, task_text (ข้อตกลงในการพัฒนางาน), challenge_issue (ประเด็นท้าทาย), status |
| `personnel_pa_evaluations` | agreement_id, evaluation_period_id (รอบ 1 ต.ค.–มี.ค. / รอบ 2 เม.ย.–ก.ย.), evaluator_id, scores_json, total_score, result_level |
| `personnel_performance_evaluations` | สำหรับลูกจ้าง/พนักงานราชการ: employee_id, evaluation_period_id, scores_json, total_score |
| `personnel_salary_promotions` | employee_id, evaluation_period_id, percent / step, quota_group, `amount_encrypted` (AES-256-GCM) |

เงินเดือนและผลประเมินอยู่ในตารางแยก ไม่อยู่ใน `employees` เพื่อแยกสิทธิ์ระดับตาราง/ฟิลด์ตามข้อกำหนดข้อ 4

---

## 5. สิทธิ์การใช้งาน (RBAC)

รูปแบบเดิม `module.resource.action_scope`

| Permission | teacher/staff | department_head / service_unit_head | personnel_officer (ใหม่) | deputy_director | director |
|---|---|---|---|---|---|
| `personnel.developments.read_own` / `create_own` / `update_own` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `personnel.developments.read_department` | | ✓ | | | |
| `personnel.developments.read_all` / `verify_all` / `create_all` (บันทึกกลุ่ม) | | | ✓ | อ่าน | อ่าน |
| `personnel.awards.*` (โครงสร้างเดียวกับ developments) | own | department | all + verify | อ่าน all | อ่าน all |
| `personnel.plc.read_own` / `manage_own` (หัวหน้ากลุ่ม) / `manage_all` | own | | all | | |
| `personnel.idp.create_own` / `comment_all` / `approve_all` | own | | อ่าน all | comment | approve |
| `personnel.leave.request_own` / `approve_department` / `approve_all` / `manage_all` | own | approve dept | manage | approve | approve |
| `personnel.travel.*` (เหมือน leave) | own | approve dept | manage | approve | approve |
| `personnel.pa.read_own` / `evaluate_department` / `read_sensitive_all` | own | evaluate | | ✓ | ✓ |
| `personnel.salary.read_sensitive_all` | | | | | ✓ (บันทึก Audit ทุกครั้ง) |
| `personnel.masters.manage_all` (ประเภท, ระดับรางวัล, กรอบ ก.ค.ศ., วันหยุด) | | | ✓ | | |
| `personnel.reports.view_department` / `view_all` | | dept | all | all | all |

กฎเพิ่มเติม: ทุก query ที่รับ `id` ต้องผ่าน scope (own = `employee_id` ของผู้ใช้) เพื่อป้องกัน IDOR; ผู้อนุมัติอนุมัติคำขอของตนเองไม่ได้

---

## 6. รายงาน (ต้องออกได้ตรงกับเอกสารเดิม)

| รหัส | รายงาน | แหล่งข้อมูล | รูปแบบ |
|---|---|---|---|
| R1 | ตารางที่ 6 — ชั่วโมงพัฒนาตนเอง + ชั่วโมง PLC + นวัตกรรม PLC รายบุคคล (SAR 2.6.4) | developments (verified) + plc | จอ / Excel / PDF A4 |
| R2 | รายการอบรมของครู ปีการศึกษา X (รายบุคคล + รวมครั้ง/ชั่วโมง) | developments `type=training` | จอ / Excel / PDF |
| R3 | การพัฒนาตนเองด้านการเป็นวิทยากร | developments `type=speaker` | จอ / Excel / PDF |
| R4 | ตารางที่ 45 — รางวัลที่ครูได้รับ ปีการศึกษา X | awards | จอ / Excel / PDF |
| R5 | ตารางที่ 20 — รางวัลย้อนหลัง 3 ปีการศึกษา × ระดับ (เมทริกซ์ ✓) | awards จัดกลุ่มตาม program × level | Excel / PDF แนวนอน |
| R6 | แฟ้มสะสมงานรายบุคคล (การพัฒนา + รางวัล + PLC ตามช่วงวันที่) | ทั้งหมด | PDF |
| R7 | ID Plan ฉบับพิมพ์ (ตอน 1-4 + ช่องลงนาม) | idp + developments + awards | PDF |
| R8 | สถิติการลา รายบุคคล/รายฝ่าย ต่อปีงบประมาณ | leave | Excel |
| R9 | สรุปผล PA / ประกอบการเลื่อนเงินเดือน (จำกัดสิทธิ์) | pa + salary | Excel (มี Audit) |

Dashboard (ต่อยอด Phase 4): ชั่วโมงพัฒนาตนเองเฉลี่ยต่อคน, คนที่ยังต่ำกว่าเกณฑ์ชั่วโมง, จำนวนรางวัลตามระดับ, รายการรอตรวจ/รออนุมัติ, คำขอลาวันนี้

---

## 7. การนำเข้าข้อมูลเดิม (ปีการศึกษา 2566-2568)

1. ไม่ parse ไฟล์ .docx โดยตรง (ตารางผสาน cell มาก ผิดพลาดง่าย) → ทำ **แม่แบบ CSV/XLSX** 3 ชุด: การพัฒนาตนเอง, รางวัล, PLC
2. เจ้าหน้าที่คัดลอกข้อมูลจาก Word ลงแม่แบบ (ช่องละ 1 รายการ, ชั่วโมงเป็นตัวเลข, วันที่แยกคอลัมน์)
3. หน้านำเข้า: อัปโหลด → ระบบจับคู่ชื่อ-สกุลกับ `employees` (ตัดคำนำหน้า, เทียบแบบไม่สนช่องว่าง) → แสดงแถวที่จับคู่ไม่ได้/ชั่วโมงหรือวันที่อ่านไม่ได้ → ยืนยัน
4. ข้อมูลนำเข้าตั้งสถานะ `verified` และบันทึก Audit ว่ามาจากการนำเข้า
5. ตรวจสอบผล: ออกรายงาน R1, R4, R5 แล้วเทียบตัวเลขกับเอกสารเดิมทีละคน

---

## 8. PDPA และความปลอดภัย

- เกียรติบัตร/คำสั่งมีข้อมูลส่วนบุคคล → เก็บใน Google Shared Drive ผ่าน `files` (visibility `private`) ดาวน์โหลดผ่าน PHP proxy ที่ตรวจ scope
- ใบรับรองแพทย์ (การลาป่วย) = ข้อมูลสุขภาพ → permission แยก, ไม่แสดงในรายงานรวม, Audit เมื่อเปิดดู
- PA, ผลประเมิน, เงินเดือน → permission ระดับ `read_sensitive`, จำนวนเงินเข้ารหัสผ่าน `Core\Crypto`, Audit ทุกการเปิดดู/ส่งออก, Redact ใน audit log
- รายงานส่วนตัวของครูเป็นสิทธิ์ `own` เสมอ (ไม่เปิดดูของเพื่อนร่วมงานได้)
- ระยะเวลาเก็บรักษา: กำหนดใน `settings` (เช่น ข้อมูลการลา 5 ปีงบประมาณ) — รอยืนยันจากหน่วยงาน

---

## 9. โครงสร้างโมดูล (เมื่อเริ่มเขียนโค้ด)

```
modules/personnel/
  routes.php
  controllers/   DevelopmentController, AwardController, PlcController, IdpController,
                 LeaveController, TravelController, ApprovalController, MasterController,
                 ReportController, ImportController, Api/*
  services/      DevelopmentService, AwardService, PlcService, IdpService, LeaveService,
                 WorkingDayCalculator, ApprovalService, PersonnelReportService, ImportService
  models/        ...Model.php ตามตาราง
  views/         developments/, awards/, plc/, idp/, leave/, travel/, approvals/, reports/, masters/
database/migrations/2026_xx_xx_phase7a_personnel.sql   (7A)
database/migrations/2026_xx_xx_phase7b_idp.sql         (7B) ...
public/assets/js/personnel-*.js
```

เมนู: เพิ่มกลุ่ม "ฝ่ายบริหารงานบุคคล" ใน `templates/sidebar.php` (พัฒนาตนเอง, ผลงาน/รางวัล, PLC, ID Plan, การลา, ไปราชการ, รออนุมัติ, รายงาน, ตั้งค่า)

---

## 10. แผนการพัฒนาที่เสนอ

| รอบ | ขอบเขต | เกณฑ์ผ่าน |
|---|---|---|
| **7A** | master data, การพัฒนาตนเอง, รางวัล, PLC, ตรวจรับรอง, นำเข้าข้อมูลเดิม, รายงาน R1-R6 | ออกตารางที่ 6, 20, 45 ปี 2568 ได้ตรงกับเอกสาร Word เดิม |
| **7B** | ID Plan (กรอบ ก.ค.ศ., ประเมินตนเอง, แผน, ความเห็นรอง ผอ./ผอ.), R7 | พิมพ์ ID Plan ได้ครบ 4 ตอนเทียบกับตัวอย่าง |
| **7C** | การลา, ไปราชการ, สายอนุมัติ, วันหยุด, ปฏิทิน, R8 | ทดสอบวันลาคงเหลือ/สะสม และสายอนุมัติแทน |
| **7D** | PA, ประเมินลูกจ้าง, เลื่อนเงินเดือน, R9 | ทดสอบสิทธิ์ข้อมูลอ่อนไหว + Audit |

แต่ละรอบต้องผ่าน test case: RBAC/scope (own/department/all), IDOR, CSRF, XSS และหน้าจอมือถือ (ตารางแปลงเป็น card)

---

## 11. คำถามที่ต้องยืนยันกับฝ่ายบริหารงานบุคคลก่อนเริ่ม 7A

1. มีไฟล์เพิ่มเติมที่ตั้งใจใส่ใน `ข้อมูล/ฝ่ายบริหารงานบุคคล` หรือไม่ (เช่น แบบฟอร์มใบลา, แบบขออนุญาตไปราชการ, แบบ PA, ข้อมูลรางวัลปี 2566-2567 ฉบับเต็ม)
2. **ช่วงเวลานับชั่วโมงพัฒนาตนเอง** ใช้ 1 เม.ย.–31 มี.ค. (ตาม ID Plan) หรือปีการศึกษา หรือปีงบประมาณ — หรือต้องใช้ทั้งหมดตามแต่ละรายงาน
3. มี **เกณฑ์ขั้นต่ำ** ชั่วโมงพัฒนาตนเอง/PLC ต่อปีหรือไม่ (เพื่อแจ้งเตือนคนที่ยังไม่ครบ)
4. ใครเป็นผู้ **ตรวจรับรอง** บันทึกการพัฒนาตนเอง/รางวัล: เจ้าหน้าที่งานบุคคล, หัวหน้าฝ่าย หรือ รอง ผอ.
5. สายอนุมัติการลาและไปราชการจริงของศูนย์ฯ มีกี่ขั้น และหน่วยบริการต่างอำเภอผ่านหัวหน้าหน่วยก่อนหรือไม่
6. รางวัล "ครูผู้ฝึกสอนนักเรียน" ต้องเชื่อมกับข้อมูลนักเรียน (โมดูลวิชาการ Phase 5) หรือบันทึกเป็นข้อความพอ
7. ต้องการให้ระบบ PA ทำได้แค่ "เก็บข้อตกลงและผลคะแนน" หรือต้องทำแบบประเมินกรอกคะแนนรายตัวชี้วัดในระบบ
