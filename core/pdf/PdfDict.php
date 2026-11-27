<?php

declare(strict_types=1);

namespace Core\Pdf;

/** พจนานุกรม PDF: คีย์คือชื่อรูปแบบดิบ (ไม่มี /) */
final class PdfDict
{
    /** @param array<string, mixed> $items */
    public function __construct(public array $items = [])
    {
    }

    public function get(string $key): mixed
    {
        return $this->items[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /** ค่าชื่อ (/Type /Page) หรือ null */
    public function name(string $key): ?string
    {
        $v = $this->items[$key] ?? null;

        return $v instanceof PdfName ? $v->value() : null;
    }
}
