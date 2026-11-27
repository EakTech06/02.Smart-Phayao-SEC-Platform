<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Auth;
use Core\Config;
use Core\Database;
use Core\GoogleWorkspaceException;
use Core\Mailer;

/** การแจ้งเตือนในระบบ (กระดิ่ง) + อีเมลผ่าน Gmail ของ Google Workspace */
final class NotificationService
{
    public const TYPES = [
        'calendar' => ['icon' => 'calendar-event', 'label' => 'ปฏิทิน'],
        'file' => ['icon' => 'file-earmark', 'label' => 'ไฟล์'],
        'pdpa' => ['icon' => 'shield-lock', 'label' => 'PDPA'],
        'personnel' => ['icon' => 'person-vcard', 'label' => 'งานบุคคล'],
        'student' => ['icon' => 'mortarboard', 'label' => 'งานทะเบียนนักเรียน'],
        'system' => ['icon' => 'info-circle', 'label' => 'ระบบ'],
    ];

    /**
     * @param list<int> $userIds
     * @param string|null $link path ภายในระบบเท่านั้น (เริ่มด้วย /)
     * @return int จำนวนผู้รับ
     */
    public static function notify(array $userIds, string $type, string $title, ?string $message = null, ?string $link = null, bool $email = false): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return 0;
        }
        $link = self::safeLink($link);
        $db = Database::instance();
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        // แจ้งเฉพาะบัญชีที่ใช้งานอยู่
        $recipients = $db->fetchAll(
            "SELECT id, email FROM users WHERE id IN ({$placeholders}) AND status = 'active' AND deleted_at IS NULL",
            $userIds
        );
        if ($recipients === []) {
            return 0;
        }

        $db->transaction(static function (Database $db) use ($recipients, $type, $title, $message, $link): void {
            foreach ($recipients as $r) {
                $db->insert('notifications', [
                    'user_id' => (int) $r['id'],
                    'type' => $type,
                    'title' => mb_substr($title, 0, 255),
                    'message' => $message,
                    'link' => $link,
                ]);
            }
        });

        if ($email) {
            self::email(array_column($recipients, 'email'), $title, $message, $link);
        }

        return count($recipients);
    }

    /** @return list<int> ผู้ใช้ที่มีสิทธิ์ (ผ่านบทบาทหรือ allow รายบุคคล และไม่ถูก deny) */
    public static function usersWithPermission(string $code): array
    {
        $rows = Database::instance()->fetchAll(
            "SELECT DISTINCT u.id FROM users u
             WHERE u.status = 'active' AND u.deleted_at IS NULL
               AND (EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id AND r.is_active = 1
                              JOIN role_permissions rp ON rp.role_id = r.id JOIN permissions p ON p.id = rp.permission_id
                             WHERE ur.user_id = u.id AND p.code = ?)
                    OR EXISTS (SELECT 1 FROM user_permissions up JOIN permissions p ON p.id = up.permission_id
                             WHERE up.user_id = u.id AND p.code = ? AND up.effect = 'allow'
                               AND (up.expires_at IS NULL OR up.expires_at > NOW())))
               AND NOT EXISTS (SELECT 1 FROM user_permissions up JOIN permissions p ON p.id = up.permission_id
                             WHERE up.user_id = u.id AND p.code = ? AND up.effect = 'deny'
                               AND (up.expires_at IS NULL OR up.expires_at > NOW()))",
            [$code, $code, $code]
        );

        return array_map('intval', array_column($rows, 'id'));
    }

    /** @return list<int> */
    public static function activeUserIds(?array $departmentIds = null): array
    {
        $sql = "SELECT DISTINCT u.id FROM users u WHERE u.status = 'active' AND u.deleted_at IS NULL";
        $params = [];
        if ($departmentIds !== null) {
            if ($departmentIds === []) {
                return [];
            }
            $sql .= ' AND EXISTS (SELECT 1 FROM user_departments ud WHERE ud.user_id = u.id AND ud.department_id IN ('
                . implode(',', array_fill(0, count($departmentIds), '?')) . '))';
            $params = array_map('intval', $departmentIds);
        }

        return array_map('intval', array_column(Database::instance()->fetchAll($sql, $params), 'id'));
    }

    public static function unreadCount(?int $userId = null): int
    {
        $userId ??= Auth::id();

        return $userId === null ? 0 : (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public static function latest(int $limit = 5, ?int $userId = null): array
    {
        $userId ??= Auth::id();

        return $userId === null ? [] : Database::instance()->fetchAll(
            'SELECT id, type, title, message, link, read_at, created_at FROM notifications
             WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)),
            [$userId]
        );
    }

    /** @return array{rows: array, total: int} */
    public static function paginate(int $userId, int $page, int $perPage, bool $unreadOnly): array
    {
        $where = 'user_id = ?' . ($unreadOnly ? ' AND read_at IS NULL' : '');
        $db = Database::instance();
        $offset = (max(1, $page) - 1) * $perPage;

        return [
            'rows' => $db->fetchAll(
                "SELECT id, type, title, message, link, read_at, created_at FROM notifications WHERE {$where}
                 ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
                [$userId]
            ),
            'total' => (int) $db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE {$where}", [$userId]),
        ];
    }

    /** ป้องกัน IDOR: ทำเครื่องหมายได้เฉพาะของตนเอง */
    public static function findOwn(int $id, int $userId): ?array
    {
        return Database::instance()->fetch('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::instance()->query('UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL', [$id, $userId]);
    }

    public static function markAllRead(int $userId): int
    {
        return Database::instance()->query('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId])->rowCount();
    }

    public static function readCount(int $userId): int
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NOT NULL',
            [$userId]
        );
    }

    /** ลบการแจ้งเตือนของตนเองที่อ่านแล้ว (ที่ยังไม่อ่านลบไม่ได้ ต้องเปิดอ่านก่อน) @return bool ลบได้หรือไม่ */
    public static function deleteRead(int $id, int $userId): bool
    {
        return Database::instance()->query(
            'DELETE FROM notifications WHERE id = ? AND user_id = ? AND read_at IS NOT NULL',
            [$id, $userId]
        )->rowCount() === 1;
    }

    /** ลบการแจ้งเตือนที่อ่านแล้วทั้งหมดของตนเอง @return int จำนวนที่ลบ */
    public static function deleteAllRead(int $userId): int
    {
        return Database::instance()->query('DELETE FROM notifications WHERE user_id = ? AND read_at IS NOT NULL', [$userId])->rowCount();
    }

    /** ลิงก์ต้องเป็น path ภายใน (กัน open redirect / javascript:) */
    public static function safeLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }

        return preg_match('#^/(?!/)[A-Za-z0-9/_\-.?=&%]*$#', $link) === 1 ? $link : null;
    }

    /** URL เต็มสำหรับอีเมล: ใช้ app.url (กัน Host header injection) ถ้าไม่ได้ตั้งจึงใช้โฮสต์ของคำขอ */
    private static function absoluteUrl(string $path): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) . base_url();
        }

        return $base . $path;
    }

    /** ส่งอีเมลล้มเหลวไม่ทำให้งานหลักล้ม (บันทึก error log ไว้ตรวจสอบ) */
    private static function email(array $emails, string $title, ?string $message, ?string $link): void
    {
        $emails = array_values(array_filter(array_map('strval', $emails)));
        if ($emails === []) {
            return;
        }
        $appName = app_name();
        $html = '<div style="font-family:Sarabun,Tahoma,sans-serif;font-size:15px">'
            . '<h3 style="margin:0 0 8px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
            . ($message !== null ? '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>' : '')
            . ($link !== null ? '<p><a href="' . htmlspecialchars(self::absoluteUrl($link), ENT_QUOTES, 'UTF-8') . '">เปิดในระบบ</a></p>' : '')
            . '<hr><small style="color:#888">' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . ' — อีเมลอัตโนมัติ ไม่ต้องตอบกลับ</small></div>';
        try {
            // ส่งทีละคนเพื่อไม่เปิดเผยอีเมลผู้รับอื่น
            foreach ($emails as $address) {
                Mailer::send([$address], "[{$appName}] {$title}", $html);
            }
        } catch (GoogleWorkspaceException $e) {
            error_log('[notification email] ' . $e->getMessage());
        }
    }
}
