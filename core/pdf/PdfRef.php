<?php

declare(strict_types=1);

namespace Core\Pdf;

/** การอ้างอิงวัตถุ (n g R) */
final class PdfRef
{
    public function __construct(public readonly int $num, public readonly int $gen = 0)
    {
    }
}
