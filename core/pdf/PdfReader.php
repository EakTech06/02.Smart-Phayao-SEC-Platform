<?php

declare(strict_types=1);

namespace Core\Pdf;

/**
 * ตัวอ่าน PDF ขนาดเล็ก (ไม่ใช้ไลบรารีภายนอก) สำหรับประทับลายเซ็นแบบเขียนต่อท้าย (incremental update)
 * - รองรับตาราง xref แบบดั้งเดิม, xref stream, ไฟล์ผสม (/XRefStm), object stream และ /Prev หลายชั้น
 * - ไฟล์ที่ตาราง xref เสีย: สร้างตารางใหม่จากการสแกน "n g obj" ทั้งไฟล์ (recovered = true)
 * - ไม่รองรับไฟล์เข้ารหัส (/Encrypt)
 */
final class PdfReader
{
    public readonly string $data;
    private int $len;
    private PdfParser $parser;

    /** @var array<int, array{t: int, off?: int, gen?: int, stm?: int, idx?: int}> */
    private array $xref = [];
    private ?PdfDict $trailer = null;
    /** ตำแหน่ง xref ล่าสุด (ใช้เป็น /Prev ของส่วนที่เขียนต่อ) */
    public int $startxref = 0;
    /** ส่วน xref ล่าสุดเป็นสตรีม (ส่วนต่อท้ายต้องเป็นสตรีมด้วย) */
    public bool $xrefIsStream = false;
    /** สร้าง xref ใหม่จากการสแกนไฟล์ */
    public bool $recovered = false;

    /** @var array<int, mixed> */
    private array $cache = [];
    /** @var array<int, array{0: string, 1: array<int, int>, 2: int}> */
    private array $objStreams = [];
    /** @var list<array{ref: PdfRef, dict: PdfDict, resources: mixed, mediabox: array, cropbox: ?array, rotate: int}>|null */
    private ?array $pages = null;

    public function __construct(string $data)
    {
        $start = strpos($data, '%PDF-');
        if ($start === false || $start > 1024) {
            throw new PdfException('ไม่ใช่ไฟล์ PDF');
        }
        $this->data = $data;
        $this->len = strlen($data);
        $this->parser = new PdfParser($data);
        try {
            $this->loadXref();
            $this->cache = [];
        } catch (PdfException) {
            $this->recover();
        }
        if ($this->trailer === null || !$this->trailer->get('Root') instanceof PdfRef) {
            $this->recover();
        }
        if ($this->trailer->has('Encrypt')) {
            throw new PdfException('ไฟล์ PDF มีการเข้ารหัสหรือตั้งรหัสผ่าน กรุณาบันทึกเป็น PDF ใหม่แบบไม่เข้ารหัส');
        }
    }

    public function trailer(): PdfDict
    {
        return $this->trailer ?? new PdfDict();
    }

    /** @return array<int, array{t: int, off?: int, gen?: int, stm?: int, idx?: int}> */
    public function xrefEntries(): array
    {
        return $this->xref;
    }

    /** เลขวัตถุถัดไปที่ว่าง */
    public function nextObjectNumber(): int
    {
        $size = $this->trailer()->get('Size');
        $max = $this->xref === [] ? 0 : max(array_keys($this->xref)) + 1;

        return max(is_int($size) ? $size : 0, $max, 1);
    }

    // ------------------------------------------------------------ วัตถุ

    /** ค่าของวัตถุเลข $num (PdfStream สำหรับสตรีม) */
    public function object(int $num): mixed
    {
        if (array_key_exists($num, $this->cache)) {
            return $this->cache[$num];
        }
        $this->cache[$num] = null; // กันวนซ้ำ
        $entry = $this->xref[$num] ?? null;
        $value = null;
        try {
            if ($entry !== null && $entry['t'] === 1) {
                $value = $this->parseIndirectAt($entry['off'], $num);
            } elseif ($entry !== null && $entry['t'] === 2) {
                $value = $this->fromObjectStream($entry['stm'], $num);
            }
        } catch (PdfException $e) {
            unset($this->cache[$num]);
            throw $e;
        }

        return $this->cache[$num] = $value;
    }

