<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Database;
use Core\Exceptions\ValidationException;

/**
 * ข้อมูลฝ่าย/งาน (ตาราง departments): ใช้เป็นตัวเลือกฝ่าย/งานของบุคลากร ผู้ใช้งาน หน่วยบริการ และสิทธิ์รายฝ่าย
 * ลบได้เฉพาะฝ่าย/งานที่ไม่มีข้อมูลอ้างถึง (ดู usage()) ส่วนที่มีข้อมูลแล้วใช้ปิดการใช้งานแทน:
 * ฝ่ายที่ปิดจะไม่แสดงในตัวเลือก แต่ข้อมูลเดิมยังอ้างถึงได้
 */
final class DepartmentService
{
    public const TYPES = [
        'administration' => 'สำนักงานผู้อำนวยการ',
        'division' => 'ฝ่าย',
        'service_unit' => 'งานหน่วยบริการ',
        'unit' => 'หน่วยบริการ/หน่วยย่อย',
    ];

    public const LABELS = [
        'sort_order' => 'ลำดับที่',
        'name_th' => 'ชื่อฝ่าย/งาน',
        'code' => 'รหัส',
        'type' => 'ประเภท',
        'parent_id' => 'สังกัด',
        'is_active' => 'สถานะการใช้งาน',
    ];

    public static function rules(): array
    {
        return [
            'sort_order' => 'required|integer',
            'name_th' => 'required|max:191',
            'code' => 'required|max:50',
            'type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'parent_id' => 'nullable|integer',
            'is_active' => 'nullable|in:1',
        ];
    }

    /** ทุกฝ่าย/งาน เรียงตามลำดับ พร้อมชื่อหน่วยแม่และจำนวนบุคลากร/ผู้ใช้งานที่สังกัด */
    public static function all(): array
    {
        return Database::instance()->fetchAll(
            'SELECT d.*, p.name_th AS parent_name,
                    (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.deleted_at IS NULL) AS employee_count,
                    (SELECT COUNT(*) FROM departments c WHERE c.parent_id = d.id) AS child_count
             FROM departments d LEFT JOIN departments p ON p.id = d.parent_id
             ORDER BY d.sort_order, d.id'
        );
    }

    public static function find(int $id): ?array
    {
        return Database::instance()->fetch('SELECT * FROM departments WHERE id = ?', [$id]);
    }

    /** ลำดับที่ถัดไป (ค่าแนะนำในฟอร์มเพิ่ม) */
    public static function nextSortOrder(): int
    {
        return (int) Database::instance()->fetchColumn('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM departments');
    }

    /** ตัวเลือก "สังกัด": ทุกฝ่ายยกเว้นตัวเองและหน่วยลูกหลาน (กันการวนซ้ำ) */
    public static function parentOptions(?int $selfId): array
    {
        $exclude = $selfId !== null ? array_merge([$selfId], self::descendantIds($selfId)) : [];

        return array_values(array_filter(self::all(), static fn (array $d): bool => !in_array((int) $d['id'], $exclude, true)));
    }

    public static function create(array $data): int
    {
        $row = self::row($data, null);
        $id = Database::instance()->insert('departments', $row);
        Audit::log('department.created', 'core', 'departments', $id, null, $row);

        return $id;
    }

    public static function update(array $current, array $data): void
    {
        $id = (int) $current['id'];
        $row = self::row($data, $id);
        Database::instance()->update('departments', $row, ['id' => $id]);
        Audit::log('department.updated', 'core', 'departments', $id, self::snapshot($current), $row);
    }

    /**
     * ข้อมูลที่อ้างถึงฝ่าย/งานนี้ (ป้ายชื่อ => จำนวน) เฉพาะรายการที่มี
     * ลบได้เมื่อไม่มีการอ้างถึงเลย เพื่อไม่ให้ข้อมูลบุคลากร/สิทธิ์รายฝ่าย/การรับบริการหลุดสังกัดโดยไม่ตั้งใจ
     * @return array<string, int>
     */
    public static function usage(int $id): array
    {
        $db = Database::instance();
        $checks = [
            'หน่วยย่อยภายใต้' => 'SELECT COUNT(*) FROM departments WHERE parent_id = ?',
            'บุคลากร' => 'SELECT COUNT(*) FROM employees WHERE department_id = ? AND deleted_at IS NULL',
            'บุคลากร (สังกัดรอง)' => 'SELECT COUNT(*) FROM employees WHERE service_unit_id = ? AND deleted_at IS NULL',
            'สิทธิ์รายฝ่ายของผู้ใช้งาน' => 'SELECT COUNT(*) FROM user_departments WHERE department_id = ?',
            'กิจกรรมในปฏิทินกลาง' => 'SELECT COUNT(*) FROM calendar_events WHERE department_id = ?',
            'ไฟล์/เอกสาร' => 'SELECT COUNT(*) FROM files WHERE department_id = ?',
            'การรับบริการของนักเรียน' => 'SELECT COUNT(*) FROM academic_student_enrollments WHERE department_id = ?',
        ];
        $out = [];
        foreach ($checks as $label => $sql) {
            preg_match('/FROM (\w+)/', $sql, $m);
            if (!self::tableExists($m[1])) {
                continue; // โมดูลที่ยังไม่ได้ติดตั้ง
            }
            $n = (int) $db->fetchColumn($sql, [$id]);
            if ($n > 0) {
                $out[$label] = $n;
            }
        }

        return $out;
    }

