<?php

declare(strict_types=1);

namespace Core\Pdf;

/** ชื่อ PDF (/Name) เก็บรูปแบบเดิม (#xx) เพื่อเขียนกลับได้ตรงทุกไบต์ */
final class PdfName
{
    public function __construct(public readonly string $raw)
    {
    }

    public function value(): string
    {
        return (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $m): string => chr((int) hexdec($m[1])), $this->raw);
    }
}
