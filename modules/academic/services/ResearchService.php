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
 * งานวิจัย: ส่งงาน ตรวจ ข้อเสนอแนะ เวอร์ชัน และประวัติสถานะ
 * - ครู (submit_own) เห็นรายละเอียด/ไฟล์ได้เฉพาะงานของตนเอง
 * - ผู้ที่มี read_all เห็นทุกงาน (ยกเว้นร่างของผู้อื่น) / review_all ตรวจและเปลี่ยนสถานะ
 */
final class ResearchService
{
    public const OWNER_NAME_SQL = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(oe.title_name, ''), COALESCE(oe.first_name, ''), ' ', COALESCE(oe.last_name, ''))), ''), ou.username)";

    // ---------------------------------------------------------------- สิทธิ์

    public static function canSubmit(?int $userId = null): bool
    {
        return Rbac::can(ResearchLookup::PERM_SUBMIT, $userId);
    }

    public static function canReadAll(?int $userId = null): bool
    {
        return Rbac::can(ResearchLookup::PERM_READ_ALL, $userId);
    }

    public static function canReview(?int $userId = null): bool
    {
        return Rbac::can(ResearchLookup::PERM_REVIEW, $userId);
    }

    public static function canAccessModule(?int $userId = null): bool
    {
        return Rbac::canAny([ResearchLookup::PERM_SUBMIT, ResearchLookup::PERM_READ_ALL, ResearchLookup::PERM_REVIEW, ResearchLookup::PERM_MANAGE], $userId);
    }

    public static function isOwner(array $work, ?int $userId = null): bool
    {
        return (int) $work['owner_user_id'] === (int) ($userId ?? Auth::id());
    }

    /** ดูรายละเอียดและไฟล์: เจ้าของ หรือผู้มี read_all/review_all (ร่างเห็นได้เฉพาะเจ้าของ) */
    public static function canView(array $work, ?int $userId = null): bool
    {
        if (self::isOwner($work, $userId)) {
            return true;
        }

        return $work['status'] !== 'draft'
            && Rbac::canAny([ResearchLookup::PERM_READ_ALL, ResearchLookup::PERM_REVIEW], $userId);
    }

    /** ป้องกัน IDOR: ไม่มีสิทธิ์ = 404 เหมือนไม่พบ */
    public static function findVisible(int $id): array
    {
        $work = self::find($id);
        if ($work === null || !self::canView($work)) {
            throw new HttpException(404);
        }

        return $work;
    }

    public static function findOwned(int $id): array
    {
        $work = self::findVisible($id);
        if (!self::isOwner($work)) {
            throw new HttpException(403);
        }

        return $work;
    }

    // ---------------------------------------------------------------- อ่านข้อมูล

    public static function find(int $id): ?array
    {
        $work = Database::instance()->fetch(
            'SELECT w.*, t.name AS type_name, y.name AS year_name, y.year_be, ' . self::OWNER_NAME_SQL . ' AS owner_name,
                    oe.position AS owner_position
             FROM research_works w
             JOIN research_types t ON t.id = w.type_id
             JOIN academic_years y ON y.id = w.academic_year_id
             JOIN users ou ON ou.id = w.owner_user_id
             LEFT JOIN employees oe ON oe.id = ou.employee_id
             WHERE w.id = ? AND w.deleted_at IS NULL',
            [$id]
        );
        if ($work !== null) {
            $work['disabilities'] = self::disabilitiesOf((int) $work['id']);
        }

        return $work;
    }

    /** @return list<string> */
    public static function disabilitiesOf(int $workId): array
    {
        return array_column(Database::instance()->fetchAll(
            'SELECT disability_type FROM research_work_disabilities WHERE work_id = ? ORDER BY disability_type',
            [$workId]
        ), 'disability_type');
    }

    /**
     * รายการงาน ค้นหาจาก ชื่อเรื่อง ชื่อผู้จัดทำ คำสำคัญ และตัวกรอง
     * @param array{q?: string, year_id?: int, semester?: int, type_id?: int, disability?: string, status?: string} $filters
     */
    public static function list(array $filters, bool $mineOnly, int $limit = 500): array
    {
        [$where, $params] = self::filterSql($filters, $mineOnly);
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']) . '%';
            $where[] = '(w.title LIKE ? OR w.keywords LIKE ? OR ' . self::OWNER_NAME_SQL . ' LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'w.status = ?';
            $params[] = $filters['status'];
        }
        $rows = Database::instance()->fetchAll(
            'SELECT w.id, w.title, w.status, w.semester, w.current_version, w.target_count, w.keywords, w.submitted_at,
                    w.status_changed_at, w.created_at, w.updated_at, w.owner_user_id,
                    t.name AS type_name, y.name AS year_name, y.year_be, ' . self::OWNER_NAME_SQL . ' AS owner_name
             FROM research_works w
             JOIN research_types t ON t.id = w.type_id
             JOIN academic_years y ON y.id = w.academic_year_id
             JOIN users ou ON ou.id = w.owner_user_id
             LEFT JOIN employees oe ON oe.id = ou.employee_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY COALESCE(w.status_changed_at, w.created_at) DESC, w.id DESC
             LIMIT ' . max(1, min(2000, $limit)),
            $params
        );
        $disabilities = self::disabilitiesFor(array_map('intval', array_column($rows, 'id')));
        foreach ($rows as &$row) {
            $row['disabilities'] = $disabilities[(int) $row['id']] ?? [];
        }

        return $rows;
    }

    /**
     * เงื่อนไขร่วมของรายการและแดชบอร์ด
     * mineOnly = งานของตนเอง (รวมร่าง) / ทั้งหมด = ไม่รวมร่างของผู้อื่น
     * @return array{0: list<string>, 1: list<mixed>}
     */
    public static function filterSql(array $filters, bool $mineOnly): array
    {
        $where = ['w.deleted_at IS NULL'];
        $params = [];
        if ($mineOnly) {
            $where[] = 'w.owner_user_id = ?';
            $params[] = Auth::id();
        } else {
            $where[] = "(w.status <> 'draft' OR w.owner_user_id = ?)";
            $params[] = Auth::id();
        }
        foreach (['year_id' => 'w.academic_year_id', 'semester' => 'w.semester', 'type_id' => 'w.type_id'] as $key => $column) {
            if ((int) ($filters[$key] ?? 0) > 0) {
                $where[] = "{$column} = ?";
                $params[] = (int) $filters[$key];
            }
        }
        if (($filters['disability'] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM research_work_disabilities d WHERE d.work_id = w.id AND d.disability_type = ?)';
            $params[] = $filters['disability'];
        }

        return [$where, $params];
    }

    /** @return array<int, list<string>> */
    private static function disabilitiesFor(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }
        $rows = Database::instance()->fetchAll(
            'SELECT work_id, disability_type FROM research_work_disabilities WHERE work_id IN (' . implode(',', array_fill(0, count($workIds), '?')) . ')',
            $workIds
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['work_id']][] = $r['disability_type'];
        }

        return $map;
    }

    public static function versions(int $workId): array
    {
        return Database::instance()->fetchAll(
            "SELECT v.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.title_name, ''), COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''), u.username) AS uploader_name
             FROM research_versions v LEFT JOIN users u ON u.id = v.uploaded_by LEFT JOIN employees e ON e.id = u.employee_id
             WHERE v.work_id = ? ORDER BY v.version_no DESC",
            [$workId]
        );
    }

    public static function version(int $workId, int $versionId): array
    {
        return Database::instance()->fetch('SELECT * FROM research_versions WHERE id = ? AND work_id = ?', [$versionId, $workId])
            ?? throw new HttpException(404);
    }

    public static function statusLogs(int $workId): array
    {
        return Database::instance()->fetchAll(
            "SELECT l.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.title_name, ''), COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''), u.username) AS user_name
             FROM research_status_logs l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN employees e ON e.id = u.employee_id
             WHERE l.work_id = ? ORDER BY l.id DESC",
            [$workId]
        );
    }

    /** @return list<array> เรื่องข้อเสนอแนะ พร้อม replies */
    public static function comments(int $workId): array
    {
        $rows = Database::instance()->fetchAll(
            "SELECT c.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(e.title_name, ''), COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))), ''), u.username) AS user_name
             FROM research_comments c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN employees e ON e.id = u.employee_id
             WHERE c.work_id = ? ORDER BY c.id",
            [$workId]
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

        return array_reverse(array_values($threads)); // เรื่องล่าสุดอยู่บน
    }

    public static function types(string $kind, bool $activeOnly = true): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM research_types WHERE kind = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, name',
            [$kind]
        );
    }

    public static function years(): array
    {
        return Database::instance()->fetchAll('SELECT id, name, year_be, is_current FROM academic_years ORDER BY year_be DESC');
    }

    public static function currentYearId(): ?int
    {
        return StudentService::currentYearId();
    }

    public static function round(string $kind, int $yearId, int $semester): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM research_rounds WHERE kind = ? AND academic_year_id = ? AND semester = ? AND is_active = 1',
            [$kind, $yearId, $semester]
        );
    }

    // ---------------------------------------------------------------- บันทึก

    /**
     * สร้างงานใหม่พร้อมไฟล์เวอร์ชันแรก
     * @param array{title: string, type_id: int, academic_year_id: int, semester: int, target_count: int, keywords: ?string, disabilities: list<string>} $data
     */
    public static function create(array $data, array $file, bool $submit): int
    {
        $db = Database::instance();
        $id = $db->transaction(static function (Database $db) use ($data, $file): int {
            $id = $db->insert('research_works', [
                'owner_user_id' => Auth::id(),
                'title' => $data['title'],
                'type_id' => $data['type_id'],
                'academic_year_id' => $data['academic_year_id'],
                'semester' => $data['semester'],
                'target_count' => $data['target_count'],
                'keywords' => $data['keywords'],
                'status' => 'draft',
                'status_changed_at' => Database::now(),
            ]);
            self::saveDisabilities($db, $id, $data['disabilities']);
            self::log($db, $id, null, 'draft', null, null);
            self::storeVersion($db, $id, 1, $file, 'ฉบับแรก');

            return $id;
        });
        Audit::log('research.created', 'academic', 'research_works', $id, null, ['title' => $data['title']]);
        if ($submit) {
            self::submit(self::find($id) ?? throw new HttpException(404));
        }

        return $id;
    }

    public static function updateMeta(array $work, array $data): void
    {
        self::assertOwnerEditable($work);
        $db = Database::instance();
        $db->transaction(static function (Database $db) use ($work, $data): void {
            $db->update('research_works', [
                'title' => $data['title'],
                'type_id' => $data['type_id'],
                'academic_year_id' => $data['academic_year_id'],
                'semester' => $data['semester'],
                'target_count' => $data['target_count'],
                'keywords' => $data['keywords'],
            ], ['id' => (int) $work['id']]);
            $db->delete('research_work_disabilities', ['work_id' => (int) $work['id']]);
            self::saveDisabilities($db, (int) $work['id'], $data['disabilities']);
        });
        Audit::log('research.updated', 'academic', 'research_works', (int) $work['id'],
            array_intersect_key($work, array_flip(['title', 'type_id', 'academic_year_id', 'semester', 'target_count', 'keywords'])),
            array_diff_key($data, ['disabilities' => true]));
    }

    /** อัปโหลดไฟล์ฉบับใหม่ (ไฟล์เดิมทุกเวอร์ชันเก็บไว้) */
    public static function addVersion(array $work, array $file, ?string $note): int
    {
        self::assertOwnerEditable($work);
        $no = Database::instance()->transaction(static function (Database $db) use ($work, $file, $note): int {
            // ล็อกแถวกันอัปโหลดพร้อมกันได้เลขเวอร์ชันซ้ำ
            $current = (int) $db->fetchColumn('SELECT current_version FROM research_works WHERE id = ? FOR UPDATE', [(int) $work['id']]);
            self::storeVersion($db, (int) $work['id'], $current + 1, $file, $note);

            return $current + 1;
        });
        Audit::log('research.version_uploaded', 'academic', 'research_works', (int) $work['id'], null, ['version' => $no]);

        return $no;
    }

    /** ครูส่งงาน: ร่าง → ส่งแล้ว / ส่งกลับแก้ไข → ส่งฉบับแก้ไขแล้ว (ต้องอัปโหลดไฟล์ใหม่หลังถูกส่งกลับ) */
    public static function submit(array $work): string
    {
        if (!self::isOwner($work)) {
            throw new HttpException(403);
        }
        $from = (string) $work['status'];
        if ($from === 'draft') {
            $to = 'submitted';
        } elseif ($from === 'revision') {
            $requestedAt = (int) Database::instance()->fetchColumn(
                "SELECT version_no FROM research_status_logs WHERE work_id = ? AND to_status = 'revision' ORDER BY id DESC LIMIT 1",
                [(int) $work['id']]
            );
            if ((int) $work['current_version'] <= $requestedAt) {
                throw new ValidationException(['file' => ['กรุณาอัปโหลดไฟล์ฉบับแก้ไขก่อนส่ง']]);
            }
            $to = 'resubmitted';
        } else {
            throw new ValidationException(['status' => ['งานนี้ส่งแล้ว']]);
        }
        if ((int) $work['current_version'] < 1) {
            throw new ValidationException(['file' => ['กรุณาแนบไฟล์งานวิจัยก่อนส่ง']]);
        }
        self::transition($work, $to, null, ['submitted_at' => $work['submitted_at'] ?? Database::now()]);

        $title = ($to === 'submitted' ? 'มีงานวิจัยส่งใหม่: ' : 'ส่งงานวิจัยฉบับแก้ไขแล้ว: ') . $work['title'];
        $reviewers = array_diff(NotificationService::usersWithPermission(ResearchLookup::PERM_REVIEW), [(int) Auth::id()]);
        NotificationService::notify($reviewers, ResearchLookup::NOTIFY_TYPE, $title,
            'ผู้ส่ง: ' . ($work['owner_name'] ?? Auth::displayName()) . ' · เวอร์ชันที่ ' . (int) $work['current_version'],
            self::link((int) $work['id']), true);

        return $to;
    }

    /** ผู้ตรวจเปลี่ยนสถานะ */
    public static function review(array $work, string $to, ?string $note): void
    {
        if (!self::canReview()) {
            throw new HttpException(403);
        }
        $from = (string) $work['status'];
        if (!in_array($from, ResearchLookup::REVIEW_TRANSITIONS[$to] ?? [], true)) {
            throw new ValidationException(['to_status' => ['เปลี่ยนจากสถานะ "' . ResearchLookup::statusLabel($from) . '" เป็นสถานะนี้ไม่ได้']]);
        }
        if (in_array($to, ResearchLookup::NOTE_REQUIRED, true) && ($note === null || $note === '')) {
            throw new ValidationException(['note' => ['กรุณาระบุเหตุผล/ข้อเสนอแนะ']]);
        }
        self::transition($work, $to, $note);
        NotificationService::notify([(int) $work['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
            'งานวิจัย "' . mb_substr((string) $work['title'], 0, 120) . '": ' . ResearchLookup::statusLabel($to),
            $note, self::link((int) $work['id']), true);
    }

    /** ครูยกเลิกงานของตนเอง */
    public static function cancelByOwner(array $work, ?string $note): void
    {
        if (!self::isOwner($work)) {
            throw new HttpException(403);
        }
        if (!in_array($work['status'], ResearchLookup::OWNER_CANCELLABLE, true)) {
            throw new ValidationException(['status' => ['ยกเลิกงานในสถานะนี้ไม่ได้']]);
        }
        self::transition($work, 'cancelled', $note);
        if ($work['status'] !== 'draft') {
            NotificationService::notify(array_diff(NotificationService::usersWithPermission(ResearchLookup::PERM_REVIEW), [(int) Auth::id()]),
                ResearchLookup::NOTIFY_TYPE, 'ครูยกเลิกงานวิจัย: ' . $work['title'], $note, self::link((int) $work['id']));
        }
    }

    /** ลบร่าง (ลบแบบกู้คืนได้: deleted_at ไฟล์ยังอยู่ครบ) */
    public static function deleteDraft(array $work): void
    {
        if (!self::isOwner($work) || !in_array($work['status'], ['draft', 'cancelled'], true) || $work['submitted_at'] !== null) {
            throw new ValidationException(['status' => ['ลบได้เฉพาะร่างที่ยังไม่เคยส่ง']]);
        }
        Database::instance()->update('research_works', ['deleted_at' => Database::now()], ['id' => (int) $work['id']]);
        Audit::log('research.deleted', 'academic', 'research_works', (int) $work['id'], ['title' => $work['title']]);
    }

    /** ข้อเสนอแนะ: ผู้ตรวจเปิดเรื่องใหม่ / เจ้าของและผู้ตรวจตอบกลับในเรื่องเดิม */
    public static function addComment(array $work, string $body, ?int $parentId): void
    {
        $isOwner = self::isOwner($work);
        $isReviewer = self::canReview();
        $db = Database::instance();
        $parent = null;
        if ($parentId !== null) {
            $parent = $db->fetch('SELECT * FROM research_comments WHERE id = ? AND work_id = ? AND parent_id IS NULL', [$parentId, (int) $work['id']])
                ?? throw new HttpException(404);
            if (!$isOwner && !$isReviewer) {
                throw new HttpException(403);
            }
        } elseif (!$isReviewer) {
            throw new HttpException(403); // ครูตอบกลับในเรื่องที่ผู้ตรวจเปิดไว้
        }
        if ($work['status'] === 'draft') {
            throw new ValidationException(['body' => ['งานยังไม่ได้ส่ง']]);
        }
        $id = $db->insert('research_comments', [
            'work_id' => (int) $work['id'],
            'parent_id' => $parentId,
            'version_no' => (int) $work['current_version'] ?: null,
            'user_id' => Auth::id(),
            'body' => $body,
        ]);
        Audit::log('research.commented', 'academic', 'research_comments', $id, null, ['work_id' => (int) $work['id'], 'parent_id' => $parentId]);

        $link = self::link((int) $work['id']) . '#feedback';
        $short = mb_substr((string) $work['title'], 0, 120);
        if (!$isOwner) {
            NotificationService::notify([(int) $work['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
                ($parent === null ? 'มีข้อเสนอแนะใหม่: ' : 'ผู้ตรวจตอบกลับข้อเสนอแนะ: ') . $short, mb_substr($body, 0, 300), $link, $parent === null);
        }
        if ($parent !== null) {
            // แจ้งผู้ที่อยู่ในเรื่องนี้ (ผู้เปิดเรื่องและผู้ตอบ) ยกเว้นเจ้าของงานที่แจ้งไปแล้ว และตัวผู้ตอบเอง
            $participants = array_map('intval', array_column($db->fetchAll(
                'SELECT DISTINCT user_id FROM research_comments WHERE (id = ? OR parent_id = ?) AND user_id IS NOT NULL',
                [$parentId, $parentId]
            ), 'user_id'));
            $others = array_diff($participants, [(int) Auth::id(), (int) $work['owner_user_id']]);
            NotificationService::notify($others, ResearchLookup::NOTIFY_TYPE,
                ($isOwner ? 'ครูตอบกลับข้อเสนอแนะ: ' : 'มีการตอบกลับข้อเสนอแนะ: ') . $short, mb_substr($body, 0, 300), $link);
        }
    }

    // ---------------------------------------------------------------- แดชบอร์ด

    /**
     * ตัวเลขสรุปและข้อมูลกราฟ (จำนวนเท่านั้น ไม่มีรายละเอียดรายงาน)
     * @return array{cards: array<string, int>, status: array, types: array, disabilities: array}
     */
    public static function dashboard(array $filters, bool $mineOnly): array
    {
        [$where, $params] = self::filterSql($filters, $mineOnly);
        $sqlWhere = implode(' AND ', $where);
        $db = Database::instance();

        $byStatus = array_fill_keys(array_keys(ResearchLookup::STATUSES), 0);
        foreach ($db->fetchAll("SELECT w.status, COUNT(*) AS n FROM research_works w WHERE {$sqlWhere} GROUP BY w.status", $params) as $r) {
            $byStatus[$r['status']] = (int) $r['n'];
        }
        $drafts = $byStatus['draft'];
        if (!$mineOnly) {
            unset($byStatus['draft']); // ภาพรวมนับเฉพาะงานที่ส่งแล้ว (ร่างของตนเองดูในมุมมอง "เฉพาะของฉัน")
        }

        $types = [];
        foreach (self::types('research', false) as $t) {
            $types[(int) $t['id']] = ['label' => $t['name'], 'count' => 0, 'active' => (bool) $t['is_active']];
        }
        foreach ($db->fetchAll("SELECT w.type_id, COUNT(*) AS n FROM research_works w WHERE {$sqlWhere} AND w.status <> 'draft' GROUP BY w.type_id", $params) as $r) {
            if (isset($types[(int) $r['type_id']])) {
                $types[(int) $r['type_id']]['count'] = (int) $r['n'];
            }
        }
        $types = array_values(array_filter($types, static fn (array $t): bool => $t['active'] || $t['count'] > 0));

        $disabilities = array_fill_keys(array_keys(StudentLookup::DISABILITY_TYPES), 0);
        foreach ($db->fetchAll(
            "SELECT d.disability_type, COUNT(*) AS n FROM research_work_disabilities d JOIN research_works w ON w.id = d.work_id
             WHERE {$sqlWhere} AND w.status <> 'draft' GROUP BY d.disability_type",
            $params
        ) as $r) {
            if (isset($disabilities[$r['disability_type']])) {
                $disabilities[$r['disability_type']] = (int) $r['n'];
            }
        }

        $submitted = array_sum($byStatus) - ($byStatus['draft'] ?? 0);
        $sum = static fn (array $keys): int => array_sum(array_intersect_key($byStatus, array_flip($keys)));

        return [
            'cards' => [
                'research_total' => $submitted,
                'pending_review' => $sum(ResearchLookup::PENDING_REVIEW),
                'revision' => $byStatus['revision'],
                'approved' => $sum(ResearchLookup::APPROVED),
                'draft' => $mineOnly ? $drafts : 0,
            ],
            'status' => array_map(
                static fn (string $k, int $n): array => ['key' => $k, 'label' => ResearchLookup::statusLabel($k), 'count' => $n],
                array_keys($byStatus), array_values($byStatus)
            ),
            'types' => $types,
            'disabilities' => array_map(
                static fn (string $k, int $n): array => ['key' => $k, 'label' => StudentLookup::DISABILITY_TYPES[$k], 'count' => $n],
                array_keys($disabilities), array_values($disabilities)
            ),
        ];
    }

    // ---------------------------------------------------------------- ภายใน

    public static function link(int $workId): string
    {
        return '/academic/research/works/' . $workId;
    }

    private static function assertOwnerEditable(array $work): void
    {
        if (!self::isOwner($work)) {
            throw new HttpException(403);
        }
        if (!in_array($work['status'], ResearchLookup::OWNER_EDITABLE, true)) {
            throw new ValidationException(['status' => ['แก้ไขได้เฉพาะงานที่เป็นร่างหรือถูกส่งกลับแก้ไข']]);
        }
    }

    private static function saveDisabilities(Database $db, int $workId, array $types): void
    {
        foreach (array_unique($types) as $type) {
            if (isset(StudentLookup::DISABILITY_TYPES[$type])) {
                $db->insert('research_work_disabilities', ['work_id' => $workId, 'disability_type' => $type]);
            }
        }
    }

    private static function storeVersion(Database $db, int $workId, int $versionNo, array $file, ?string $note): void
    {
        $stored = ResearchStorage::store($file, (string) $workId, 'v' . $versionNo);
        $db->insert('research_versions', $stored + [
            'work_id' => $workId,
            'version_no' => $versionNo,
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'uploaded_by' => Auth::id(),
        ]);
        $db->update('research_works', ['current_version' => $versionNo], ['id' => $workId]);
    }

    /** เปลี่ยนสถานะแบบอะตอมมิก (สถานะต้นทางต้องยังเป็นค่าเดิม กันกดซ้ำ/แก้พร้อมกัน) */
    private static function transition(array $work, string $to, ?string $note, array $extra = []): void
    {
        $from = (string) $work['status'];
        $db = Database::instance();
        $db->transaction(static function (Database $db) use ($work, $from, $to, $note, $extra): void {
            $sets = ['status = ?', 'status_changed_at = ?'];
            $params = [$to, Database::now()];
            foreach ($extra as $column => $value) {
                $sets[] = Database::quote($column) . ' = ?';
                $params[] = $value;
            }
            $params[] = (int) $work['id'];
            $params[] = $from;
            $changed = $db->query(
                'UPDATE research_works SET ' . implode(', ', $sets) . ' WHERE id = ? AND status = ? AND deleted_at IS NULL',
                $params
            )->rowCount();
            if ($changed !== 1) {
                throw new ValidationException(['status' => ['สถานะของงานเปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่']]);
            }
            $version = (int) $db->fetchColumn('SELECT current_version FROM research_works WHERE id = ?', [(int) $work['id']]);
            self::log($db, (int) $work['id'], $from, $to, $version ?: null, $note);
        });
        Audit::log('research.status_changed', 'academic', 'research_works', (int) $work['id'], ['status' => $from], ['status' => $to, 'note' => $note]);
    }

    private static function log(Database $db, int $workId, ?string $from, string $to, ?int $version, ?string $note): void
    {
        $db->insert('research_status_logs', [
            'work_id' => $workId,
            'from_status' => $from,
            'to_status' => $to,
            'version_no' => $version,
            'user_id' => Auth::id(),
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 1000) : null,
        ]);
    }
}
