<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Database;
use Core\Exceptions\HttpException;
use Core\Rbac;

/**
 * Phase 4: สถิติรายฝ่ายและรายงานภาพรวมของหน่วยงาน
 *
 * ทุกฟังก์ชันคืนเฉพาะตัวเลขสรุป (aggregate) ไม่คืนข้อมูลรายบุคคล
 * $departmentIds = null หมายถึงทั้งหน่วยงาน, list<int> หมายถึงจำกัดเฉพาะฝ่ายที่ระบุ (Data Scope)
 */
final class ReportService
{
    /** ฝ่ายที่ไม่ได้ระบุ (employees.department_id IS NULL) ใช้ id 0 ในผลลัพธ์ */
    public const NO_DEPARTMENT = 0;

    /** ลำดับสีตามชุดสีที่ผ่านการตรวจ CVD (ห้ามสลับลำดับ: สีผูกกับประเภท ไม่ใช่อันดับ) */
    public const EMPLOYMENT_COLORS = [
        'civil_servant' => '#2a78d6',
        'government_employee' => '#eb6834',
        'permanent_employee' => '#1baf7a',
        'temporary_employee' => '#eda100',
        'contract_9000' => '#7a5fd3',
        'contract_15000' => '#19a3b8',
        'other' => '#e87ba4',
    ];

    /** ประเภทบุคลากรในรายงาน: ตัวเลือกปัจจุบัน + ประเภทเดิม ('other') เฉพาะเมื่อยังมีบุคลากรประเภทนั้น */
    public static function employmentTypes(): array
    {
        static $types = null;
        if ($types === null) {
            $hasLegacy = Database::instance()->fetchColumn(
                "SELECT 1 FROM employees WHERE employment_type = 'other' AND deleted_at IS NULL LIMIT 1"
            ) !== null;
            $types = $hasLegacy ? UserService::EMPLOYMENT_TYPE_LABELS : UserService::EMPLOYMENT_TYPES;
        }

        return $types;
    }

    public const PERIOD_MONTH = 'month';
    public const PERIOD_CUSTOM = 'custom';

    // ---------------------------------------------------------------- Data Scope

    public static function canViewAll(): bool
    {
        return Rbac::can('core.reports.view_all');
    }

    /**
     * ฝ่ายที่ผู้ใช้ปัจจุบันออกรายงานได้
     * @return list<int>|null null = ทั้งหน่วยงาน
     * @throws HttpException 403 เมื่อไม่มีสิทธิ์หรือไม่ได้สังกัดฝ่ายใด
     */
    public static function scopeDepartmentIds(): ?array
    {
        if (self::canViewAll()) {
            return null;
        }
        if (!Rbac::can('core.reports.view_department')) {
            throw new HttpException(403);
        }
        $ids = array_values(array_unique(array_merge(Rbac::departmentIds(), Rbac::serviceUnitDepartmentIds())));
        if ($ids === []) {
            throw new HttpException(403, 'บัญชีของคุณยังไม่ได้สังกัดฝ่าย/งาน จึงออกรายงานรายฝ่ายไม่ได้');
        }
        sort($ids);

        return $ids;
    }

    /**
     * ฝ่ายที่เลือกได้ในตัวกรอง (เฉพาะใน scope)
     * @return list<array{id:int, code:string, name_th:string, type:string}>
     */
    public static function departments(?array $departmentIds): array
    {
        [$in, $params] = self::inClause('id', $departmentIds);
        $rows = Database::instance()->fetchAll(
            "SELECT id, code, name_th, type FROM departments WHERE is_active = 1 AND {$in} ORDER BY sort_order, id",
            $params
        );
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
        }

