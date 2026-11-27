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
    public static function create(array $data, array $file): int
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
     * บันทึกรายงานการใช้สื่อ (เมื่อคู่มืออนุมัติแล้วเท่านั้น) เลือกจากสื่อเดิม ไม่ต้องกรอกข้อมูลสื่อซ้ำ
     * อยู่ระหว่างใช้สื่อ → รายงาน: บันทึกแล้ว (จากนั้นกำหนดตำแหน่งลายเซ็น ลงนามลำดับที่ 1 และส่ง)
     * @param array{actual_start: string, actual_end: string, actual: array<string, int>} $data
     */
    public static function saveReport(array $media, array $data, array $file): void
    {
        self::assertOwnerIn($media, ['in_use'], 'ส่งรายงานได้เมื่อคู่มือได้รับอนุมัติแล้ว (สถานะอยู่ระหว่างใช้สื่อ) เท่านั้น');
        Database::instance()->transaction(static function (Database $db) use ($media, $data, $file): void {
            $db->update('innovation_media', ['actual_start' => $data['actual_start'], 'actual_end' => $data['actual_end']], ['id' => (int) $media['id']]);
            self::saveTargets($db, (int) $media['id'], 'actual', $data['actual']);
            $no = (int) $db->fetchColumn('SELECT report_version FROM innovation_media WHERE id = ? FOR UPDATE', [(int) $media['id']]) + 1;
            self::storeVersion($db, (int) $media['id'], 'report', $no, $file, $no === 1 ? 'ฉบับแรก' : null);
            self::transition($media, 'report', 'report_draft', null);
        });
    }

    public static function updateReport(array $media, array $data): void
    {
        self::assertOwnerIn($media, ['report_draft', 'report_revision']);
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
        $doc = InnovationLookup::stage((string) $media['status']);
        $no = Database::instance()->transaction(static function (Database $db) use ($media, $doc, $file, $note): int {
            $no = (int) $db->fetchColumn("SELECT {$doc}_version FROM innovation_media WHERE id = ? FOR UPDATE", [(int) $media['id']]) + 1;
            self::storeVersion($db, (int) $media['id'], $doc, $no, $file, $note);

            return $no;
        });
        Audit::log('innovation.version_uploaded', 'academic', 'innovation_media', (int) $media['id'], null, ['doc' => $doc, 'version' => $no]);

        return $no;
    }

    /**
     * ครูลงนามลำดับที่ 1 และส่ง (ไฟล์ PDF + ตำแหน่งลายเซ็นครบ 4 ลำดับ + ยืนยันรหัสผ่าน)
     * ร่าง → คู่มือส่งแล้ว / รายงานบันทึกแล้ว → รายงานส่งแล้ว / ส่งกลับแก้ไข → ส่งฉบับแก้ไขแล้ว (ต้องอัปโหลดไฟล์ใหม่หลังถูกส่งกลับ)
     */
    public static function submit(array $media, string $password): string
    {
        if (!self::isOwner($media)) {
            throw new HttpException(403);
        }
        $status = (string) $media['status'];
        $extra = [];
        if ($status === 'draft') {
            $doc = 'manual';
            $to = 'manual_submitted';
            $extra = ['submitted_at' => $media['submitted_at'] ?? Database::now()];
        } elseif ($status === 'report_draft') {
            $doc = 'report';
            $to = 'report_submitted';
            $extra = ['report_submitted_at' => Database::now()];
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
        $ctx = self::signContext($media, $doc);
        SigningService::assertSubmittable($ctx);
        SigningService::confirmPassword((int) Auth::id(), $password);
        Database::instance()->transaction(static function () use ($media, $ctx, $doc, $to, $extra): void {
            // ส่งใหม่: ลายเซ็นเดิมของฉบับนี้ทั้งหมดถูกยกเลิก เริ่มลำดับที่ 1 ใหม่
            SigningService::revokeAll($doc, (int) $media['id'], (int) Auth::id(), 'ส่งเวอร์ชันที่ ' . (int) $ctx['version']['version_no']);
            SigningService::sign($ctx, 1, (int) Auth::id());
            self::transition($media, $doc, $to, null, $extra);
        });
        self::notifyReviewers($media, match ($to) {
            'manual_submitted' => 'มีคู่มือการใช้สื่อนวัตกรรมส่งใหม่: ',
            'manual_resubmitted' => 'ส่งคู่มือการใช้สื่อฉบับแก้ไขแล้ว: ',
            'report_submitted' => 'มีรายงานการใช้สื่อนวัตกรรมส่งใหม่: ',
            default => 'ส่งรายงานการใช้สื่อฉบับแก้ไขแล้ว: ',
        });

        return $to;
    }

    /** ผู้ตรวจเปลี่ยนสถานะ (action ตาม InnovationLookup::REVIEW_ACTIONS) "ผ่านการตรวจ" = ส่งต่อลงนามลำดับที่ 2 */
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
        if ($action === 'pass') {
            SigningService::assertFirstSigned(self::signContext($media, $doc), 'action');
        }
        self::transition($media, $doc, $to, $note);
        if ($action === 'pass') {
            self::notifyNextSigner($media, $doc, 2);
        }
        NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
            'สื่อนวัตกรรม "' . mb_substr((string) $media['title'], 0, 120) . '": ' . InnovationLookup::statusLabel($to),
            $note, self::link((int) $media['id']), true);

        return $to;
    }

    // ---------------------------------------------------------------- ลงนาม 4 ลำดับ

    /** บริบทเอกสารสำหรับ SigningService ($doc = manual|report ค่าเริ่มต้น = ฉบับที่อยู่ในขั้นตอนของสถานะ) */
    public static function signContext(array $media, ?string $doc = null): array
    {
        $doc ??= InnovationLookup::stage((string) $media['status']);
        $no = (int) ($media[$doc . '_version'] ?? 0);
        $version = $no > 0
            ? Database::instance()->fetch('SELECT * FROM innovation_versions WHERE media_id = ? AND doc = ? AND version_no = ?', [(int) $media['id'], $doc, $no])
            : null;
        if ($version === null) {
            throw new ValidationException(['file' => ['ยังไม่มีไฟล์' . InnovationLookup::DOCS[$doc]]]);
        }
        $current = InnovationLookup::stage((string) $media['status']) === $doc;

        return [
            'type' => $doc,
            'id' => (int) $media['id'],
            'owner_user_id' => (int) $media['owner_user_id'],
            'owner_name' => (string) $media['owner_name'],
            'title' => (string) $media['title'],
            'year_be' => (string) $media['year_be'],
            'status' => (string) $media['status'],
            'status_label' => InnovationLookup::statusLabel((string) $media['status']),
            'version' => $version,
            'dir' => 'm' . (int) $media['id'],
            'stem' => $doc . '_v' . $no,
            'base' => self::link((int) $media['id']),
            'awaiting' => $current ? InnovationLookup::awaitingOrder((string) $media['status']) : null,
            'owner_editable' => $current && in_array($media['status'], InnovationLookup::OWNER_EDITABLE, true),
            'in_review' => $current && in_array($media['status'], InnovationLookup::PENDING_REVIEW, true),
        ];
    }

    /** ลงนามลำดับ 2-4 ตามลำดับ ลำดับที่ 4 ของคู่มือ = เริ่มใช้สื่อ / ของรายงาน = เสร็จสมบูรณ์ */
    public static function signStep(array $media, int $order, string $password): string
    {
        $status = (string) $media['status'];
        if (InnovationLookup::awaitingOrder($status) !== $order) {
            throw new ValidationException(['status' => ['เอกสารไม่ได้อยู่ระหว่างรอลงนามลำดับที่ ' . $order]]);
        }
        $doc = InnovationLookup::stage($status);
        $ctx = self::signContext($media, $doc);
        SigningService::assertCanSign($ctx, $order, (int) Auth::id());
        SigningService::confirmPassword((int) Auth::id(), $password);
        $to = InnovationLookup::afterSign($doc, $order);
        $extra = match ($to) {
            'in_use' => [
                'manual_approved_at' => Database::now(),
                'report_due_on' => date('Y-m-d', strtotime($media['plan_end'] . ' +' . InnovationLookup::reportDueDays() . ' days')),
            ],
            'completed' => ['completed_at' => Database::now()],
            default => [],
        };
        $result = Database::instance()->transaction(static function () use ($media, $ctx, $doc, $order, $to, $extra): array {
            $result = SigningService::sign($ctx, $order, (int) Auth::id());
            self::transition($media, $doc, $to, 'ลงนามลำดับที่ ' . $order . ' (' . SigningLookup::ORDERS[$order] . ')', $extra);

            return $result;
        });

        $short = mb_substr((string) $media['title'], 0, 120);
        $docNo = 'เลขที่เอกสาร ' . ($result['document']['doc_no'] ?? '');
        if ($to === 'in_use') {
            NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
                "คู่มือการใช้สื่ออนุมัติแล้ว เริ่มใช้สื่อได้: {$short}",
                $docNo . "\nใช้สื่อตามแผน " . thai_date((string) $media['plan_start'], false) . ' - ' . thai_date((string) $media['plan_end'], false)
                . ' และส่งรายงานภายใน ' . thai_date((string) $extra['report_due_on'], false, true),
                self::link((int) $media['id']), true);
        } elseif ($to === 'completed') {
            NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
                "รายงานการใช้สื่ออนุมัติแล้ว (เสร็จสมบูรณ์): {$short}", $docNo, self::link((int) $media['id']), true);
        } else {
            self::notifyNextSigner($media, $doc, $order + 1);
        }

        return $to;
    }

    /** ผู้ลงนามลำดับที่รอ ส่งกลับแก้ไขพร้อมเหตุผล (ลายเซ็นของฉบับนั้นถูกยกเลิก) */
    public static function returnFromSigning(array $media, string $note): void
    {
        $status = (string) $media['status'];
        $order = InnovationLookup::awaitingOrder($status);
        if ($order === null || !SigningLookup::canSignOrder($order, (int) $media['owner_user_id'], (int) Auth::id())) {
            throw new HttpException(403);
        }
        if (trim($note) === '') {
            throw new ValidationException(['note' => ['กรุณาระบุเหตุผลที่ส่งกลับแก้ไข']]);
        }
        $doc = InnovationLookup::stage($status);
        $text = 'ผู้ลงนามลำดับที่ ' . $order . ' ส่งกลับแก้ไข: ' . $note;
        Database::instance()->transaction(static function () use ($media, $doc, $text): void {
            SigningService::revokeAll($doc, (int) $media['id'], (int) Auth::id(), $text);
            self::transition($media, $doc, $doc . '_revision', $text);
        });
        NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
            InnovationLookup::DOCS[$doc] . 'ถูกส่งกลับแก้ไขระหว่างการลงนาม: ' . mb_substr((string) $media['title'], 0, 120), $text, self::link((int) $media['id']), true);
    }

    /**
     * ผู้ดูแล (manage_all) ยกเลิกการอนุมัติฉบับที่อยู่ระหว่างลงนามหรืออนุมัติแล้ว
     * คู่มือ (อยู่ระหว่างใช้สื่อ/ลงนาม) → คู่มือส่งกลับแก้ไข / รายงาน (เสร็จสมบูรณ์/ลงนาม) → รายงานส่งกลับแก้ไข
     */
    public static function revokeApproval(array $media, string $reason): void
    {
        if (!Rbac::can(ResearchLookup::PERM_MANAGE)) {
            throw new HttpException(403);
        }
        $status = (string) $media['status'];
        $doc = match (true) {
            $status === 'in_use' || str_starts_with($status, 'manual_sign') || $status === 'manual_passed' => 'manual',
            $status === 'completed' || str_starts_with($status, 'report_sign') || $status === 'report_passed' => 'report',
            default => throw new ValidationException(['reason' => ['ยกเลิกการอนุมัติได้เฉพาะฉบับที่ผ่านการตรวจหรืออนุมัติแล้ว (ก่อนเริ่มส่งรายงาน)']]),
        };
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => ['กรุณาระบุเหตุผลการยกเลิกการอนุมัติ']]);
        }
        $text = 'ผู้ดูแลยกเลิกการอนุมัติ' . InnovationLookup::DOCS[$doc] . ': ' . $reason;
        $extra = $doc === 'manual' ? ['manual_approved_at' => null, 'report_due_on' => null] : ['completed_at' => null];
        Database::instance()->transaction(static function () use ($media, $doc, $text, $extra): void {
            SigningService::revokeAll($doc, (int) $media['id'], (int) Auth::id(), $text);
            self::transition($media, $doc, $doc . '_revision', $text, $extra);
        });
        Audit::log('innovation.approval_revoked', 'academic', 'innovation_media', (int) $media['id'], ['status' => $status], ['doc' => $doc, 'reason' => $reason]);
        NotificationService::notify([(int) $media['owner_user_id']], ResearchLookup::NOTIFY_TYPE,
            'ยกเลิกการอนุมัติ' . InnovationLookup::DOCS[$doc] . ': ' . mb_substr((string) $media['title'], 0, 120), $text, self::link((int) $media['id']), true);
    }

    private static function notifyNextSigner(array $media, string $doc, int $order): void
    {
        NotificationService::notify(
            array_diff(NotificationService::usersWithPermission(SigningLookup::PERMS[$order]), [(int) Auth::id()]),
            ResearchLookup::NOTIFY_TYPE,
            InnovationLookup::DOCS[$doc] . 'รอลงนามลำดับที่ ' . $order . ': ' . mb_substr((string) $media['title'], 0, 120),
            'ผู้จัดทำ: ' . $media['owner_name'] . ' · ลงนามในฐานะ' . SigningLookup::ORDERS[$order],
            self::link((int) $media['id']) . '/sign', true
        );
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
            "SELECT COUNT(*) FROM innovation_media m WHERE {$sqlWhere} AND m.status IN ('in_use', 'report_draft') AND m.report_due_on < ?",
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
