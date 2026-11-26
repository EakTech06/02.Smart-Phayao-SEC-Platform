<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Database;

/**
 * รายงานข้อมูลนักเรียนรายบุคคล (ใช้ร่วมกันทั้งหน้าพิมพ์/PDF และไฟล์ Excel ให้เนื้อหาตรงกัน)
 * - เลขประจำตัวประชาชนแสดงแบบปิดบัง (4 หลักท้าย) เสมอ
 * - ส่วนความพิการและสุขภาพรวมเฉพาะผู้มีสิทธิ์ดูข้อมูลอ่อนไหว (การเปิดดูถูกบันทึกโดย StudentService::sensitive)
 *
 * @phpstan-type Block array{type:'fields', title:string, rows:list<array{0:string,1:string}>}
 *                   |array{type:'table', title:string, head:list<string>, rows:list<list<string>>, empty:string}
 */
final class StudentProfileReport
{
    /**
     * @return array{title:string, student:array, name:string, sensitive:bool, blocks:list<array>}
     */
    public static function build(array $student): array
    {
        $id = (int) $student['id'];
        $sensitive = StudentService::canViewSensitive($id);
        $date = static fn (?string $d): string => $d ? thai_date($d, false, true) : '-';
        $v = static fn (mixed $x): string => $x === null || $x === '' ? '-' : (string) $x;
        $code = static fn (array $list, mixed $k): string => $k === null || $k === '' ? '-' : (string) ($list[(string) $k] ?? $k);

        // 1. ข้อมูลทั่วไป
        $idValue = '-';
        if ($student['id_type'] === 'g_code') {
            $idValue = $v($student['g_code']);
        } elseif ((int) $student['has_national_id'] === 1) {
            $idValue = '*********' . $student['national_id_last4'];
        }
        $travel = [];
        foreach (StudentLookup::TRAVEL_FIELDS as $k => $label) {
            if ($student[$k] !== null) {
                $travel[] = $label . ' ' . rtrim(rtrim((string) $student[$k], '0'), '.') . ' กม.';
            }
        }
        $general = [
            ['เลขประจำตัวนักเรียน', (string) $student['student_code']],
            [StudentLookup::ID_TYPES[$student['id_type']] ?? 'เลขประจำตัว', $idValue],
            ['ชื่อ-นามสกุล', StudentService::displayName($student)],
            ['ชื่อภาษาอังกฤษ', $v(trim(($student['first_name_en'] ?? '') . ' ' . ($student['last_name_en'] ?? '')))],
            ['ชื่อเล่น', $v($student['nickname'])],
            ['เพศ', $code(StudentLookup::GENDERS, $student['gender'])],
            ['วันเดือนปีเกิด', $date($student['birth_date'])],
            ['อายุ', $v(StudentLookup::age($student['birth_date']))],
            ['สัญชาติ / เชื้อชาติ', $v($student['nationality']) . ' / ' . $v($student['ethnicity'])],
            ['ศาสนา / ชนเผ่า', $v($student['religion']) . ' / ' . $v($student['tribe'])],
            ['เบอร์โทรศัพท์', $v($student['phone'])],
            ['สถานะ', StudentLookup::statusLabel((string) $student['status'])],
            ['ที่อยู่ตามทะเบียนบ้าน', $v(StudentLookup::formatAddress($student['address_registered']))
                . (!empty($student['address_registered']['house_code']) ? ' (รหัสประจำบ้าน ' . $student['address_registered']['house_code'] . ')' : '')],
            ['ที่อยู่ปัจจุบัน', $student['address_current'] === null ? 'ตามทะเบียนบ้าน' : $v(StudentLookup::formatAddress($student['address_current']))],
            ['ระยะทางเดินทาง', $travel === [] ? '-' : implode(' · ', $travel)],
            ['ลูกเสือ / เหล่า', $code(StudentLookup::SCOUT_LEVELS, $student['scout_level']) . ' / ' . $code(StudentLookup::SCOUT_GROUPS, $student['scout_group'])],
            ['ยุวกาชาด', $code(StudentLookup::REDCROSS_STATUSES, $student['redcross_status'])],
            ['วันที่มาสมัคร', $date($student['applied_at'])],
            ['วันที่เริ่มรับบริการ', $date($student['admitted_at'])],
        ];
        if (in_array($student['status'], StudentLookup::LEFT_STATUSES, true)) {
            $general[] = ['ออกจากการรับบริการ', $date($student['left_at']) . ($student['left_reason'] ? ' · ' . $student['left_reason'] : '')];
        }
        if ($student['note']) {
            $general[] = ['หมายเหตุ', (string) $student['note']];
        }

        // 2. ครอบครัว
        $sib = [];
        foreach (['older_brothers' => 'พี่ชาย', 'older_sisters' => 'พี่สาว', 'younger_brothers' => 'น้องชาย', 'younger_sisters' => 'น้องสาว'] as $k => $label) {
            if ($student[$k] !== null) {
                $sib[] = $label . ' ' . (int) $student[$k];
            }
        }
        $family = [
            ['สถานภาพบิดา-มารดา', $code(StudentLookup::PARENTS_STATUSES, $student['parents_status'])],
            ['อาศัยอยู่กับ', $code(StudentLookup::LIVES_WITH, $student['lives_with'])],
            ['พี่น้อง', $sib === [] ? '-' : implode(' · ', $sib)],
            ['เป็นบุตรคนที่', $v($student['child_order'])],
            ['พี่น้องที่กำลังศึกษา (คน)', $v($student['siblings_studying'])],
        ];
        $guardians = StudentService::guardians($id);
        $guardianRows = [];
        foreach (StudentLookup::RELATIONS as $rel => $relLabel) {
            if (!isset($guardians[$rel])) {
                continue;
            }
            $g = $guardians[$rel];
            $guardianRows[] = [
                $relLabel . ($g['relation_note'] ? ' (' . $g['relation_note'] . ')' : ''),
                trim(($g['title_name'] ?? '') . $g['first_name'] . ' ' . ($g['last_name'] ?? ''))
                    . ($g['life_status'] === 'deceased' ? ' (เสียชีวิต)' : ''),
                $v($g['phone']),
                $v($g['occupation']),
                $g['income_per_year'] !== null ? number_format((int) $g['income_per_year']) : '-',
                (int) $g['is_primary_contact'] === 1 ? 'ใช่' : '',
            ];
        }

        // 3. การรับบริการ
        $enrollRows = [];
        foreach (StudentService::enrollments($id) as $en) {
            $enrollRows[] = [
                (string) $en['year_name'] . ((int) $en['is_current'] === 1 ? ' (ปัจจุบัน)' : ''),
                StudentLookup::serviceLabel($en['service_type']),
                $v($en['department_name']),
                $en['room_code'] ? $en['room_code'] . ' ' . $en['room_name'] : '-',
                $v($en['grade_level']),
                $v($en['teacher_name']),
            ];
        }

        $blocks = [
            ['type' => 'fields', 'title' => 'ข้อมูลทั่วไป', 'rows' => $general],
            ['type' => 'fields', 'title' => 'ข้อมูลครอบครัว', 'rows' => $family],
            ['type' => 'table', 'title' => 'บิดา มารดา และผู้ปกครอง', 'empty' => 'ยังไม่มีข้อมูล',
                'head' => ['ความสัมพันธ์', 'ชื่อ-นามสกุล', 'โทรศัพท์', 'อาชีพ', 'รายได้ (บาท/ปี)', 'ผู้ติดต่อหลัก'], 'rows' => $guardianRows],
            ['type' => 'table', 'title' => 'การรับบริการรายปีการศึกษา', 'empty' => 'ยังไม่มีข้อมูลการรับบริการ',
                'head' => ['ปีการศึกษา', 'ประเภทการรับบริการ', 'หน่วยบริการ', 'ห้องบริการ', 'ระดับชั้น', 'ครูผู้รับผิดชอบ'], 'rows' => $enrollRows],
        ];

        // 4. ความพิการและสุขภาพ (ข้อมูลอ่อนไหว)
        if ($sensitive) {
            $blocks[] = ['type' => 'fields', 'title' => 'ข้อมูลความพิการและสุขภาพ (ข้อมูลอ่อนไหว)', 'rows' => self::healthRows(StudentService::sensitive($id))];
        }

        // 5. ประวัติสถานะ
        $statusRows = [];
        foreach (StudentStatusService::logs($id) as $log) {
            $statusRows[] = [
                $date($log['effective_on']),
                ($log['from_status'] ? StudentLookup::statusLabel((string) $log['from_status']) . ' → ' : '') . StudentLookup::statusLabel((string) $log['to_status']),
                $v($log['reason']),
                $v($log['reference_no']),
            ];
        }
        $blocks[] = ['type' => 'table', 'title' => 'ประวัติการเปลี่ยนสถานะ', 'empty' => 'ยังไม่มีประวัติ',
            'head' => ['วันที่มีผล', 'สถานะ', 'เหตุผล', 'เลขที่หนังสือ/คำสั่ง'], 'rows' => $statusRows];

        return [
            'title' => 'ระเบียนข้อมูลนักเรียนรายบุคคล',
            'student' => $student,
            'name' => StudentService::displayName($student),
            'sensitive' => $sensitive,
            'blocks' => $blocks,
        ];
    }

