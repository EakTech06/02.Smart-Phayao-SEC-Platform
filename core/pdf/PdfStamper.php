<?php

declare(strict_types=1);

namespace Core\Pdf;

/**
 * ประทับภาพ/QR Code ลงบนหน้า PDF แบบเขียนต่อท้ายไฟล์ (incremental update)
 * เนื้อหาเดิมทุกไบต์อยู่ครบ ส่วนที่เพิ่มคือ: วัตถุภาพ, สตรีมคำสั่งวาด, หน้าเดิมฉบับแก้ไข (Contents/Resources) และ xref ใหม่
 *
 * ตำแหน่งระบุเป็นสัดส่วนของพื้นที่แสดงผลของหน้า (หลังหมุนตาม /Rotate) จากมุมซ้ายบน: x, y, w, h ∈ [0, 1]
 * รายการ: ['page' => เลขหน้า (เริ่ม 1), 'x', 'y', 'w', 'h', 'image' => \GdImage] หรือ [..., 'qr' => list<list<bool>>]
 */
final class PdfStamper
{
    private PdfReader $reader;
    private int $next;
    /** @var array<int, array{gen: int, body: string}> */
    private array $objects = [];

    public function __construct(string $pdf)
    {
        $this->reader = new PdfReader($pdf);
        $this->next = $this->reader->nextObjectNumber();
    }

    public function reader(): PdfReader
    {
        return $this->reader;
    }

    /** @param list<array<string, mixed>> $items */
    public function stamp(array $items): string
    {
        $byPage = [];
        foreach ($items as $item) {
            $byPage[(int) $item['page']][] = $item;
        }
        $pages = $this->reader->pages();
        foreach ($byPage as $pageNo => $pageItems) {
            if ($pageNo < 1 || $pageNo > count($pages)) {
                throw new PdfException("ไม่พบหน้าที่ {$pageNo} ในเอกสาร");
            }
            $this->stampPage($pageNo - 1, $pageItems);
        }

        return $this->output();
    }

    // ------------------------------------------------------------ หน้า

    private function stampPage(int $index, array $items): void
    {
        $page = $this->reader->pages()[$index];
        $geo = $this->reader->pageGeometry($index);
        $uid = substr(bin2hex(random_bytes(4)), 0, 6);
        $ops = '';
        $xobjects = [];
        foreach ($items as $i => $item) {
            $rect = $this->rect($geo, $item);
            if (isset($item['image'])) {
                $name = 'SgIm' . $uid . $i;
                $xobjects[$name] = $this->imageObject($item['image']);
                $ops .= 'q ' . self::matrix($rect) . " cm /{$name} Do Q\n";
            } elseif (isset($item['qr'])) {
                $ops .= $this->qrOps($rect, $item['qr']);
            }
        }

        $dict = new PdfDict($page['dict']->items);
        // Resources: คัดลอกของเดิม (รวมที่สืบทอดจาก /Pages) แล้วเพิ่ม XObject
        $resources = $this->reader->resolve($page['resources']);
        $resources = new PdfDict($resources instanceof PdfDict ? $resources->items : []);
        $xo = $this->reader->resolve($resources->get('XObject'));
        $xo = new PdfDict($xo instanceof PdfDict ? $xo->items : []);
        foreach ($xobjects as $name => $num) {
            $xo->set($name, new PdfRef($num));
        }
        $resources->set('XObject', $xo);
        $dict->set('Resources', $resources);
        if (!$dict->has('MediaBox')) {
            // MediaBox ที่สืบทอดจาก /Pages ใส่ไว้ที่หน้าโดยตรง
            $dict->set('MediaBox', $page['mediabox']);
        }

        // Contents: [q] + เดิม + [Q + ของใหม่] กันสถานะกราฟิกของเนื้อหาเดิมรั่วมาถึงภาพที่ประทับ
        $old = $dict->get('Contents');
        $resolved = $this->reader->resolve($old);
        $list = [];
        if ($old instanceof PdfRef && $resolved instanceof PdfStream) {
            $list = [$old];
        } elseif (is_array($resolved)) {
            $list = array_values(array_filter($resolved, static fn ($v): bool => $v instanceof PdfRef));
        }
        $contents = [];
        if ($list !== []) {
            $contents[] = new PdfRef($this->addStream(new PdfDict(), "q\n"));
            array_push($contents, ...$list);
            $ops = "Q\n" . $ops;
        }
        $contents[] = new PdfRef($this->addStream(new PdfDict(), $ops, true));
        $dict->set('Contents', $contents);

        $ref = $page['ref'];
        $this->objects[$ref->num] = ['gen' => $this->reader->generation($ref->num), 'body' => self::serialize($dict)];
    }

