<?php

declare(strict_types=1);

namespace Core\Exceptions;

class HttpException extends \RuntimeException
{
    public function __construct(private int $status, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'คำขอไม่ถูกต้อง',
            401 => 'กรุณาเข้าสู่ระบบ',
            403 => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้',
            404 => 'ไม่พบหน้าหรือข้อมูลที่ต้องการ',
            405 => 'ไม่รองรับรูปแบบคำขอนี้',
            419 => 'เซสชันหมดอายุหรือโทเคนความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่',
            422 => 'ข้อมูลไม่ถูกต้อง',
            429 => 'มีการร้องขอมากเกินไป',
            default => 'เกิดข้อผิดพลาดภายในระบบ',
        };
    }
}