<?php

declare(strict_types=1);

namespace Core;

/**
 * จัดเก็บไฟล์บน Google Shared Drive ผ่าน Apps Script (ไม่เก็บไฟล์บน Disk โฮสต์)
 * ไฟล์ถูกจัดโฟลเดอร์เป็น {module}/{ปี-เดือน} ภายใต้โฟลเดอร์รากที่กำหนดใน Script Property ROOT_FOLDER_ID
 */
final class DriveUploader
{
    /** @return array{id: string, size: int} */
    public static function upload(string $content, string $name, string $mimeType, string $folderPath): array
    {
        $data = GoogleWorkspace::call('drive.upload', [
            'name' => $name,
            'mimeType' => $mimeType,
            'folder' => $folderPath,
            'base64' => base64_encode($content),
        ]);
        if (empty($data['id']) || !is_string($data['id'])) {
            throw new GoogleWorkspaceException('Google Drive ไม่ส่งรหัสไฟล์กลับมา');
        }

        return ['id' => $data['id'], 'size' => (int) ($data['size'] ?? strlen($content))];
    }

    /** @return array{content: string, mimeType: string} */
    public static function download(string $driveFileId): array
    {
        $data = GoogleWorkspace::call('drive.download', ['id' => $driveFileId]);
        $content = base64_decode((string) ($data['base64'] ?? ''), true);
        if ($content === false) {
            throw new GoogleWorkspaceException('ข้อมูลไฟล์จาก Google Drive ไม่ถูกต้อง');
        }

        return ['content' => $content, 'mimeType' => (string) ($data['mimeType'] ?? 'application/octet-stream')];
    }

    /** ย้ายไปถังขยะของ Shared Drive (กู้คืนได้ 30 วันตามนโยบาย Google) */
    public static function trash(string $driveFileId): void
    {
        GoogleWorkspace::call('drive.trash', ['id' => $driveFileId]);
    }
}
