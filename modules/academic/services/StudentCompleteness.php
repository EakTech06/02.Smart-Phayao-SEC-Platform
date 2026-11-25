<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Database;
use Core\Exceptions\HttpException;

/**
 * Phase 5B: ตรวจความครบถ้วนของทะเบียนนักเรียน
 * ใช้กฎช่องบังคับชุดเดียวกับ checkformaddstudent() ของระบบ SET แทนเมนู "*นักเรียนข้อมูลไม่ครบ" และ "ตรวจสอบการกรอกข้อมูล"
 * หมวดความพิการตรวจเฉพาะนักเรียนที่ผู้ใช้มีสิทธิ์ดูข้อมูลอ่อนไหว (ผู้ไม่มีสิทธิ์จะเห็นเป็น "ไม่มีสิทธิ์ตรวจ")
 */
final class StudentCompleteness
{
    public const CATEGORIES = [
        'identity' => ['ตัวตน', 'person-badge'],
        'profile' => ['ประวัติส่วนตัว', 'person-lines-fill'],
        'disability' => ['ความพิการ/คัดกรอง', 'shield-lock'],
        'service' => ['การรับบริการปีนี้', 'door-open'],
        'address' => ['ที่อยู่', 'house'],
        'family' => ['ผู้ปกครอง', 'people'],
        'documents' => ['รูปถ่าย', 'image'],
    ];

    private const PROFILE_FIELDS = [
        'title_name' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'first_name_en' => 'ชื่อภาษาอังกฤษ',
        'last_name_en' => 'นามสกุลภาษาอังกฤษ', 'gender' => 'เพศ', 'birth_date' => 'วันเกิด', 'nationality' => 'สัญชาติ',
        'ethnicity' => 'เชื้อชาติ', 'religion' => 'ศาสนา',
    ];

    private const ADDRESS_REQUIRED = ['house_no', 'subdistrict', 'district', 'province', 'postcode'];

    private const MALE_TITLES = ['เด็กชาย', 'นาย'];
    private const FEMALE_TITLES = ['เด็กหญิง', 'นางสาว', 'นาง'];
    private const CHILD_TITLES = ['เด็กชาย', 'เด็กหญิง'];
    private const ADULT_TITLES = ['นาย', 'นางสาว', 'นาง'];