        return $rows;
    }

    /**
     * จำกัด scope ให้เหลือฝ่ายที่เลือก (ป้องกันเลือกฝ่ายนอกสิทธิ์ด้วยการแก้ URL)
     * @return list<int>|null
     */
    public static function narrowScope(?array $scope, ?int $departmentId): ?array
    {
        if ($departmentId === null || $departmentId <= 0) {
            return $scope;
        }
        if ($scope !== null && !in_array($departmentId, $scope, true)) {
            throw new HttpException(403);
        }

        return [$departmentId];
    }

    // ---------------------------------------------------------------- ช่วงเวลา

    /**
     * แปลงตัวกรองช่วงเวลาเป็นวันที่
     * period: ay-{id} ปีการศึกษา | fy-{id} ปีงบประมาณ | month เดือนนี้ | custom (ใช้ from/to)
     * @return array{period:string, from:string, to:string, label:string, academic_year_id:?int}
     */
    public static function resolvePeriod(string $period, ?string $from, ?string $to): array
    {
        $db = Database::instance();
        if (preg_match('/^(ay|fy)-(\d+)$/', $period, $m)) {
            $table = $m[1] === 'ay' ? 'academic_years' : 'fiscal_years';
            $row = $db->fetch("SELECT id, name, start_date, end_date FROM {$table} WHERE id = ?", [(int) $m[2]]);
            if ($row !== null) {
                return [
                    'period' => $period,
                    'from' => (string) $row['start_date'],
                    'to' => (string) $row['end_date'],
                    'label' => (string) $row['name'],
                    'academic_year_id' => $m[1] === 'ay' ? (int) $row['id'] : self::academicYearIdFor((string) $row['end_date']),
                ];
            }
        }
        if ($period === self::PERIOD_CUSTOM && self::isDate($from) && self::isDate($to)) {
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            // จำกัดช่วงไม่เกิน 5 ปี กัน query หนักเกินไป
            if ((new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days > 366 * 5) {
                $from = (new \DateTimeImmutable($to))->modify('-5 years')->format('Y-m-d');
            }

            return [
                'period' => $period,
                'from' => $from,
                'to' => $to,
                'label' => thai_date($from, false, true) . ' ถึง ' . thai_date($to, false, true),
                'academic_year_id' => self::academicYearIdFor($to),
            ];
        }
        if ($period === self::PERIOD_MONTH) {
            $from = date('Y-m-01');
            $to = date('Y-m-t');

            return [
                'period' => $period,
                'from' => $from,
                'to' => $to,
                'label' => 'เดือน' . THAI_MONTHS[(int) date('n')] . ' ' . ((int) date('Y') + 543),
                'academic_year_id' => self::academicYearIdFor($to),
            ];
        }

        // ค่าเริ่มต้น: ปีการศึกษาปัจจุบัน (ถ้ายังไม่ได้ตั้ง ใช้ปีปฏิทินนี้)
        $current = $db->fetch('SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1');
        if ($current !== null) {
            return self::resolvePeriod('ay-' . (int) $current['id'], null, null);
        }

        return self::resolvePeriod(self::PERIOD_CUSTOM, date('Y-01-01'), date('Y-12-31'));
    }

    /** @return array{academic: list<array>, fiscal: list<array>} */
    public static function periodOptions(): array
    {
        $db = Database::instance();

        return [
            'academic' => $db->fetchAll('SELECT id, name, is_current FROM academic_years ORDER BY year_be DESC'),
            'fiscal' => $db->fetchAll('SELECT id, name, is_current FROM fiscal_years ORDER BY year_be DESC'),
        ];
    }

    // ---------------------------------------------------------------- บุคลากร

    /**
     * จำนวนบุคลากรที่ปฏิบัติงานรายฝ่าย แยกประเภทการจ้างและเพศ
     * @return list<array{id:int, name:string, total:int, users:int, by_type: array<string,int>, male:int, female:int}>
     */
    public static function personnelByDepartment(?array $departmentIds): array
    {
        $result = [];
        foreach (self::departments($departmentIds) as $d) {
            $result[$d['id']] = self::emptyDepartmentRow($d['id'], $d['name_th']);
        }
        if ($departmentIds === null) {
            $result[self::NO_DEPARTMENT] = self::emptyDepartmentRow(self::NO_DEPARTMENT, 'ไม่ระบุฝ่าย');
        }

        [$in, $params] = self::inClause('e.department_id', $departmentIds);
        $rows = Database::instance()->fetchAll(
            "SELECT COALESCE(e.department_id, 0) AS dept, e.employment_type, e.gender,
                    COUNT(*) AS n, SUM(u.id IS NOT NULL AND u.status = 'active') AS users
             FROM employees e
             LEFT JOIN users u ON u.employee_id = e.id AND u.deleted_at IS NULL
             WHERE e.deleted_at IS NULL AND e.status = 'active' AND {$in}
             GROUP BY dept, e.employment_type, e.gender",
            $params
        );
        foreach ($rows as $row) {
            $dept = (int) $row['dept'];
            if (!isset($result[$dept])) {
                continue; // ฝ่ายที่ปิดใช้งานแล้ว
            }
            $n = (int) $row['n'];
            $result[$dept]['total'] += $n;
            $result[$dept]['users'] += (int) $row['users'];
            $result[$dept]['by_type'][$row['employment_type']] = ($result[$dept]['by_type'][$row['employment_type']] ?? 0) + $n;
            if ($row['gender'] === 'male' || $row['gender'] === 'female') {
                $result[$dept][$row['gender']] += $n;
            }
        }
        if (isset($result[self::NO_DEPARTMENT]) && $result[self::NO_DEPARTMENT]['total'] === 0) {
            unset($result[self::NO_DEPARTMENT]);
        }

        return array_values($result);
    }

    /**
     * สรุปบุคลากรตามมิติต่าง ๆ (นับทุกสถานะสำหรับ status, ที่เหลือนับเฉพาะผู้ปฏิบัติงาน)
     * @return array{status: array<string,int>, gender: array<string,int>, academic_rank: array<string,int>, education_level: array<string,int>}
     */
    public static function personnelBreakdown(?array $departmentIds): array
    {
        $db = Database::instance();
        [$in, $params] = self::inClause('department_id', $departmentIds);

        $status = array_fill_keys(array_keys(PersonnelService::STATUSES), 0);
        foreach ($db->fetchAll("SELECT status, COUNT(*) AS n FROM employees WHERE deleted_at IS NULL AND {$in} GROUP BY status", $params) as $r) {
            $status[$r['status']] = (int) $r['n'];
        }
        $gender = array_fill_keys(array_keys(PersonnelService::GENDERS), 0);
        foreach ($db->fetchAll("SELECT COALESCE(gender, 'unspecified') AS g, COUNT(*) AS n FROM employees
                                WHERE deleted_at IS NULL AND status = 'active' AND {$in} GROUP BY g", $params) as $r) {
            $gender[$r['g']] += (int) $r['n'];
        }

        return [
            'status' => $status,
            'gender' => $gender,
            'academic_rank' => self::countBy('academic_rank', $in, $params, 'ไม่มีวิทยฐานะ/ไม่ระบุ'),
            'education_level' => self::countBy('education_level', $in, $params, 'ไม่ระบุ'),
        ];
    }

    // ---------------------------------------------------------------- บัญชีผู้ใช้

    /** @return array{total:int, active:int, inactive:int, locked:int, two_factor:int, logged_in:int, never_logged_in:int} */
    public static function users(?array $departmentIds, string $from, string $to): array
    {
        [$in, $params] = self::inClause('e.department_id', $departmentIds);
        $row = Database::instance()->fetch(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(u.status = 'active'), 0) AS active,
                    COALESCE(SUM(u.status = 'inactive'), 0) AS inactive,
                    COALESCE(SUM(u.status = 'locked'), 0) AS locked,
                    COALESCE(SUM(tf.is_enabled = 1), 0) AS two_factor,
                    COALESCE(SUM(u.last_login_at BETWEEN ? AND ?), 0) AS logged_in,
                    COALESCE(SUM(u.last_login_at IS NULL), 0) AS never_logged_in
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id
             LEFT JOIN user_2fa tf ON tf.user_id = u.id
             WHERE u.deleted_at IS NULL AND {$in}",
            array_merge([$from . ' 00:00:00', $to . ' 23:59:59'], $params)
        ) ?? [];

        return array_map('intval', $row);
    }

    // ---------------------------------------------------------------- ห้องบริการ

    /** @return array{year: ?string, total:int, with_teacher:int, rooms: list<array>} */
    public static function serviceRooms(?int $academicYearId, ?array $departmentIds): array
    {
        $db = Database::instance();
        $year = $academicYearId !== null ? $db->fetchColumn('SELECT name FROM academic_years WHERE id = ?', [$academicYearId]) : null;
        if ($academicYearId === null || $year === false) {
            return ['year' => null, 'total' => 0, 'with_teacher' => 0, 'rooms' => []];
        }
        // ผู้ดูรายฝ่าย: นับห้องที่มีครูประจำชั้นอย่างน้อย 1 คนสังกัดฝ่ายในสิทธิ์
        [$in, $params] = self::inClause('fe.department_id', $departmentIds);
        $rooms = $db->fetchAll(
            "SELECT sr.room_code, sr.name_th, sr.grade_level,
                    (SELECT GROUP_CONCAT(TRIM(CONCAT_WS(' ', CONCAT(COALESCE(e.title_name, ''), e.first_name), e.last_name))
                            ORDER BY st.sort_order SEPARATOR ', ')
                     FROM service_room_teachers st JOIN employees e ON e.id = st.employee_id AND e.deleted_at IS NULL
                     WHERE st.service_room_id = sr.id) AS teacher,
                    CASE WHEN EXISTS (SELECT 1 FROM service_room_teachers st JOIN employees e ON e.id = st.employee_id
                                      WHERE st.service_room_id = sr.id AND e.deleted_at IS NULL AND e.status = 'active')
                         THEN 'active' ELSE 'inactive' END AS teacher_status
             FROM service_rooms sr
             WHERE sr.academic_year_id = ? AND " . ($departmentIds === null ? '1 = 1'
                : "EXISTS (SELECT 1 FROM service_room_teachers fs JOIN employees fe ON fe.id = fs.employee_id
                           WHERE fs.service_room_id = sr.id AND fe.deleted_at IS NULL AND {$in})") . '
             ORDER BY sr.room_code',
            array_merge([$academicYearId], $departmentIds === null ? [] : $params)
        );

        return [
            'year' => (string) $year,
            'total' => count($rooms),
            'with_teacher' => count(array_filter($rooms, static fn (array $r): bool => $r['teacher_status'] === 'active')),
            'rooms' => $rooms,
        ];
    }

    // ---------------------------------------------------------------- ปฏิทิน / ไฟล์ / PDPA / การเข้าใช้

    /**
     * กิจกรรมในปฏิทินกลางช่วงเวลาที่เลือก (ไม่นับกิจกรรมส่วนตัว)
     * @return array{total:int, by_department: list<array{name:string, n:int}>}
     */
    public static function calendar(?array $departmentIds, string $from, string $to): array
    {
        [$in, $params] = self::inClause('ce.department_id', $departmentIds);
        $scopeSql = $departmentIds === null ? '1 = 1' : "(ce.visibility = 'public' OR {$in})";
        $rows = Database::instance()->fetchAll(
            "SELECT COALESCE(d.name_th, 'ทั้งหน่วยงาน') AS name, COUNT(*) AS n
             FROM calendar_events ce
             LEFT JOIN departments d ON d.id = ce.department_id
             WHERE ce.deleted_at IS NULL AND ce.visibility <> 'private'
               AND ce.start_at <= ? AND COALESCE(ce.end_at, ce.start_at) >= ? AND {$scopeSql}
             GROUP BY name ORDER BY n DESC, name",
            array_merge([$to . ' 23:59:59', $from . ' 00:00:00'], $departmentIds === null ? [] : $params)
        );

        return ['total' => array_sum(array_map('intval', array_column($rows, 'n'))), 'by_department' => self::intRows($rows)];
    }

    /** @return array{total:int, bytes:int, by_department: list<array>, by_type: array<string,int>} */
    public static function files(?array $departmentIds, string $from, string $to): array
    {
        $db = Database::instance();
        [$in, $params] = self::inClause('f.department_id', $departmentIds);
        $base = "FROM files f WHERE f.deleted_at IS NULL AND f.created_at BETWEEN ? AND ? AND {$in}";
        $args = array_merge([$from . ' 00:00:00', $to . ' 23:59:59'], $params);

        $byDept = $db->fetchAll(
            "SELECT COALESCE(d.name_th, 'ไม่ระบุฝ่าย') AS name, COUNT(*) AS n, COALESCE(SUM(f.size_bytes), 0) AS bytes
             FROM files f LEFT JOIN departments d ON d.id = f.department_id
             WHERE f.deleted_at IS NULL AND f.created_at BETWEEN ? AND ? AND {$in}
             GROUP BY name ORDER BY n DESC, name",
            $args
        );
        $byType = [];
        foreach ($db->fetchAll("SELECT f.mime_type, COUNT(*) AS n {$base} GROUP BY f.mime_type", $args) as $r) {
            $label = self::fileTypeLabel((string) $r['mime_type']);
            $byType[$label] = ($byType[$label] ?? 0) + (int) $r['n'];
        }
        arsort($byType);

        return [
            'total' => array_sum(array_map('intval', array_column($byDept, 'n'))),
            'bytes' => array_sum(array_map('intval', array_column($byDept, 'bytes'))),
            'by_department' => self::intRows($byDept),
            'by_type' => $byType,
        ];
    }

    /** คำขอ PDPA ทั้งหน่วยงาน (เฉพาะผู้ดูรายงานทั้งหน่วยงาน) */
    public static function pdpa(string $from, string $to): array
    {
        $db = Database::instance();
        $args = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $status = array_fill_keys(array_keys(PdpaService::STATUSES), 0);
        foreach ($db->fetchAll('SELECT status, COUNT(*) AS n FROM pdpa_requests WHERE created_at BETWEEN ? AND ? GROUP BY status', $args) as $r) {
            $status[$r['status']] = (int) $r['n'];
        }
        $type = array_fill_keys(array_keys(PdpaService::TYPES), 0);
        foreach ($db->fetchAll('SELECT request_type, COUNT(*) AS n FROM pdpa_requests WHERE created_at BETWEEN ? AND ? GROUP BY request_type', $args) as $r) {
            $type[$r['request_type']] = (int) $r['n'];
        }
        $overdue = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM pdpa_requests WHERE status IN ('pending','in_progress') AND due_date < CURDATE()"
        );
        $late = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM pdpa_requests WHERE created_at BETWEEN ? AND ?
               AND status IN ('completed','rejected') AND DATE(handled_at) > due_date",
            $args
        );

        return ['total' => array_sum($status), 'status' => $status, 'type' => $type, 'overdue_open' => $overdue, 'late_closed' => $late];
    }

    /** การเข้าสู่ระบบ (เฉพาะผู้ดูรายงานทั้งหน่วยงาน) */
    public static function logins(string $from, string $to): array
    {
        $row = Database::instance()->fetch(
            'SELECT COALESCE(SUM(success = 1), 0) AS success, COALESCE(SUM(success = 0), 0) AS failed,
                    COUNT(DISTINCT CASE WHEN success = 1 THEN username END) AS users
             FROM login_attempts WHERE attempted_at BETWEEN ? AND ?',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        ) ?? [];

        return array_map('intval', $row);
    }

    // ---------------------------------------------------------------- แดชบอร์ด

    /**
     * สถิติการใช้งานรายเดือนย้อนหลัง (ไฟล์ที่อัปโหลด / กิจกรรมปฏิทิน / การเข้าสู่ระบบ)
     * @return array{labels: list<string>, files: list<int>, events: list<int>, logins: ?list<int>}
     */
    public static function monthlyTrend(int $months, bool $withLogins): array
    {
        $months = max(3, min(12, $months));
        $start = (new \DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months')->format('Y-m-01');
        $keys = [];
        $labels = [];
        for ($i = 0; $i < $months; $i++) {
            $d = (new \DateTimeImmutable($start))->modify("+{$i} months");
            $keys[] = $d->format('Y-m');
            $labels[] = THAI_MONTHS_SHORT[(int) $d->format('n')] . ' ' . substr((string) ((int) $d->format('Y') + 543), 2);
        }
        $db = Database::instance();
        $series = static function (string $sql) use ($db, $start, $keys): array {
            $map = [];
            foreach ($db->fetchAll($sql, [$start . ' 00:00:00']) as $r) {
                $map[$r['ym']] = (int) $r['n'];
            }

            return array_map(static fn (string $k): int => $map[$k] ?? 0, $keys);
        };

        return [
            'labels' => $labels,
            'files' => $series("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n FROM files
                                WHERE deleted_at IS NULL AND created_at >= ? GROUP BY ym"),
            'events' => $series("SELECT DATE_FORMAT(start_at, '%Y-%m') AS ym, COUNT(*) AS n FROM calendar_events
                                 WHERE deleted_at IS NULL AND visibility <> 'private' AND start_at >= ? GROUP BY ym"),
            'logins' => $withLogins
                ? $series("SELECT DATE_FORMAT(attempted_at, '%Y-%m') AS ym, COUNT(*) AS n FROM login_attempts
                           WHERE success = 1 AND attempted_at >= ? GROUP BY ym")
                : null,
        ];
    }

    /**
     * ตารางสถิติรายฝ่ายสำหรับแดชบอร์ด (บุคลากร, บัญชี, ไฟล์ทั้งหมด, กิจกรรมเดือนนี้)
     * @return list<array{id:int, name:string, total:int, users:int, files:int, events:int, by_type: array<string,int>}>
     */
    public static function departmentSummary(): array
    {
        $db = Database::instance();
        $rows = self::personnelByDepartment(null);
        $files = [];
        foreach ($db->fetchAll('SELECT COALESCE(department_id, 0) AS d, COUNT(*) AS n FROM files WHERE deleted_at IS NULL GROUP BY d') as $r) {
            $files[(int) $r['d']] = (int) $r['n'];
        }
        $events = [];
        foreach ($db->fetchAll(
            "SELECT COALESCE(department_id, 0) AS d, COUNT(*) AS n FROM calendar_events
             WHERE deleted_at IS NULL AND visibility <> 'private' AND start_at <= ? AND COALESCE(end_at, start_at) >= ?
             GROUP BY d",
            [date('Y-m-t 23:59:59'), date('Y-m-01 00:00:00')]
        ) as $r) {
            $events[(int) $r['d']] = (int) $r['n'];
        }
        foreach ($rows as &$row) {
            $row['files'] = $files[$row['id']] ?? 0;
            $row['events'] = $events[$row['id']] ?? 0;
        }

        return $rows;
    }

    // ---------------------------------------------------------------- รายงานรวม

    /** รวบรวมข้อมูลทุกส่วนของรายงานภาพรวม */
    public static function overview(array $period, ?array $departmentIds): array
    {
        $from = $period['from'];
        $to = $period['to'];
        $orgWide = $departmentIds === null;

        return [
            'period' => $period,
            'org_wide' => $orgWide,
            'departments_in_scope' => self::departments($departmentIds),
            'personnel' => self::personnelByDepartment($departmentIds),
            'breakdown' => self::personnelBreakdown($departmentIds),
            'users' => self::users($departmentIds, $from, $to),
            'rooms' => self::serviceRooms($period['academic_year_id'], $departmentIds),
            'calendar' => self::calendar($departmentIds, $from, $to),
            'files' => self::files($departmentIds, $from, $to),
            'pdpa' => $orgWide ? self::pdpa($from, $to) : null,
            'logins' => $orgWide ? self::logins($from, $to) : null,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * แปลงรายงานเป็นแผ่นงาน Excel
     * @return array<string, list<list<scalar|null>>>
     */
    public static function toSheets(array $report, string $orgName): array
    {
        $types = self::employmentTypes();
        $p = $report['period'];
        $u = $report['users'];

        $summary = [
            ['รายงานภาพรวมหน่วยงาน', $orgName],
            ['ช่วงเวลา', $p['label'] . ' (' . thai_date_numeric($p['from']) . ' ถึง ' . thai_date_numeric($p['to']) . ')'],
            ['ขอบเขต', $report['org_wide'] ? 'ทั้งหน่วยงาน' : implode(', ', array_column($report['departments_in_scope'], 'name_th'))],
            ['ออกรายงานเมื่อ', thai_date_numeric($report['generated_at'], true)],
            [],
            ['หัวข้อ', 'จำนวน'],
            ['บุคลากรที่ปฏิบัติงาน', array_sum(array_column($report['personnel'], 'total'))],
            ['บัญชีผู้ใช้ทั้งหมด', $u['total']],
            ['บัญชีที่ใช้งานได้', $u['active']],
            ['บัญชีที่เปิด 2FA', $u['two_factor']],
            ['บัญชีที่เข้าสู่ระบบในช่วงเวลา', $u['logged_in']],
            ['ห้องบริการ' . ($report['rooms']['year'] ? ' (' . $report['rooms']['year'] . ')' : ''), $report['rooms']['total']],
            ['กิจกรรมในปฏิทินกลาง', $report['calendar']['total']],
            ['ไฟล์ที่อัปโหลด', $report['files']['total']],
            ['ขนาดไฟล์รวม (MB)', round($report['files']['bytes'] / 1048576, 2)],
        ];
        if ($report['pdpa'] !== null) {
            $summary[] = ['คำขอใช้สิทธิ์ PDPA', $report['pdpa']['total']];
            $summary[] = ['คำขอ PDPA ค้างเกินกำหนด (ปัจจุบัน)', $report['pdpa']['overdue_open']];
        }
        if ($report['logins'] !== null) {
            $summary[] = ['เข้าสู่ระบบสำเร็จ (ครั้ง)', $report['logins']['success']];
            $summary[] = ['เข้าสู่ระบบไม่สำเร็จ (ครั้ง)', $report['logins']['failed']];
        }

        $personnel = [array_merge(['ฝ่าย/งาน', 'รวม'], array_values($types), ['ชาย', 'หญิง', 'มีบัญชีผู้ใช้'])];
        foreach ($report['personnel'] as $row) {
            $line = [$row['name'], $row['total']];
            foreach (array_keys($types) as $t) {
                $line[] = $row['by_type'][$t] ?? 0;
            }
            $personnel[] = array_merge($line, [$row['male'], $row['female'], $row['users']]);
        }
        $totals = ['รวมทั้งสิ้น', array_sum(array_column($report['personnel'], 'total'))];
        foreach (array_keys($types) as $t) {
            $totals[] = array_sum(array_map(static fn (array $r): int => $r['by_type'][$t] ?? 0, $report['personnel']));
        }
        $personnel[] = array_merge($totals, [
            array_sum(array_column($report['personnel'], 'male')),
            array_sum(array_column($report['personnel'], 'female')),
            array_sum(array_column($report['personnel'], 'users')),
        ]);

        $breakdown = [['มิติ', 'รายการ', 'จำนวน']];
        foreach ($report['breakdown']['status'] as $k => $n) {
            $breakdown[] = ['สถานะ', PersonnelService::STATUSES[$k] ?? $k, $n];
        }
        foreach ($report['breakdown']['academic_rank'] as $k => $n) {
            $breakdown[] = ['วิทยฐานะ', $k, $n];
        }
        foreach ($report['breakdown']['education_level'] as $k => $n) {
            $breakdown[] = ['วุฒิการศึกษา', $k, $n];
        }

        $rooms = [['รหัสห้อง', 'ชื่อห้องบริการ', 'ระดับชั้น', 'ครูประจำชั้น']];
        foreach ($report['rooms']['rooms'] as $r) {
            $rooms[] = [$r['room_code'], $r['name_th'], $r['grade_level'] ?: '-', $r['teacher'] ?: '-'];
        }

        $activity = [['ปฏิทินกลาง: ฝ่าย/งาน', 'จำนวนกิจกรรม']];
        foreach ($report['calendar']['by_department'] as $r) {
            $activity[] = [$r['name'], $r['n']];
        }
        $activity[] = [];
        $activity[] = ['ไฟล์: ฝ่าย/งาน', 'จำนวนไฟล์', 'ขนาด (MB)'];
        foreach ($report['files']['by_department'] as $r) {
            $activity[] = [$r['name'], $r['n'], round($r['bytes'] / 1048576, 2)];
        }
        if ($report['pdpa'] !== null) {
            $activity[] = [];
            $activity[] = ['PDPA: สถานะ', 'จำนวน'];
            foreach ($report['pdpa']['status'] as $k => $n) {
                $activity[] = [PdpaService::STATUSES[$k][0] ?? $k, $n];
            }
            $activity[] = ['PDPA: ประเภทคำขอ', 'จำนวน'];
            foreach ($report['pdpa']['type'] as $k => $n) {
                $activity[] = [PdpaService::TYPES[$k][0] ?? $k, $n];
            }
        }

        return [
            'สรุปภาพรวม' => $summary,
            'บุคลากรรายฝ่าย' => $personnel,
            'บุคลากรตามมิติ' => $breakdown,
            'ห้องบริการ' => $rooms,
            'กิจกรรมและไฟล์' => $activity,
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0:string, 1:list<int>} */
    private static function inClause(string $column, ?array $ids): array
    {
        if ($ids === null) {
            return ['1 = 1', []];
        }
        if ($ids === []) {
            return ['1 = 0', []];
        }
        $ids = array_map('intval', $ids);

        return [$column . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }

    /** @return array<string,int> */
    private static function countBy(string $column, string $in, array $params, string $emptyLabel): array
    {
        $rows = Database::instance()->fetchAll(
            "SELECT NULLIF(TRIM({$column}), '') AS k, COUNT(*) AS n FROM employees
             WHERE deleted_at IS NULL AND status = 'active' AND {$in} GROUP BY k ORDER BY n DESC, k",
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $key = $r['k'] ?? $emptyLabel;
            $out[$key] = ($out[$key] ?? 0) + (int) $r['n'];
        }

        return $out;
    }

    private static function emptyDepartmentRow(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'total' => 0, 'users' => 0, 'by_type' => [], 'male' => 0, 'female' => 0];
    }

    private static function intRows(array $rows): array
    {
        return array_map(static function (array $r): array {
            foreach (['n', 'bytes'] as $k) {
                if (isset($r[$k])) {
                    $r[$k] = (int) $r[$k];
                }
            }

            return $r;
        }, $rows);
    }

    private static function fileTypeLabel(string $mime): string
    {
        return match (true) {
            $mime === 'application/pdf' => 'PDF',
            str_starts_with($mime, 'image/') => 'รูปภาพ',
            str_contains($mime, 'wordprocessingml') => 'Word',
            str_contains($mime, 'spreadsheetml') => 'Excel',
            default => 'อื่น ๆ',
        };
    }

    private static function isDate(?string $value): bool
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    private static function academicYearIdFor(string $date): ?int
    {
        $id = Database::instance()->fetchColumn(
            'SELECT id FROM academic_years WHERE ? BETWEEN start_date AND end_date ORDER BY year_be DESC LIMIT 1',
            [$date]
        );

        return $id !== false && $id !== null ? (int) $id : null;
    }
}