    /**
     * สี่เหลี่ยมในพิกัดผู้ใช้ของหน้า: ทิศและจุดเริ่มสำหรับภาพตั้งตรงในมุมมองที่หมุนแล้ว
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} เมทริกซ์ [A B C D E F] ของ unit square
     */
    private function rect(array $geo, array $item): array
    {
        $x = max(0.0, min(1.0, (float) $item['x'])) * $geo['width'];
        $y = max(0.0, min(1.0, (float) $item['y'])) * $geo['height'];
        $w = max(0.001, min(1.0, (float) $item['w'])) * $geo['width'];
        $h = max(0.001, min(1.0, (float) $item['h'])) * $geo['height'];
        [$llx, $lly, $urx, $ury] = $geo['box'];
        // จุดในมุมมอง (dx ไปขวา, dy ลงล่าง) → พิกัดผู้ใช้: ux = a dx + c dy + e, uy = b dx + d dy + f
        [$a, $b, $c, $d, $e, $f] = match ($geo['rotate']) {
            90 => [0, 1, 1, 0, $llx, $lly],
            180 => [-1, 0, 0, 1, $urx, $lly],
            270 => [0, -1, -1, 0, $urx, $ury],
            default => [1, 0, 0, -1, $llx, $ury],
        };
        // unit square (u ไปขวา, v ขึ้นบน) → มุมมอง: dx = x + u w, dy = y + h - v h
        return [
            $a * $w,
            $b * $w,
            -$c * $h,
            -$d * $h,
            $a * $x + $c * ($y + $h) + $e,
            $b * $x + $d * ($y + $h) + $f,
        ];
    }

    private static function matrix(array $m): string
    {
        return implode(' ', array_map([self::class, 'num'], $m));
    }

    /** คำสั่งวาด QR Code แบบเวกเตอร์ (พื้นขาวรวมขอบเงียบ 2 โมดูล) */
    private function qrOps(array $m, array $modules): string
    {
        $n = count($modules);
        $quiet = 2;
        $size = $n + 2 * $quiet;
        $scale = [$m[0] / $size, $m[1] / $size, $m[2] / $size, $m[3] / $size, $m[4], $m[5]];
        $ops = 'q ' . self::matrix($scale) . " cm\n1 g 0 0 {$size} {$size} re f\n0 g\n";
        foreach ($modules as $r => $row) {
            $v = $size - $quiet - 1 - $r;
            $c = 0;
            while ($c < $n) {
                if (!$row[$c]) {
                    $c++;
                    continue;
                }
                $start = $c;
                while ($c < $n && $row[$c]) {
                    $c++;
                }
                $ops .= ($start + $quiet) . " {$v} " . ($c - $start) . " 1 re\n";
            }
        }

        return $ops . "f\nQ\n";
    }

    // ------------------------------------------------------------ ภาพ

