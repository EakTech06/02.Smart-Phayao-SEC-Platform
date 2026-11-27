<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;

/**
 * ไฟล์งานวิจัย/สื่อนวัตกรรม เก็บในเครื่องที่ storage/research (นอก public เข้าถึงผ่าน PHP ที่ตรวจสิทธิ์เท่านั้น)
 * ไม่ลบไฟล์จริง ทุกเวอร์ชันอยู่ครบ ตรวจ checksum ทุกครั้งที่อ่าน
 */
final class ResearchStorage
{
    /** นามสกุล => [MIME ที่บันทึก, MIME ที่ finfo อาจตรวจได้] */
    public const TYPES = [
        'pdf' => ['application/pdf', ['application/pdf']],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
    ];

    public static function baseDir(): string
    {
        return BASE_PATH . '/storage/research';
    }

    /**
     * ตรวจไฟล์ที่อัปโหลดและย้ายเข้าที่เก็บ
     * @param list<string> $allowed นามสกุลที่รับ
     * @return array{original_name: string, mime_type: string, size_bytes: int, storage_path: string, checksum_sha256: string}
     */
    public static function store(array $file, int $workId, int $versionNo, array $allowed = ['pdf', 'docx'], string $field = 'file'): array
    {
        $fail = static fn (string $m): ValidationException => new ValidationException([$field => [$m]]);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $maxMb = (int) (ResearchLookup::MAX_BYTES / 1024 / 1024);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw $fail("ไฟล์ต้องมีขนาดไม่เกิน {$maxMb} MB");
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            throw $fail('กรุณาเลือกไฟล์');
        }
        $size = (int) filesize($tmp);
        if ($size === 0) {
            throw $fail('ไฟล์ว่างเปล่า');
        }
        if ($size > ResearchLookup::MAX_BYTES) {
            throw $fail("ไฟล์ต้องมีขนาดไม่เกิน {$maxMb} MB");
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true) || !isset(self::TYPES[$ext])) {
            throw $fail('รองรับเฉพาะไฟล์ ' . strtoupper(implode(', ', $allowed)));
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, self::TYPES[$ext][1], true)) {
            throw $fail("ชนิดไฟล์จริง ({$mime}) ไม่ตรงกับนามสกุล .{$ext}");
        }
        $content = (string) file_get_contents($tmp);
        if ($ext === 'pdf' && !str_starts_with(ltrim(substr($content, 0, 1024)), '%PDF-')) {
            throw $fail('ไฟล์ PDF ไม่ถูกต้อง');
        }
        if ($ext === 'docx' && (!str_starts_with($content, "PK\x03\x04") || !str_contains($content, '[Content_Types].xml') || !str_contains($content, 'word/'))) {
            throw $fail('ไฟล์ .docx ไม่ใช่เอกสาร Word ที่ถูกต้อง');
        }

        $dir = self::baseDir() . '/' . $workId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('สร้างโฟลเดอร์เก็บไฟล์ไม่สำเร็จ');
        }
        $relative = $workId . '/v' . $versionNo . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, self::baseDir() . '/' . $relative)) {
            throw new \RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
        }

        return [
            'original_name' => self::cleanName((string) $file['name'], $ext),
            'mime_type' => self::TYPES[$ext][0],
            'size_bytes' => $size,
            'storage_path' => $relative,
            'checksum_sha256' => hash('sha256', $content),
        ];
    }

    /** อ่านไฟล์ของเวอร์ชัน (ตรวจ path และ checksum) */
    public static function read(array $version): string
    {
        $relative = (string) $version['storage_path'];
        if (preg_match('#^\d+/[A-Za-z0-9_]+\.(pdf|docx)$#', $relative) !== 1) {
            throw new HttpException(404);
        }
        $path = self::baseDir() . '/' . $relative;
        if (!is_file($path)) {
            throw new HttpException(404, 'ไม่พบไฟล์ในที่เก็บ');
        }
        $content = (string) file_get_contents($path);
        if (!hash_equals((string) $version['checksum_sha256'], hash('sha256', $content))) {
            throw new HttpException(500, 'ไฟล์ไม่ตรงกับที่บันทึกไว้ (checksum) โปรดแจ้งผู้ดูแลระบบ');
        }

        return $content;
    }

    public static function cleanName(string $name, string $ext): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $base);
        $base = trim(mb_substr($base, 0, 150), " ._");

        return ($base !== '' ? $base : 'file') . '.' . $ext;
    }
}