    /** @return list<array{0:string,1:string}> */
    private static function healthRows(?array $h): array
    {
        if ($h === null) {
            return [['ข้อมูล', 'ยังไม่มีข้อมูล']];
        }
        $date = static fn (mixed $d): string => is_string($d) && $d !== '' ? thai_date($d, false, true) : '-';
        $v = static fn (mixed $x): string => $x === null || $x === '' ? '-' : (string) $x;
        $type = (string) ($h['disability_type'] ?? '');
        $rows = [
            ['ประเภทความพิการ', $v(StudentLookup::DISABILITY_TYPES[$type] ?? null)
                . (!empty($h['disability_level']) ? ' (' . (StudentLookup::DISABILITY_LEVELS[$h['disability_level']][0] ?? '') . ')' : '')],
        ];
        if (!empty($h['multiple_disabilities'])) {
            $rows[] = ['ความพิการซ้อน', implode(', ', array_map(
                static fn (string $k): string => str_replace('บกพร่องทาง', '', StudentLookup::DISABILITY_TYPES[$k] ?? $k),
                (array) $h['multiple_disabilities']
            ))];
        }
        $rows[] = ['ระดับเชาวน์ปัญญา (IQ)', $v($h['iq'] ?? null)];
        $rows[] = ['ลักษณะความพิการ', $v($h['disability_description'] ?? null)];

        $screening = (string) ($h['screening_type'] ?? '');
        $rows[] = ['ประเภทผู้คัดกรอง', $v(StudentLookup::SCREENING_TYPES[$screening] ?? null)];
        if ($screening === 'none' || ($screening === '' && !empty($h['screener_employee_id']))) {
            $rows[] = ['ครูผู้คัดกรอง', $v(self::employeeName($h['screener_employee_id'] ?? null))];
        }
        if ($screening === 'medical_cert' || ($screening === '' && (!empty($h['hospital']) || !empty($h['doctor'])))) {
            $rows[] = ['ชื่อโรงพยาบาล', $v($h['hospital'] ?? null)];
            $rows[] = ['ชื่อ-นามสกุลแพทย์', $v($h['doctor'] ?? null)];
            $rows[] = ['ได้รับการตรวจ ณ วันที่', $date($h['examined_at'] ?? null)];
        }
        if ($screening === 'disability_card' || ($screening === '' && (!empty($h['card_types']) || !empty($h['card_expires_at'])))) {
            $rows[] = ['ประเภทตามบัตรประจำตัวคนพิการ', !empty($h['card_types'])
                ? implode(', ', array_map(static fn (string $k): string => StudentLookup::CARD_TYPES[$k] ?? $k, (array) $h['card_types'])) : '-'];
            $rows[] = ['วันที่ออกบัตร', $date($h['card_issued_at'] ?? null)];
            $status = StudentCompleteness::cardStatus($h);
            $rows[] = ['วันที่บัตรหมดอายุ', $date($h['card_expires_at'] ?? null)
                . ($status !== null && $status['state'] !== 'valid' ? ' (' . $status['text'] . ')' : '')];
        }
        $rows[] = ['กลุ่มเลือด', $v(StudentLookup::BLOOD_GROUPS[$h['blood_group'] ?? ''] ?? null)];
        $rows[] = ['โรคประจำตัว', $v($h['chronic_conditions'] ?? null)];
        $rows[] = ['ประวัติการแพ้ยา/อาหาร', $v($h['allergies'] ?? null)];
        $rows[] = ['ยาที่ใช้ประจำ', $v($h['medications'] ?? null)];
        if (!empty($h['health_note'])) {
            $rows[] = ['หมายเหตุด้านสุขภาพ', (string) $h['health_note']];
        }

        return $rows;
    }

