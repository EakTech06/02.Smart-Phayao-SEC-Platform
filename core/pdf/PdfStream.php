<?php

declare(strict_types=1);

namespace Core\Pdf;

/** สตรีม PDF: dict + ข้อมูลดิบ (ยังเข้ารหัสตาม /Filter) */
final class PdfStream
{
    public function __construct(public PdfDict $dict, public string $data)
    {
    }
}
