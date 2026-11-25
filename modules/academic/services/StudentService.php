<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Auth;
use Core\Crypto;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;
use Core\Setting;

/**
 * Phase 5A: ทะเบียนนักเรียน
 *
 * Data Scope (academic.students.{read|read_sensitive}_{scope}):
 *   all          ทุกคน
 *   service_unit นักเรียนที่รับบริการในปีการศึกษาปัจจุบันกับหน่วยบริการที่ผู้ใช้สังกัด (รวมหน่วยย่อย)
 *   department   นักเรียนที่รับบริการในปีการศึกษาปัจจุบันกับฝ่าย/งานที่ผู้ใช้สังกัด
 *   own          นักเรียนที่ผู้ใช้เป็นครูผู้รับผิดชอบ หรือครูประจำชั้นของห้องบริการ ในปีการศึกษาปัจจุบัน
 * ข้อมูลความพิการ/สุขภาพและเลขบัตรเต็มใช้สิทธิ์ read_sensitive แยกต่างหาก และบันทึก Audit ทุกครั้งที่เปิดดู
 */
final class StudentService
{
    public const CODE_PATTERN = '/^\d{3,10}$/';

    public const LABELS = [
        'student_code' => 'เลขประจำตัวนักเรียน', 'id_type' => 'ประเภทเอกสารแสดงตน', 'national_id' => 'เลขประจำตัวประชาชน/หนังสือเดินทาง',
        'g_code' => 'เลข G', 'title_name' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น',
        'first_name_en' => 'ชื่อ (ภาษาอังกฤษ)', 'last_name_en' => 'นามสกุล (ภาษาอังกฤษ)', 'gender' => 'เพศ', 'birth_date' => 'วันเกิด',
        'nationality' => 'สัญชาติ', 'ethnicity' => 'เชื้อชาติ', 'religion' => 'ศาสนา', 'tribe' => 'ชนเผ่า', 'phone' => 'เบอร์โทรศัพท์',
        'parents_status' => 'สถานภาพบิดา-มารดา', 'lives_with' => 'ปัจจุบันอาศัยอยู่กับ', 'applied_at' => 'วันที่มาสมัคร',
        'admitted_at' => 'วันที่เริ่มรับบริการ', 'status' => 'สถานะ', 'left_at' => 'วันที่ออกจากการรับบริการ',
        'left_reason' => 'สาเหตุที่ออก', 'note' => 'หมายเหตุ',
        'academic_year_id' => 'ปีการศึกษา', 'service_type' => 'ประเภทการรับบริการ', 'department_id' => 'หน่วยบริการ',
        'service_room_id' => 'ห้องบริการ', 'grade_level' => 'ระดับชั้น', 'responsible_employee_id' => 'ครูผู้รับผิดชอบ',
        'scout_level' => 'ประเภทลูกเสือ', 'scout_group' => 'เหล่าลูกเสือ', 'redcross_status' => 'ยุวกาชาด',
        'identity_change_reason' => 'เหตุผลที่เปลี่ยนเลขประจำตัว',
    ];

    private const STUDENT_FIELDS = [
        'id_type', 'g_code', 'title_name', 'first_name', 'last_name', 'nickname', 'first_name_en', 'last_name_en', 'gender',
        'birth_date', 'nationality', 'ethnicity', 'religion', 'tribe', 'phone', 'parents_status', 'lives_with',
        'applied_at', 'admitted_at', 'status', 'left_at', 'left_reason', 'note',
        'older_brothers', 'younger_brothers', 'older_sisters', 'younger_sisters', 'child_order', 'siblings_studying',
        'scout_level', 'scout_group', 'redcross_status', 'travel_km_unpaved', 'travel_km_paved', 'travel_km_waterway',
    ];

    /** ฟิลด์สถานะ: กำหนดได้เฉพาะตอนเพิ่มนักเรียน หลังจากนั้นเปลี่ยนผ่าน StudentStatusService เท่านั้น */
    private const STATUS_FIELDS = ['status', 'left_at', 'left_reason'];

    private const GUARDIAN_FIELDS = ['title_name', 'first_name', 'last_name', 'birth_date', 'occupation', 'income_per_year', 'phone', 'line_id', 'life_status', 'relation_note'];

    /** @var array<int, ?int> */
    private static array $employeeIdCache = [];

    // ================================================================ Data Scope

    public static function currentYearId(): ?int
    {
        $id = Database::instance()->fetchColumn('SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1');

        return $id !== null ? (int) $id : null;
    }

    public static function canList(?int $userId = null): bool
    {
        return Rbac::scope('academic.students', 'read', $userId) !== null;
    }

