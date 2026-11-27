<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

/**
 * ค่าคงที่ของสื่อนวัตกรรม (สิทธิ์ใช้ชุดเดียวกับงานวิจัย ResearchLookup::PERM_*)
 * ภาพรวม: คู่มือ (ส่ง → ตรวจ ⇄ แก้ไข → ผ่าน) → อยู่ระหว่างใช้สื่อ → รายงาน (ส่ง → ตรวจ ⇄ แก้ไข → ผ่าน) → เสร็จสมบูรณ์
 * ระยะการลงนาม 4 ลำดับจะเพิ่มสถานะระหว่าง "ผ่านการตรวจ" กับ "อยู่ระหว่างใช้สื่อ"/"เสร็จสมบูรณ์"
 */
final class InnovationLookup
{
    public const DOCS = ['manual' => 'คู่มือการใช้สื่อนวัตกรรม', 'report' => 'รายงานการใช้สื่อนวัตกรรม'];

    /** [ชื่อ, สี Bootstrap, ไอคอน] */
    public const STATUSES = [
        'draft' => ['ร่าง', 'secondary', 'pencil'],
        'manual_submitted' => ['คู่มือ: ส่งแล้ว (รอตรวจสอบ)', 'info', 'send'],
        'manual_reviewing' => ['คู่มือ: กำลังตรวจสอบ', 'primary', 'search'],
        'manual_revision' => ['คู่มือ: ส่งกลับแก้ไข', 'warning', 'arrow-return-left'],
        'manual_resubmitted' => ['คู่มือ: ส่งฉบับแก้ไขแล้ว', 'info', 'arrow-repeat'],
        'in_use' => ['อยู่ระหว่างใช้สื่อ', 'primary', 'play-circle'],
        'report_submitted' => ['รายงาน: ส่งแล้ว (รอตรวจสอบ)', 'info', 'send'],
        'report_reviewing' => ['รายงาน: กำลังตรวจสอบ', 'primary', 'search'],
        'report_revision' => ['รายงาน: ส่งกลับแก้ไข', 'warning', 'arrow-return-left'],
        'report_resubmitted' => ['รายงาน: ส่งฉบับแก้ไขแล้ว', 'info', 'arrow-repeat'],
        'completed' => ['เสร็จสมบูรณ์', 'success', 'patch-check'],
        'failed' => ['ไม่ผ่าน', 'danger', 'x-circle'],
        'cancelled' => ['ยกเลิก', 'dark', 'slash-circle'],
    ];

    public const PENDING_REVIEW = ['manual_submitted', 'manual_resubmitted', 'manual_reviewing', 'report_submitted', 'report_resubmitted', 'report_reviewing'];
    public const REVISION = ['manual_revision', 'report_revision'];
    public const APPROVED = ['completed'];

    /** การตรวจของผู้ตรวจ: action => [ชื่อ, ต้องระบุเหตุผล] */
    public const REVIEW_ACTIONS = [
        'reviewing' => ['กำลังตรวจสอบ', false],
        'revision' => ['ส่งกลับแก้ไข', true],
        'pass' => ['ผ่านการตรวจ (อนุมัติ)', false],
        'failed' => ['ไม่ผ่าน', true],
        'cancelled' => ['ยกเลิก', true],
    ];

    /** ครูแก้ข้อมูลได้: draft/manual_revision = ข้อมูลคู่มือ, report_revision = ข้อมูลการใช้จริง */
    public const OWNER_EDITABLE = ['draft', 'manual_revision', 'report_revision'];
    public const OWNER_CANCELLABLE = ['draft', 'manual_submitted', 'manual_revision'];
    /** ปิดงานแล้ว */
    public const FINAL = ['completed', 'failed', 'cancelled'];

    public const DEFAULT_REPORT_DUE_DAYS = 30;
    public const REPORT_DUE_SETTING = 'research.innovation_report_due_days';

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    public static function statusBadge(string $status): string
    {
        [$label, $color, $icon] = self::STATUSES[$status] ?? [$status, 'secondary', 'circle'];

        return '<span class="badge text-bg-' . e($color) . '"><i class="bi bi-' . e($icon) . ' me-1"></i>' . e($label) . '</span>';
    }

    /** ฉบับที่อยู่ในขั้นตอนของสถานะนี้ (manual / report) */
    public static function stage(string $status): string
    {
        return str_starts_with($status, 'report_') || in_array($status, ['in_use', 'completed'], true) ? 'report' : 'manual';
    }

    /** สถานะเป้าหมายของการตรวจ (null = เปลี่ยนจากสถานะปัจจุบันไม่ได้) */
    public static function reviewTarget(string $status, string $action): ?string
    {
        if ($action === 'cancelled') {
            return in_array($status, self::FINAL, true) || $status === 'draft' ? null : 'cancelled';
        }
        if (!preg_match('/^(manual|report)_(submitted|resubmitted|reviewing)$/', $status, $m)) {
            return null;
        }
        [, $doc, $state] = $m;

        return match ($action) {
            'reviewing' => $state === 'reviewing' ? null : $doc . '_reviewing',
            'revision' => $doc . '_revision',
            'pass' => $doc === 'manual' ? 'in_use' : 'completed',
            'failed' => 'failed',
            default => null,
        };
    }

    public static function reportDueDays(): int
    {
        $days = (int) \Core\Setting::get(self::REPORT_DUE_SETTING, self::DEFAULT_REPORT_DUE_DAYS);

        return $days >= 1 && $days <= 365 ? $days : self::DEFAULT_REPORT_DUE_DAYS;
    }
}
