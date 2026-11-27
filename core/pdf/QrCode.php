<?php

declare(strict_types=1);

namespace Core\Pdf;

/**
 * ตัวสร้าง QR Code (Model 2) แบบไม่ใช้ไลบรารีภายนอก: โหมดไบต์, ระดับแก้ไขข้อผิดพลาด M, เวอร์ชัน 1-10 (สูงสุด 213 ไบต์)
 * คืนค่าเป็นตารางโมดูล true = ดำ
 */
final class QrCode
{
    /** เวอร์ชัน => [EC ต่อบล็อก, [[จำนวนบล็อก, data codewords ต่อบล็อก], ...]] (ระดับ M) */
    private const BLOCKS_M = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $size;
    /** @var list<list<bool>> */
    private array $modules = [];
    /** @var list<list<bool>> */
    private array $function = [];

    /** @return list<list<bool>> */
    public static function matrix(string $data): array
    {
        return (new self())->encode($data);
    }

    /** @return list<list<bool>> */
    private function encode(string $data): array
    {
        $len = strlen($data);
        $version = 0;
        foreach (self::BLOCKS_M as $v => [, $groups]) {
            $capacity = array_sum(array_map(static fn (array $g): int => $g[0] * $g[1], $groups));
            $countBits = $v <= 9 ? 8 : 16;
            if (4 + $countBits + 8 * $len <= $capacity * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new PdfException('ข้อมูลยาวเกินกว่าจะสร้าง QR Code');
        }
        [$ecLen, $groups] = self::BLOCKS_M[$version];
        $capacity = array_sum(array_map(static fn (array $g): int => $g[0] * $g[1], $groups));

        // บิตข้อมูล
        $bits = '0100' . str_pad(decbin($len), $version <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $codewords = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($codewords) < $capacity; $pad ^= 1) {
            $codewords[] = $pad === 0 ? 0xEC : 0x11;
        }

        // แบ่งบล็อก + Reed-Solomon + สลับลำดับ
        $blocks = [];
        $offset = 0;
        foreach ($groups as [$count, $dataLen]) {
            for ($b = 0; $b < $count; $b++) {
                $blocks[] = array_slice($codewords, $offset, $dataLen);
                $offset += $dataLen;
            }
        }
        $divisor = self::rsDivisor($ecLen);
        $ecBlocks = array_map(static fn (array $blk): array => self::rsRemainder($blk, $divisor), $blocks);
        $final = [];
        $maxData = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $blk) {
                if ($i < count($blk)) {
                    $final[] = $blk[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $ec) {
                $final[] = $ec[$i];
            }
        }

        // ตาราง
        $this->size = 17 + 4 * $version;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->function = $this->modules;
        $this->drawFunctionPatterns($version);
        $this->placeData($final);

        $best = null;
        $bestScore = PHP_INT_MAX;
        $base = $this->modules;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->modules = $base;
            $this->applyMask($mask);
            $this->drawFormat($mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $this->modules;
            }
        }

        return $best;
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->function[$y][$x] = true;
    }

    private function drawFunctionPatterns(int $version): void
    {
        $n = $this->size;
        for ($i = 0; $i < $n; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $n && $y >= 0 && $y < $n) {
                        $dist = max(abs($dx), abs($dy));
                        $this->set($x, $y, $dist !== 2 && $dist !== 4);
                    }
                }
            }
        }
        $pos = self::ALIGN[$version];
        $count = count($pos);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // จองพื้นที่ format (วาดจริงตอนเลือก mask)
        $this->drawFormat(0);
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function drawFormat(int $mask): void
    {
        $n = $this->size;
        $data = (0 << 3) | $mask; // ระดับ M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($n - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $n - 15 + $i, $bit($i));
        }
        $this->set(8, $n - 8, true);
    }

    /** @param list<int> $codewords */
    private function placeData(array $codewords): void
    {
        $n = $this->size;
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $n - 1 - $vert : $vert;
                    if (!$this->function[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->function[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $score = 0;
        $finder = [true, false, true, true, true, false, true];
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $n; $a++) {
                $line = [];
                for ($b = 0; $b < $n; $b++) {
                    $line[] = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                }
                // กฎ 1: สีเดียวกันติดกัน ≥ 5
                $run = 1;
                for ($b = 1; $b <= $n; $b++) {
                    if ($b < $n && $line[$b] === $line[$b - 1]) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $score += 3 + $run - 5;
                        }
                        $run = 1;
                    }
                }
                // กฎ 3: รูปแบบคล้าย finder 1:1:3:1:1 ติดพื้นขาว 4 โมดูล
                for ($b = 0; $b + 7 <= $n; $b++) {
                    if (array_slice($line, $b, 7) !== $finder) {
                        continue;
                    }
                    $before = $b >= 4 && !in_array(true, array_slice($line, $b - 4, 4), true);
                    $after = $b + 11 <= $n && !in_array(true, array_slice($line, $b + 7, 4), true);
                    if ($before || $after) {
                        $score += 40;
                    }
                }
            }
        }
        // กฎ 2: บล็อก 2x2 สีเดียวกัน
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $dark += $m[$y][$x] ? 1 : 0;
                if ($x < $n - 1 && $y < $n - 1 && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        // กฎ 4: สัดส่วนสีดำ
        $total = $n * $n;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;

        return $score + max(0, $k) * 10;
    }

    // ------------------------------------------------------------ Reed-Solomon (GF(256), poly 0x11D)

    /** @return list<int> */
    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }

        return $result;
    }

    /** @param list<int> $data @param list<int> $divisor @return list<int> */
    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMul($coef, $factor);
            }
        }

        return $result;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }
}
