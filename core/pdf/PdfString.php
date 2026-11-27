<?php

declare(strict_types=1);

namespace Core\Pdf;

/** สตริง PDF เก็บข้อความดิบพร้อมตัวคั่น ( ) หรือ < > เพื่อเขียนกลับได้ตรงทุกไบต์ */
final class PdfString
{
    public function __construct(public readonly string $raw)
    {
    }

    /** สตริงตัวอักษร ASCII ที่ปลอดภัย */
    public static function literal(string $text): self
    {
        return new self('(' . strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => '\\n']) . ')');
    }

    public static function hex(string $bytes): self
    {
        return new self('<' . bin2hex($bytes) . '>');
    }
}