    /**
     * @param array $student แถว academic_students (address_* เป็น array แล้ว)
     * @param array<string,array> $guardians relation => แถว
     * @param array|null $enrollment การรับบริการปีปัจจุบัน
     * @param array|false|null $health ข้อมูลอ่อนไหวที่ถอดรหัสแล้ว | null = ยังไม่บันทึก | false = ไม่มีสิทธิ์
     * @return array{score:int, missing:int, categories:array<string,array{label:string,icon:string,total:int,done:int,missing:list<string>,skipped:bool}>, warnings:list<string>}
     */
    public static function evaluate(array $student, array $guardians, ?array $enrollment, array|false|null $health, bool $hasPhoto): array
    {
        $checks = [];

        // 1. ตัวตน
        $idOk = match ($student['id_type']) {
            'citizen', 'passport' => !empty($student['national_id_last4']),
            'g_code' => !empty($student['g_code']),
            default => false,
        };
        $checks['identity'] = [
            'เลขประจำตัวนักเรียน' => !empty($student['student_code']),
            $student['id_type'] === 'none' ? 'เอกสารแสดงตน (ยังไม่มี)' : (StudentLookup::ID_TYPES[$student['id_type']] ?? 'เอกสารแสดงตน') => $idOk,
        ];

        // 2. ประวัติส่วนตัว
        foreach (self::PROFILE_FIELDS as $k => $label) {
            $checks['profile'][$label] = self::filled($student[$k] ?? null);
        }

        // 3. ความพิการ/คัดกรอง (ข้อมูลอ่อนไหว)
        if ($health !== false) {
            $h = $health ?? [];
            $type = $h['disability_type'] ?? null;
            $screening = $h['screening_type'] ?? null;
            $checks['disability'] = ['ประเภทความพิการ' => self::filled($type)];
            if (in_array($type, ['visual', 'hearing'], true)) {
                $checks['disability']['ระดับความพิการ'] = self::filled($h['disability_level'] ?? null);
            }
            if ($type === 'multiple') {
                $checks['disability']['ลักษณะความพิการซ้อน (อย่างน้อย 2 ด้าน)'] = count((array) ($h['multiple_disabilities'] ?? [])) >= 2;
            }
            $checks['disability']['การคัดกรอง'] = self::filled($screening);
            $checks['disability']['ครูผู้คัดกรอง'] = self::filled($h['screener_employee_id'] ?? null);
            if (in_array($screening, ['medical_cert', 'disability_card'], true)) {
                $checks['disability']['โรงพยาบาล'] = self::filled($h['hospital'] ?? null);
                $checks['disability']['วันที่ตรวจ'] = self::filled($h['examined_at'] ?? null);
            }
            if ($screening === 'disability_card') {
                $checks['disability']['ประเภทตามบัตรคนพิการ'] = !empty($h['card_types']);
                $checks['disability']['วันที่บัตรหมดอายุ'] = self::filled($h['card_expires_at'] ?? null);
            }
        }

        // 4. การรับบริการปีปัจจุบัน
        if ($enrollment === null) {
            $checks['service'] = ['บันทึกการรับบริการปีการศึกษาปัจจุบัน' => false];
        } else {
            $checks['service'] = ['ประเภทการรับบริการ' => self::filled($enrollment['service_type'] ?? null)];
            if (($enrollment['service_type'] ?? '') === 'service_unit') {
                $checks['service']['หน่วยบริการ'] = self::filled($enrollment['department_id'] ?? null);
            }
            $checks['service']['ห้องบริการ'] = self::filled($enrollment['service_room_id'] ?? null);
            $checks['service']['ระดับชั้น'] = self::filled($enrollment['grade_level'] ?? null);
            $checks['service']['ครูผู้รับผิดชอบ'] = self::filled($enrollment['responsible_employee_id'] ?? null);
        }

        // 5. ที่อยู่ (ทะเบียนบ้าน + ปัจจุบันถ้าไม่ใช่ที่เดียวกัน)
        $labels = StudentLookup::ADDRESS_FIELDS;
        foreach (self::ADDRESS_REQUIRED as $k) {
            $checks['address'][$labels[$k][0] . ' (ทะเบียนบ้าน)'] = self::filled($student['address_registered'][$k] ?? null);
        }
        if ($student['address_current'] !== null) {
            foreach (self::ADDRESS_REQUIRED as $k) {
                $checks['address'][$labels[$k][0] . ' (ปัจจุบัน)'] = self::filled($student['address_current'][$k] ?? null);
            }
        }

        // 6. ผู้ปกครอง: SET บังคับข้อมูลผู้ปกครองทุกช่อง (ผู้ปกครอง = รายการ "ผู้ปกครอง" หรือผู้ติดต่อหลัก)
        $checks['family'] = [
            'สถานภาพบิดา-มารดา' => self::filled($student['parents_status'] ?? null),
            'ปัจจุบันอาศัยอยู่กับ' => self::filled($student['lives_with'] ?? null),
        ];
        $contact = $guardians['guardian'] ?? null;
        foreach ($guardians as $g) {
            if ($contact === null && (int) $g['is_primary_contact'] === 1) {
                $contact = $g;
            }
        }
        if ($contact === null) {
            $checks['family']['ข้อมูลผู้ปกครอง/ผู้ติดต่อหลัก'] = false;
        } else {
            $checks['family'] += [
                'ชื่อ-นามสกุลผู้ปกครอง' => self::filled($contact['first_name']) && self::filled($contact['last_name']),
                'เลขประจำตัวประชาชนผู้ปกครอง' => self::filled($contact['national_id_last4']),
                'อาชีพผู้ปกครอง' => self::filled($contact['occupation']),
                'เบอร์โทรศัพท์ผู้ปกครอง' => self::filled($contact['phone']),
            ];
            if ($contact['relation'] === 'guardian') {
                $checks['family']['ความเกี่ยวข้องกับนักเรียน'] = self::filled($contact['relation_note']);
            }
        }

        // 7. รูปถ่าย (ใช้พิมพ์บัตรนักเรียน)
        $checks['documents'] = ['รูปถ่ายนักเรียน' => $hasPhoto];

        $categories = [];
        $total = 0;
        $done = 0;
        foreach (self::CATEGORIES as $key => [$label, $icon]) {
            $items = $checks[$key] ?? null;
            $missing = $items === null ? [] : array_keys(array_filter($items, static fn (bool $ok): bool => !$ok));
            $categories[$key] = [
                'label' => $label, 'icon' => $icon, 'skipped' => $items === null,
                'total' => $items === null ? 0 : count($items), 'done' => $items === null ? 0 : count($items) - count($missing),
                'missing' => array_map('strval', $missing),
            ];
            $total += $categories[$key]['total'];
            $done += $categories[$key]['done'];
        }

        return [
            'score' => $total === 0 ? 100 : (int) floor($done * 100 / $total),
            'missing' => $total - $done,
            'categories' => $categories,
            'warnings' => self::warnings($student, $health),
        ];
    }

