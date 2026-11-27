<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;

/** ผู้ดูแล: ประเภทงานวิจัย ประเภทสื่อนวัตกรรม และรอบการส่ง */
final class ResearchSettingsService
{
    public static function saveType(string $kind, array $data, ?int $id): int
    {
        if (!isset(ResearchLookup::KINDS[$kind])) {
            throw new HttpException(404);
        }
        $db = Database::instance();
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $data['name']));
        $dup = $db->fetchColumn('SELECT id FROM research_types WHERE kind = ? AND name = ? AND id <> ?', [$kind, $name, $id ?? 0]);
        if ($dup !== null) {
            throw new ValidationException(['name' => ['มีประเภทชื่อนี้แล้ว']]);
        }
        $row = ['name' => $name, 'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => !empty($data['is_active']) ? 1 : 0];
        if ($id === null) {
            $id = $db->insert('research_types', $row + ['kind' => $kind]);
            Audit::log('research.type_created', 'academic', 'research_types', $id, null, $row + ['kind' => $kind]);

            return $id;
        }
        $old = $db->fetch('SELECT * FROM research_types WHERE id = ? AND kind = ?', [$id, $kind]) ?? throw new HttpException(404);
        $db->update('research_types', $row, ['id' => $id]);
        Audit::log('research.type_updated', 'academic', 'research_types', $id,
            array_intersect_key($old, $row), $row);

        return $id;
    }

    public static function rounds(): array
    {
        return Database::instance()->fetchAll(
            'SELECT r.*, y.name AS year_name, y.year_be FROM research_rounds r JOIN academic_years y ON y.id = r.academic_year_id
             ORDER BY y.year_be DESC, r.semester DESC, r.kind'
        );
    }

    public static function saveRound(array $data, ?int $id): int
    {
        $db = Database::instance();
        $kind = (string) $data['kind'];
        $yearId = (int) $data['academic_year_id'];
        $semester = (int) $data['semester'];
        if (!isset(ResearchLookup::KINDS[$kind]) || !isset(ResearchLookup::SEMESTERS[$semester])
            || $db->fetchColumn('SELECT id FROM academic_years WHERE id = ?', [$yearId]) === null) {
            throw new ValidationException(['kind' => ['ข้อมูลรอบการส่งไม่ถูกต้อง']]);
        }
        if ($data['opens_on'] !== null && $data['opens_on'] > $data['due_on']) {
            throw new ValidationException(['due_on' => ['วันครบกำหนดต้องไม่ก่อนวันเปิดรับ']]);
        }
        $dup = $db->fetchColumn(
            'SELECT id FROM research_rounds WHERE kind = ? AND academic_year_id = ? AND semester = ? AND id <> ?',
            [$kind, $yearId, $semester, $id ?? 0]
        );
        if ($dup !== null) {
            throw new ValidationException(['semester' => ['มีรอบการส่งของปีการศึกษาและภาคเรียนนี้แล้ว']]);
        }
        $row = [
            'kind' => $kind,
            'academic_year_id' => $yearId,
            'semester' => $semester,
            'title' => trim((string) $data['title']),
            'opens_on' => $data['opens_on'],
            'due_on' => $data['due_on'],
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($id === null) {
            $id = $db->insert('research_rounds', $row + ['created_by' => Auth::id()]);
            Audit::log('research.round_created', 'academic', 'research_rounds', $id, null, $row);

            return $id;
        }
        $old = $db->fetch('SELECT * FROM research_rounds WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $db->transaction(static function (Database $db) use ($row, $id, $old): void {
            $db->update('research_rounds', $row, ['id' => $id]);
            // เลื่อนวันครบกำหนด: เริ่มนับการแจ้งเตือนใหม่ตามวันใหม่
            if ($old['due_on'] !== $row['due_on']) {
                $db->delete('research_round_alerts', ['round_id' => $id]);
            }
        });
        Audit::log('research.round_updated', 'academic', 'research_rounds', $id, array_intersect_key($old, $row), $row);

        return $id;
    }

    public static function deleteRound(int $id): void
    {
        $db = Database::instance();
        $old = $db->fetch('SELECT * FROM research_rounds WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $db->delete('research_rounds', ['id' => $id]);
        Audit::log('research.round_deleted', 'academic', 'research_rounds', $id, $old);
    }
}