    public static function delete(array $current): void
    {
        $id = (int) $current['id'];
        $usage = self::usage($id);
        if ($usage !== []) {
            throw new ValidationException(['department' => ['ลบ ' . $current['name_th'] . ' ไม่ได้ เนื่องจากยังมีข้อมูลที่อ้างถึง: '
                . self::usageText($usage) . ' — ย้ายข้อมูลออกก่อน หรือใช้การปิดการใช้งานแทน']]);
        }
        Database::instance()->delete('departments', ['id' => $id]);
        Audit::log('department.deleted', 'core', 'departments', $id, self::snapshot($current));
    }

    /** @param array<string, int> $usage */
    public static function usageText(array $usage): string
    {
        return implode(', ', array_map(static fn (string $k, int $n): string => "{$k} {$n} รายการ", array_keys($usage), $usage));
    }

    private static function tableExists(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Database::instance()->fetchColumn(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]
        ) !== null;
    }

    /** เปิด/ปิดการใช้งาน */
    public static function setActive(array $current, bool $active): void
    {
        $id = (int) $current['id'];
        Database::instance()->update('departments', ['is_active' => $active ? 1 : 0], ['id' => $id]);
        Audit::log($active ? 'department.enabled' : 'department.disabled', 'core', 'departments', $id,
            ['is_active' => (int) $current['is_active']], ['is_active' => $active ? 1 : 0]);
    }

    private static function row(array $data, ?int $id): array
    {
        $db = Database::instance();
        $errors = [];
        $code = strtoupper(trim((string) $data['code']));
        if (preg_match('/^[A-Z0-9_]{2,50}$/', $code) !== 1) {
            $errors['code'][] = 'รหัสใช้ได้เฉพาะ A-Z ตัวเลข และ _ (2-50 ตัว) เช่น ACADEMIC, SU_PONG';
        } elseif ($db->fetchColumn('SELECT 1 FROM departments WHERE code = ? AND id <> ?', [$code, $id ?? 0]) !== null) {
            $errors['code'][] = "รหัส {$code} มีอยู่แล้ว";
        }
        $name = trim((string) $data['name_th']);
        if ($name !== '' && $db->fetchColumn('SELECT 1 FROM departments WHERE name_th = ? AND id <> ?', [$name, $id ?? 0]) !== null) {
            $errors['name_th'][] = "มีฝ่าย/งานชื่อ \"{$name}\" อยู่แล้ว";
        }
        $sort = (int) $data['sort_order'];
        if ($sort < 0 || $sort > 65535) {
            $errors['sort_order'][] = 'ลำดับที่ต้องอยู่ระหว่าง 0-65535';
        }
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId !== null) {
            if (self::find($parentId) === null) {
                $errors['parent_id'][] = 'ไม่พบฝ่าย/งานที่เลือกเป็นสังกัด';
            } elseif ($id !== null && ($parentId === $id || in_array($parentId, self::descendantIds($id), true))) {
                $errors['parent_id'][] = 'เลือกตัวเองหรือหน่วยที่อยู่ภายใต้เป็นสังกัดไม่ได้';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'sort_order' => $sort,
            'name_th' => $name,
            'code' => $code,
            'type' => (string) $data['type'],
            'parent_id' => $parentId,
            'is_active' => ($data['is_active'] ?? null) === '1' ? 1 : 0,
        ];
    }

    /** @return list<int> */
    private static function descendantIds(int $id): array
    {
        $all = Database::instance()->fetchAll('SELECT id, parent_id FROM departments');
        $out = [];
        $queue = [$id];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($all as $d) {
                if ((int) $d['parent_id'] === $current && !in_array((int) $d['id'], $out, true)) {
                    $out[] = (int) $d['id'];
                    $queue[] = (int) $d['id'];
                }
            }
        }

        return $out;
    }

    private static function snapshot(array $d): array
    {
        return array_intersect_key($d, array_flip(['sort_order', 'name_th', 'code', 'type', 'parent_id', 'is_active']));
    }
}
