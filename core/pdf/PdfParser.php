<?php

declare(strict_types=1);

namespace Core\Pdf;

/** ตัวแยกวิเคราะห์ค่า PDF (dict, array, name, string, number, ref) จากบัฟเฟอร์ */
final class PdfParser
{
    public const WS = "\x00\t\n\x0C\r ";
    private const DELIM = '()<>[]{}/%';

    public int $pos = 0;
    private int $len;

    public function __construct(public readonly string $buf)
    {
        $this->len = strlen($buf);
    }

    public function length(): int
    {
        return $this->len;
    }

    public function skipWs(): void
    {
        while ($this->pos < $this->len) {
            $c = $this->buf[$this->pos];
            if (str_contains(self::WS, $c)) {
                $this->pos++;
            } elseif ($c === '%') {
                $this->pos += strcspn($this->buf, "\r\n", $this->pos);
            } else {
                break;
            }
        }
    }

    public function startsWith(string $text): bool
    {
        return substr($this->buf, $this->pos, strlen($text)) === $text;
    }

    public function parseValue(int $depth = 0): mixed
    {
        if ($depth > 200) {
            throw new PdfException('โครงสร้างซ้อนลึกเกินไป');
        }
        $buf = $this->buf;
        $this->skipWs();
        if ($this->pos >= $this->len) {
            throw new PdfException('ข้อมูลสิ้นสุดก่อนกำหนด');
        }
        $c = $buf[$this->pos];
        if ($c === '/') {
            $this->pos++;
            $n = strcspn($buf, self::WS . self::DELIM, $this->pos);
            $name = substr($buf, $this->pos, $n);
            $this->pos += $n;

            return new PdfName($name);
        }
        if ($c === '<' && ($buf[$this->pos + 1] ?? '') === '<') {
            $this->pos += 2;
            $items = [];
            while (true) {
                $this->skipWs();
                if ($this->pos >= $this->len) {
                    throw new PdfException('dict ไม่ปิด');
                }
                if ($buf[$this->pos] === '>' && ($buf[$this->pos + 1] ?? '') === '>') {
                    $this->pos += 2;

                    return new PdfDict($items);
                }
                $key = $this->parseValue($depth + 1);
                if (!$key instanceof PdfName) {
                    throw new PdfException('คีย์ของ dict ไม่ถูกต้อง');
                }
                $this->skipWs();
                if (($buf[$this->pos] ?? '') === '>' && ($buf[$this->pos + 1] ?? '') === '>') {
                    $items[$key->raw] = null;
                    continue;
                }
                $items[$key->raw] = $this->parseValue($depth + 1);
            }
        }
        if ($c === '<') {
            $end = strpos($buf, '>', $this->pos);
            if ($end === false) {
                throw new PdfException('hex string ไม่ปิด');
            }
            $raw = substr($buf, $this->pos, $end - $this->pos + 1);
            $this->pos = $end + 1;

            return new PdfString($raw);
        }
        if ($c === '(') {
            $start = $this->pos;
            $level = 0;
            while ($this->pos < $this->len) {
                $ch = $buf[$this->pos];
                if ($ch === '\\') {
                    $this->pos += 2;
                    continue;
                }
                if ($ch === '(') {
                    $level++;
                } elseif ($ch === ')') {
                    $level--;
                    if ($level === 0) {
                        $this->pos++;

                        return new PdfString(substr($buf, $start, $this->pos - $start));
                    }
                }
                $this->pos++;
            }
            throw new PdfException('string ไม่ปิด');
        }
        if ($c === '[') {
            $this->pos++;
            $items = [];
            while (true) {
                $this->skipWs();
                if ($this->pos >= $this->len) {
                    throw new PdfException('array ไม่ปิด');
                }
                if ($buf[$this->pos] === ']') {
                    $this->pos++;

                    return $items;
                }
                $items[] = $this->parseValue($depth + 1);
            }
        }
        if (preg_match('/\G[+\-]?(\d+\.?\d*|\.\d+)/', $buf, $m, 0, $this->pos)) {
            $this->pos += strlen($m[0]);
            if (!str_contains($m[0], '.')) {
                // n g R
                if (preg_match('/\G\s+(\d+)\s+R(?=[\s()<>\[\]{}\/%]|$)/', $buf, $r, 0, $this->pos)) {
                    $this->pos += strlen($r[0]);

                    return new PdfRef((int) $m[0], (int) $r[1]);
                }

                return (int) $m[0];
            }

            return (float) $m[0];
        }
        $n = strcspn($buf, self::WS . self::DELIM, $this->pos);
        if ($n === 0) {
            $this->pos++;
            throw new PdfException('อักขระไม่คาดคิด: ' . $c);
        }
        $word = substr($buf, $this->pos, $n);
        $this->pos += $n;

        return match ($word) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => new PdfKeyword($word),
        };
    }
}