    /** ข้อควรตรวจสอบ (ไม่นับเป็นข้อมูลไม่ครบ): อายุ คำนำหน้า บัตรคนพิการ */
    public static function warnings(array $student, array|false|null $health): array
    {
        $out = [];
        $title = (string) ($student['title_name'] ?? '');
        $age = null;
        if (!empty($student['birth_date'])) {
            $age = (new \DateTimeImmutable((string) $student['birth_date']))->diff(new \DateTimeImmutable('today'))->y;
            if ($age > 20) {
                $out[] = "อายุ {$age} ปี เกินเกณฑ์ 20 ปีของระบบ SET";
            }
            if ($age >= 15 && in_array($title, self::CHILD_TITLES, true)) {
                $out[] = "อายุ {$age} ปี แต่ใช้คำนำหน้า \"{$title}\" (ควรเป็นนาย/นางสาว)";
            } elseif ($age < 15 && in_array($title, self::ADULT_TITLES, true)) {
                $out[] = "อายุ {$age} ปี แต่ใช้คำนำหน้า \"{$title}\" (ควรเป็นเด็กชาย/เด็กหญิง)";
            }
        }
        $gender = $student['gender'] ?? null;
        if (($gender === 'male' && in_array($title, self::FEMALE_TITLES, true)) || ($gender === 'female' && in_array($title, self::MALE_TITLES, true))) {
            $out[] = "คำนำหน้า \"{$title}\" ไม่ตรงกับเพศ" . (StudentLookup::GENDERS[$gender] ?? '');
        }
        $days = is_array($health) ? self::cardDaysLeft($health) : null;
        if ($days !== null && $days < 0) {
            $out[] = 'บัตรประจำตัวคนพิการหมดอายุแล้ว ' . abs($days) . ' วัน';
        } elseif ($days !== null && $days <= StudentLookup::CARD_EXPIRY_WARN_DAYS) {
            $out[] = "บัตรประจำตัวคนพิการจะหมดอายุในอีก {$days} วัน";
        }

        return $out;
    }

