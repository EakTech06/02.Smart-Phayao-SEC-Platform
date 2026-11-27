<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

/** ค่าคงที่ของงานวิจัยและพัฒนานวัตกรรม */
final class ResearchLookup
{
    public const PERM_SUBMIT = 'academic.research.submit_own';
    public const PERM_READ_ALL = 'academic.research.read_all';
    public const PERM_REVIEW = 'academic.research.review_all';
    public const PERM_MANAGE = 'academic.research.manage_all';

    public const KINDS = [
        'research' => 'ประเภทงานวิจัย',
        'innovation' => 'ประเภทสื่อนวัตกรรม',
    ];

    /** สถานะงานวิจัย: [ชื่อ, สี Bootstrap, ไอคอน] */
    public const STATUSES = [
        'draft' => ['ร่าง', 'secondary', 'pencil'],
        'submitted' => ['ส่งแล้ว (รอตรวจสอบ)', 'info', 'send'],
        'reviewing' => ['กำลังตรวจสอบ', 'primary', 'search'],
        'revision' => ['ส่งกลับแก้ไข', 'warning', 'arrow-return-left'],
        'resubmitted' => ['ส่งฉบับแก้ไขแล้ว', 'info', 'arrow-repeat'],
        'passed' => ['ผ่านการตรวจ', 'success', 'check-circle'],
        'failed' => ['ไม่ผ่าน', 'danger', 'x-circle'],
        'cancelled' => ['ยกเลิก', 'dark', 'slash-circle'],
    ];

    /** รอผู้ตรวจดำเนินการ */
    public const PENDING_REVIEW = ['submitted', 'resubmitted', 'reviewing'];
    /** ผ่านการตรวจแล้ว (ระยะการลงนามจะเพิ่มสถานะต่อจากนี้) */
    public const APPROVED = ['passed'];

    /** ผู้ตรวจเปลี่ยนสถานะได้: สถานะปลายทาง => สถานะต้นทางที่อนุญาต */
    public const REVIEW_TRANSITIONS = [
        'reviewing' => ['submitted', 'resubmitted'],
        'revision' => ['submitted', 'resubmitted', 'reviewing'],
        'passed' => ['submitted', 'resubmitted', 'reviewing'],
        'failed' => ['submitted', 'resubmitted', 'reviewing'],
        'cancelled' => ['submitted', 'resubmitted', 'reviewing', 'revision'],
    ];
    /** สถานะที่ผู้ตรวจต้องระบุหมายเหตุ/เหตุผล */
    public const NOTE_REQUIRED = ['revision', 'failed', 'cancelled'];

    /** ครูแก้ข้อมูลและอัปโหลดไฟล์ใหม่ได้ */
    public const OWNER_EDITABLE = ['draft', 'revision'];
    /** ครูยกเลิกงานของตนเองได้ */
    public const OWNER_CANCELLABLE = ['draft', 'submitted', 'revision'];

    public const SEMESTERS = [1 => 'ภาคเรียนที่ 1', 2 => 'ภาคเรียนที่ 2'];

    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_KEYWORDS = 10;

    /** วันที่แจ้งเตือนก่อนครบกำหนดส่ง */
    public const REMIND_DAYS = [7, 3, 1];

    public const NOTIFY_TYPE = 'research';

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    public static function statusBadge(string $status): string
    {
        [$label, $color, $icon] = self::STATUSES[$status] ?? [$status, 'secondary', 'circle'];

        return '<span class="badge text-bg-' . e($color) . '"><i class="bi bi-' . e($icon) . ' me-1"></i>' . e($label) . '</span>';
    }

    public static function semesterLabel(int $semester): string
    {
        return self::SEMESTERS[$semester] ?? (string) $semester;
    }
}
