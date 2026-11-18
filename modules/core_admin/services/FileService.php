<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\DriveUploader;
use Core\Exceptions\ValidationException;
use Core\GoogleWorkspaceException;
use Core\Rbac;

/**
 * ไฟล์กลางของระบบ: ตรวจไฟล์ใน PHP แล้วส่งต่อไป Google Shared Drive ผ่าน Apps Script
 * ฐานข้อมูลเก็บเฉพาะ metadata (drive_file_id, ชื่อเดิม, MIME, ขนาด, module, ผู้อัปโหลด)
 * ดาวน์โหลดผ่าน PHP proxy ที่ตรวจสิทธิ์ก่อนทุกครั้ง
 */
final class FileService
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** นามสกุลที่อนุญาต → MIME มาตรฐานที่บันทึก + MIME ที่ finfo อาจตรวจได้ */
    private const TYPES = [
        'pdf' => ['application/pdf', ['application/pdf']],
        'jpg' => ['image/jpeg', ['image/jpeg']],
        'jpeg' => ['image/jpeg', ['image/jpeg']],
        'png' => ['image/png', ['image/png']],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream']],
    ];

    public const VISIBILITIES = [
        'private' => 'เฉพาะฉัน',
        'department' => 'ฝ่าย/งานของฉัน',
        'public' => 'ทุกคนในองค์กร',
    ];

    public const ACCEPT = '.pdf,.jpg,.jpeg,.png,.docx,.xlsx';

    /**
     * @param array $file รายการจาก $_FILES
     * @param array{module?: string, ref_type?: ?string, ref_id?: ?int, visibility?: string, title?: ?string, department_id?: ?int} $meta
     * @return array แถวใน files
     */
    public static function upload(array $file, array $meta = []): array
    {
        [$content, $ext] = self::inspect($file);
        $module = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($meta['module'] ?? 'core'))) ?: 'core';
        $visibility = $meta['visibility'] ?? 'private';
        if (!array_key_exists($visibility, self::VISIBILITIES)) {
            throw new ValidationException(['visibility' => ['ระดับการมองเห็นไม่ถูกต้อง']]);
        }
        $departmentId = $meta['department_id'] ?? self::primaryDepartmentId();
        if ($visibility === 'department' && $departmentId === null) {
            throw new ValidationException(['visibility' => ['บัญชีของคุณยังไม่มีฝ่าย/งานสังกัด เลือกการมองเห็นแบบอื่น']]);
        }

        $name = self::cleanName((string) $file['name'], $ext);
        $mime = self::TYPES[$ext][0];
        $uuid = self::uuid();
        try {
            // ชื่อบน Drive ขึ้นต้นด้วย uuid เพื่อไม่ชนกัน และค้นย้อนกลับได้
            $drive = DriveUploader::upload($content, $uuid . '_' . $name, $mime, $module . '/' . date('Y-m'));
        } catch (GoogleWorkspaceException $e) {
            throw new ValidationException(['file' => ['อัปโหลดไป Google Drive ไม่สำเร็จ: ' . $e->getMessage()]]);
        }

        $id = Database::instance()->insert('files', [
            'uuid' => $uuid,
            'owner_user_id' => Auth::id(),
            'department_id' => $departmentId,
            'module' => $module,
            'ref_type' => $meta['ref_type'] ?? null,
            'ref_id' => $meta['ref_id'] ?? null,
            'original_name' => $name,
            'title' => isset($meta['title']) && trim((string) $meta['title']) !== '' ? mb_substr(trim((string) $meta['title']), 0, 255) : null,
            'mime_type' => $mime,
            'size_bytes' => strlen($content),
            'storage' => 'google_drive',
            'drive_file_id' => $drive['id'],
            'checksum_sha256' => hash('sha256', $content),
            'visibility' => $visibility,
        ]);
        $row = self::find($id);
        Audit::log('file.uploaded', $module, 'files', $id, null, [
            'name' => $name, 'size' => strlen($content), 'visibility' => $visibility, 'ref_type' => $meta['ref_type'] ?? null,
        ]);

        return $row;
    }

    public static function find(int $id): array
    {
        return Database::instance()->fetch('SELECT * FROM files WHERE id = ?', [$id]) ?? [];
    }

    public static function findByUuid(string $uuid): ?array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) !== 1) {
            return null;
        }

        return Database::instance()->fetch(
            'SELECT f.*, CONCAT(COALESCE(e.title_name, \'\'), e.first_name, \' \', e.last_name) AS owner_name, u.username AS owner_username
             FROM files f LEFT JOIN users u ON u.id = f.owner_user_id LEFT JOIN employees e ON e.id = u.employee_id
             WHERE f.uuid = ? AND f.deleted_at IS NULL',
            [$uuid]
        );
    }

    /**
     * สิทธิ์ดูไฟล์: เจ้าของ, ผู้มี core.files.read_all, ไฟล์สาธารณะ, ไฟล์ระดับฝ่ายของฝ่ายที่สังกัด
     * (core.files.read_department ดูไฟล์ฝ่ายตนได้ทุกระดับยกเว้นส่วนตัว) และไฟล์แนบที่ผูกกับเอกสารอื่นใช้กฎของเอกสารนั้น
     */
    public static function canView(array $file, ?int $userId = null): bool
    {
        $userId ??= Auth::id();
        if ($userId === null) {
            return false;
        }
        if ($file['ref_type'] !== null) {
            return self::canViewAttachment($file, $userId);
        }
        if ((int) $file['owner_user_id'] === $userId || Rbac::can('core.files.read_all', $userId)) {
            return true;
        }
        $departmentId = $file['department_id'] !== null ? (int) $file['department_id'] : null;
        $inDepartment = $departmentId !== null && in_array($departmentId, Rbac::departmentIds($userId), true);

        return match ($file['visibility']) {
            'public' => true,
            'department' => $inDepartment,
            default => false,
        };
    }

    public static function canDelete(array $file, ?int $userId = null): bool
    {
        $userId ??= Auth::id();
        if ($userId === null || $file['ref_type'] !== null) {
            return false; // ไฟล์แนบลบผ่านเอกสารต้นทางเท่านั้น
        }

        return (int) $file['owner_user_id'] === $userId || Rbac::can('core.files.delete_all', $userId);
    }

    /** @return array{content: string, mime: string} */
    public static function content(array $file): array
    {
        try {
            $data = DriveUploader::download((string) $file['drive_file_id']);
        } catch (GoogleWorkspaceException $e) {
            throw new \Core\Exceptions\HttpException(502, 'ดึงไฟล์จาก Google Drive ไม่สำเร็จ: ' . $e->getMessage());
        }
        if ($file['checksum_sha256'] !== null && !hash_equals((string) $file['checksum_sha256'], hash('sha256', $data['content']))) {
            throw new \Core\Exceptions\HttpException(502, 'ไฟล์บน Google Drive ไม่ตรงกับที่บันทึกไว้ (checksum)');
        }
        Audit::log('file.downloaded', (string) $file['module'], 'files', (int) $file['id']);

        return ['content' => $data['content'], 'mime' => (string) $file['mime_type']];
    }

    public static function delete(array $file): void
    {
        Database::instance()->update('files', ['deleted_at' => Database::now()], ['id' => (int) $file['id']]);
        try {
            DriveUploader::trash((string) $file['drive_file_id']);
        } catch (GoogleWorkspaceException $e) {
            // ลบในระบบแล้ว (ผู้ใช้เข้าถึงไม่ได้) ไฟล์บน Drive ให้ผู้ดูแลตามลบภายหลังได้จาก log
            error_log('[file trash] ' . $file['drive_file_id'] . ': ' . $e->getMessage());
        }
        Audit::log('file.deleted', (string) $file['module'], 'files', (int) $file['id'], ['name' => $file['original_name']]);
    }

    /** @return list<array> ไฟล์แนบของเอกสาร */
    public static function attachments(string $refType, int $refId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM files WHERE ref_type = ? AND ref_id = ? AND deleted_at IS NULL ORDER BY id',
            [$refType, $refId]
        );
    }

    /** @param array{q?: string, scope?: string} $filters */
    public static function listVisible(array $filters): array
    {
        $userId = (int) Auth::id();
        $params = [];
        $where = ['f.deleted_at IS NULL', 'f.ref_type IS NULL'];
        $scope = $filters['scope'] ?? 'all';
        if ($scope === 'mine') {
            $where[] = 'f.owner_user_id = ?';
            $params[] = $userId;
        } elseif (!Rbac::can('core.files.read_all')) {
            $departmentIds = Rbac::departmentIds();
            $dept = $departmentIds === [] ? '0' : implode(',', array_map('intval', $departmentIds));
            $where[] = "(f.owner_user_id = ? OR f.visibility = 'public' OR (f.visibility = 'department' AND f.department_id IN ({$dept})))";
            $params[] = $userId;
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $where[] = "(f.original_name LIKE ? ESCAPE '!' OR f.title LIKE ? ESCAPE '!')";
            array_push($params, $like, $like);
        }

        return Database::instance()->fetchAll(
            'SELECT f.id, f.uuid, f.owner_user_id, f.department_id, f.module, f.ref_type, f.original_name, f.title, f.mime_type, f.size_bytes,
                    f.visibility, f.created_at, d.name_th AS department_name,
                    CONCAT(COALESCE(e.title_name, \'\'), e.first_name, \' \', e.last_name) AS owner_name
             FROM files f LEFT JOIN departments d ON d.id = f.department_id
             LEFT JOIN users u ON u.id = f.owner_user_id LEFT JOIN employees e ON e.id = u.employee_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY f.id DESC LIMIT 500',
            $params
        );
    }

    public static function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0) . ' KB',
            default => $bytes . ' B',
        };
    }

    public static function icon(string $mime): string
    {
        return match (true) {
            $mime === 'application/pdf' => 'file-earmark-pdf text-danger',
            str_starts_with($mime, 'image/') => 'file-earmark-image text-info',
            str_contains($mime, 'wordprocessingml') => 'file-earmark-word text-primary',
            str_contains($mime, 'spreadsheetml') => 'file-earmark-excel text-success',
            default => 'file-earmark',
        };
    }

    /** แสดงในเบราว์เซอร์ได้อย่างปลอดภัย (ไฟล์อื่นบังคับดาวน์โหลด) */
    public static function isInline(string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    }

    // ------------------------------------------------------------------ ภายใน

    /** @return array{0: string, 1: string} [เนื้อไฟล์, นามสกุล] */
    private static function inspect(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['file' => ['ไฟล์ต้องมีขนาดไม่เกิน 20 MB']]);
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new ValidationException(['file' => ['กรุณาเลือกไฟล์']]);
        }
        $size = (int) filesize((string) $file['tmp_name']);
        if ($size === 0) {
            throw new ValidationException(['file' => ['ไฟล์ว่างเปล่า']]);
        }
        if ($size > self::MAX_BYTES) {
            throw new ValidationException(['file' => ['ไฟล์ต้องมีขนาดไม่เกิน 20 MB']]);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext])) {
            throw new ValidationException(['file' => ['รองรับเฉพาะไฟล์ pdf, jpg, png, docx, xlsx']]);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, self::TYPES[$ext][1], true)) {
            throw new ValidationException(['file' => ["ชนิดไฟล์จริง ({$mime}) ไม่ตรงกับนามสกุล .{$ext}"]]);
        }
        $content = (string) file_get_contents((string) $file['tmp_name']);

        // docx/xlsx ต้องเป็น ZIP ของ Office จริง (ตรวจชื่อไฟล์ภายในจาก local header ที่ไม่ถูกบีบอัด)
        if ($ext === 'docx' || $ext === 'xlsx') {
            $marker = $ext === 'docx' ? 'word/' : 'xl/';
            if (!str_starts_with($content, "PK\x03\x04") || !str_contains($content, '[Content_Types].xml') || !str_contains($content, $marker)) {
                throw new ValidationException(['file' => ["ไฟล์ .{$ext} ไม่ใช่เอกสาร Office ที่ถูกต้อง"]]);
            }
        }
        // กันโค้ด PHP/สคริปต์ที่ฝังมา (polyglot) ก่อนส่งต่อ (ไม่ตรวจ "<?=" เพราะสั้นจนชนข้อมูลไบนารีปกติได้)
        if (preg_match('/<\?php|<script\b/i', $content) === 1) {
            throw new ValidationException(['file' => ['ไฟล์มีโค้ดที่ไม่อนุญาต (PHP/Script) ฝังอยู่']]);
        }

        return [$content, $ext];
    }

    private static function cleanName(string $name, string $ext): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '_', $base);
        $base = trim(mb_substr($base, 0, 150), " ._");

        return ($base !== '' ? $base : 'file') . '.' . $ext;
    }

    private static function primaryDepartmentId(): ?int
    {
        $id = Database::instance()->fetchColumn(
            'SELECT department_id FROM user_departments WHERE user_id = ? ORDER BY is_primary DESC, department_id LIMIT 1',
            [Auth::id()]
        );

        return $id !== null ? (int) $id : null;
    }

    private static function canViewAttachment(array $file, int $userId): bool
    {
        return match ($file['ref_type']) {
            'pdpa_requests' => PdpaService::canViewRequestId((int) $file['ref_id'], $userId),
            default => (int) $file['owner_user_id'] === $userId || Rbac::can('core.files.read_all', $userId),
        };
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