    /** ชื่อบุคลากร + เลขทะเบียนผู้คัดกรอง (ถ้ามี) */
    public static function employeeName(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }
        $row = Database::instance()->fetch('SELECT title_name, first_name, last_name, screener_reg_no FROM employees WHERE id = ?', [(int) $id]);

        return $row !== null ? trim(($row['title_name'] ?? '') . $row['first_name'] . ' ' . $row['last_name'])
            . (!empty($row['screener_reg_no']) ? ' (เลขทะเบียนผู้คัดกรอง ' . $row['screener_reg_no'] . ')' : '') : null;
    }

    /** แปลงเป็นแถวสำหรับ XlsxWriter (ส่วนหัว + ทุกส่วนเรียงลงมาในชีตเดียว) */
    public static function toRows(array $report, string $orgName): array
    {
        $rows = [
            [$report['title']],
            [$orgName],
            ['ข้อมูล ณ ' . thai_date_numeric(date('Y-m-d H:i'), true)],
        ];
        foreach ($report['blocks'] as $block) {
            $rows[] = [];
            $rows[] = [$block['title']];
            if ($block['type'] === 'fields') {
                foreach ($block['rows'] as [$label, $value]) {
                    $rows[] = [$label, $value];
                }
                continue;
            }
            if ($block['rows'] === []) {
                $rows[] = [$block['empty']];
                continue;
            }
            $rows[] = $block['head'];
            foreach ($block['rows'] as $r) {
                $rows[] = $r;
            }
        }

        return $rows;
    }
}
