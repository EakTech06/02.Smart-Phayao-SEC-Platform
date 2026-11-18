<?php

declare(strict_types=1);

namespace Core;

/**
 * สร้างไฟล์ Excel (.xlsx) แบบ Native PHP ไม่ต้องใช้ Composer หรือ ext-zip (โฮสต์ Shared Hosting)
 *
 * - ZIP แบบ stored (ไม่บีบอัด) เขียนเองตามสเปก PKWARE ใช้แค่ crc32()
 * - ข้อความเขียนเป็น inlineStr เสมอ: Excel ไม่ตีความเป็นสูตร (กัน Formula Injection)
 * - แถวแรกของแต่ละแผ่นที่มีหลายคอลัมน์ หรือแถวที่ต่อจากแถวว่าง ถือเป็นหัวตาราง (ตัวหนา + พื้นหลัง)
 */
final class XlsxWriter
{
    /** @var array<string, list<list<scalar|null>>> */
    private array $sheets = [];

    /** @param list<list<scalar|null>> $rows */
    public function addSheet(string $name, array $rows): self
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name) ?? '') ?: 'Sheet' . (count($this->sheets) + 1);
        $name = mb_substr($name, 0, 31);
        $base = $name;
        for ($i = 2; isset($this->sheets[$name]); $i++) {
            $name = mb_substr($base, 0, 28) . ' ' . $i;
        }
        $this->sheets[$name] = array_values($rows);

        return $this;
    }

    public function build(): string
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1', []);
        }
        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(),
            'xl/styles.xml' => self::styles(),
        ];
        $i = 1;
        foreach ($this->sheets as $rows) {
            $files['xl/worksheets/sheet' . $i++ . '.xml'] = self::sheet($rows);
        }

        return self::zip($files);
    }

    // ---------------------------------------------------------------- OOXML parts

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";

    private function contentTypes(): string
    {
        $xml = self::XML . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1, $n = count($this->sheets); $i <= $n; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml . '</Types>';
    }

    private function workbook(): string
    {
        $xml = self::XML . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $i = 1;
        foreach (array_keys($this->sheets) as $name) {
            $xml .= '<sheet name="' . self::esc((string) $name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $i++;
        }

        return $xml . '</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $xml = self::XML . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $n = count($this->sheets);
        for ($i = 1; $i <= $n; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return $xml . '</Relationships>';
    }

    /** style 0 = ปกติ, 1 = หัวตาราง (ตัวหนา พื้นเทาอ่อน มีเส้นขอบ), 2 = ข้อมูลมีเส้นขอบ */
    private static function styles(): string
    {
        return self::XML . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><name val="Tahoma"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE9EEF6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFBFC5CE"/></left><right style="thin"><color rgb="FFBFC5CE"/></right>'
            . '<top style="thin"><color rgb="FFBFC5CE"/></top><bottom style="thin"><color rgb="FFBFC5CE"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/></cellXfs>'
            . '</styleSheet>';
    }

    /** @param list<list<scalar|null>> $rows */
    private static function sheet(array $rows): string
    {
        // ความกว้างคอลัมน์ประมาณจากความยาวข้อความ (ภาษาไทยมีสระบน/ล่างจึงนับตามจำนวนอักขระก็พอ)
        $widths = [];
        foreach ($rows as $row) {
            foreach (array_values($row) as $c => $value) {
                $len = mb_strlen((string) $value);
                $widths[$c] = max($widths[$c] ?? 8, min(60, $len + 2));
            }
        }
        $xml = self::XML . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($widths !== []) {
            $xml .= '<cols>';
            foreach ($widths as $c => $w) {
                $xml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        $previousEmpty = true;
        foreach ($rows as $r => $row) {
            $row = array_values($row);
            if ($row === []) {
                $previousEmpty = true;
                continue;
            }
            $isHeader = $previousEmpty && count($row) > 1;
            $style = $isHeader ? 1 : (count($row) > 1 ? 2 : 0);
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach ($row as $c => $value) {
                $ref = self::column($c) . ($r + 1);
                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"/>';
                } elseif ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
                } else {
                    $text = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (string) $value;
                    $xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc($text) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
            $previousEmpty = false;
        }

        return $xml . '</sheetData></worksheet>';
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }

    private static function esc(string $text): string
    {
        // ตัดอักขระควบคุมที่ XML 1.0 ไม่อนุญาต (ทำให้ Excel แจ้งว่าไฟล์เสีย)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ---------------------------------------------------------------- ZIP (stored)

    /** @param array<string, string> $files */
    private static function zip(array $files): string
    {
        $time = (int) date('G') << 11 | (int) date('i') << 5 | intdiv((int) date('s'), 2);
        $date = ((int) date('Y') - 1980) << 9 | (int) date('n') << 5 | (int) date('j');
        $data = '';
        $central = '';
        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $offset = strlen($data);
            // general purpose flag 0x0800 = ชื่อไฟล์เป็น UTF-8
            $header = pack('vvvvvVVVvv', 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0);
            $data .= "PK\x03\x04" . $header . $name . $content;
            $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }

        return $data . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($central), strlen($data), 0);
    }
}
