<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Audit;
use Core\Controller;
use Core\Database;
use Core\ErrorHandler;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\GoogleWorkspace;
use Core\Rbac;
use Core\Response;
use Core\Session;
use Core\XlsxWriter;
use Modules\Academic\Services\StudentCompleteness;
use Core\Setting;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentProfileReport;
use Modules\Academic\Services\StudentService;
use Modules\Academic\Services\StudentStatusService;
use Modules\CoreAdmin\Services\FileService;

/** Phase 5A: ทะเบียนนักเรียน (ฝ่ายบริหารวิชาการ) */
final class StudentController extends Controller
{
    public function index(): Response
    {
        $filters = $this->filters();

        return $this->view('academic::students/index', [
            'title' => 'ทะเบียนนักเรียน',
            'students' => StudentService::list($filters),
            'counts' => StudentService::statusCounts(),
            'filters' => $filters,
            'options' => StudentService::enrollmentOptions(),
            'currentYearId' => StudentService::currentYearId(),
            'scope' => Rbac::scope('academic.students', 'read'),
            'sensitiveScope' => Rbac::scope('academic.students', 'read_sensitive'),
        ]);
    }

    /** ส่งออก Excel ตามตัวกรองปัจจุบัน (ประเภทความพิการเฉพาะผู้มีสิทธิ์ข้อมูลอ่อนไหว) */
    public function export(): Response
    {
        $filters = $this->filters();
        $students = StudentService::list($filters);
        $withSensitive = Rbac::can('academic.students.read_sensitive_all');
        $year = Database::instance()->fetchColumn('SELECT name FROM academic_years WHERE id = ?', [$filters['year_id'] ?: (StudentService::currentYearId() ?? 0)]);

        $head = ['เลขประจำตัวนักเรียน', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'ชื่อเล่น', 'เพศ', 'วันเกิด', 'อายุ', 'สถานะ',
            'ประเภทการรับบริการ', 'หน่วยบริการ', 'ห้องบริการ', 'ระดับชั้น', 'ครูผู้รับผิดชอบ'];
        if ($withSensitive) {
            $head[] = 'ประเภทความพิการ';
        }
        $rows = [['ทะเบียนนักเรียน ' . ($year ?? ''), 'ข้อมูล ณ ' . thai_date_numeric(date('Y-m-d H:i'), true)], [], $head];
        foreach ($students as $s) {
            $line = [
                $s['student_code'], $s['title_name'], $s['first_name'], $s['last_name'], $s['nickname'],
                StudentLookup::GENDERS[$s['gender']] ?? '', thai_date_numeric($s['birth_date']), StudentLookup::age($s['birth_date']),
                StudentLookup::statusLabel((string) $s['status']), $s['service_type'] ? StudentLookup::serviceLabel($s['service_type']) : '',
                $s['department_name'], $s['room_code'] ? $s['room_code'] . ' ' . $s['room_name'] : '', $s['grade_level'], $s['teacher_name'],
            ];
            if ($withSensitive) {
                $line[] = StudentLookup::DISABILITY_TYPES[$s['disability_type'] ?? ''] ?? '';
            }
            $rows[] = $line;
        }
        $content = (new XlsxWriter())->addSheet('ทะเบียนนักเรียน', $rows)->build();
        Audit::log('academic.students.exported', 'academic', 'academic_students', null, null, [
            'count' => count($students), 'with_sensitive' => $withSensitive, 'filters' => array_filter($filters),
        ]);

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="students-' . date('Ymd') . ".xlsx\"; filename*=UTF-8''" . rawurlencode('ทะเบียนนักเรียน_' . date('Ymd') . '.xlsx'),
        ]);
    }

    public function show(string $id): Response
    {
        return $this->renderShow($this->find($id));
    }

    /** รายงานรายบุคคลสำหรับพิมพ์/บันทึกเป็น PDF (ผ่านหน้าต่างพิมพ์ของเบราว์เซอร์) */
    public function printProfile(string $id): Response
    {
        $student = $this->find($id);
        $report = StudentProfileReport::build($student);
        Audit::log('academic.student.profile_printed', 'academic', 'academic_students', (int) $student['id'], null, ['with_sensitive' => $report['sensitive']]);

        return $this->view('academic::students/print', [
            'title' => $report['title'] . ' ' . $student['student_code'],
            'report' => $report,
            'orgName' => (string) Setting::get('org.name', config('app.org')),
            'photo' => $this->photoOf((int) $student['id']),
        ]);
    }

    /** รายงานรายบุคคลเป็นไฟล์ Excel */
    public function exportProfile(string $id): Response
    {
        $student = $this->find($id);
        $report = StudentProfileReport::build($student);
        $rows = StudentProfileReport::toRows($report, (string) Setting::get('org.name', config('app.org')));
        $content = (new XlsxWriter())->addSheet((string) $student['student_code'], $rows)->build();
        Audit::log('academic.student.profile_exported', 'academic', 'academic_students', (int) $student['id'], null, ['with_sensitive' => $report['sensitive']]);
        $file = 'ข้อมูลนักเรียน_' . $student['student_code'] . '_' . date('Ymd') . '.xlsx';

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'attachment; filename="student-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $student['student_code']) . '-' . date('Ymd')
                . ".xlsx\"; filename*=UTF-8''" . rawurlencode($file),
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm(null);
    }

    public function store(): Response
    {
        $data = $this->validateStudent(true, null);
        $id = StudentService::create($data, true, $this->request->all());
        Session::flash('success', 'เพิ่มนักเรียนเรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . $id);
    }

    public function edit(string $id): Response
    {
        return $this->renderForm($this->find($id));
    }

    public function update(string $id): Response
    {
        $student = $this->find($id);
        $data = $this->validateStudent(false, (int) $student['id']);
        StudentService::update($student, $data, $this->request->all());
        Session::flash('success', 'บันทึกข้อมูลนักเรียนเรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id']);
    }

    public function destroy(string $id): Response
    {
        $student = $this->find($id);
        StudentService::delete($student);
        Session::flash('success', 'ลบข้อมูลนักเรียน ' . StudentService::displayName($student) . ' เรียบร้อยแล้ว');

        return $this->redirect('/academic/students');
    }

    /** POST เพื่อไม่ให้เลขบัตรเต็มติดอยู่ใน URL และแสดงครั้งเดียวโดยไม่เก็บใน Session */
    public function revealNationalId(string $id): Response
    {
        $student = $this->find($id);

        return $this->renderShow($student, StudentService::revealNationalId((int) $student['id']));
    }

    // ---------------------------------------------------------------- สถานะนักเรียน (Phase 5B)

    public function statusStore(string $id): Response
    {
        $student = $this->find($id);
        $data = $this->validate(StudentStatusService::RULES, StudentStatusService::LABELS);
        StudentStatusService::change($student, $data);
        Session::flash('success', 'เปลี่ยนสถานะเป็น "' . StudentLookup::statusLabel((string) $data['to_status']) . '" เรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#status');
    }

    // ---------------------------------------------------------------- ความพิการ/สุขภาพ

    public function healthEdit(string $id): Response
    {
        $student = $this->find($id);
        $health = StudentService::sensitive((int) $student['id']) ?? [];
        $current = $health['screener_employee_id'] ?? null;

        return $this->view('academic::students/health_form', [
            'title' => 'ข้อมูลความพิการและสุขภาพ',
            'student' => $student,
            'health' => $health,
            'teachers' => StudentService::screenerOptions(is_numeric($current) ? (int) $current : null),
        ]);
    }

    public function healthUpdate(string $id): Response
    {
        $student = $this->find($id);
        // ข้อมูลสุขภาพ/ความพิการห้ามค้างอยู่ใน Session แบบไม่เข้ารหัส: หากกรอกผิด ฟอร์มจะแสดงค่าที่บันทึกไว้เดิม
        ErrorHandler::doNotFlash(...array_merge(array_keys(StudentService::sensitiveRules()), ['multiple_disabilities', 'card_types']));
        $data = $this->validate(StudentService::sensitiveRules(), StudentService::SENSITIVE_LABELS);
        StudentService::saveSensitive((int) $student['id'], $data, $this->stringList('multiple_disabilities'), $this->stringList('card_types'));
        Session::flash('success', 'บันทึกข้อมูลความพิการและสุขภาพเรียบร้อยแล้ว (เข้ารหัสแล้ว)');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#health');
    }

    // ---------------------------------------------------------------- การรับบริการ

    public function enrollmentCreate(string $id): Response
    {
        return $this->renderEnrollmentForm($this->find($id), null);
    }

    public function enrollmentStore(string $id): Response
    {
        $student = $this->find($id);
        $data = $this->validate(StudentService::enrollmentRules(), StudentService::labels());
        StudentService::saveEnrollment((int) $student['id'], $data, null);
        Session::flash('success', 'บันทึกการรับบริการเรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#enrollments');
    }

    public function enrollmentEdit(string $id, string $eid): Response
    {
        $student = $this->find($id);

        return $this->renderEnrollmentForm($student, $this->findEnrollment($student, $eid));
    }

    public function enrollmentUpdate(string $id, string $eid): Response
    {
        $student = $this->find($id);
        $enrollment = $this->findEnrollment($student, $eid);
        $data = $this->validate(StudentService::enrollmentRules(), StudentService::labels());
        StudentService::saveEnrollment((int) $student['id'], $data, $enrollment);
        Session::flash('success', 'บันทึกการรับบริการเรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#enrollments');
    }

    public function enrollmentDestroy(string $id, string $eid): Response
    {
        $student = $this->find($id);
        StudentService::deleteEnrollment($this->findEnrollment($student, $eid));
        Session::flash('success', 'ลบข้อมูลการรับบริการเรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#enrollments');
    }

    // ---------------------------------------------------------------- เอกสารแนบ

    public function documentStore(string $id): Response
    {
        $student = $this->find($id);
        $type = (string) $this->request->input('document_type', '');
        if (!isset(StudentLookup::DOCUMENT_TYPES[$type])) {
            throw new ValidationException(['document_type' => ['กรุณาเลือกประเภทเอกสาร']]);
        }
        [$label, $sensitive] = StudentLookup::DOCUMENT_TYPES[$type];
        if ($sensitive && !StudentService::canViewSensitive((int) $student['id'])) {
            throw new HttpException(403); // ผู้ที่ไม่มีสิทธิ์ข้อมูลอ่อนไหวจะอัปโหลดเอกสารที่ตนเองเปิดดูไม่ได้
        }
        $file = $_FILES['file'] ?? [];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['file' => ['กรุณาเลือกไฟล์']]);
        }
        if ($type === 'photo' && !in_array(strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)) {
            throw new ValidationException(['file' => ['รูปถ่ายต้องเป็นไฟล์ JPG หรือ PNG']]);
        }
        $note = trim((string) $this->request->input('note', ''));
        FileService::upload($file, [
            'module' => 'academic',
            'ref_type' => $sensitive ? StudentLookup::REF_TYPE_SENSITIVE : StudentLookup::REF_TYPE,
            'ref_id' => (int) $student['id'],
            'visibility' => 'private',
            'title' => $label . ($note !== '' ? ': ' . mb_substr($note, 0, 150) : ''),
        ]);
        Session::flash('success', 'อัปโหลด' . $label . 'เรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#documents');
    }

    public function documentDestroy(string $id, string $fid): Response
    {
        $student = $this->find($id);
        $file = Database::instance()->fetch(
            'SELECT * FROM files WHERE id = ? AND ref_id = ? AND ref_type IN (?, ?) AND deleted_at IS NULL',
            [(int) $fid, (int) $student['id'], StudentLookup::REF_TYPE, StudentLookup::REF_TYPE_SENSITIVE]
        ) ?? throw new HttpException(404);
        if ($file['ref_type'] === StudentLookup::REF_TYPE_SENSITIVE && !StudentService::canViewSensitive((int) $student['id'])) {
            throw new HttpException(403);
        }
        FileService::delete($file);
        Session::flash('success', 'ลบเอกสาร ' . $file['original_name'] . ' เรียบร้อยแล้ว');

        return $this->redirect('/academic/students/' . (int) $student['id'] . '#documents');
    }

    // ---------------------------------------------------------------- ภายใน

    private function renderShow(array $student, ?string $nationalId = null): Response
    {
        $id = (int) $student['id'];
        $canSensitive = StudentService::canViewSensitive($id);
        $documents = FileService::attachments(StudentLookup::REF_TYPE, $id);
        $sensitiveDocs = $canSensitive ? FileService::attachments(StudentLookup::REF_TYPE_SENSITIVE, $id) : [];
        $photo = $this->photoOf($id, $documents);

        $guardians = StudentService::guardians($id);
        $enrollments = StudentService::enrollments($id);
        $health = $canSensitive ? StudentService::sensitive($id) : null;
        $current = null;
        foreach ($enrollments as $en) {
            if ((int) $en['is_current'] === 1) {
                $current = $en;
            }
        }

        return $this->view('academic::students/show', [
            'title' => 'ข้อมูลนักเรียน',
            'student' => $student,
            'guardians' => $guardians,
            'enrollments' => $enrollments,
            'health' => $health,
            'completeness' => StudentCompleteness::evaluate($student, $guardians, $current, $canSensitive ? $health : false, $photo !== null),
            'statusLogs' => StudentStatusService::logs($id),
            'statusTargets' => StudentStatusService::canChange() ? StudentStatusService::allowedTargets((string) $student['status']) : [],
            'identityHistory' => $canSensitive ? StudentService::identityHistory($id) : [],
            'canSensitive' => $canSensitive,
            'nationalId' => $nationalId,
            'documents' => array_merge($documents, $sensitiveDocs),
            'photo' => $photo,
            'canUpdate' => Rbac::can('academic.students.update_all'),
            'canUpdateHealth' => Rbac::can('academic.students.update_sensitive_all'),
            'canDelete' => Rbac::can('academic.students.delete_all'),
            'driveReady' => GoogleWorkspace::isConfigured(),
            'screenerName' => self::employeeName($canSensitive ? (StudentService::sensitive($id, false)['screener_employee_id'] ?? null) : null),
        ]);
    }

    /**
     * ตรวจข้อมูลนักเรียน + ประเภทความพิการ/กลุ่มเลือด แล้วแจ้งข้อผิดพลาดพร้อมกัน
     * ค่าความพิการ/กลุ่มเลือดเป็นข้อมูลอ่อนไหว จึงไม่เก็บค่าที่กรอกค้างไว้ใน Session
     */
    private function validateStudent(bool $isCreate, ?int $studentId): array
    {
        ErrorHandler::doNotFlash(...StudentService::BASIC_SENSITIVE_INPUTS);
        $errors = [];
        $data = [];
        try {
            $data = $this->validate(StudentService::rules($isCreate, $this->request->all()), StudentService::labels());
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }
        if (StudentService::canEditBasicSensitive($studentId)) {
            try {
                StudentService::basicSensitiveInput($this->request->all());
            } catch (ValidationException $e) {
                $errors += $e->errors();
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $data;
    }

    private function renderForm(?array $student): Response
    {
        $studentId = $student !== null ? (int) $student['id'] : null;
        $canBasic = StudentService::canEditBasicSensitive($studentId);

        return $this->view('academic::students/form', [
            'canEditBasicSensitive' => $canBasic,
            // เปิดฟอร์มแก้ไข = เปิดดูข้อมูลอ่อนไหว (บันทึก Audit)
            'basicSensitive' => $canBasic && $studentId !== null ? (StudentService::sensitive($studentId) ?? []) : [],
            'title' => $student === null ? 'เพิ่มนักเรียน' : 'แก้ไขข้อมูลนักเรียน',
            'student' => $student,
            'guardians' => $student !== null ? StudentService::guardians((int) $student['id']) : [],
            'options' => $student === null ? StudentService::enrollmentOptions() : null,
            'currentYearId' => StudentService::currentYearId(),
            'nextCode' => $student === null ? StudentService::nextStudentCode() : null,
        ]);
    }

    private function renderEnrollmentForm(array $student, ?array $enrollment): Response
    {
        return $this->view('academic::students/enrollment_form', [
            'title' => $enrollment === null ? 'เพิ่มการรับบริการ' : 'แก้ไขการรับบริการ',
            'student' => $student,
            'enrollment' => $enrollment,
            'options' => StudentService::enrollmentOptions($enrollment),
            'currentYearId' => StudentService::currentYearId(),
        ]);
    }

    private function find(string $id): array
    {
        return StudentService::findVisible((int) $id) ?? throw new HttpException(404);
    }

    private function findEnrollment(array $student, string $eid): array
    {
        return StudentService::findEnrollment((int) $student['id'], (int) $eid) ?? throw new HttpException(404);
    }

    private function filters(): array
    {
        $q = $this->request->query('q', '');

        return [
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'status' => $this->choice('status', array_keys(StudentLookup::STATUSES), 'active'),
            'year_id' => (int) $this->request->query('year_id', 0),
            'service_type' => $this->choice('service_type', array_keys(StudentLookup::SERVICE_TYPES)),
            'department_id' => (int) $this->request->query('department_id', 0),
            'room_id' => (int) $this->request->query('room_id', 0),
            'disability' => $this->choice('disability', array_keys(StudentLookup::DISABILITY_TYPES)),
            'mine' => $this->request->query('mine', '') === '1',
            'enrolled' => $this->choice('enrolled', ['no']),
        ];
    }

    /** ค่าที่อนุญาตเท่านั้น; status ส่ง "all" เพื่อดูทุกสถานะ */
    private function choice(string $key, array $allowed, string $default = ''): string
    {
        $value = $this->request->query($key, null);
        if ($value === null) {
            return $default;
        }

        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }

    /** @return list<string> */
    private function stringList(string $key): array
    {
        $raw = $this->request->input($key, []);

        return is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];
    }

    /** ชื่อครูผู้คัดกรอง + เลขทะเบียนผู้คัดกรองคนพิการ (ถ้ามี) */
    /** รูปถ่ายล่าสุดจากเอกสารแนบ (ชื่อขึ้นต้นด้วย "รูปถ่ายนักเรียน" และเป็นไฟล์ภาพ) */
    private function photoOf(int $id, ?array $documents = null): ?array
    {
        $documents ??= FileService::attachments(StudentLookup::REF_TYPE, $id);
        $photoTitle = StudentLookup::DOCUMENT_TYPES['photo'][0];
        foreach (array_reverse($documents) as $doc) {
            if (str_starts_with((string) $doc['title'], $photoTitle) && str_starts_with((string) $doc['mime_type'], 'image/')) {
                return $doc;
            }
        }

        return null;
    }

    private static function employeeName(mixed $id): ?string
    {
        return StudentProfileReport::employeeName($id);
    }
}
