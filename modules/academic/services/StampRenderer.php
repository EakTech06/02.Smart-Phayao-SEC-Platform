<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Exceptions\ValidationException;

/**
 * สร้างภาพที่จะประทับลง PDF ด้วย GD: กรอบลายเซ็น (ภาพลายเซ็น + ชื่อ + ตำแหน่ง + วันเวลา) และท้ายเอกสาร (เลขที่เอกสาร)
 * ข้อความภาษาไทยใช้ฟอนต์ TrueType: resources/fonts/*.ttf ก่อน แล้วจึงฟอนต์ไทยของระบบปฏิบัติการ
 */
final class StampRenderer
{
    private const SYSTEM_FONTS = [
        'C:/Windows/Fonts/tahoma.ttf',
        'C:/Windows/Fonts/leelawad.ttf',
        '/usr/share/fonts/truetype/tlwg/Loma.ttf',
        '/usr/share/fonts/truetype/tlwg/Garuda.ttf',
        '/usr/share/fonts/truetype/tlwg/Norasi.ttf',
        '/usr/share/fonts/truetype/thai/Garuda.ttf',
        '/usr/share/fonts/thai-tlwg/Loma.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
        '/usr/share/fonts/opentype/noto/NotoSansThai-Regular.ttf',
        '/usr/share/fonts/google-noto/NotoSansThai-Regular.ttf',
        '/System/Library/Fonts/Supplemental/Thonburi.ttf',
    ];

    /** ความละเอียดภาพ (พิกเซลต่อ pt) */
    private const SCALE = 4;
    /** ขนาดตัวอักษรของ GD เป็น pt ที่ 96 dpi: พิกเซล x 0.75 */
    private const PX = 0.75;

    public static function fontPath(): string
    {
        $configured = (string) config('research.signing_font', '');
        $candidates = array_merge(
            $configured !== '' ? [$configured] : [],
            glob(BASE_PATH . '/resources/fonts/*.ttf') ?: [],
            self::SYSTEM_FONTS
        );
        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        throw new ValidationException(['status' => ['ไม่พบฟอนต์ภาษาไทยสำหรับประทับลายเซ็น กรุณาให้ผู้ดูแลระบบวางไฟล์ฟอนต์ .ttf ไว้ที่ resources/fonts']]);
    }

    /** กรอบลายเซ็นขนาด $wPt x $hPt (pt) */
    public static function signature(\GdImage $signature, string $name, string $position, string $when, float $wPt, float $hPt): \GdImage
    {
        $font = self::fontPath();
        $s = min(self::SCALE, 1600 / max($wPt, 1), 900 / max($hPt, 1));
        $w = max(20, (int) round($wPt * $s));
        $h = max(10, (int) round($hPt * $s));
        $img = self::canvas($w, $h);

        $lines = [
            ['(' . $name . ')', [20, 20, 20]],
            [$position, [20, 20, 20]],
            [$when, [70, 70, 70]],
        ];
        $fontPt = max(4.5, min(9.0, $hPt * 0.13, $wPt * 0.06));
        $lineH = $fontPt * 1.42;
        $textH = $lineH * count($lines);
        // พื้นที่ภาพลายเซ็นอย่างน้อย 35% ของกรอบ
        if ($textH > $hPt * 0.65) {
            $fontPt *= ($hPt * 0.65) / $textH;
            $lineH = $fontPt * 1.42;
            $textH = $lineH * count($lines);
        }
        $sigH = (int) floor(($hPt - $textH) * $s);

        // ภาพลายเซ็น: ย่อให้พอดีพื้นที่ คงสัดส่วน จัดกึ่งกลาง ชิดล่างของพื้นที่
        $sw = imagesx($signature);
        $sh = imagesy($signature);
        $fit = min(($w * 0.9) / $sw, ($sigH * 0.95) / $sh);
        $dw = max(1, (int) round($sw * $fit));
        $dh = max(1, (int) round($sh * $fit));
        imagecopyresampled($img, $signature, (int) (($w - $dw) / 2), max(0, $sigH - $dh), 0, 0, $dw, $dh, $sw, $sh);

        $y = $sigH;
        foreach ($lines as [$text, $rgb]) {
            $y += (int) round($lineH * $s);
            self::centered($img, $font, $text, $fontPt * $s * self::PX, $y - (int) round($lineH * $s * 0.3), $w, $rgb);
        }

        return $img;
    }

    /** ท้ายเอกสาร: เลขที่เอกสาร + ข้อความตรวจสอบ (ข้างกรอบ QR Code) */
    public static function footer(array $lines, float $wPt, float $hPt): \GdImage
    {
        $font = self::fontPath();
        $s = self::SCALE;
        $w = (int) round($wPt * $s);
        $h = (int) round($hPt * $s);
        $img = self::canvas($w, $h, [255, 255, 255, 0]);
        $fontPt = min(7.5, $hPt / (count($lines) * 1.5));
        $lineH = $hPt / count($lines);
        foreach ($lines as $i => $text) {
            $size = $fontPt * $s * self::PX * ($i === 0 ? 1.12 : 1.0);
            $size = self::fit($font, $text, $size, $w * 0.98);
            $box = imagettfbbox($size, 0, $font, $text);
            $x = $w - ($box[2] - $box[0]) - (int) ($w * 0.01);
            $y = (int) round(($i + 0.72) * $lineH * $s);
            imagettftext($img, $size, 0, $x, $y, $i === 0 ? imagecolorallocate($img, 0, 0, 0) : imagecolorallocate($img, 60, 60, 60), $font, $text);
        }

        return $img;
    }

    private static function canvas(int $w, int $h, array $bg = [255, 255, 255, 127]): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, $bg[0], $bg[1], $bg[2], $bg[3]));
        imagealphablending($img, true);

        return $img;
    }

    private static function centered(\GdImage $img, string $font, string $text, float $size, int $baseline, int $width, array $rgb): void
    {
        if (trim($text) === '') {
            return;
        }
        $size = self::fit($font, $text, $size, $width * 0.97);
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = $box[2] - $box[0];
        imagettftext($img, $size, 0, (int) (($width - $tw) / 2) - $box[0], $baseline, imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]), $font, $text);
    }

    /** ลดขนาดตัวอักษรให้ข้อความกว้างไม่เกิน $maxWidth */
    private static function fit(string $font, string $text, float $size, float $maxWidth): float
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = $box[2] - $box[0];

        return $tw > $maxWidth && $tw > 0 ? $size * $maxWidth / $tw : $size;
    }
}
