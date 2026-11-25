<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;

/**
 * คำขอใช้สิทธิ์ของเจ้าของข้อมูลส่วนบุคคล (พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 มาตรา 30-36)
 * ผู้ควบคุมข้อมูลต้องดำเนินการภายใน 30 วันนับแต่วันที่ได้รับคำขอ
 */
final class PdpaService
{
    public const DUE_DAYS = 30;

    public const TYPES = [
        'access' => ['ขอเข้าถึงและขอรับสำเนาข้อมูล', 'มาตรา 30'],
        'portability' => ['ขอรับหรือโอนย้ายข้อมูล', 'มาตรา 31'],
        'objection' => ['คัดค้านการเก็บ ใช้ หรือเปิดเผยข้อมูล', 'มาตรา 32'],
        'erasure' => ['ขอให้ลบหรือทำลายข้อมูล', 'มาตรา 33'],
        'restriction' => ['ขอให้ระงับการใช้ข้อมูล', 'มาตรา 34'],
        'rectification' => ['ขอแก้ไขข้อมูลให้ถูกต้อง', 'มาตรา 35'],
        'withdraw_consent' => ['ขอถอนความยินยอม', 'มาตรา 19'],
    ];

    public const STATUSES = [
        'pending' => ['รอดำเนินการ', 'warning'],
        'in_progress' => ['กำลังดำเนินการ', 'info'],
        'completed' => ['ดำเนินการเสร็จสิ้น', 'success'],
        'rejected' => ['ปฏิเสธคำขอ', 'danger'],
        'cancelled' => ['ผู้ยื่นยกเลิก', 'secondary'],
    ];

    /** สถานะที่ผู้ดูแลเปลี่ยนได้ */
    public const HANDLER_STATUSES = ['in_progress', 'completed', 'rejected'];

    private const SELECT = 'SELECT pr.*, u.username, u.email AS user_email,
            CONCAT(COALESCE(e.title_name, \'\'), e.first_name, \' \', e.last_name) AS requester_name, e.employee_code,
            CONCAT(COALESCE(he.title_name, \'\'), he.first_name, \' \', he.last_name) AS handler_name
        FROM pdpa_requests pr
        JOIN users u ON u.id = pr.user_id
        LEFT JOIN employees e ON e.id = u.employee_id
        LEFT JOIN users hu ON hu.id = pr.handled_by
        LEFT JOIN employees he ON he.id = hu.employee_id';

    public static function canManage(?int $userId = null): bool
    {
        return Rbac::can('core.pdpa.manage_all', $userId);
    }

    /** @param array{status?: string, q?: string} $filters */
    public static function listAll(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['status'] ?? '') === 'open') {
            $where[] = "pr.status IN ('pending','in_progress')";
        } elseif (isset(self::STATUSES[$filters['status'] ?? ''])) {
            $where[] = 'pr.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']) . '%';
            $where[] = "(pr.request_no LIKE ? ESCAPE '!' OR e.first_name LIKE ? ESCAPE '!' OR e.last_name LIKE ? ESCAPE '!' OR u.username LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like, $like);
        }

        return Database::instance()->fetchAll(
            self::SELECT . ' WHERE ' . implode(' AND ', $where)
            . " ORDER BY FIELD(pr.status, 'pending', 'in_progress') DESC, pr.due_date, pr.id DESC",
            $params
        );
    }

    public static function listOwn(int $userId): array
    {
        return Database::instance()->fetchAll(self::SELECT . ' WHERE pr.user_id = ? ORDER BY pr.id DESC', [$userId]);
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        $row = Database::instance()->fetch(
            "SELECT SUM(status IN ('pending','in_progress')) AS open,
                    SUM(status IN ('pending','in_progress') AND due_date < CURDATE()) AS overdue,
                    SUM(status IN ('pending','in_progress') AND due_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY) AS due_soon,
                    SUM(status = 'completed') AS completed
             FROM pdpa_requests"
        ) ?? [];

        return array_map('intval', $row);
    }

    /** ป้องกัน IDOR: เจ้าของคำขอหรือผู้ดูแล (DPO) เท่านั้น */
    public static function findVisible(int $id): ?array
    {
        $row = Database::instance()->fetch(self::SELECT . ' WHERE pr.id = ?', [$id]);

        return $row !== null && ((int) $row['user_id'] === Auth::id() || self::canManage()) ? $row : null;
    }

