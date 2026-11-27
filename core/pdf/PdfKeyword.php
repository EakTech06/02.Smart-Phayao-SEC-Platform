<?php

declare(strict_types=1);

namespace Core\Pdf;

/** คำสงวนที่พบระหว่างแยกวิเคราะห์ (obj, endobj, stream, R ...) ใช้ภายใน */
final class PdfKeyword
{
    public function __construct(public readonly string $word)
    {
    }
}
