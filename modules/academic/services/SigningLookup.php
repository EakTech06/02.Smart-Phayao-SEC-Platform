<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Rbac;

/** ค่าคงที่การลงนาม 4 ลำดับ (ใช้ร่วมกันทั้งงานวิจัย คู่มือ และรายงานการใช้สื่อ) */
final class SigningLookup
{
    /** ลำดับ => ตำแหน่ง/ฐานะผู้ลงนาม */
    public const ORDERS = [
        1 => 'ครูผู้จัดทำ',
        2 => 'หัวหน้างานวิจัยและพัฒนานวัตกรรม',
        3 => 'หัวหน้าฝ่ายบริหารวิชาการ',
        4 => 'รองผู้อำนวยการสถานศึกษาฝ่ายบริหารวิชาการ',
    ];

    /** สิทธิ์ลงนามลำดับ 2-4 (ลำดับ 1 = เจ้าของงาน) */
    public const PERMS = [
        2 => 'academic.research.sign_2',
        3 => 'academic.research.sign_3',
        4 => 'academic.research.sign_4',
    ];

    /** สีกรอบของแต่ละลำดับ (หน้ากำหนดตำแหน่ง) */
    public const COLORS = [1 => '#0d6efd', 2 => '#198754', 3 => '#e8590c', 4 => '#d63384'];

    public const DOC_TYPES = [
        'research' => 'งานวิจัย',
        'manual' => 'คู่มือการใช้สื่อนวัตกรรม',
        'report' => 'รายงานการใช้สื่อนวัตกรรม',
    ];

    /** อักษรนำเลขที่เอกสาร */
    public const DOC_PREFIX = ['research' => 'RS', 'manual' => 'IM', 'report' => 'IR'];

    /** ขนาดกรอบเริ่มต้น (สัดส่วนของหน้า A4 แนวตั้ง) */
    public const DEFAULT_W = 0.36;
    public const DEFAULT_H = 0.085;
    public const MIN_W = 0.08;
    public const MIN_H = 0.03;

    /** ใส่รหัสผ่านผิดได้กี่ครั้งใน 15 นาที */
    public const MAX_PASSWORD_FAILS = 5;

    public static function canSignOrder(int $order, int $ownerUserId, ?int $userId = null): bool
    {
        if ($order === 1) {
            return $userId !== null && $userId === $ownerUserId;
        }

        return isset(self::PERMS[$order]) && Rbac::can(self::PERMS[$order], $userId);
    }

    /** ตำแหน่งเริ่มต้นของกรอบลายเซ็น: หน้าสุดท้าย เรียง 2 x 2 ด้านล่างของหน้า */
    public static function defaultBox(int $order, int $pageCount): array
    {
        $col = ($order - 1) % 2;
        $row = intdiv($order - 1, 2);

        return [
            'page' => max(1, $pageCount),
            'x' => $col === 0 ? 0.08 : 0.56,
            'y' => 0.70 + $row * 0.11,
            'w' => self::DEFAULT_W,
            'h' => self::DEFAULT_H,
        ];
    }
}