    public function generation(int $num): int
    {
        $entry = $this->xref[$num] ?? null;

        return $entry !== null && $entry['t'] === 1 ? (int) ($entry['gen'] ?? 0) : 0;
    }

    /** แปลง PdfRef เป็นค่าจริง (ค่าอื่นคืนตามเดิม) */
    public function resolve(mixed $value): mixed
    {
        $depth = 0;
        while ($value instanceof PdfRef && $depth++ < 32) {
            $value = $this->object($value->num);
        }

        return $value;
    }

    /** ถอดรหัสข้อมูลสตรีมตาม /Filter (รองรับ FlateDecode + PNG predictor, ASCIIHexDecode) */
    public function decodeStream(PdfStream $stream): string
    {
        $data = $stream->data;
        $filters = $this->resolve($stream->dict->get('Filter'));
        $params = $this->resolve($stream->dict->get('DecodeParms'));
        $filters = $filters === null ? [] : (is_array($filters) ? $filters : [$filters]);
        $params = is_array($params) ? $params : [$params];
        foreach (array_values($filters) as $i => $filter) {
            $name = $filter instanceof PdfName ? $filter->value() : '';
            $param = $this->resolve($params[$i] ?? null);
            $data = match ($name) {
                'FlateDecode', 'Fl' => $this->predict(self::inflate($data), $param instanceof PdfDict ? $param : null),
                'ASCIIHexDecode', 'AHx' => self::asciiHex($data),
                default => throw new PdfException("ไม่รองรับตัวกรอง {$name}"),
            };
        }

        return $data;
    }

    // ------------------------------------------------------------ หน้า

    /**
     * รายการหน้าตามลำดับ พร้อมค่าที่สืบทอดจาก /Pages (Resources, MediaBox, CropBox, Rotate)
     * @return list<array{ref: PdfRef, dict: PdfDict, resources: mixed, mediabox: array, cropbox: ?array, rotate: int}>
     */
    public function pages(): array
    {
        if ($this->pages !== null) {
            return $this->pages;
        }
        $root = $this->resolve($this->trailer()->get('Root'));
        if (!$root instanceof PdfDict) {
            throw new PdfException('ไม่พบโครงสร้างเอกสาร (Root)');
        }
        $pages = [];
        $visited = [];
        $walk = function (mixed $node, array $inherit) use (&$walk, &$pages, &$visited): void {
            if (!$node instanceof PdfRef || isset($visited[$node->num]) || count($visited) > 100000) {
                return;
            }
            $visited[$node->num] = true;
            $dict = $this->resolve($node);
            if (!$dict instanceof PdfDict) {
                return;
            }
            foreach (['Resources', 'MediaBox', 'CropBox', 'Rotate'] as $key) {
                if ($dict->has($key)) {
                    $inherit[$key] = $dict->get($key);
                }
            }
            $kids = $this->resolve($dict->get('Kids'));
            if ($dict->name('Type') === 'Pages' || (is_array($kids) && $dict->name('Type') !== 'Page')) {
                foreach (is_array($kids) ? $kids : [] as $kid) {
                    $walk($kid, $inherit);
                }

                return;
            }
            $pages[] = [
                'ref' => $node,
                'dict' => $dict,
                'resources' => $inherit['Resources'] ?? null,
                'mediabox' => $this->box($inherit['MediaBox'] ?? null) ?? [0.0, 0.0, 612.0, 792.0],
                'cropbox' => $this->box($inherit['CropBox'] ?? null),
                'rotate' => ((((int) $this->resolve($inherit['Rotate'] ?? 0)) % 360) + 360) % 360,
            ];
        };
        $walk($root->get('Pages'), []);
        if ($pages === []) {
            throw new PdfException('ไม่พบหน้าในเอกสาร');
        }

        return $this->pages = $pages;
    }

    public function pageCount(): int
    {
        return count($this->pages());
    }

