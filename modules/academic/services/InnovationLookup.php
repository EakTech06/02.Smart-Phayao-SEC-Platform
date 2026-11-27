<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

/**
 * ค่าคงที่ของสื่อนวัตกรรม (สิทธิ์ใช้ชุดเดียวกับงานวิจัย ResearchLookup::PERM_*)
 * ภาพรวม: คู่มือ (ส่ง → ตรวจ ⇄ แก้ไข → ผ่าน) → อยู่ระหว่างใช้สื่อ → รายงาน (ส่ง → ตรวจ ⇄ แก้ไข → ผ่าน) → เสร็จสมบูรณ์
 * แต่ละฉบับ: ครูลงนามลำดับ 1 พร้อมการส่ง → ผ่านการตรวจ → ลงนามลำดับ 2 → 3 → 4 → อนุมัติ
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
        'manual_passed' => ['คู่มือ: ผ่านการตรวจ (รอลงนามลำดับที่ 2)', 'success', 'check-circle'],
        'manual_sign_3' => ['คู่มือ: รอลงนามลำดับที่ 3', 'success', 'pen'],
        'manual_sign_4' => ['คู่มือ: รอลงนามลำดับที่ 4', 'success', 'pen'],
        'in_use' => ['อยู่ระหว่างใช้สื่อ', 'primary', 'play-circle'],
        'report_draft' => ['รายงาน: บันทึกแล้ว (รอลงนามและส่ง)', 'secondary', 'pencil'],
        'report_submitted' => ['รายงาน: ส่งแล้ว (รอตรวจสอบ)', 'info', 'send'],
        'report_reviewing' => ['รายงาน: กำลังตรวจสอบ', 'primary', 'search'],
        'report_revision' => ['รายงาน: ส่งกลับแก้ไข', 'warning', 'arrow-return-left'],
        'report_resubmitted' => ['รายงาน: ส่งฉบับแก้ไขแล้ว', 'info', 'arrow-repeat'],
        'report_passed' => ['รายงาน: ผ่านการตรวจ (รอลงนามลำดับที่ 2)', 'success', 'check-circle'],
        'report_sign_3' => ['รายงาน: รอลงนามลำดับที่ 3', 'success', 'pen'],
        'report_sign_4' => ['รายงาน: รอลงนามลำดับที่ 4', 'success', 'pen'],
        'completed' => ['เสร็จสมบูรณ์', 'success', 'patch-check'],
        'failed' => ['ไม่ผ่าน', 'danger', 'x-circle'],
        'cancelled' => ['ยกเลิก', 'dark', 'slash-circle'],
    ];

    public const PENDING_REVIEW = ['manual_submitted', 'manual_resubmitted', 'manual_reviewing', 'report_submitted', 'report_resubmitted', 'report_reviewing'];
    public const REVISION = ['manual_revision', 'report_revision'];
    public const APPROVED = ['completed'];
    /** อยู่ระหว่างลงนามลำดับ 2-4 */
    public const SIGNING = ['manual_passed', 'manual_sign_3', 'manual_sign_4', 'report_passed', 'report_sign_3', 'report_sign_4'];

    /** การตรวจของผู้ตรวจ: action => [ชื่อ, ต้องระบุเหตุผล] */
    public const REVIEW_ACTIONS = [
        'reviewing' => ['กำลังตรวจสอบ', false],
        'revision' => ['ส่งกลับแก้ไข', true],
        'pass' => ['ผ่านการตรวจ (ส่งต่อลงนามลำดับที่ 2)', false],
        'failed' => ['ไม่ผ่าน', true],
        'cancelled' => ['ยกเลิก', true],
    ];

    /** ครูแก้ข้อมูล/อัปโหลด/ลงนามและส่งได้: draft/manual_revision = คู่มือ, report_draft/report_revision = รายงาน */
    public const OWNER_EDITABLE = ['draft', 'manual_revision', 'report_draft', 'report_revision'];
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
            'pass' => $doc . '_passed',
            'failed' => 'failed',
            default => null,
        };
    }

    /** ลำดับที่รอลงนามในสถานะนี้ (null = ไม่อยู่ระหว่างลงนาม) */
    public static function awaitingOrder(string $status): ?int
    {
        return match (true) {
            str_ends_with($status, '_passed') && in_array($status, self::SIGNING, true) => 2,
            str_ends_with($status, '_sign_3') => 3,
            str_ends_with($status, '_sign_4') => 4,
            default => null,
        };
    }

    /** สถานะหลังลงนามลำดับ $order ของฉบับ $doc */
    public static function afterSign(string $doc, int $order): string
    {
        return match ($order) {
            2 => $doc . '_sign_3',
            3 => $doc . '_sign_4',
            default => $doc === 'manual' ? 'in_use' : 'completed',
        };
    }

    public static function reportDueDays(): int
    {
        $days = (int) \Core\Setting::get(self::REPORT_DUE_SETTING, self::DEFAULT_REPORT_DUE_DAYS);

        return $days >= 1 && $days <= 365 ? $days : self::DEFAULT_REPORT_DUE_DAYS;
    }
}