    public static function canViewRequestId(int $requestId, int $userId): bool
    {
        if (self::canManage($userId)) {
            return true;
        }

        return (int) Database::instance()->fetchColumn('SELECT user_id FROM pdpa_requests WHERE id = ?', [$requestId]) === $userId;
    }

    public static function isOverdue(array $request): bool
    {
        return in_array($request['status'], ['pending', 'in_progress'], true) && $request['due_date'] < date('Y-m-d');
    }

    public static function create(string $type, string $details, ?array $attachment): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new ValidationException(['request_type' => ['กรุณาเลือกประเภทคำขอ']]);
        }
        $details = trim($details);
        if (mb_strlen($details) < 10) {
            throw new ValidationException(['details' => ['กรุณาอธิบายรายละเอียดคำขออย่างน้อย 10 ตัวอักษร']]);
        }
        $userId = (int) Auth::id();
        $db = Database::instance();
        $open = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM pdpa_requests WHERE user_id = ? AND request_type = ? AND status IN ('pending','in_progress')",
            [$userId, $type]
        );
        if ($open > 0) {
            throw new ValidationException(['request_type' => ['คุณมีคำขอประเภทนี้ที่ยังดำเนินการอยู่ กรุณารอผลหรือยกเลิกคำขอเดิมก่อน']]);
        }

        $id = $db->transaction(static function (Database $db) use ($userId, $type, $details): int {
            // เลขที่คำขอ PDPA-{ปี พ.ศ.}-{ลำดับ 4 หลัก} เรียงต่อภายในปี (ล็อกแถวของปีเพื่อกันเลขซ้ำ)
            $year = (int) date('Y') + 543;
            $prefix = "PDPA-{$year}-";
            $last = (string) ($db->fetchColumn(
                'SELECT request_no FROM pdpa_requests WHERE request_no LIKE ? ORDER BY request_no DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            ) ?? '');
            $seq = $last !== '' ? (int) substr($last, strlen($prefix)) + 1 : 1;

            return $db->insert('pdpa_requests', [
                'request_no' => $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'user_id' => $userId,
                'request_type' => $type,
                'details' => $details,
                'status' => 'pending',
                'due_date' => date('Y-m-d', strtotime('+' . self::DUE_DAYS . ' days')),
            ]);
        });

        if ($attachment !== null && ($attachment['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                FileService::upload($attachment, ['module' => 'pdpa', 'ref_type' => 'pdpa_requests', 'ref_id' => $id, 'visibility' => 'private']);
            } catch (ValidationException $e) {
                $db->delete('pdpa_requests', ['id' => $id]); // ไฟล์แนบไม่ผ่าน = ไม่รับคำขอ ให้ยื่นใหม่
                throw $e;
            }
        }

        $request = $db->fetch(self::SELECT . ' WHERE pr.id = ?', [$id]);
        Audit::log('pdpa.requested', 'core', 'pdpa_requests', $id, null, ['request_no' => $request['request_no'], 'type' => $type]);
        NotificationService::notify(
            array_values(array_diff(NotificationService::usersWithPermission('core.pdpa.manage_all'), [$userId])),
            'pdpa',
            'คำขอใช้สิทธิ์ PDPA ใหม่ ' . $request['request_no'],
            self::TYPES[$type][0] . ' — ต้องดำเนินการภายใน ' . thai_date((string) $request['due_date'], false),
            '/pdpa/' . $id,
            true
        );

        return $request;
    }

    public static function cancel(array $request): void
    {
        if ((int) $request['user_id'] !== Auth::id() || $request['status'] !== 'pending') {
            throw new HttpException(403, 'ยกเลิกได้เฉพาะคำขอของตนเองที่ยังไม่เริ่มดำเนินการ');
        }
        Database::instance()->update('pdpa_requests', ['status' => 'cancelled'], ['id' => (int) $request['id']]);
        Audit::log('pdpa.cancelled', 'core', 'pdpa_requests', (int) $request['id'], ['status' => 'pending'], ['status' => 'cancelled']);
    }

    public static function handle(array $request, string $status, string $response, ?array $attachment): void
    {
        if (!self::canManage()) {
            throw new HttpException(403);
        }
        if (!in_array($request['status'], ['pending', 'in_progress'], true)) {
            throw new ValidationException(['status' => ['คำขอนี้ปิดแล้ว ไม่สามารถเปลี่ยนสถานะได้']]);
        }
        if (!in_array($status, self::HANDLER_STATUSES, true)) {
            throw new ValidationException(['status' => ['สถานะไม่ถูกต้อง']]);
        }
        $response = trim($response);
        if ($status !== 'in_progress' && mb_strlen($response) < 5) {
            throw new ValidationException(['response' => ['กรุณาระบุผลการดำเนินการ/เหตุผล เพื่อแจ้งผู้ยื่นคำขอ']]);
        }
        $id = (int) $request['id'];
        if ($attachment !== null && ($attachment['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            FileService::upload($attachment, ['module' => 'pdpa', 'ref_type' => 'pdpa_requests', 'ref_id' => $id, 'visibility' => 'private']);
        }
        Database::instance()->update('pdpa_requests', [
            'status' => $status,
            'response' => $response !== '' ? $response : $request['response'],
            'handled_by' => Auth::id(),
            'handled_at' => Database::now(),
        ], ['id' => $id]);
        Audit::log('pdpa.handled', 'core', 'pdpa_requests', $id, ['status' => $request['status']], ['status' => $status]);

        NotificationService::notify(
            [(int) $request['user_id']],
            'pdpa',
            'คำขอ ' . $request['request_no'] . ': ' . self::STATUSES[$status][0],
            $response !== '' ? $response : null,
            '/pdpa/' . $id,
            true
        );
    }

    /**
     * สำเนาข้อมูลส่วนบุคคลของผู้ใช้ (สิทธิ์เข้าถึง/โอนย้าย มาตรา 30-31) เป็น JSON ที่อ่านด้วยเครื่องได้
     * เลขประจำตัวประชาชนแสดงเฉพาะ 4 หลักท้าย และไม่รวม hash/ข้อมูลเข้ารหัส/รหัสผ่าน
     */
    public static function exportFor(int $userId): array
    {
        $db = Database::instance();
        $user = $db->fetch(
            'SELECT u.id, u.username, u.email, u.status, u.last_login_at, u.created_at, u.employee_id FROM users u WHERE u.id = ?',
            [$userId]
        ) ?? throw new HttpException(404);
        $employee = $user['employee_id'] !== null ? $db->fetch(
            'SELECT e.employee_code, e.title_name, e.first_name, e.last_name, e.nickname, e.gender, e.birth_date, e.position,
                    e.position_number, e.academic_rank, e.employment_type, e.education_level, e.education_major, e.screener_reg_no,
                    d.name_th AS department, e.phone, e.email, e.address, e.start_date, e.end_date, e.status, e.national_id_last4
             FROM employees e LEFT JOIN departments d ON d.id = e.department_id WHERE e.id = ?',
            [(int) $user['employee_id']]
        ) : null;
        if ($employee !== null) {
            $employee['national_id'] = $employee['national_id_last4'] !== null ? '*********' . $employee['national_id_last4'] : null;
            unset($employee['national_id_last4']);
        }
        unset($user['employee_id']);
        Audit::log('pdpa.data_exported', 'core', 'users', $userId);

        return [
            'exported_at' => date('c'),
            'controller' => (string) config('app.org'),
            'note' => 'สำเนาข้อมูลส่วนบุคคลตามสิทธิ์ของเจ้าของข้อมูล (พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 มาตรา 30-31)',
            'account' => $user,
            'personnel' => $employee,
            'roles' => array_column($db->fetchAll(
                'SELECT r.name_th FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$userId]), 'name_th'),
            'departments' => array_column($db->fetchAll(
                'SELECT d.name_th FROM user_departments ud JOIN departments d ON d.id = ud.department_id WHERE ud.user_id = ?', [$userId]), 'name_th'),
            'files' => $db->fetchAll(
                'SELECT original_name, title, mime_type, size_bytes, visibility, created_at FROM files WHERE owner_user_id = ? AND deleted_at IS NULL',
                [$userId]
            ),
            'login_history' => $db->fetchAll(
                'SELECT ip_address, success, attempted_at FROM login_attempts WHERE username IN (?, ?) ORDER BY id DESC LIMIT 100',
                [$user['username'], (string) ($user['email'] ?? '')]
            ),
            'activity_log' => $db->fetchAll(
                'SELECT action, entity_type, ip_address, created_at FROM audit_logs WHERE user_id = ? ORDER BY id DESC LIMIT 200',
                [$userId]
            ),
            'pdpa_requests' => $db->fetchAll(
                'SELECT request_no, request_type, status, created_at, due_date, handled_at FROM pdpa_requests WHERE user_id = ? ORDER BY id',
                [$userId]
            ),
        ];
    }
}
