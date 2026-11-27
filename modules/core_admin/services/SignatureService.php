<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Database;
use Core\Exceptions\ValidationException;

/**
 * ภาพลายเซ็นของผู้ใช้ (วาดบนจอหรืออัปโหลดภาพ) เก็บใน storage/signatures นอก public
 * ระบบอ่านเพื่อประทับลงเอกสาร และให้เจ้าของดูตัวอย่างของตนเองเท่านั้น ผู้ใช้อื่นดาวน์โหลดแยกไม่ได้
 */
final class SignatureService
{
    public const MAX_UPLOAD = 2 * 1024 * 1024;
    private const MAX_W = 900;
    private const MAX_H = 360;

    public static function baseDir(): string
    {
        return BASE_PATH . '/storage/signatures';
    }

    public static function find(int $userId): ?array
    {
        return Database::instance()->fetch('SELECT * FROM user_signatures WHERE user_id = ?', [$userId]);
    }

    public static function has(int $userId): bool
    {
        return self::find($userId) !== null;
    }

    /** ภาพจากการวาด: data URL ของ PNG จาก canvas */
    public static function saveDrawing(int $userId, string $dataUrl): void
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new ValidationException(['signature' => ['ไม่พบลายเซ็นที่วาด กรุณาวาดลายเซ็นก่อนบันทึก']]);
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || strlen($bytes) > self::MAX_UPLOAD) {
            throw new ValidationException(['signature' => ['ข้อมูลลายเซ็นไม่ถูกต้อง']]);
        }
        self::save($userId, $bytes, 'draw');
    }

    /** ภาพจากไฟล์ที่อัปโหลด (PNG/JPG/WEBP) พื้นหลังสีขาวจะถูกทำให้โปร่งใส */
    public static function saveUpload(int $userId, array $file): void
    {
        $fail = static fn (string $msg): ValidationException => new ValidationException(['signature_file' => [$msg]]);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > self::MAX_UPLOAD) {
            throw $fail('ไฟล์ภาพต้องมีขนาดไม่เกิน 2 MB');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            throw $fail('กรุณาเลือกไฟล์ภาพลายเซ็น');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw $fail('รองรับเฉพาะไฟล์ภาพ PNG, JPG หรือ WEBP');
        }
        self::save($userId, (string) file_get_contents($tmp), 'upload');
    }

    public static function delete(int $userId): void
    {
        $row = self::find($userId);
        if ($row === null) {
            return;
        }
        Database::instance()->delete('user_signatures', ['user_id' => $userId]);
        @unlink(self::baseDir() . '/' . $row['storage_path']);
        Audit::log('profile.signature_deleted', 'core', 'user_signatures', $userId);
    }

    /** ไฟล์ PNG ของลายเซ็น (ตรวจ checksum) */
    public static function png(int $userId): ?string
    {
        $row = self::find($userId);
        if ($row === null || preg_match('/^u\d+_[a-f0-9]{16}\.png$/', (string) $row['storage_path']) !== 1) {
            return null;
        }
        $path = self::baseDir() . '/' . $row['storage_path'];
        $bytes = is_file($path) ? (string) file_get_contents($path) : '';
        if ($bytes === '' || !hash_equals((string) $row['checksum_sha256'], hash('sha256', $bytes))) {
            return null;
        }

        return $bytes;
    }

    /** ภาพ GD สำหรับประทับลงเอกสาร */
    public static function image(int $userId): ?\GdImage
    {
        $png = self::png($userId);
        $img = $png !== null ? @imagecreatefromstring($png) : false;
        if ($img === false) {
            return null;
        }
        imagesavealpha($img, true);

        return $img;
    }

    // ---------------------------------------------------------------- ภายใน

    private static function save(int $userId, string $bytes, string $source): void
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 20 || $info[1] < 10 || $info[0] > 6000 || $info[1] > 6000) {
            throw new ValidationException([$source === 'draw' ? 'signature' : 'signature_file' => ['ไฟล์ภาพไม่ถูกต้อง หรือขนาดภาพไม่เหมาะสม']]);
        }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new ValidationException([$source === 'draw' ? 'signature' : 'signature_file' => ['อ่านไฟล์ภาพไม่ได้']]);
        }
        $img = self::normalize($src, $source === 'upload');
        if ($img === null) {
            throw new ValidationException([$source === 'draw' ? 'signature' : 'signature_file' => ['ไม่พบเส้นลายเซ็นในภาพ']]);
        }
        ob_start();
        imagepng($img, null, 9);
        $png = (string) ob_get_clean();

        $dir = self::baseDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('สร้างโฟลเดอร์เก็บลายเซ็นไม่สำเร็จ');
        }
        $name = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.png';
        if (file_put_contents($dir . '/' . $name, $png) === false) {
            throw new \RuntimeException('บันทึกลายเซ็นไม่สำเร็จ');
        }
        $old = self::find($userId);
        Database::instance()->query(
            'INSERT INTO user_signatures (user_id, storage_path, checksum_sha256, width, height, source) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE storage_path = VALUES(storage_path), checksum_sha256 = VALUES(checksum_sha256),
             width = VALUES(width), height = VALUES(height), source = VALUES(source), updated_at = NOW()',
            [$userId, $name, hash('sha256', $png), imagesx($img), imagesy($img), $source]
        );
        if ($old !== null && $old['storage_path'] !== $name) {
            @unlink($dir . '/' . $old['storage_path']);
        }
        Audit::log('profile.signature_saved', 'core', 'user_signatures', $userId, null, ['source' => $source]);
    }

    /** ทำพื้นสว่างให้โปร่งใส (ภาพอัปโหลด) ตัดขอบว่าง และย่อให้ไม่เกินขนาดที่กำหนด */
    private static function normalize(\GdImage $src, bool $removeBackground): ?\GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        // ย่อก่อนประมวลผลรายพิกเซลถ้าภาพใหญ่มาก
        $pre = min(1.0, 1600 / max($w, 1), 1000 / max($h, 1));
        if ($pre < 1.0) {
            $src = self::resize($src, max(1, (int) round($w * $pre)), max(1, (int) round($h * $pre)));
            $w = imagesx($src);
            $h = imagesy($src);
        }
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        $truecolor = imageistruecolor($src);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($src, $x, $y);
                if (!$truecolor) {
                    $ci = imagecolorsforindex($src, $c);
                    $c = ($ci['alpha'] << 24) | ($ci['red'] << 16) | ($ci['green'] << 8) | $ci['blue'];
                }
                $a = ($c >> 24) & 0x7F;
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                if ($removeBackground) {
                    $lum = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                    if ($lum >= 215) {
                        continue;
                    }
                    // ขอบเส้นนุ่มขึ้น: ยิ่งสว่างยิ่งโปร่งใส
                    $a = max($a, (int) round(max(0, $lum - 120) * 127 / 95));
                }
                if ($a >= 120) {
                    continue;
                }
                imagesetpixel($img, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0 || ($maxX - $minX) < 4 || ($maxY - $minY) < 2) {
            return null;
        }
        $pad = 4;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $cw = min($w, $maxX + $pad + 1) - $minX;
        $ch = min($h, $maxY + $pad + 1) - $minY;
        $crop = imagecreatetruecolor($cw, $ch);
        imagealphablending($crop, false);
        imagesavealpha($crop, true);
        imagecopy($crop, $img, 0, 0, $minX, $minY, $cw, $ch);
        $scale = min(1.0, self::MAX_W / $cw, self::MAX_H / $ch);

        return $scale < 1.0 ? self::resize($crop, max(1, (int) round($cw * $scale)), max(1, (int) round($ch * $scale))) : $crop;
    }

    private static function resize(\GdImage $src, int $w, int $h): \GdImage
    {
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

        return $dst;
    }
}