    /**
     * พื้นที่แสดงผลของหน้า (pt): box ในพิกัดผู้ใช้, การหมุน และกว้าง/สูงหลังหมุน
     * @return array{box: array{0: float, 1: float, 2: float, 3: float}, rotate: int, width: float, height: float}
     */
    public function pageGeometry(int $index): array
    {
        $page = $this->pages()[$index] ?? throw new PdfException('ไม่พบหน้าที่ ' . ($index + 1));
        $box = $page['mediabox'];
        if ($page['cropbox'] !== null) {
            $c = $page['cropbox'];
            $cut = [max($box[0], $c[0]), max($box[1], $c[1]), min($box[2], $c[2]), min($box[3], $c[3])];
            if ($cut[2] > $cut[0] && $cut[3] > $cut[1]) {
                $box = $cut;
            }
        }
        $w = $box[2] - $box[0];
        $h = $box[3] - $box[1];
        $rotate = $page['rotate'] % 90 === 0 ? $page['rotate'] : 0;
        $swap = $rotate === 90 || $rotate === 270;

        return ['box' => $box, 'rotate' => $rotate, 'width' => $swap ? $h : $w, 'height' => $swap ? $w : $h];
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    private function box(mixed $value): ?array
    {
        $value = $this->resolve($value);
        if (!is_array($value) || count($value) < 4) {
            return null;
        }
        $n = array_map(fn ($v): float => (float) $this->resolve($v), array_slice(array_values($value), 0, 4));

        return [min($n[0], $n[2]), min($n[1], $n[3]), max($n[0], $n[2]), max($n[1], $n[3])];
    }

    // ------------------------------------------------------------ xref

    private function loadXref(): void
    {
        $tail = substr($this->data, max(0, $this->len - 2048));
        if (!preg_match_all('/startxref\s+(\d+)/', $tail, $m)) {
            throw new PdfException('ไม่พบ startxref');
        }
        $offset = (int) end($m[1]);
        $this->startxref = $offset;
        $visited = [];
        $first = true;
        while ($first || $offset > 0) {
            if (isset($visited[$offset]) || $offset >= $this->len || count($visited) > 512) {
                if ($first) {
                    throw new PdfException('ตำแหน่ง xref ไม่ถูกต้อง');
                }
                break;
            }
            $visited[$offset] = true;
            $this->parser->pos = $offset;
            $this->parser->skipWs();
            if ($this->parser->startsWith('xref')) {
                $trailer = $this->readXrefTable();
                $isStream = false;
                $stm = $trailer->get('XRefStm');
                if (is_int($stm)) {
                    $this->readXrefStream($stm);
                }
            } else {
                $trailer = $this->readXrefStream($offset);
                $isStream = true;
            }
            if ($first) {
                $this->trailer = $trailer;
                $this->xrefIsStream = $isStream;
                $first = false;
            }
            $prev = $trailer->get('Prev');
            $offset = is_int($prev) ? $prev : (is_float($prev) ? (int) $prev : 0);
        }
        $root = $this->trailer?->get('Root');
        if (!$root instanceof PdfRef || $this->entryLooksWrong($root->num)) {
            throw new PdfException('ตำแหน่งวัตถุใน xref ไม่ถูกต้อง');
        }
    }

    private function entryLooksWrong(int $num): bool
    {
        $e = $this->xref[$num] ?? null;
        if ($e === null || $e['t'] === 0) {
            return true;
        }
        if ($e['t'] === 2) {
            return false;
        }

        return preg_match('/\G\s*' . $num . '\s+\d+\s+obj/', $this->data, $m, 0, $e['off']) !== 1;
    }

    private function readXrefTable(): PdfDict
    {
        $p = $this->parser;
        $p->pos += 4;
        while (true) {
            $p->skipWs();
            if ($p->startsWith('trailer')) {
                $p->pos += 7;
                $trailer = $p->parseValue();

                return $trailer instanceof PdfDict ? $trailer : throw new PdfException('trailer ไม่ถูกต้อง');
            }
            if (!preg_match('/\G(\d+)[ \t]+(\d+)/', $this->data, $m, 0, $p->pos)) {
                throw new PdfException('ตาราง xref ไม่ถูกต้อง');
            }
            $p->pos += strlen($m[0]);
            $start = (int) $m[1];
            $count = (int) $m[2];
            for ($i = 0; $i < $count; $i++) {
                $p->skipWs();
                if (!preg_match('/\G(\d{1,10})[ \t]+(\d{1,5})[ \t]+([nf])/', $this->data, $e, 0, $p->pos)) {
                    throw new PdfException('รายการ xref ไม่ถูกต้อง');
                }
                $p->pos += strlen($e[0]);
                $num = $start + $i;
                if (!isset($this->xref[$num])) {
                    $this->xref[$num] = $e[3] === 'n' ? ['t' => 1, 'off' => (int) $e[1], 'gen' => (int) $e[2]] : ['t' => 0];
                }
            }
        }
    }

    private function readXrefStream(int $offset): PdfDict
    {
        $stream = $this->parseIndirectAt($offset, null);
        if (!$stream instanceof PdfStream || $stream->dict->name('Type') !== 'XRef') {
            throw new PdfException('xref stream ไม่ถูกต้อง');
        }
        $dict = $stream->dict;
        $w = $this->resolve($dict->get('W'));
        if (!is_array($w) || count($w) < 3) {
            throw new PdfException('xref stream /W ไม่ถูกต้อง');
        }
        $w = array_map('intval', array_slice($w, 0, 3));
        $size = (int) $this->resolve($dict->get('Size'));
        $index = $this->resolve($dict->get('Index'));
        $index = is_array($index) && $index !== [] ? array_map('intval', $index) : [0, $size];
        $data = $this->decodeStream($stream);
        $rowLen = array_sum($w);
        if ($rowLen <= 0) {
            throw new PdfException('xref stream /W ไม่ถูกต้อง');
        }
        $total = strlen($data);
        $p = 0;
        $read = static function (int $n) use (&$p, $data): int {
            $v = 0;
            for ($i = 0; $i < $n; $i++) {
                $v = ($v << 8) | ord($data[$p + $i]);
            }
            $p += $n;

            return $v;
        };
        for ($i = 0; $i + 1 < count($index); $i += 2) {
            for ($j = 0; $j < $index[$i + 1]; $j++) {
                if ($p + $rowLen > $total) {
                    break 2;
                }
                $type = $w[0] === 0 ? 1 : $read($w[0]);
                $f2 = $read($w[1]);
                $f3 = $read($w[2]);
                $num = $index[$i] + $j;
                if (isset($this->xref[$num])) {
                    continue;
                }
                $this->xref[$num] = match ($type) {
                    1 => ['t' => 1, 'off' => $f2, 'gen' => $f3],
                    2 => ['t' => 2, 'stm' => $f2, 'idx' => $f3],
                    default => ['t' => 0],
                };
            }
        }

        return $dict;
    }

    /** สร้าง xref ใหม่จากการสแกน "n g obj" ทั้งไฟล์ (ไฟล์ที่ตาราง xref เสีย) */
    private function recover(): void
    {
        $this->recovered = true;
        $this->xrefIsStream = false;
        $this->xref = [];
        $this->cache = [];
        $this->objStreams = [];
        $this->pages = null;
        $this->startxref = 0;
        if (preg_match_all('/(?<![0-9])(\d{1,10})[ \t\r\n]+(\d{1,5})[ \t\r\n]+obj\b/', $this->data, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => [, $off]) {
                // วัตถุที่ปรากฏทีหลังแทนของเดิม (ตามหลัก incremental update)
                $this->xref[(int) $m[1][$i][0]] = ['t' => 1, 'off' => $off, 'gen' => (int) $m[2][$i][0]];
            }
        }
        foreach (array_keys($this->xref) as $num) {
            try {
                $obj = $this->object($num);
                if ($obj instanceof PdfStream && $obj->dict->name('Type') === 'ObjStm') {
                    [, $offsets] = $this->loadObjectStream($num);
                    foreach (array_keys($offsets) as $idx => $inner) {
                        $this->xref[$inner] ??= ['t' => 2, 'stm' => $num, 'idx' => $idx];
                    }
                }
            } catch (PdfException) {
                // ข้ามวัตถุที่เสีย
            }
        }
        $trailer = null;
        if (preg_match_all('/trailer\s*<</', $this->data, $t, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($t[0]) as [, $off]) {
                $this->parser->pos = $off + 7;
                try {
                    $value = $this->parser->parseValue();
                } catch (PdfException) {
                    continue;
                }
                if ($value instanceof PdfDict && $value->get('Root') instanceof PdfRef && isset($this->xref[$value->get('Root')->num])) {
                    $trailer = $value;
                    break;
                }
            }
        }
        if ($trailer === null) {
            foreach (array_keys($this->xref) as $num) {
                try {
                    $obj = $this->object($num);
                } catch (PdfException) {
                    continue;
                }
                if ($obj instanceof PdfStream && $obj->dict->name('Type') === 'XRef' && $obj->dict->get('Root') instanceof PdfRef) {
                    $trailer = $obj->dict;
                }
                if ($obj instanceof PdfDict && $obj->name('Type') === 'Catalog') {
                    $trailer ??= new PdfDict(['Root' => new PdfRef($num, $this->generation($num))]);
                }
            }
        }
        if ($trailer === null) {
            throw new PdfException('ไฟล์ PDF เสียหาย อ่านโครงสร้างเอกสารไม่ได้');
        }
        $trailer = new PdfDict($trailer->items);
        foreach (['Prev', 'XRefStm', 'Type', 'W', 'Index', 'Length', 'Filter', 'DecodeParms'] as $k) {
            unset($trailer->items[$k]);
        }
        $trailer->set('Size', $this->xref === [] ? 1 : max(array_keys($this->xref)) + 1);
        $this->trailer = $trailer;
    }

    // ------------------------------------------------------------ object stream

    /** @return array{0: string, 1: array<int, int>, 2: int} [ข้อมูล, เลขวัตถุ => ตำแหน่ง, First] */
    private function loadObjectStream(int $num): array
    {
        if (isset($this->objStreams[$num])) {
            return $this->objStreams[$num];
        }
        $stream = $this->object($num);
        if (!$stream instanceof PdfStream) {
            throw new PdfException('object stream ไม่ถูกต้อง');
        }
        $data = $this->decodeStream($stream);
        $n = (int) $this->resolve($stream->dict->get('N'));
        $first = (int) $this->resolve($stream->dict->get('First'));
        if (!preg_match_all('/(\d+)\s+(\d+)/', substr($data, 0, $first), $m) || count($m[1]) < $n) {
            throw new PdfException('ส่วนหัว object stream ไม่ถูกต้อง');
        }
        $offsets = [];
        for ($i = 0; $i < $n; $i++) {
            $offsets[(int) $m[1][$i]] = (int) $m[2][$i];
        }

        return $this->objStreams[$num] = [$data, $offsets, $first];
    }

    private function fromObjectStream(int $stm, int $num): mixed
    {
        [$data, $offsets, $first] = $this->loadObjectStream($stm);
        if (!isset($offsets[$num])) {
            return null;
        }
        $sub = new PdfParser($data);
        $sub->pos = $first + $offsets[$num];

        return $sub->parseValue();
    }

    // ------------------------------------------------------------ แยกวิเคราะห์

    private function parseIndirectAt(int $offset, ?int $expect): mixed
    {
        if ($offset < 0 || $offset >= $this->len) {
            return null;
        }
        $p = $this->parser;
        $p->pos = $offset;
        $p->skipWs();
        if (!preg_match('/\G(\d+)\s+(\d+)\s+obj\b/', $this->data, $m, 0, $p->pos)) {
            throw new PdfException('ไม่พบวัตถุที่ตำแหน่ง ' . $offset);
        }
        if ($expect !== null && (int) $m[1] !== $expect) {
            throw new PdfException('เลขวัตถุไม่ตรงกับ xref');
        }
        $p->pos += strlen($m[0]);
        $value = $p->parseValue();
        $p->skipWs();
        if (!$value instanceof PdfDict || !$p->startsWith('stream')) {
            return $value;
        }
        $p->pos += 6;
        if (($this->data[$p->pos] ?? '') === "\r") {
            $p->pos++;
        }
        if (($this->data[$p->pos] ?? '') === "\n") {
            $p->pos++;
        }
        $start = $p->pos;
        $length = $value->get('Length');
        if ($length instanceof PdfRef) {
            $length = $this->resolve($length);
        }
        $length = is_int($length) ? $length : -1;
        if ($length < 0 || $start + $length > $this->len || !preg_match('/\G\s*endstream/', $this->data, $mm, 0, $start + $length)) {
            $end = strpos($this->data, 'endstream', $start);
            if ($end === false) {
                throw new PdfException('ไม่พบ endstream');
            }
            $length = $end - $start;
            if ($length > 0 && $this->data[$start + $length - 1] === "\n") {
                $length--;
            }
            if ($length > 0 && $this->data[$start + $length - 1] === "\r") {
                $length--;
            }
        }

        return new PdfStream($value, substr($this->data, $start, $length));
    }

    // ------------------------------------------------------------ ตัวกรอง

    private static function inflate(string $data): string
    {
        $out = @gzuncompress($data);
        if ($out === false) {
            $out = @gzinflate(substr($data, 2));
        }
        if ($out === false) {
            // ข้อมูลถูกตัดท้ายหรือ checksum ผิด: ถอดเท่าที่ได้
            $ctx = inflate_init(ZLIB_ENCODING_DEFLATE);
            $out = $ctx !== false ? @inflate_add($ctx, substr($data, 2), ZLIB_SYNC_FLUSH) : false;
        }
        if ($out === false || $out === '') {
            throw new PdfException('ถอดการบีบอัด FlateDecode ไม่สำเร็จ');
        }

        return $out;
    }

    private static function asciiHex(string $data): string
    {
        $end = strpos($data, '>');
        $hex = (string) preg_replace('/[^0-9A-Fa-f]/', '', $end === false ? $data : substr($data, 0, $end));
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        return (string) hex2bin($hex);
    }

    private function predict(string $data, ?PdfDict $params): string
    {
        $predictor = $params !== null ? (int) $this->resolve($params->get('Predictor')) : 1;
        if ($predictor < 10) {
            if ($predictor === 2) {
                throw new PdfException('ไม่รองรับ TIFF predictor');
            }

            return $data;
        }
        $colors = max(1, (int) ($this->resolve($params->get('Colors')) ?? 1));
        $bpc = max(1, (int) ($this->resolve($params->get('BitsPerComponent')) ?? 8));
        $columns = max(1, (int) ($this->resolve($params->get('Columns')) ?? 1));
        $bpp = max(1, intdiv($colors * $bpc + 7, 8));
        $rowLen = intdiv($columns * $colors * $bpc + 7, 8);
        $out = '';
        $prev = str_repeat("\0", $rowLen);
        $len = strlen($data);
        for ($p = 0; $p < $len; $p += $rowLen + 1) {
            $type = ord($data[$p]);
            $row = str_pad(substr($data, $p + 1, $rowLen), $rowLen, "\0");
            if ($type === 0) {
                $cur = $row;
            } elseif ($type === 2) {
                $cur = '';
                for ($i = 0; $i < $rowLen; $i++) {
                    $cur .= chr((ord($row[$i]) + ord($prev[$i])) & 0xFF);
                }
            } else {
                $cur = '';
                for ($i = 0; $i < $rowLen; $i++) {
                    $x = ord($row[$i]);
                    $a = $i >= $bpp ? ord($cur[$i - $bpp]) : 0;
                    $b = ord($prev[$i]);
                    $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;
                    $v = match ($type) {
                        1 => $x + $a,
                        3 => $x + intdiv($a + $b, 2),
                        4 => $x + self::paeth($a, $b, $c),
                        default => throw new PdfException('PNG predictor ไม่ถูกต้อง'),
                    };
                    $cur .= chr($v & 0xFF);
                }
            }
            $out .= $cur;
            $prev = $cur;
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }
}