    /** ภาพ GD → XObject RGB + SMask (ความโปร่งใส) บีบอัด Flate */
    private function imageObject(\GdImage $img): int
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $rgb = '';
        $alpha = '';
        $hasAlpha = false;
        $truecolor = imageistruecolor($img);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                if (!$truecolor) {
                    $c = imagecolorsforindex($img, $c);
                    $c = ($c['alpha'] << 24) | ($c['red'] << 16) | ($c['green'] << 8) | $c['blue'];
                }
                $a = ($c >> 24) & 0x7F;
                $rgb .= chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
                $alpha .= chr(255 - (int) round($a * 255 / 127));
                $hasAlpha = $hasAlpha || $a > 0;
            }
        }
        $dict = new PdfDict([
            'Type' => new PdfName('XObject'),
            'Subtype' => new PdfName('Image'),
            'Width' => $w,
            'Height' => $h,
            'ColorSpace' => new PdfName('DeviceRGB'),
            'BitsPerComponent' => 8,
        ]);
        if ($hasAlpha) {
            $mask = $this->addStream(new PdfDict([
                'Type' => new PdfName('XObject'),
                'Subtype' => new PdfName('Image'),
                'Width' => $w,
                'Height' => $h,
                'ColorSpace' => new PdfName('DeviceGray'),
                'BitsPerComponent' => 8,
            ]), $alpha, true);
            $dict->set('SMask', new PdfRef($mask));
        }

        return $this->addStream($dict, $rgb, true);
    }

    // ------------------------------------------------------------ เขียนไฟล์

    private function addStream(PdfDict $dict, string $data, bool $compress = false): int
    {
        if ($compress) {
            $data = (string) gzcompress($data, 6);
            $dict->set('Filter', new PdfName('FlateDecode'));
        }
        $dict->set('Length', strlen($data));
        $num = $this->next++;
        $this->objects[$num] = ['gen' => 0, 'body' => self::serialize($dict) . "\nstream\n" . $data . "\nendstream"];

        return $num;
    }

    private function output(): string
    {
        $r = $this->reader;
        $out = $r->data;
        if (!str_ends_with($out, "\n") && !str_ends_with($out, "\r")) {
            $out .= "\n";
        }
        $offsets = [];
        ksort($this->objects);
        foreach ($this->objects as $num => $obj) {
            $offsets[$num] = [strlen($out), $obj['gen']];
            $out .= "{$num} {$obj['gen']} obj\n{$obj['body']}\nendobj\n";
        }

        $trailer = new PdfDict();
        foreach (['Root', 'Info', 'ID'] as $key) {
            if ($r->trailer()->has($key)) {
                $trailer->set($key, $r->trailer()->get($key));
            }
        }

        if ($r->recovered) {
            // xref เดิมใช้ไม่ได้: เขียน xref stream ครบทุกวัตถุ (รวมวัตถุใน object stream)
            $entries = [0 => [0, 0, 65535]];
            foreach ($r->xrefEntries() as $num => $e) {
                $entries[$num] = match ($e['t']) {
                    1 => [1, $e['off'], $e['gen'] ?? 0],
                    2 => [2, $e['stm'], $e['idx']],
                    default => [0, 0, 0],
                };
            }
            foreach ($offsets as $num => [$off, $gen]) {
                $entries[$num] = [1, $off, $gen];
            }

            return $this->writeXrefStream($out, $entries, $trailer, null);
        }
        $entries = [];
        foreach ($offsets as $num => [$off, $gen]) {
            $entries[$num] = [1, $off, $gen];
        }
        if ($r->xrefIsStream) {
            return $this->writeXrefStream($out, $entries, $trailer, $r->startxref);
        }

        $xrefAt = strlen($out);
        $out .= "xref\n";
        foreach (self::runs(array_keys($entries)) as [$start, $count]) {
            $out .= "{$start} {$count}\n";
            for ($n = $start; $n < $start + $count; $n++) {
                $out .= sprintf("%010d %05d n\r\n", $entries[$n][1], $entries[$n][2]);
            }
        }
        $trailer->set('Size', max($this->next, $r->nextObjectNumber()));
        $trailer->set('Prev', $r->startxref);

        return $out . 'trailer' . "\n" . self::serialize($trailer) . "\nstartxref\n{$xrefAt}\n%%EOF\n";
    }

    /** @param array<int, array{0: int, 1: int, 2: int}> $entries */
    private function writeXrefStream(string $out, array $entries, PdfDict $trailer, ?int $prev): string
    {
        $num = $this->next++;
        $xrefAt = strlen($out);
        $entries[$num] = [1, $xrefAt, 0];
        ksort($entries);
        $data = '';
        $index = [];
        foreach (self::runs(array_keys($entries)) as [$start, $count]) {
            array_push($index, $start, $count);
            for ($n = $start; $n < $start + $count; $n++) {
                [$t, $f2, $f3] = $entries[$n];
                $data .= chr($t) . pack('N', $f2) . pack('n', $f3);
            }
        }
        $trailer->set('Type', new PdfName('XRef'));
        $trailer->set('Size', max($this->next, $this->reader->nextObjectNumber()));
        $trailer->set('Index', $index);
        $trailer->set('W', [1, 4, 2]);
        if ($prev !== null) {
            $trailer->set('Prev', $prev);
        }
        $trailer->set('Length', strlen($data));

        return $out . "{$num} 0 obj\n" . self::serialize($trailer) . "\nstream\n" . $data . "\nendstream\nendobj\nstartxref\n{$xrefAt}\n%%EOF\n";
    }

    /** @param list<int> $nums @return list<array{0: int, 1: int}> ช่วงเลขต่อเนื่อง [เริ่ม, จำนวน] */
    private static function runs(array $nums): array
    {
        sort($nums);
        $runs = [];
        foreach ($nums as $n) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last][0] + $runs[$last][1] === $n) {
                $runs[$last][1]++;
            } else {
                $runs[] = [$n, 1];
            }
        }

        return $runs;
    }

    // ------------------------------------------------------------ แปลงค่าเป็นข้อความ PDF

    public static function serialize(mixed $v): string
    {
        return match (true) {
            $v === null => 'null',
            $v === true => 'true',
            $v === false => 'false',
            is_int($v) => (string) $v,
            is_float($v) => self::num($v),
            $v instanceof PdfName => '/' . $v->raw,
            $v instanceof PdfRef => $v->num . ' ' . $v->gen . ' R',
            $v instanceof PdfString => $v->raw,
            $v instanceof PdfKeyword => $v->word,
            $v instanceof PdfDict => '<<' . implode('', array_map(
                static fn (string $k, mixed $val): string => '/' . $k . ' ' . self::serialize($val),
                array_keys($v->items),
                array_values($v->items)
            )) . '>>',
            is_array($v) => '[' . implode(' ', array_map([self::class, 'serialize'], $v)) . ']',
            default => throw new PdfException('เขียนค่าชนิดนี้ไม่ได้'),
        };
    }

    public static function num(float|int $v): string
    {
        if (is_int($v)) {
            return (string) $v;
        }
        $s = rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');

        return $s === '-0' || $s === '' ? '0' : $s;
    }
}