    /**
     * เงื่อนไข SQL (alias นักเรียน = s) ของนักเรียนที่ผู้ใช้มีสิทธิ์ตาม action
     * @param 'read'|'read_sensitive' $action
     * @return array{0:string, 1:list<int>}|null null = ไม่มีสิทธิ์เลย
     */
    public static function scopeCondition(string $action = 'read', ?int $userId = null): ?array
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return null;
        }
        $parts = [];
        $params = [];
        $year = self::currentYearId() ?? 0;
        foreach (['all', 'service_unit', 'department', 'own'] as $scope) {
            if (!Rbac::can("academic.students.{$action}_{$scope}", $userId)) {
                continue;
            }
            if ($scope === 'all') {
                return ['1 = 1', []];
            }
            if ($scope === 'own') {
                $employeeId = self::employeeIdOf($userId);
                if ($employeeId !== null) {
                    $parts[] = 'EXISTS (SELECT 1 FROM academic_student_enrollments xo
                        LEFT JOIN service_room_teachers ro ON ro.service_room_id = xo.service_room_id AND ro.employee_id = ?
                        WHERE xo.student_id = s.id AND xo.academic_year_id = ?
                          AND (xo.responsible_employee_id = ? OR ro.employee_id IS NOT NULL))';
                    array_push($params, $employeeId, $year, $employeeId);
                }
                continue;
            }
            $ids = $scope === 'department' ? Rbac::departmentIds($userId) : Rbac::serviceUnitDepartmentIds($userId);
            if ($ids !== []) {
                $parts[] = 'EXISTS (SELECT 1 FROM academic_student_enrollments xd
                    WHERE xd.student_id = s.id AND xd.academic_year_id = ?
                      AND xd.department_id IN (' . implode(',', array_fill(0, count($ids), '?')) . '))';
                array_push($params, $year, ...$ids);
            }
        }
        if ($parts === []) {
            // มีสิทธิ์ระดับ own/department แต่ยังไม่ได้ผูกบุคลากร/ฝ่าย: เห็นรายการว่าง (ไม่ใช่ 403)
            return Rbac::scope('academic.students', $action, $userId) !== null ? ['1 = 0', []] : null;
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    public static function canView(int $studentId, ?int $userId = null, string $action = 'read'): bool
    {
        $cond = self::scopeCondition($action, $userId);
        if ($cond === null) {
            return false;
        }

        return Database::instance()->fetchColumn(
            "SELECT 1 FROM academic_students s WHERE s.id = ? AND s.deleted_at IS NULL AND {$cond[0]}",
            array_merge([$studentId], $cond[1])
        ) !== null;
    }

    public static function canViewSensitive(int $studentId, ?int $userId = null): bool
    {
        return self::canView($studentId, $userId, 'read_sensitive');
    }

    /** นโยบายไฟล์แนบ (ลงทะเบียนกับ FileService): เอกสารอ่อนไหวต้องมีสิทธิ์ read_sensitive */
    public static function canViewDocument(int $studentId, int $userId, bool $sensitive): bool
    {
        return $sensitive ? self::canViewSensitive($studentId, $userId) : self::canView($studentId, $userId);
    }

    public static function employeeIdOf(?int $userId = null): ?int
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return null;
        }
        if (!array_key_exists($userId, self::$employeeIdCache)) {
            $id = Database::instance()->fetchColumn('SELECT employee_id FROM users WHERE id = ?', [$userId]);
            self::$employeeIdCache[$userId] = $id !== null ? (int) $id : null;
        }

        return self::$employeeIdCache[$userId];
    }

    // ================================================================ อ่านข้อมูล

    /**
     * รายการนักเรียนในสิทธิ์ พร้อมการรับบริการของปีที่เลือก
     * @return list<array>
     */
    public static function list(array $filters): array
    {
        $cond = self::scopeCondition('read') ?? throw new HttpException(403);
        $sensitive = self::scopeCondition('read_sensitive');
        $yearId = (int) ($filters['year_id'] ?? 0) ?: (self::currentYearId() ?? 0);

        $where = ['s.deleted_at IS NULL', $cond[0]];
        $params = $cond[1];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $q = (string) $filters['q'];
            $digits = (string) preg_replace('/\D/', '', $q);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $or = 's.student_code LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.nickname LIKE ?
                   OR CONCAT(s.first_name, \' \', s.last_name) LIKE ? OR s.g_code LIKE ?';
            array_push($params, $like, $like, $like, $like, $like, $like);
            if (strlen($digits) === 13) {
                $or .= ' OR s.national_id_hash = ?';
                $params[] = self::nationalIdHash($digits);
            }
            $where[] = "({$or})";
        }
        foreach (['service_type' => 'en.service_type', 'department_id' => 'en.department_id', 'room_id' => 'en.service_room_id'] as $key => $col) {
            if (!empty($filters[$key])) {
                $where[] = "{$col} = ?";
                $params[] = $filters[$key];
            }
        }
        if (!empty($filters['mine'])) {
            $where[] = '(en.responsible_employee_id = ? OR EXISTS (SELECT 1 FROM service_room_teachers mt WHERE mt.service_room_id = en.service_room_id AND mt.employee_id = ?))';
            $emp = self::employeeIdOf() ?? 0;
            array_push($params, $emp, $emp);
        }
        if (($filters['enrolled'] ?? '') === 'no') {
            $where[] = 'en.id IS NULL';
        }

        // สิทธิ์ดูข้อมูลอ่อนไหวคำนวณรายแถว: ถอดรหัสเฉพาะแถวที่มีสิทธิ์
        $canSensitive = $sensitive === null ? '0' : "(CASE WHEN {$sensitive[0]} THEN 1 ELSE 0 END)";
        $rows = Database::instance()->fetchAll(
            "SELECT * FROM (
             SELECT s.id, s.student_code, s.title_name, s.first_name, s.last_name, s.nickname, s.gender, s.birth_date,
                    s.status, s.g_code, s.id_type, s.national_id_last4,
                    en.id AS enrollment_id, en.service_type, en.grade_level, d.name_th AS department_name,
                    r.room_code, r.name_th AS room_name,
                    TRIM(CONCAT(COALESCE(t.title_name, ''), t.first_name, ' ', t.last_name)) AS teacher_name,
                    {$canSensitive} AS can_sensitive, sv.data_encrypted AS sensitive_blob
             FROM academic_students s
             LEFT JOIN academic_student_enrollments en ON en.student_id = s.id AND en.academic_year_id = ?
             LEFT JOIN departments d ON d.id = en.department_id
             LEFT JOIN service_rooms r ON r.id = en.service_room_id
             LEFT JOIN employees t ON t.id = en.responsible_employee_id
             LEFT JOIN academic_student_sensitive sv ON sv.student_id = s.id
             WHERE " . implode(' AND ', $where) . '
             ) x ORDER BY x.student_code',
            array_merge($sensitive[1] ?? [], [$yearId], $params)
        );

        $disability = (string) ($filters['disability'] ?? '');
        $out = [];
        foreach ($rows as $row) {
            $row['sensitive_visible'] = (int) $row['can_sensitive'] === 1;
            $data = $row['sensitive_visible'] && $row['sensitive_blob'] !== null ? self::decryptSensitive((string) $row['sensitive_blob']) : null;
            unset($row['sensitive_blob'], $row['can_sensitive']);
            $row['disability_type'] = $data['disability_type'] ?? null;
            if ($disability !== '' && $row['disability_type'] !== $disability) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @return array<string,int> จำนวนตามสถานะ (ในสิทธิ์) */
    public static function statusCounts(): array
    {
        $cond = self::scopeCondition('read') ?? throw new HttpException(403);
        $counts = array_fill_keys(array_keys(StudentLookup::STATUSES), 0);
        foreach (Database::instance()->fetchAll(
            "SELECT s.status, COUNT(*) AS n FROM academic_students s WHERE s.deleted_at IS NULL AND {$cond[0]} GROUP BY s.status",
            $cond[1]
        ) as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }

        return $counts;
    }

    /** จำนวนนักเรียนที่กำลังรับบริการในปีการศึกษาปัจจุบัน (ในสิทธิ์) สำหรับแดชบอร์ด */
    public static function activeEnrolledCount(): ?int
    {
        $cond = self::scopeCondition('read');
        if ($cond === null) {
            return null;
        }

        return (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM academic_students s
             JOIN academic_student_enrollments en ON en.student_id = s.id AND en.academic_year_id = ?
             WHERE s.deleted_at IS NULL AND s.status = 'active' AND {$cond[0]}",
            array_merge([self::currentYearId() ?? 0], $cond[1])
        );
    }

    /** ป้องกัน IDOR: คืน null ทั้งกรณีไม่พบและไม่มีสิทธิ์ */
    public static function findVisible(int $id): ?array
    {
        if (!self::canView($id)) {
            return null;
        }
        $row = Database::instance()->fetch(
            'SELECT s.*, s.national_id_encrypted IS NOT NULL AS has_national_id,
                    cu.username AS created_by_name, uu.username AS updated_by_name
             FROM academic_students s
             LEFT JOIN users cu ON cu.id = s.created_by
             LEFT JOIN users uu ON uu.id = s.updated_by
             WHERE s.id = ? AND s.deleted_at IS NULL',
            [$id]
        );
        if ($row === null) {
            return null;
        }
        unset($row['national_id_encrypted'], $row['national_id_hash']);
        $row['address_registered'] = self::jsonArray($row['address_registered']);
        $row['address_current'] = self::jsonArray($row['address_current']);

        return $row;
    }

    /** @return array<string, array> relation => แถว (เลขบัตรผู้ปกครองแสดงเฉพาะ 4 หลักท้าย) */
    public static function guardians(int $studentId): array
    {
        $out = [];
        foreach (Database::instance()->fetchAll(
            'SELECT id, relation, relation_note, title_name, first_name, last_name, birth_date, national_id_last4,
                    occupation, income_per_year, phone, line_id, life_status, is_primary_contact
             FROM academic_student_guardians WHERE student_id = ?',
            [$studentId]
        ) as $row) {
            $out[$row['relation']] = $row;
        }

        return $out;
    }

    /** @return list<array> ประวัติการรับบริการทุกปีการศึกษา (ใหม่สุดก่อน) */
    public static function enrollments(int $studentId): array
    {
        return Database::instance()->fetchAll(
            "SELECT en.*, ay.name AS year_name, ay.year_be, ay.is_current, d.name_th AS department_name,
                    r.room_code, r.name_th AS room_name,
                    TRIM(CONCAT(COALESCE(t.title_name, ''), t.first_name, ' ', t.last_name)) AS teacher_name, t.status AS teacher_status,
                    (SELECT GROUP_CONCAT(TRIM(CONCAT(COALESCE(h.title_name, ''), h.first_name, ' ', h.last_name))
                            ORDER BY st.sort_order SEPARATOR ', ')
                     FROM service_room_teachers st JOIN employees h ON h.id = st.employee_id
                     WHERE st.service_room_id = en.service_room_id) AS homeroom_name
             FROM academic_student_enrollments en
             JOIN academic_years ay ON ay.id = en.academic_year_id
             LEFT JOIN departments d ON d.id = en.department_id
             LEFT JOIN service_rooms r ON r.id = en.service_room_id
             LEFT JOIN employees t ON t.id = en.responsible_employee_id
             WHERE en.student_id = ?
             ORDER BY ay.year_be DESC",
            [$studentId]
        );
    }

    public static function findEnrollment(int $studentId, int $enrollmentId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM academic_student_enrollments WHERE id = ? AND student_id = ?',
            [$enrollmentId, $studentId]
        );
    }

    // ================================================================ ข้อมูลอ่อนไหว

    /** ถอดรหัสข้อมูลความพิการ/สุขภาพ (ต้องผ่าน canViewSensitive ก่อน) และบันทึก Audit */
    public static function sensitive(int $studentId, bool $audit = true): ?array
    {
        if (!self::canViewSensitive($studentId)) {
            throw new HttpException(403);
        }
        $blob = Database::instance()->fetchColumn('SELECT data_encrypted FROM academic_student_sensitive WHERE student_id = ?', [$studentId]);
        if ($audit) {
            Audit::log('academic.student.sensitive_viewed', 'academic', 'academic_students', $studentId);
        }

        return $blob !== null ? self::decryptSensitive((string) $blob) : null;
    }

    public static function revealNationalId(int $studentId): ?string
    {
        if (!self::canViewSensitive($studentId)) {
            throw new HttpException(403);
        }
        $cipher = Database::instance()->fetchColumn('SELECT national_id_encrypted FROM academic_students WHERE id = ?', [$studentId]);
        Audit::log('academic.student.national_id_viewed', 'academic', 'academic_students', $studentId);

        return is_string($cipher) && $cipher !== '' ? Crypto::decrypt($cipher) : null;
    }

    public static function sensitiveRules(): array
    {
        $date = 'nullable|date';

        return [
            'disability_type' => 'nullable|in:' . implode(',', array_keys(StudentLookup::DISABILITY_TYPES)),
            'disability_level' => 'nullable|in:' . implode(',', array_keys(StudentLookup::DISABILITY_LEVELS)),
            'disability_description' => 'nullable|max:1000',
            'iq' => 'nullable|integer',
            'screening_type' => 'nullable|in:' . implode(',', array_keys(StudentLookup::SCREENING_TYPES)),
            'screener_employee_id' => 'nullable|integer',
            'hospital' => 'nullable|max:150',
            'doctor' => 'nullable|max:150',
            'examined_at' => $date,
            'card_issued_at' => $date,
            'card_expires_at' => $date,
            'blood_group' => 'nullable|in:' . implode(',', array_keys(StudentLookup::BLOOD_GROUPS)),
            'chronic_conditions' => 'nullable|max:500',
            'allergies' => 'nullable|max:500',
            'medications' => 'nullable|max:500',
            'health_note' => 'nullable|max:1000',
        ];
    }

    public const SENSITIVE_LABELS = [
        'disability_type' => 'ประเภทความพิการ', 'disability_level' => 'ระดับความพิการ', 'multiple_disabilities' => 'ความพิการซ้อน',
        'disability_description' => 'ลักษณะความพิการ', 'iq' => 'ระดับเชาวน์ปัญญา (IQ)', 'screening_type' => 'ประเภทผู้คัดกรอง',
        'screener_employee_id' => 'ครูผู้คัดกรอง', 'hospital' => 'ชื่อโรงพยาบาล', 'doctor' => 'ชื่อ-นามสกุลแพทย์',
        'examined_at' => 'ได้รับการตรวจ ณ วันที่', 'card_types' => 'ประเภทตามบัตรประจำตัวคนพิการ',
        'card_issued_at' => 'วันที่ออกบัตร', 'card_expires_at' => 'วันที่บัตรหมดอายุ', 'blood_group' => 'กลุ่มเลือด',
        'chronic_conditions' => 'โรคประจำตัว', 'allergies' => 'ประวัติการแพ้ยา/อาหาร', 'medications' => 'ยาที่ใช้ประจำ',
        'health_note' => 'หมายเหตุด้านสุขภาพ',
    ];

    /** @param array $data ผลจาก Validator + multiple_disabilities/card_types (list) */
    public static function saveSensitive(int $studentId, array $data, array $multiple, array $cardTypes): void
    {
        if (!Rbac::can('academic.students.update_sensitive_all') || !self::canView($studentId)) {
            throw new HttpException(403);
        }
        $errors = [];
        $type = $data['disability_type'] ?? null;
        $level = $data['disability_level'] ?? null;
        if ($level !== null && StudentLookup::DISABILITY_LEVELS[$level][1] !== $type) {
            $errors['disability_level'][] = 'ระดับความพิการใช้ได้เฉพาะความบกพร่องทางการเห็น/การได้ยินที่ตรงกัน';
        }
        $multiple = array_values(array_intersect(array_unique($multiple), array_diff(array_keys(StudentLookup::DISABILITY_TYPES), ['multiple'])));
        if ($type === 'multiple' && count($multiple) < 2) {
            $errors['multiple_disabilities'][] = 'พิการซ้อนต้องเลือกลักษณะความพิการอย่างน้อย 2 ด้าน';
        }
        if ($type !== 'multiple') {
            $multiple = [];
        }
        $cardTypes = array_values(array_intersect(array_unique($cardTypes), array_keys(StudentLookup::CARD_TYPES)));
        if ($data['iq'] !== null && ((int) $data['iq'] < 0 || (int) $data['iq'] > 250)) {
            $errors['iq'][] = 'ระดับเชาวน์ปัญญาต้องอยู่ระหว่าง 0-250';
        }
        if ($data['card_issued_at'] !== null && $data['card_expires_at'] !== null && $data['card_expires_at'] < $data['card_issued_at']) {
            $errors['card_expires_at'][] = 'วันที่หมดอายุต้องไม่ก่อนวันที่ออกบัตร';
        }
        if ($data['screener_employee_id'] !== null) {
            $previous = self::sensitive($studentId, false)['screener_employee_id'] ?? null;
            if ((int) $data['screener_employee_id'] !== (int) $previous && !self::isActiveEmployee((int) $data['screener_employee_id'])) {
                $errors['screener_employee_id'][] = 'กรุณาเลือกครูผู้คัดกรองที่ยังปฏิบัติงานอยู่';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $payload = array_merge($data, [
            'iq' => $data['iq'] !== null ? (int) $data['iq'] : null,
            'screener_employee_id' => $data['screener_employee_id'] !== null ? (int) $data['screener_employee_id'] : null,
            'multiple_disabilities' => $multiple,
            'card_types' => $cardTypes,
        ]);
        $encrypted = Crypto::encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        Database::instance()->query(
            'INSERT INTO academic_student_sensitive (student_id, data_encrypted, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data_encrypted = VALUES(data_encrypted), updated_by = VALUES(updated_by)',
            [$studentId, $encrypted, Auth::id()]
        );
        // ไม่เก็บค่าข้อมูลอ่อนไหวใน Audit Log (บันทึกเฉพาะว่ามีการแก้ไข)
        Audit::log('academic.student.sensitive_updated', 'academic', 'academic_students', $studentId);
    }

    // ================================================================ บันทึกนักเรียน

    /** @param array $input ค่าที่ส่งมา (ใช้เลือกกฎตามประเภทเอกสาร เพื่อแจ้งเลขบัตรผิดพร้อมข้อผิดพลาดอื่น) */
    public static function rules(bool $withEnrollment, array $input = []): array
    {
        $rules = [
            'student_code' => 'nullable|max:10',
            'id_type' => 'required|in:' . implode(',', array_keys(StudentLookup::ID_TYPES)),
            'national_id' => ($input['id_type'] ?? '') === 'citizen' ? 'nullable|thai_id' : 'nullable|max:20',
            'g_code' => 'nullable|max:20',
            'title_name' => 'nullable|max:30',
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'nickname' => 'nullable|max:50',
            'first_name_en' => 'nullable|max:100',
            'last_name_en' => 'nullable|max:100',
            'gender' => 'nullable|in:' . implode(',', array_keys(StudentLookup::GENDERS)),
            'birth_date' => 'nullable|date',
            'nationality' => 'nullable|max:50',
            'ethnicity' => 'nullable|max:50',
            'religion' => 'nullable|max:50',
            'tribe' => 'nullable|max:50',
            'phone' => 'nullable|max:30',
            'parents_status' => 'nullable|in:' . implode(',', array_keys(StudentLookup::PARENTS_STATUSES)),
            'lives_with' => 'nullable|in:' . implode(',', array_keys(StudentLookup::LIVES_WITH)),
            'applied_at' => 'nullable|date',
            'admitted_at' => 'nullable|date',
            'note' => 'nullable|max:500',
            'scout_level' => 'nullable|in:' . implode(',', array_keys(StudentLookup::SCOUT_LEVELS)),
            'scout_group' => 'nullable|in:' . implode(',', array_keys(StudentLookup::SCOUT_GROUPS)),
            'redcross_status' => 'nullable|in:' . implode(',', array_keys(StudentLookup::REDCROSS_STATUSES)),
            'cur_same' => 'nullable|in:1',
            'primary_contact' => 'nullable|in:' . implode(',', array_keys(StudentLookup::RELATIONS)),
        ];
        foreach (array_keys(StudentLookup::SIBLING_FIELDS) as $key) {
            $rules[$key] = 'nullable|integer';
        }
        foreach (array_keys(StudentLookup::TRAVEL_FIELDS) as $key) {
            $rules[$key] = 'nullable|max:6';
        }
        if ($withEnrollment) {
            // เพิ่มนักเรียน: เลือกสถานะแรกได้เฉพาะผู้สมัคร/กำลังรับบริการ
            $rules['status'] = 'required|in:' . implode(',', StudentLookup::INITIAL_STATUSES);
        } else {
            $rules['identity_change_reason'] = 'nullable|max:255';
        }
        foreach (['reg', 'cur'] as $prefix) {
            foreach (StudentLookup::ADDRESS_FIELDS as $key => [, $max]) {
                $rules["{$prefix}_{$key}"] = 'nullable|max:' . $max;
            }
        }
        foreach (array_keys(StudentLookup::RELATIONS) as $rel) {
            $rules += [
                "{$rel}_title_name" => 'nullable|max:30',
                "{$rel}_first_name" => 'nullable|max:100',
                "{$rel}_last_name" => 'nullable|max:100',
                "{$rel}_birth_date" => 'nullable|date',
                "{$rel}_national_id" => 'nullable|thai_id',
                "{$rel}_occupation" => 'nullable|max:100',
                "{$rel}_income_per_year" => 'nullable|integer',
                "{$rel}_phone" => 'nullable|max:30',
                "{$rel}_line_id" => 'nullable|max:100',
                "{$rel}_life_status" => 'nullable|in:' . implode(',', array_keys(StudentLookup::LIFE_STATUSES)),
                "{$rel}_relation_note" => 'nullable|max:50',
            ];
        }
        if ($withEnrollment) {
            $rules += self::enrollmentRules(false);
        }

        return $rules;
    }

    public static function labels(): array
    {
        $labels = self::LABELS + ['cur_same' => 'ที่อยู่ปัจจุบันตามทะเบียนบ้าน', 'primary_contact' => 'ผู้ติดต่อหลัก']
            + StudentLookup::SIBLING_FIELDS
            + array_map(static fn (string $l): string => "ระยะทาง{$l} (กม.)", StudentLookup::TRAVEL_FIELDS);
        foreach (['reg' => 'ที่อยู่ตามทะเบียนบ้าน', 'cur' => 'ที่อยู่ปัจจุบัน'] as $prefix => $group) {
            foreach (StudentLookup::ADDRESS_FIELDS as $key => [$label]) {
                $labels["{$prefix}_{$key}"] = "{$label} ({$group})";
            }
        }
        $g = ['title_name' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'birth_date' => 'วันเกิด',
            'national_id' => 'เลขประจำตัวประชาชน', 'occupation' => 'อาชีพ', 'income_per_year' => 'รายได้ต่อปี', 'phone' => 'เบอร์โทรศัพท์',
            'line_id' => 'ID Line', 'life_status' => 'สถานะ', 'relation_note' => 'ความเกี่ยวข้องกับนักเรียน'];
        foreach (StudentLookup::RELATIONS as $rel => $relLabel) {
            foreach ($g as $key => $label) {
                $labels["{$rel}_{$key}"] = "{$label}{$relLabel}";
            }
        }
        foreach (self::enrollmentRules(false) as $key => $_) {
            $labels[$key] = self::LABELS[substr($key, 7)] ?? $key;
        }

        return $labels;
    }

    /** @return int id ของนักเรียนใหม่ */
    public static function create(array $data, bool $withEnrollment): int
    {
        if (!Rbac::can('academic.students.create_all')) {
            throw new HttpException(403);
        }
        // รวมข้อผิดพลาดของข้อมูลนักเรียนและการรับบริการ แจ้งผู้ใช้ครั้งเดียว
        $errors = [];
        try {
            [$row, $identity] = self::prepare($data, null);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }
        $enrollment = null;
        try {
            $enrollment = $withEnrollment && !empty($data['enroll_service_type']) ? self::prepareEnrollment(self::unprefix($data), null) : null;
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $id = Database::instance()->transaction(static function (Database $db) use ($row, $identity, $data, $enrollment): int {
            $id = self::insertWithCode($db, $row + $identity + ['created_by' => Auth::id()], $data['student_code'] ?? null);
            self::saveGuardians($db, $id, $data);
            if ($enrollment !== null) {
                $db->insert('academic_student_enrollments', $enrollment + ['student_id' => $id, 'created_by' => Auth::id()]);
            }
            StudentStatusService::logInitial($db, $id, (string) $row['status'], $row['admitted_at'] ?? null);

            return $id;
        });
        Audit::log('academic.student.created', 'academic', 'academic_students', $id, null, self::snapshot($id));

        return $id;
    }

    public static function update(array $current, array $data): void
    {
        if (!Rbac::can('academic.students.update_all')) {
            throw new HttpException(403);
        }
        $id = (int) $current['id'];
        [$row, $identity] = self::prepare($data, $id);
        $code = trim((string) ($data['student_code'] ?? ''));
        if ($code !== '' && $code !== $current['student_code']) {
            $row['student_code'] = $code;
        }
        $before = self::snapshot($id);
        Database::instance()->transaction(static function (Database $db) use ($id, $row, $identity, $data): void {
            self::recordIdentityChange($db, $id, (string) $data['id_type'], $identity, $row['g_code'], $data['identity_change_reason'] ?? null);
            $db->update('academic_students', $row + $identity + ['updated_by' => Auth::id()], ['id' => $id]);
            self::saveGuardians($db, $id, $data);
        });
        Audit::log('academic.student.updated', 'academic', 'academic_students', $id, $before, self::snapshot($id));
    }

    /** Soft delete: ล้าง blind index/เลข G เพื่อให้เพิ่มนักเรียนคนเดิมใหม่ได้หากลบผิด (รหัสนักเรียนยังถูกสงวนไว้) */
    public static function delete(array $current): void
    {
        if (!Rbac::can('academic.students.delete_all')) {
            throw new HttpException(403);
        }
        $id = (int) $current['id'];
        Database::instance()->update('academic_students', [
            'deleted_at' => Database::now(), 'national_id_hash' => null, 'g_code' => null, 'updated_by' => Auth::id(),
        ], ['id' => $id]);
        Audit::log('academic.student.deleted', 'academic', 'academic_students', $id, [
            'student_code' => $current['student_code'], 'name' => self::displayName($current),
        ]);
    }

    // ================================================================ การรับบริการรายปี

    public static function enrollmentRules(bool $required = true): array
    {
        $req = $required ? 'required' : 'nullable';

        return [
            'enroll_academic_year_id' => "{$req}|integer",
            'enroll_service_type' => "{$req}|in:" . implode(',', array_keys(StudentLookup::SERVICE_TYPES)),
            'enroll_department_id' => 'nullable|integer',
            'enroll_service_room_id' => 'nullable|integer',
            'enroll_grade_level' => 'nullable|max:50',
            'enroll_responsible_employee_id' => 'nullable|integer',
            'enroll_note' => 'nullable|max:255',
        ];
    }

    /** บันทึก (เพิ่ม/แก้ไข) การรับบริการของปีการศึกษา: 1 ปีต่อ 1 รายการ */
    public static function saveEnrollment(int $studentId, array $data, ?array $current): int
    {
        if (!Rbac::can('academic.students.update_all')) {
            throw new HttpException(403);
        }
        $row = self::prepareEnrollment(self::unprefix($data), $current);
        $db = Database::instance();
        $existing = $db->fetch(
            'SELECT id FROM academic_student_enrollments WHERE student_id = ? AND academic_year_id = ?',
            [$studentId, $row['academic_year_id']]
        );
        if ($existing !== null && ($current === null || (int) $existing['id'] !== (int) $current['id'])) {
            throw new ValidationException(['enroll_academic_year_id' => ['นักเรียนมีข้อมูลการรับบริการของปีการศึกษานี้แล้ว (แก้ไขรายการเดิมแทน)']]);
        }
        if ($current === null) {
            $id = $db->insert('academic_student_enrollments', $row + ['student_id' => $studentId, 'created_by' => Auth::id()]);
            Audit::log('academic.enrollment.created', 'academic', 'academic_student_enrollments', $id, null, $row);
        } else {
            $id = (int) $current['id'];
            $db->update('academic_student_enrollments', $row + ['updated_by' => Auth::id()], ['id' => $id]);
            Audit::log('academic.enrollment.updated', 'academic', 'academic_student_enrollments', $id, $current, $row);
        }

        return $id;
    }

    public static function deleteEnrollment(array $enrollment): void
    {
        if (!Rbac::can('academic.students.update_all')) {
            throw new HttpException(403);
        }
        Database::instance()->delete('academic_student_enrollments', ['id' => (int) $enrollment['id']]);
        Audit::log('academic.enrollment.deleted', 'academic', 'academic_student_enrollments', (int) $enrollment['id'], $enrollment);
    }

    /** ตัวเลือกในฟอร์มการรับบริการ */
    public static function enrollmentOptions(?array $current = null): array
    {
        $db = Database::instance();

        return [
            'years' => $db->fetchAll('SELECT id, name, is_current FROM academic_years ORDER BY year_be DESC'),
            'units' => $db->fetchAll(
                "SELECT d.id, d.name_th FROM departments d LEFT JOIN departments p ON p.id = d.parent_id
                 WHERE d.is_active = 1 AND (d.type = 'service_unit' OR p.type = 'service_unit') ORDER BY d.sort_order, d.id"
            ),
            'rooms' => $db->fetchAll(
                "SELECT r.id, r.academic_year_id, r.room_code, r.name_th, r.grade_level FROM service_rooms r ORDER BY r.room_code"
            ),
            'teachers' => $db->fetchAll(
                "SELECT e.id, e.title_name, e.first_name, e.last_name, e.status FROM employees e
                 WHERE (e.deleted_at IS NULL AND e.status = 'active') OR e.id = ? ORDER BY e.first_name, e.last_name",
                [(int) ($current['responsible_employee_id'] ?? 0)]
            ),
        ];
    }

    // ================================================================ เลขประจำตัวนักเรียน

    public static function codeDigits(): int
    {
        return max(3, min(10, (int) Setting::get('academic.student_code_digits', 5)));
    }

    /** เลขถัดไป = เลขสูงสุดที่เคยใช้ (รวมที่ลบแล้ว) + 1 */
    public static function nextStudentCode(?Database $db = null): string
    {
        $db ??= Database::instance();
        $max = (int) $db->fetchColumn(
            "SELECT MAX(CAST(student_code AS UNSIGNED)) FROM academic_students WHERE student_code REGEXP '^[0-9]+$'"
        );

        return str_pad((string) ($max + 1), self::codeDigits(), '0', STR_PAD_LEFT);
    }

    /** ระบุเลขเอง (เช่น เลขเดิมจากระบบ SET) หรือเว้นว่างให้ระบบกำหนด; กรณีระบบกำหนดแล้วชนจะลองเลขใหม่ */
    private static function insertWithCode(Database $db, array $row, ?string $manualCode): int
    {
        $manualCode = trim((string) $manualCode);
        for ($attempt = 1; ; $attempt++) {
            $code = $manualCode !== '' ? $manualCode : self::nextStudentCode($db);
            try {
                return $db->insert('academic_students', ['student_code' => $code] + $row);
            } catch (\PDOException $e) {
                $dup = ($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'uq_academic_students_code');
                if (!$dup || $manualCode !== '' || $attempt >= 5) {
                    if ($dup) {
                        throw new ValidationException(['student_code' => ['เลขประจำตัวนักเรียนนี้ถูกใช้แล้ว']]);
                    }
                    throw $e;
                }
            }
        }
    }

    // ================================================================ ภายใน

    public static function displayName(array $row): string
    {
        return trim(($row['title_name'] ?? '') . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }

    public static function nationalIdHash(string $value): string
    {
        return hash_hmac('sha256', 'student:' . $value, Crypto::key());
    }

    public static function decryptSensitive(string $blob): ?array
    {
        try {
            $data = json_decode(Crypto::decrypt($blob), true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            error_log('[academic sensitive] decrypt failed: ' . $e->getMessage());

            return null;
        }

        return is_array($data) ? $data : null;
    }

    /** @return array{0: array, 1: array} [ข้อมูลทั่วไป, ฟิลด์เลขบัตร] */
    private static function prepare(array $data, ?int $studentId): array
    {
        $errors = [];
        $db = Database::instance();
        $id = $studentId ?? 0;

        $code = trim((string) ($data['student_code'] ?? ''));
        if ($code !== '') {
            if (!preg_match(self::CODE_PATTERN, $code)) {
                $errors['student_code'][] = 'เลขประจำตัวนักเรียนต้องเป็นตัวเลข 3-10 หลัก';
            } elseif ($db->fetchColumn('SELECT 1 FROM academic_students WHERE student_code = ? AND id <> ?', [$code, $id]) !== null) {
                $errors['student_code'][] = 'เลขประจำตัวนักเรียนนี้ถูกใช้แล้ว';
            }
        }

        // เอกสารแสดงตน: เลขบัตรประชาชน/พาสปอร์ตเข้ารหัส, เลข G เก็บแบบปกติ (เป็นเลขอ้างอิงของ สพฐ.)
        $idType = (string) $data['id_type'];
        $raw = trim((string) ($data['national_id'] ?? ''));
        $identity = [];
        $gCode = null;
        if ($idType === 'citizen' && $raw !== '') {
            $digits = (string) preg_replace('/\D/', '', $raw);
            if (!\Core\Validator::isThaiId($digits)) {
                $errors['national_id'][] = 'เลขประจำตัวประชาชนไม่ถูกต้อง';
            } else {
                $identity = self::identityFields($digits);
            }
        } elseif ($idType === 'passport' && $raw !== '') {
            $passport = strtoupper((string) preg_replace('/\s+/', '', $raw));
            if (!preg_match('/^[A-Z0-9]{5,20}$/', $passport)) {
                $errors['national_id'][] = 'เลขหนังสือเดินทางต้องเป็นตัวอักษร A-Z หรือตัวเลข 5-20 ตัว';
            } else {
                $identity = self::identityFields($passport);
            }
        } elseif ($idType === 'g_code') {
            $gCode = strtoupper((string) preg_replace('/\s+/', '', (string) ($data['g_code'] ?? '')));
            if ($gCode === '') {
                $errors['g_code'][] = 'กรุณากรอกเลข G';
            } elseif (!preg_match('/^G[0-9]{9,12}$/', $gCode)) {
                $errors['g_code'][] = 'เลข G ต้องขึ้นต้นด้วย G ตามด้วยตัวเลข เช่น G123456789';
            } elseif ($db->fetchColumn('SELECT 1 FROM academic_students WHERE g_code = ? AND id <> ?', [$gCode, $id]) !== null) {
                $errors['g_code'][] = 'เลข G นี้มีในระบบแล้ว';
            }
        }
        if ($idType !== 'citizen' && $idType !== 'passport') {
            // เปลี่ยนเป็นเลข G/ไม่มีเอกสาร: ล้างเลขบัตรเดิม
            $identity = ['national_id_hash' => null, 'national_id_encrypted' => null, 'national_id_last4' => null];
        } elseif ($identity === [] && $studentId !== null) {
            $previousType = $db->fetchColumn('SELECT id_type FROM academic_students WHERE id = ?', [$studentId]);
            if ($previousType !== $idType) {
                $identity = ['national_id_hash' => null, 'national_id_encrypted' => null, 'national_id_last4' => null];
            } // ประเภทเดิม + เว้นว่าง = ไม่เปลี่ยนเลขเดิม
        }
        if (isset($identity['national_id_hash']) && $identity['national_id_hash'] !== null
            && $db->fetchColumn('SELECT 1 FROM academic_students WHERE national_id_hash = ? AND id <> ?', [$identity['national_id_hash'], $id]) !== null) {
            $errors['national_id'][] = 'เลขนี้มีในทะเบียนนักเรียนแล้ว';
        }

        $today = date('Y-m-d');
        if (!empty($data['birth_date']) && $data['birth_date'] > $today) {
            $errors['birth_date'][] = 'วันเกิดต้องไม่เป็นวันในอนาคต';
        }
        if ($studentId !== null) {
            $leftAt = $db->fetchColumn('SELECT left_at FROM academic_students WHERE id = ?', [$studentId]);
            if ($leftAt !== null && !empty($data['admitted_at']) && $data['admitted_at'] > $leftAt) {
                $errors['admitted_at'][] = 'วันที่เริ่มรับบริการต้องไม่หลังวันที่ออกจากการรับบริการ';
            }
        }
        foreach (['first_name_en', 'last_name_en'] as $k) {
            if (!empty($data[$k]) && !preg_match("/^[A-Za-z][A-Za-z .'\\-]*$/", (string) $data[$k])) {
                $errors[$k][] = "ชื่อภาษาอังกฤษใช้ได้เฉพาะตัวอักษร A-Z เว้นวรรค จุด ขีด และ '";
            }
        }
        foreach (StudentLookup::SIBLING_FIELDS as $k => $label) {
            $min = $k === 'child_order' ? 1 : 0;
            if (($data[$k] ?? null) !== null && ((int) $data[$k] < $min || (int) $data[$k] > 30)) {
                $errors[$k][] = "{$label}ต้องอยู่ระหว่าง {$min}-30";
            }
        }
        foreach (StudentLookup::TRAVEL_FIELDS as $k => $label) {
            if (($data[$k] ?? null) !== null && !preg_match('/^\d{1,4}(\.\d)?$/', (string) $data[$k])) {
                $errors[$k][] = "ระยะทาง{$label}ต้องเป็นตัวเลข 0-9999.9 กม. (ทศนิยม 1 ตำแหน่ง)";
            }
        }
        foreach (['reg_postcode', 'cur_postcode'] as $k) {
            if (!empty($data[$k]) && !preg_match('/^\d{5}$/', (string) $data[$k])) {
                $errors[$k][] = 'รหัสไปรษณีย์ต้องเป็นตัวเลข 5 หลัก';
            }
        }
        if (!empty($data['reg_house_code']) && !preg_match('/^\d{11}$/', (string) $data['reg_house_code'])) {
            $errors['reg_house_code'][] = 'รหัสประจำบ้านต้องเป็นตัวเลข 11 หลัก';
        }
        foreach (array_keys(StudentLookup::RELATIONS) as $rel) {
            $hasAny = false;
            foreach (self::GUARDIAN_FIELDS as $f) {
                $hasAny = $hasAny || !empty($data["{$rel}_{$f}"]) && $f !== 'life_status';
            }
            if (($hasAny || !empty($data["{$rel}_national_id"])) && empty($data["{$rel}_first_name"])) {
                $errors["{$rel}_first_name"][] = 'กรุณากรอกชื่อ' . StudentLookup::RELATIONS[$rel];
            }
            if (!empty($data["{$rel}_income_per_year"]) && (int) $data["{$rel}_income_per_year"] < 0) {
                $errors["{$rel}_income_per_year"][] = 'รายได้ต้องไม่ติดลบ';
            }
        }
        if (!empty($data['primary_contact']) && empty($data[$data['primary_contact'] . '_first_name'])) {
            $errors['primary_contact'][] = 'ผู้ติดต่อหลักต้องมีข้อมูลชื่อ';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $row = [];
        foreach (self::STUDENT_FIELDS as $f) {
            if ($studentId !== null && in_array($f, self::STATUS_FIELDS, true)) {
                continue; // แก้ไขข้อมูลทั่วไปไม่เปลี่ยนสถานะ
            }
            $v = $data[$f] ?? null;
            $row[$f] = $v === '' ? null : $v;
        }
        $row['g_code'] = $idType === 'g_code' ? $gCode : null;
        if ($studentId === null) {
            $row['left_at'] = null;
            $row['left_reason'] = null;
        }
        $row['address_registered'] = self::addressJson($data, 'reg');
        $row['address_current'] = !empty($data['cur_same']) ? null : self::addressJson($data, 'cur');

        return [$row, $identity];
    }

    /**
     * เก็บเลขประจำตัวเดิม (เข้ารหัส) ก่อนถูกแทนที่ แทนการจดเลขเดิมไว้ในช่องหมายเหตุแบบระบบ SET
     * $identity = [] หมายถึงไม่เปลี่ยนเลขบัตร
     */
    private static function recordIdentityChange(Database $db, int $id, string $newType, array $identity, ?string $newGCode, ?string $reason): void
    {
        $old = $db->fetch('SELECT id_type, national_id_hash, national_id_encrypted, national_id_last4, g_code FROM academic_students WHERE id = ?', [$id]);
        if ($old === null || ($old['national_id_encrypted'] === null && $old['g_code'] === null)) {
            return; // ไม่มีเลขเดิมให้เก็บ
        }
        $nidChanged = $old['national_id_encrypted'] !== null && array_key_exists('national_id_hash', $identity)
            && $identity['national_id_hash'] !== $old['national_id_hash'];
        $gChanged = $old['g_code'] !== null && $old['g_code'] !== $newGCode;
        if (!$nidChanged && !$gChanged) {
            return;
        }
        $db->insert('academic_student_identity_history', [
            'student_id' => $id,
            'id_type' => $old['id_type'],
            'national_id_encrypted' => $nidChanged ? $old['national_id_encrypted'] : null,
            'national_id_last4' => $nidChanged ? $old['national_id_last4'] : null,
            'g_code' => $gChanged ? $old['g_code'] : null,
            'reason' => $reason !== null && $reason !== '' ? $reason : null,
            'changed_by' => Auth::id(),
            'changed_at' => Database::now(),
        ]);
        Audit::log('academic.student.identity_changed', 'academic', 'academic_students', $id,
            ['id_type' => $old['id_type']], ['id_type' => $newType, 'reason' => $reason]);
    }

    /** @return list<array> ประวัติเลขประจำตัวเดิม (แสดงเฉพาะ 4 หลักท้าย) */
    public static function identityHistory(int $studentId): array
    {
        return Database::instance()->fetchAll(
            'SELECT h.id, h.id_type, h.national_id_last4, h.g_code, h.reason, h.changed_at, u.username AS changed_by_name
             FROM academic_student_identity_history h LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.student_id = ? ORDER BY h.changed_at DESC, h.id DESC',
            [$studentId]
        );
    }

    private static function identityFields(string $value): array
    {
        return [
            'national_id_hash' => self::nationalIdHash($value),
            'national_id_encrypted' => Crypto::encrypt($value),
            'national_id_last4' => substr($value, -4),
        ];
    }

    private static function addressJson(array $data, string $prefix): ?string
    {
        $address = [];
        foreach (array_keys(StudentLookup::ADDRESS_FIELDS) as $key) {
            $v = trim((string) ($data["{$prefix}_{$key}"] ?? ''));
            if ($v !== '') {
                $address[$key] = $v;
            }
        }

        return $address === [] ? null : json_encode($address, JSON_UNESCAPED_UNICODE);
    }

    /** บิดา/มารดา/ผู้ปกครอง: กรอกชื่อ = บันทึก, ลบชื่อออก = ลบรายการ; เลขบัตรเว้นว่าง = คงค่าเดิม */
    private static function saveGuardians(Database $db, int $studentId, array $data): void
    {
        foreach (array_keys(StudentLookup::RELATIONS) as $rel) {
            $first = trim((string) ($data["{$rel}_first_name"] ?? ''));
            if ($first === '') {
                $db->query('DELETE FROM academic_student_guardians WHERE student_id = ? AND relation = ?', [$studentId, $rel]);
                continue;
            }
            $row = ['student_id' => $studentId, 'relation' => $rel];
            foreach (self::GUARDIAN_FIELDS as $f) {
                $v = $data["{$rel}_{$f}"] ?? null;
                $row[$f] = $v === '' ? null : $v;
            }
            $row['life_status'] ??= 'alive';
            $row['income_per_year'] = $row['income_per_year'] !== null ? (int) $row['income_per_year'] : null;
            if ($rel !== 'guardian') {
                $row['relation_note'] = null;
            }
            $row['is_primary_contact'] = ($data['primary_contact'] ?? '') === $rel ? 1 : 0;
            $nid = (string) preg_replace('/\D/', '', (string) ($data["{$rel}_national_id"] ?? ''));
            if ($nid !== '') {
                $row['national_id_encrypted'] = Crypto::encrypt($nid);
                $row['national_id_last4'] = substr($nid, -4);
            }
            $columns = array_keys($row);
            $updates = implode(', ', array_map(static fn (string $c): string => "`{$c}` = VALUES(`{$c}`)", array_diff($columns, ['student_id', 'relation'])));
            $db->query(
                'INSERT INTO academic_student_guardians (`' . implode('`, `', $columns) . '`) VALUES ('
                . implode(', ', array_fill(0, count($columns), '?')) . ") ON DUPLICATE KEY UPDATE {$updates}",
                array_values($row)
            );
        }
    }

    private static function prepareEnrollment(array $e, ?array $current): array
    {
        $db = Database::instance();
        $errors = [];
        $yearId = (int) ($e['academic_year_id'] ?? 0);
        if ($yearId === 0 || $db->fetchColumn('SELECT 1 FROM academic_years WHERE id = ?', [$yearId]) === null) {
            $errors['enroll_academic_year_id'][] = 'กรุณาเลือกปีการศึกษา';
        }
        $type = (string) ($e['service_type'] ?? '');
        if (!isset(StudentLookup::SERVICE_TYPES[$type])) {
            $errors['enroll_service_type'][] = 'กรุณาเลือกประเภทการรับบริการ';
        }
        $deptId = !empty($e['department_id']) ? (int) $e['department_id'] : null;
        $unitIds = array_map('intval', array_column(self::enrollmentOptions()['units'], 'id'));
        if ($deptId !== null && !in_array($deptId, $unitIds, true)) {
            $errors['enroll_department_id'][] = 'หน่วยบริการไม่ถูกต้อง';
        }
        if ($type === 'service_unit' && $deptId === null) {
            $errors['enroll_department_id'][] = 'กรุณาเลือกหน่วยบริการ';
        }
        $roomId = !empty($e['service_room_id']) ? (int) $e['service_room_id'] : null;
        $roomGrade = null;
        if ($roomId !== null) {
            $roomRow = $db->fetch('SELECT academic_year_id, grade_level FROM service_rooms WHERE id = ?', [$roomId]);
            $roomYear = $roomRow['academic_year_id'] ?? null;
            $roomGrade = $roomRow['grade_level'] ?? null;
            if ($roomYear === null || (int) $roomYear !== $yearId) {
                $errors['enroll_service_room_id'][] = 'ห้องบริการต้องเป็นของปีการศึกษาเดียวกัน';
            }
        }
        $teacherId = !empty($e['responsible_employee_id']) ? (int) $e['responsible_employee_id'] : null;
        if ($teacherId !== null && $teacherId !== (int) ($current['responsible_employee_id'] ?? 0) && !self::isActiveEmployee($teacherId)) {
            $errors['enroll_responsible_employee_id'][] = 'กรุณาเลือกครูที่ยังปฏิบัติงานอยู่';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'academic_year_id' => $yearId,
            'service_type' => $type,
            'department_id' => $deptId,
            'service_room_id' => $roomId,
            'grade_level' => ($e['grade_level'] ?? '') !== '' ? $e['grade_level'] : $roomGrade, // ว่าง = ใช้ระดับชั้นของห้อง
            'responsible_employee_id' => $teacherId,
            'note' => ($e['note'] ?? '') !== '' ? $e['note'] : null,
        ];
    }

    /** enroll_xxx → xxx */
    private static function unprefix(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (str_starts_with((string) $k, 'enroll_')) {
                $out[substr((string) $k, 7)] = $v;
            }
        }

        return $out;
    }

    private static function isActiveEmployee(int $id): bool
    {
        return Database::instance()->fetchColumn(
            "SELECT 1 FROM employees WHERE id = ? AND deleted_at IS NULL AND status = 'active'",
            [$id]
        ) !== null;
    }

    private static function jsonArray(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /** ข้อมูลสำหรับ Audit (ไม่รวมเลขบัตร) */
    private static function snapshot(int $id): array
    {
        $row = Database::instance()->fetch(
            'SELECT student_code, id_type, g_code, title_name, first_name, last_name, nickname, gender, birth_date, status, left_at
             FROM academic_students WHERE id = ?',
            [$id]
        ) ?? [];
        $row['guardians'] = count(self::guardians($id));

        return $row;
    }
}
