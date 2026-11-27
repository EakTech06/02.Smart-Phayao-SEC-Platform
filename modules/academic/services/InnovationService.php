<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;
use Modules\CoreAdmin\Services\NotificationService;

/**
 * สื่อนวัตกรรม: ระยะที่ 1 คู่มือการใช้สื่อ → ใช้สื่อกับกลุ่มเป้าหมาย → ระยะที่ 2 รายงานการใช้สื่อ
 * สิทธิ์เหมือนงานวิจัย: ครูเห็นรายละเอียด/ไฟล์เฉพาะของตนเอง ผู้มี read_all/review_all เห็นทุกชิ้น (ยกเว้นร่างของผู้อื่น)
 * ข้อมูลผู้เรียนเก็บเป็นจำนวนแยกตามประเภทความพิการเท่านั้น
 */
final class InnovationService
{
    private const OWNER_NAME_SQL = ResearchService::OWNER_NAME_SQL;
    private const PERSON_SQL = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.title_name, ''), COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''), u.username)";

    // ---------------------------------------------------------------- สิทธิ์และการค้นหา

    public static function isOwner(array $media, ?int $userId = null): bool
    {
        return (int) $media['owner_user_id'] === (int) ($userId ?? Auth::id());
    }

    public static function canView(array $media, ?int $userId = null): bool
    {
        return self::isOwner($media, $userId)
            || ($media['status'] !== 'draft' && Rbac::canAny([ResearchLookup::PERM_READ_ALL, ResearchLookup::PERM_REVIEW], $userId));
    }

    public static function findVisible(int $id): array
    {
        $media = self::find($id);
        if ($media === null || !self::canView($media)) {
            throw new HttpException(404);
        }

        return $media;
    }

    public static function findOwned(int $id): array
    {
        $media = self::findVisible($id);
        if (!self::isOwner($media)) {
            throw new HttpException(403);
        }

        return $media;
    }

    public static function find(int $id): ?array
    {
        $media = Database::instance()->fetch(
            'SELECT m.*, t.name AS type_name, y.name AS year_name, y.year_be, ' . self::OWNER_NAME_SQL . ' AS owner_name, oe.position AS owner_position
             FROM innovation_media m
             JOIN research_types t ON t.id = m.type_id
             JOIN academic_years y ON y.id = m.academic_year_id
             JOIN users ou ON ou.id = m.owner_user_id
             LEFT JOIN employees oe ON oe.id = ou.employee_id
             WHERE m.id = ? AND m.deleted_at IS NULL',
            [$id]
        );
        if ($media !== null) {
            $media['targets'] = self::targets((int) $media['id']);
        }

        return $media;
    }

    /** @return array{planned: array<string, int>, actual: array<string, int>} */
    public static function targets(int $mediaId): array
    {
        $out = ['planned' => [], 'actual' => []];
        foreach (Database::instance()->fetchAll(
            'SELECT phase, disability_type, target_count FROM innovation_targets WHERE media_id = ?', [$mediaId]
        ) as $r) {
            $out[$r['phase']][$r['disability_type']] = (int) $r['target_count'];
        }

        return $out;
    }

    /** @param array{q?: string, year_id?: int, semester?: int, media_type_id?: int, disability?: string, status?: string} $filters */
    public static function list(array $filters, bool $mineOnly, int $limit = 500): array
    {
        [$where, $params] = self::filterSql($filters, $mineOnly);
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']) . '%';
            $where[] = '(m.title LIKE ? OR ' . self::OWNER_NAME_SQL . ' LIKE ?)';
            array_push($params, $like, $like);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'm.status = ?';
            $params[] = $filters['status'];
        }
        $rows = Database::instance()->fetchAll(
            'SELECT m.id, m.title, m.status, m.semester, m.plan_start, m.plan_end, m.report_due_on, m.manual_version, m.report_version,
                    m.status_changed_at, m.created_at, m.owner_user_id, t.name AS type_name, y.year_be, ' . self::OWNER_NAME_SQL . ' AS owner_name,
                    (SELECT COALESCE(SUM(target_count), 0) FROM innovation_targets x WHERE x.media_id = m.id AND x.phase = \'planned\') AS planned_total,
                    (SELECT COALESCE(SUM(target_count), 0) FROM innovation_targets x WHERE x.media_id = m.id AND x.phase = \'actual\') AS actual_total
             FROM innovation_media m
             JOIN research_types t ON t.id = m.type_id
             JOIN academic_years y ON y.id = m.academic_year_id
             JOIN users ou ON ou.id = m.owner_user_id
             LEFT JOIN employees oe ON oe.id = ou.employee_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY COALESCE(m.status_changed_at, m.created_at) DESC, m.id DESC
             LIMIT ' . max(1, min(2000, $limit)),
            $params
        );

        return $rows;
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    public static function filterSql(array $filters, bool $mineOnly): array
    {
        $where = ['m.deleted_at IS NULL'];
        $params = [];
        if ($mineOnly) {
            $where[] = 'm.owner_user_id = ?';
        } else {
            $where[] = "(m.status <> 'draft' OR m.owner_user_id = ?)";
        }
        $params[] = Auth::id();
        foreach (['year_id' => 'm.academic_year_id', 'semester' => 'm.semester', 'media_type_id' => 'm.type_id'] as $key => $column) {
            if ((int) ($filters[$key] ?? 0) > 0) {
                $where[] = "{$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (($filters['disability'] ?? '') !== '') {
            $where[] = "EXISTS (SELECT 1 FROM innovation_targets d WHERE d.media_id = m.id AND d.phase = 'planned' AND d.target_count > 0 AND d.disability_type = ?)";
            $params[] = $filters['disability'];
        }

        return [$where, $params];
    }

    /** @return array{manual: list<array>, report: list<array>} */
    public static function versions(int $mediaId): array
    {
        $out = ['manual' => [], 'report' => []];
        foreach (Database::instance()->fetchAll(
            'SELECT v.*, ' . self::PERSON_SQL . ' AS uploader_name FROM innovation_versions v
             LEFT JOIN users u ON u.id = v.uploaded_by LEFT JOIN employees e ON e.id = u.employee_id
             WHERE v.media_id = ? ORDER BY v.doc, v.version_no DESC',
            [$mediaId]
        ) as $v) {
            $out[$v['doc']][] = $v;
        }

        return $out;
    }

    public static function version(int $mediaId, int $versionId): array
    {
        return Database::instance()->fetch('SELECT * FROM innovation_versions WHERE id = ? AND media_id = ?', [$versionId, $mediaId])
            ?? throw new HttpException(404);
    }

    public static function statusLogs(int $mediaId): array
    {
        return Database::instance()->fetchAll(
            'SELECT l.*, ' . self::PERSON_SQL . ' AS user_name FROM innovation_status_logs l
             LEFT JOIN users u ON u.id = l.user_id LEFT JOIN employees e ON e.id = u.employee_id
             WHERE l.media_id = ? ORDER BY l.id DESC',
            [$mediaId]
        );
    }

    public static function comments(int $mediaId): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT c.*, ' . self::PERSON_SQL . ' AS user_name FROM innovation_comments c
             LEFT JOIN users u ON u.id = c.user_id LEFT JOIN employees e ON e.id = u.employee_id
             WHERE c.media_id = ? ORDER BY c.id',
            [$mediaId]
        );
        $threads = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) {
                $threads[(int) $r['id']] = $r + ['replies' => []];
            }
        }
        foreach ($rows as $r) {
            if ($r['parent_id'] !== null && isset($threads[(int) $r['parent_id']])) {
                $threads[(int) $r['parent_id']]['replies'][] = $r;
            }
        }

        return array_reverse(array_values($threads));
    }

    /** กำหนดส่งรายงานตามแผน (ก่อนคู่มืออนุมัติใช้คำนวณแสดงผล) */
    public static function reportDueFor(array $media): string
    {
        return $media['report_due_on'] !== null && $media['report_due_on'] !== ''
            ? (string) $media['report_due_on']
            : date('Y-m-d', strtotime($media['plan_end'] . ' +' . InnovationLookup::reportDueDays() . ' days'));
    }

    public static function link(int $id): string
    {
        return '/academic/innovation/media/' . $id;
    }

    // ---------------------------------------------------------------- ระยะที่ 1: คู่มือ

    /** @param array{title: string, type_id: int, academic_year_id: int, semester: int, plan_start: string, plan_end: string, planned: array<string, int>} $data */
    public static function create(array $data, array $file, bool $submit): int
    {
        $id = Database::instance()->transaction(static function (Database $db) use ($data, $file): int {
            $id = $db->insert('innovation_media', [
                'owner_user_id' => Auth::id(),
                'title' => $data['title'],
                'type_id' => $data['type_id'],
                'academic_year_id' => $data['academic_year_id'],
                'semester' => $data['semester'],
                'plan_start' => $data['plan_start'],
                'plan_end' => $data['plan_end'],
                'status' => 'draft',
                'status_changed_at' => Database::now(),
            ]);
            self::saveTargets($db, $id, 'planned', $data['planned']);
            self::log($db, $id, 'manual', null, 'draft', null, null);
            self::storeVersion($db, $id, 'manual', 1, $file, 'ฉบับแรก');

            return $id;
        });
        Audit::log('innovation.created', 'academic', 'innovation_media', $id, null, ['title' => $data['title']]);
        if ($submit) {
            self::submit(self::find($id) ?? throw new HttpException(404));
        }

        return $id;
    }

    public static function updateManual(array $media, array $data): void
    {
        self::assertOwnerIn($media, ['draft', 'manual_revision']);
        Database::instance()->transaction(static function (Database $db) use ($media, $data): void {
            $db->update('innovation_media', array_intersect_key($data, array_flip(
                ['title', 'type_id', 'academic_year_id', 'semester', 'plan_start', 'plan_end']
            )), ['id' => (int) $media['id']]);
            self::saveTargets($db, (int) $media['id'], 'planned', $data['planned']);
        });
        Audit::log('innovation.updated', 'academic', 'innovation_media', (int) $media['id'],
            array_intersect_key($media, array_flip(['title', 'type_id', 'academic_year_id', 'semester', 'plan_start', 'plan_end'])),
            array_diff_key($data, ['planned' => true]));
    }

    // ---------------------------------------------------------------- ระยะที่ 2: รายงาน

    /**
     * ส่งรายงานการใช้สื่อ (เมื่อคู่มืออนุมัติแล้วเท่านั้น) เลือกจากสื่อเดิม ไม่ต้องกรอกข้อมูลสื่อซ้ำ
     * @param array{actual_start: string, actual_end: string, actual: array<string, int>} $data
     */
    public static function submitReport(array $media, array $data, array $file): void
    {
        self::assertOwnerIn($media, ['in_use'], 'ส่งรายงานได้เมื่อคู่มือได้รับอนุมัติแล้ว (สถานะอยู่ระหว่างใช้สื่อ) เท่านั้น');
        Database::instance()->transaction(static function (Database $db) use ($media, $data, $file): void {
            $db->update('innovation_media', ['actual_start' => $data['actual_start'], 'actual_end' => $data['actual_end']], ['id' => (int) $media['id']]);
            self::saveTargets($db, (int) $media['id'], 'actual', $data['actual']);
            $no = (int) $db->fetchColumn('SELECT report_version FROM innovation_media WHERE id = ? FOR UPDATE', [(int) $media['id']]) + 1;
            self::storeVersion($db, (int) $media['id'], 'report', $no, $file, $no === 1 ? 'ฉบับแรก' : null);
        });
        $media = self::find((int) $media['id']) ?? throw new HttpException(404);
        self::transition($media, 'report', 'report_submitted', null, ['report_submitted_at' => Database::now()]);
        self::notifyReviewers($media, 'มีรายงานการใช้สื่อนวัตกรรมส่งใหม่: ');
    }

    public static function updateReport(array $media, array $data): void
    {
        self::assertOwnerIn($media, ['report_revision']);
        Database::instance()->transaction(static function (Database $db) use ($media, $data): void {
            $db->update('innovation_media', ['actual_start' => $data['actual_start'], 'actual_end' => $data['actual_end']], ['id' => (int) $media['id']]);
            self::saveTargets($db, (int) $media['id'], 'actual', $data['actual']);
        });
        Audit::log('innovation.report_updated', 'academic', 'innovation_media', (int) $media['id'],
            ['actual_start' => $media['actual_start'], 'actual_end' => $media['actual_end']],
            ['actual_start' => $data['actual_start'], 'actual_end' => $data['actual_end']]);
    }

    // ---------------------------------------------------------------- ไฟล์ ส่ง ตรวจ

    /** อัปโหลดไฟล์ฉบับใหม่ของฉบับที่อยู่ในขั้นตอน (ร่าง/แก้คู่มือ = คู่มือ, แก้รายงาน = รายงาน) */
    public static function addVersion(array $media, array $file, ?string $note): int
    {
        self::assertOwnerIn($media, InnovationLookup::OWNER_EDITABLE);
        $doc = $media['status'] === 'report_revision' ? 'report' : 'manual';
        $no = Database::instance()->transaction(static function (Database $db) use ($media, $doc, $file, $note): int {
            $no = (int) $db->fetchColumn("SELECT {$doc}_version FROM innovation_media WHERE id = ? FOR UPDATE", [(int) $media['id']]) + 1;
            self::storeVersion($db, (int) $media['id'], $doc, $no, $file, $note);

            return $no;
        });
        Audit::log('innovation.version_uploaded', 'academic', 'innovation_media', (int) $media['id'], null, ['doc' => $doc, 'version' => $no]);

        return $no;
    }

    /** ครูส่ง: ร่าง → คู่มือส่งแล้ว / ส่งกลับแก้ไข → ส่งฉบับแก้ไขแล้ว (ต้องอัปโหลดไฟล์ใหม่หลังถูกส่งกลับ) */
    public static function submit(array $media): string
    {
        if (!self::isOwner($media)) {
            throw new HttpException(403);
        }
        $status = (string) $media['status'];
        if ($status === 'draft') {
            $doc = 'manual';
            $to = 'manual_submitted';
        } elseif ($status === 'manual_revision' || $status === 'report_revision') {
            $doc = $status === 'manual_revision' ? 'manual' : 'report';
            $requestedAt = (int) Database::instance()->fetchColumn(
                'SELECT version_no FROM innovation_status_logs WHERE media_id = ? AND to_status = ? ORDER BY id DESC LIMIT 1',
                [(int) $media['id'], $status]
            );
            if ((int) $media[$doc . '_version'] <= $requestedAt) {
                throw new ValidationException(['file' => ['กรุณาอัปโหลดไฟล์ฉบับแก้ไขก่อนส่ง']]);
            }
            $to = $doc . '_resubmitted';
        } else {
            throw new ValidationException(['status' => ['ส่งในสถานะนี้ไม่ได้']]);
        }
        $extra = $status === 'draft' ? ['submitted_at' => $media['submitted_at'] ?? Database::now()] : [];
        self::transition($media, $doc, $to, null, $extra);
        self::notifyReviewers($media, match ($to) {
            'manual_submitted' => 'มีคู่มือการใช้สื่อนวัตกรรมส่งใหม่: ',
            'manual_resubmitted' => 'ส่งคู่มือการใช้สื่อฉบับแก้ไขแล้ว: ',
            default => 'ส่งรายงานการใช้สื่อฉบับแก้ไขแล้ว: ',
        });

        return $to;
    }

    /** ผู้ตรวจเปลี่ยนสถานะ (action ตาม InnovationLookup::REVIEW_ACTIONS) */
    public static function review(array $media, string $action, ?string $note): string
    {
        if (!Rbac::can(ResearchLookup::PERM_REVIEW)) {
            throw new HttpException(403);
        }
        $from = (string) $media['status'];
        $to = isset(InnovationLookup::REVIEW_ACTIONS[$action]) ? InnovationLookup::reviewTarget($from, $action) : null;
        if ($to === null) {
            throw new ValidationException(['action' => ['ดำเนินการนี้กับสถานะ "' . InnovationLookup::statusLabel($from) . '" ไม่ได้']]);
        }
        if (InnovationLookup::REVIEW_ACTIONS[$action][1] && ($note === null || $note === '')) {
            throw new ValidationException(['note' => ['กรุณาระบุเหตุผล/ข้อเสนอแนะ']]);
        }
        $doc = InnovationLookup::stage($from);
        $extra = [];
        if ($to === 'in_use') {
            $extra = [
                'manual_approved_at' => Database::now(),
                'report_due_on' => date('Y-m-d', strtotime($media['plan_end'] . ' +' . InnovationLookup::reportDueDays() . ' days')),
            ];
        } elseif ($to === 'completed') {
            $extra = ['completed_at' => Database::now()];
        }
        self::transition($media, $doc, $to, $note, $extra);

        $short = mb_substr((string) $media['title'], 0, 120);
        [$title, $message] = match ($to) {
            'in_use' => ["คู่มือการใช้สื่ออนุมัติแล้ว เริ่มใช้สื่อได้: {$short}",
                'ใช้สื่อตามแผน ' . thai_date((string) $media['plan_start'], false) . ' - ' . thai_date((string) $media['plan_end'], false)
                . ' และส่งรายงานภายใน ' . thai_date((string) $extra['report_due_on'], false, true) . ($note ? "\n{$note}" : '')],
            'completed' => ["รายงานการใช้สื่ออนุมัติแล้ว (เสร็จสมบูรณ์): {$short}", $note],
            default => ['สื่อนวัตกรรม "' . $short . '": ' . InnovationLookup::statusLabel($to), $note],
        };
        NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE, $title, $message, self::link((int) $media['id']), true);

        return $to;
    }

    public static function cancelByOwner(array $media, ?string $note): void
    {
        self::assertOwnerIn($media, InnovationLookup::OWNER_CANCELLABLE, 'ยกเลิกในสถานะนี้ไม่ได้');
        self::transition($media, 'manual', 'cancelled', $note);
        if ($media['status'] !== 'draft') {
            self::notifyReviewers($media, 'ครูยกเลิกสื่อนวัตกรรม: ', $note);
        }
    }

    public static function deleteDraft(array $media): void
    {
        if (!self::isOwner($media) || !in_array($media['status'], ['draft', 'cancelled'], true) || $media['submitted_at'] !== null) {
            throw new ValidationException(['status' => ['ลบได้เฉพาะร่างที่ยังไม่เคยส่ง']]);
        }
        Database::instance()->update('innovation_media', ['deleted_at' => Database::now()], ['id' => (int) $media['id']]);
        Audit::log('innovation.deleted', 'academic', 'innovation_media', (int) $media['id'], ['title' => $media['title']]);
    }

    public static function addComment(array $media, string $body, ?int $parentId): void
    {
        $isOwner = self::isOwner($media);
        $isReviewer = Rbac::can(ResearchLookup::PERM_REVIEW);
        $db = Database::instance();
        $parent = null;
        if ($parentId !== null) {
            $parent = $db->fetch('SELECT * FROM innovation_comments WHERE id = ? AND media_id = ? AND parent_id IS NULL', [$parentId, (int) $media['id']])
                ?? throw new HttpException(404);
            if (!$isOwner && !$isReviewer) {
                throw new HttpException(403);
            }
        } elseif (!$isReviewer) {
            throw new HttpException(403);
        }
        if ($media['status'] === 'draft') {
            throw new ValidationException(['body' => ['งานยังไม่ได้ส่ง']]);
        }
        $doc = $parent['doc'] ?? InnovationLookup::stage((string) $media['status']);
        $id = $db->insert('innovation_comments', [
            'media_id' => (int) $media['id'],
            'parent_id' => $parentId,
            'doc' => $doc,
            'version_no' => (int) $media[$doc . '_version'] ?: null,
            'user_id' => Auth::id(),
            'body' => $body,
        ]);
        Audit::log('innovation.commented', 'academic', 'innovation_comments', $id, null, ['media_id' => (int) $media['id'], 'parent_id' => $parentId]);

        $link = self::link((int) $media['id']) . '#feedback';
        $short = mb_substr((string) $media['title'], 0, 120);
        if (!$isOwner) {
            NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
                ($parent === null ? 'มีข้อเสนอแนะใหม่ (สื่อนวัตกรรม): ' : 'ผู้ตรวจตอบกลับข้อเสนอแนะ: ') . $short, mb_substr($body, 0, 300), $link, $parent === null);
        }
        if ($parent !== null) {
            $participants = array_map('intval', array_column($db->fetchAll(
                'SELECT DISTINCT user_id FROM innovation_comments WHERE (id = ? OR parent_id = ?) AND user_id IS NOT NULL', [$parentId, $parentId]
            ), 'user_id'));
            NotificationService::notify(array_diff($participants, [(int) Auth::id(), (int) $media['owner_user_id']]), ResearchLookup::NOTIFY_TYPE,
                ($isOwner ? 'ครูตอบกลับข้อเสนอแนะ: ' : 'มีการตอบกลับข้อเสนอแนะ: ') . $short, mb_substr($body, 0, 300), $link);
        }
    }

    // ---------------------------------------------------------------- แดชบอร์ด

    /**
     * จำนวนเท่านั้น (ไม่มีรายละเอียดรายชิ้น)
     * @return array{cards: array<string, int>, status: list<array>, types: list<array>, disabilities: array<string, int>, targets: list<array>}
     */
    public static function dashboard(array $filters, bool $mineOnly): array
    {
        [$where, $params] = self::filterSql($filters, $mineOnly);
        $sqlWhere = implode(' AND ', $where);
        $db = Database::instance();

        $byStatus = array_fill_keys(array_keys(InnovationLookup::STATUSES), 0);
        foreach ($db->fetchAll("SELECT m.status, COUNT(*) AS n FROM innovation_media m WHERE {$sqlWhere} GROUP BY m.status", $params) as $r) {
            $byStatus[$r['status']] = (int) $r['n'];
        }
        $drafts = $byStatus['draft'];
        if (!$mineOnly) {
            unset($byStatus['draft']);
        }
        $sum = static fn (array $keys): int => array_sum(array_intersect_key($byStatus, array_flip($keys)));
        $overdue = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM innovation_media m WHERE {$sqlWhere} AND m.status = 'in_use' AND m.report_due_on < ?",
            [...$params, date('Y-m-d')]
        );

        $types = [];
        foreach (ResearchService::types('innovation', false) as $t) {
            $types[(int) $t['id']] = ['label' => $t['name'], 'count' => 0, 'active' => (bool) $t['is_active']];
        }
        foreach ($db->fetchAll("SELECT m.type_id, COUNT(*) AS n FROM innovation_media m WHERE {$sqlWhere} AND m.status <> 'draft' GROUP BY m.type_id", $params) as $r) {
            if (isset($types[(int) $r['type_id']])) {
                $types[(int) $r['type_id']]['count'] = (int) $r['n'];
            }
        }

        // จำนวนชิ้น/จำนวนกลุ่มเป้าหมาย แยกตามประเภทความพิการ (ตามแผน และใช้จริง)
        $disabilities = array_fill_keys(array_keys(StudentLookup::DISABILITY_TYPES), 0);
        $planned = $disabilities;
        $actual = $disabilities;
        foreach ($db->fetchAll(
            "SELECT d.phase, d.disability_type, COUNT(*) AS n, SUM(d.target_count) AS total
             FROM innovation_targets d JOIN innovation_media m ON m.id = d.media_id
             WHERE {$sqlWhere} AND m.status <> 'draft' AND d.target_count > 0 GROUP BY d.phase, d.disability_type",
            $params
        ) as $r) {
            if (!isset($disabilities[$r['disability_type']])) {
                continue;
            }
            if ($r['phase'] === 'planned') {
                $disabilities[$r['disability_type']] = (int) $r['n'];
                $planned[$r['disability_type']] = (int) $r['total'];
            } else {
                $actual[$r['disability_type']] = (int) $r['total'];
            }
        }

        return [
            'cards' => [
                'media_total' => array_sum($byStatus) - ($byStatus['draft'] ?? 0),
                'pending_review' => $sum(InnovationLookup::PENDING_REVIEW),
                'revision' => $sum(InnovationLookup::REVISION),
                'approved' => $sum(InnovationLookup::APPROVED),
                'in_use' => $byStatus['in_use'],
                'overdue_reports' => $overdue,
                'draft' => $mineOnly ? $drafts : 0,
            ],
            'status' => array_map(
                static fn (string $k, int $n): array => ['key' => $k, 'label' => InnovationLookup::statusLabel($k), 'count' => $n],
                array_keys($byStatus), array_values($byStatus)
            ),
            'types' => array_values(array_filter($types, static fn (array $t): bool => $t['active'] || $t['count'] > 0)),
            'disabilities' => $disabilities,
            'targets' => array_map(
                static fn (string $k): array => ['key' => $k, 'label' => StudentLookup::DISABILITY_TYPES[$k], 'planned' => $planned[$k], 'actual' => $actual[$k]],
                array_keys(StudentLookup::DISABILITY_TYPES)
            ),
        ];
    }

    // ---------------------------------------------------------------- ภายใน

    private static function assertOwnerIn(array $media, array $statuses, string $message = 'แก้ไขในสถานะนี้ไม่ได้'): void
    {
        if (!self::isOwner($media)) {
            throw new HttpException(403);
        }
        if (!in_array($media['status'], $statuses, true)) {
            throw new ValidationException(['status' => [$message]]);
        }
    }

    /** @param array<string, int> $counts */
    private static function saveTargets(Database $db, int $mediaId, string $phase, array $counts): void
    {
        $db->delete('innovation_targets', ['media_id' => $mediaId, 'phase' => $phase]);
        foreach ($counts as $type => $count) {
            if (isset(StudentLookup::DISABILITY_TYPES[$type]) && $count > 0) {
                $db->insert('innovation_targets', ['media_id' => $mediaId, 'phase' => $phase, 'disability_type' => $type, 'target_count' => $count]);
            }
        }
    }

    private static function storeVersion(Database $db, int $mediaId, string $doc, int $versionNo, array $file, ?string $note): void
    {
        $stored = ResearchStorage::store($file, 'm' . $mediaId, $doc . '_v' . $versionNo, ['pdf']);
        $db->insert('innovation_versions', $stored + [
            'media_id' => $mediaId,
            'doc' => $doc,
            'version_no' => $versionNo,
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'uploaded_by' => Auth::id(),
        ]);
        $db->update('innovation_media', [$doc . '_version' => $versionNo], ['id' => $mediaId]);
    }

    private static function transition(array $media, string $doc, string $to, ?string $note, array $extra = []): void
    {
        $from = (string) $media['status'];
        Database::instance()->transaction(static function (Database $db) use ($media, $doc, $from, $to, $note, $extra): void {
            $sets = ['status = ?', 'status_changed_at = ?'];
            $params = [$to, Database::now()];
            foreach ($extra as $column => $value) {
                $sets[] = Database::quote($column) . ' = ?';
                $params[] = $value;
            }
            array_push($params, (int) $media['id'], $from);
            if ($db->query('UPDATE innovation_media SET ' . implode(', ', $sets) . ' WHERE id = ? AND status = ? AND deleted_at IS NULL', $params)->rowCount() !== 1) {
                throw new ValidationException(['status' => ['สถานะของงานเปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่']]);
            }
            $version = (int) $db->fetchColumn("SELECT {$doc}_version FROM innovation_media WHERE id = ?", [(int) $media['id']]);
            self::log($db, (int) $media['id'], $doc, $from, $to, $version ?: null, $note);
        });
        Audit::log('innovation.status_changed', 'academic', 'innovation_media', (int) $media['id'], ['status' => $from], ['status' => $to, 'note' => $note]);
    }

    private static function log(Database $db, int $mediaId, string $doc, ?string $from, string $to, ?int $version, ?string $note): void
    {
        $db->insert('innovation_status_logs', [
            'media_id' => $mediaId,
            'doc' => $doc,
            'from_status' => $from,
            'to_status' => $to,
            'version_no' => $version,
            'user_id' => Auth::id(),
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 1000) : null,
        ]);
    }

    private static function notifyReviewers(array $media, string $prefix, ?string $message = null): void
    {
        $reviewers = array_diff(NotificationService::usersWithPermission(ResearchLookup::PERM_REVIEW), [(int) Auth::id()]);
        NotificationService::notify($reviewers, ResearchLookup::NOTIFY_TYPE, $prefix . mb_substr((string) $media['title'], 0, 150),
            $message ?? ('ผู้ส่ง: ' . ($media['owner_name'] ?? Auth::displayName())), self::link((int) $media['id']), true);
    }
}