    /** จำนวนวันก่อนบัตรคนพิการหมดอายุ (ติดลบ = หมดแล้ว) | null = ไม่มีข้อมูล */
    public static function cardDaysLeft(array $health): ?int
    {
        $expires = $health['card_expires_at'] ?? null;
        if (!is_string($expires) || $expires === '') {
            return null;
        }
        $diff = (new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable($expires));

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * รายงานความครบถ้วนของนักเรียนทุกคนในสิทธิ์ (โหลดข้อมูลแบบกลุ่ม ไม่ query รายคน)
     * @param array{status_group?:string, department_id?:int, category?:string, only_incomplete?:bool} $filters
     * @return array{rows:list<array>, summary:array, cards:list<array>, can_sensitive:bool}
     */
    public static function report(array $filters): array
    {
        $cond = StudentService::scopeCondition('read') ?? throw new HttpException(403);
        $sensitive = StudentService::scopeCondition('read_sensitive');
        $db = Database::instance();
        $yearId = StudentService::currentYearId() ?? 0;

        $where = ['s.deleted_at IS NULL', $cond[0]];
        $params = $cond[1];
        $statuses = ($filters['status_group'] ?? 'open') === 'active' ? ['active'] : StudentLookup::OPEN_STATUSES;
        $where[] = 's.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        array_push($params, ...$statuses);
        if (!empty($filters['department_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM academic_student_enrollments xf WHERE xf.student_id = s.id AND xf.academic_year_id = ? AND xf.department_id = ?)';
            array_push($params, $yearId, (int) $filters['department_id']);
        }
        $whereSql = implode(' AND ', $where);
        $canSensitive = $sensitive === null ? '0' : "(CASE WHEN {$sensitive[0]} THEN 1 ELSE 0 END)";

        $students = $db->fetchAll(
            "SELECT * FROM (SELECT s.*, {$canSensitive} AS can_sensitive, sv.data_encrypted AS sensitive_blob
             FROM academic_students s LEFT JOIN academic_student_sensitive sv ON sv.student_id = s.id
             WHERE {$whereSql}) x ORDER BY x.student_code",
            array_merge($sensitive[1] ?? [], $params)
        );
        if ($students === []) {
            return ['rows' => [], 'summary' => self::emptySummary(), 'cards' => [], 'can_sensitive' => $sensitive !== null];
        }
        // ข้อมูลประกอบ: ใช้เงื่อนไขสิทธิ์ชุดเดียวกัน เพื่อไม่ต้องส่งรายการ id ยาว
        $sub = "SELECT s.id FROM academic_students s WHERE {$whereSql}";
        $guardians = [];
        foreach ($db->fetchAll(
            "SELECT student_id, relation, relation_note, first_name, last_name, national_id_last4, occupation, phone, is_primary_contact
             FROM academic_student_guardians WHERE student_id IN ({$sub})",
            $params
        ) as $g) {
            $guardians[(int) $g['student_id']][$g['relation']] = $g;
        }
        $enrollments = [];
        foreach ($db->fetchAll(
            "SELECT en.*, d.name_th AS department_name, r.room_code FROM academic_student_enrollments en
             LEFT JOIN departments d ON d.id = en.department_id LEFT JOIN service_rooms r ON r.id = en.service_room_id
             WHERE en.academic_year_id = ? AND en.student_id IN ({$sub})",
            array_merge([$yearId], $params)
        ) as $en) {
            $enrollments[(int) $en['student_id']] = $en;
        }
        $photos = array_flip(array_map('intval', array_column($db->fetchAll(
            "SELECT DISTINCT ref_id FROM files WHERE ref_type = ? AND deleted_at IS NULL AND mime_type LIKE 'image/%' AND title LIKE ?
             AND ref_id IN ({$sub})",
            array_merge([StudentLookup::REF_TYPE, StudentLookup::DOCUMENT_TYPES['photo'][0] . '%'], $params)
        ), 'ref_id')));

        $category = (string) ($filters['category'] ?? '');
        $onlyIncomplete = !empty($filters['only_incomplete']);
        $summary = self::emptySummary();
        $rows = [];
        $cards = [];
        foreach ($students as $s) {
            $id = (int) $s['id'];
            $s['address_registered'] = self::json($s['address_registered']);
            $s['address_current'] = self::json($s['address_current']);
            $visible = (int) $s['can_sensitive'] === 1;
            $health = $visible ? ($s['sensitive_blob'] !== null ? StudentService::decryptSensitive((string) $s['sensitive_blob']) : null) : false;
            unset($s['sensitive_blob'], $s['national_id_encrypted'], $s['national_id_hash']);
            $result = StudentCompleteness::evaluate($s, $guardians[$id] ?? [], $enrollments[$id] ?? null, $health, isset($photos[$id]));

            $summary['students']++;
            $summary['complete'] += $result['missing'] === 0 ? 1 : 0;
            $summary['score_sum'] += $result['score'];
            foreach ($result['categories'] as $key => $c) {
                if (!$c['skipped'] && $c['missing'] !== []) {
                    $summary['by_category'][$key]++;
                }
            }
            if (is_array($health) && ($days = self::cardDaysLeft($health)) !== null && $days <= StudentLookup::CARD_EXPIRY_WARN_DAYS) {
                $cards[] = ['student' => $s, 'expires' => $health['card_expires_at'], 'days' => $days, 'enrollment' => $enrollments[$id] ?? null];
            }

            if ($onlyIncomplete && $result['missing'] === 0) {
                continue;
            }
            if ($category !== '' && (($result['categories'][$category]['missing'] ?? []) === [])) {
                continue;
            }
            $rows[] = ['student' => $s, 'enrollment' => $enrollments[$id] ?? null, 'result' => $result];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['result']['score'], $a['student']['student_code']] <=> [$b['result']['score'], $b['student']['student_code']]);
        usort($cards, static fn (array $a, array $b): int => $a['days'] <=> $b['days']);

        return ['rows' => $rows, 'summary' => $summary, 'cards' => $cards, 'can_sensitive' => $sensitive !== null];
    }

    private static function emptySummary(): array
    {
        return ['students' => 0, 'complete' => 0, 'score_sum' => 0, 'by_category' => array_fill_keys(array_keys(self::CATEGORIES), 0)];
    }

    private static function filled(mixed $v): bool
    {
        return $v !== null && trim((string) $v) !== '';
    }

    private static function json(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }
}
