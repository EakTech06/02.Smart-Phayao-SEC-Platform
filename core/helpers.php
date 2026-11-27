<?php

declare(strict_types=1);

use Core\Config;
use Core\Csrf;
use Core\Rbac;
use Core\Request;
use Core\Security;
use Core\Session;

function e(mixed $value): string
{
    return Security::e($value);
}

function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/');
    }

    return $base;
}

function url(string $path = '/'): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** URL ของไฟล์ใน public/assets ต่อท้าย ?v=เวลาแก้ไขไฟล์ เพื่อให้เบราว์เซอร์โหลดไฟล์ใหม่ทุกครั้งที่ไฟล์เปลี่ยน (ไม่ต้องกด Ctrl+F5) */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = dirname(__DIR__) . '/public/assets/' . $path;
    $mtime = is_file($file) ? filemtime($file) : false;

    return url('assets/' . $path) . ($mtime !== false && !str_contains($path, '?') ? '?v=' . $mtime : '');
}

function current_path(): string
{
    return Request::capture()->path();
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function old(string $key, string $default = ''): string
{
    $old = Session::getFlash('old', []);

    return is_array($old) && isset($old[$key]) && is_scalar($old[$key]) ? (string) $old[$key] : $default;
}

function errors(): array
{
    $errors = Session::getFlash('errors', []);

    return is_array($errors) ? $errors : [];
}

function error_for(string $field): ?string
{
    return errors()[$field][0] ?? null;
}

function can(string $permission): bool
{
    return Rbac::can($permission);
}
/** วันที่แบบไทย เช่น 12 พ.ย. 2569 09:00 (ปี พ.ศ.) */
function thai_date(?string $datetime, bool $withTime = true, bool $fullMonth = false): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '-';
    }
    $months = $fullMonth ? THAI_MONTHS : THAI_MONTHS_SHORT;
    $text = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . ((int) date('Y', $ts) + 543);

    return $withTime ? $text . ' ' . date('H:i', $ts) : $text;
}

/** วันที่แบบตัวเลข พ.ศ. เช่น 10/05/2563 (ใช้ในไฟล์ส่งออก/ช่องที่ต้องการแบบสั้น) */
function thai_date_numeric(?string $datetime, bool $withTime = false): string
{
    $ts = $datetime !== null && $datetime !== '' ? strtotime($datetime) : false;
    if ($ts === false) {
        return '';
    }

    return date('d/m/', $ts) . ((int) date('Y', $ts) + 543) . ($withTime ? ' ' . date('H:i', $ts) : '');
}

/**
 * ชื่อระบบที่ผู้ดูแลตั้งได้ (ตั้งค่าระบบ > ชื่อระบบ) ไม่มีค่า/อ่านฐานข้อมูลไม่ได้ ใช้ค่าเริ่มต้น
 * @param 'name'|'brand_main'|'brand_sub'|'short_name'|'tagline' $part
 */
function app_name(string $part = 'name'): string
{
    $defaults = [
        'name' => (string) config('app.name', 'Smart Phayao SEC Platform'),
        'brand_main' => 'Smart Phayao SEC',
        'brand_sub' => 'Platform',
        'short_name' => 'Smart SEC',
        'tagline' => 'ระบบสารสนเทศเพื่อการบริหารจัดการ',
    ];
    try {
        $value = \Core\Setting::get('app.' . $part);
    } catch (\Throwable) {
        $value = null; // หน้า error ตอนฐานข้อมูลล่ม
    }

    $value = $value === null ? null : trim((string) $value);
    // บรรทัดรอง/คำอธิบาย เว้นว่างได้ ช่องอื่นถ้าว่างใช้ค่าเริ่มต้น
    if ($value === null || ($value === '' && !in_array($part, ['brand_sub', 'tagline'], true))) {
        return $defaults[$part] ?? $defaults['name'];
    }

    return $value;
}

/** ข้อความท้ายกระดาษรายงาน: พิมพ์ออกจากระบบ ... ผู้พิมพ์ ... เมื่อวันที่ วว/ดด/ปปปป (พ.ศ.) เวลา ชม.:นาที น. */
function print_footer_text(?int $timestamp = null): string
{
    $ts = $timestamp ?? time();

    return 'พิมพ์ออกจากระบบ ' . app_name()
        . ' ผู้พิมพ์: ' . (\Core\Auth::displayName() ?: '-')
        . ' เมื่อวันที่ ' . thai_date_numeric(date('Y-m-d', $ts))
        . ' เวลา ' . date('H:i', $ts) . ' น.';
}

/** เวลาที่ผ่านมาแบบสั้น เช่น "5 นาทีที่แล้ว" (เกิน 7 วันแสดงวันที่) */
function time_ago(?string $datetime): string
{
    $ts = $datetime !== null ? strtotime($datetime) : false;
    if ($ts === false) {
        return '-';
    }
    $diff = time() - $ts;

    return match (true) {
        $diff < 60 => 'เมื่อสักครู่',
        $diff < 3600 => intdiv($diff, 60) . ' นาทีที่แล้ว',
        $diff < 86400 => intdiv($diff, 3600) . ' ชั่วโมงที่แล้ว',
        $diff < 604800 => intdiv($diff, 86400) . ' วันที่แล้ว',
        default => thai_date($datetime, false),
    };
}

const THAI_MONTHS = [1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const THAI_MONTHS_SHORT = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
