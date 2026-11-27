<?php

declare(strict_types=1);

namespace Core\Pdf;

/** อ่าน/เขียน PDF ไม่สำเร็จ (ไฟล์เสีย เข้ารหัส หรือใช้คุณสมบัติที่ไม่รองรับ) */
final class PdfException extends \RuntimeException
{
}
