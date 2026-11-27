<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Database;
use Core\Rbac;
use Core\Response;
use Core\View;
use Modules\CoreAdmin\Models\UserModel;
use Modules\CoreAdmin\Services\CalendarService;
use Modules\CoreAdmin\Services\LicenseReminderService;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\NotificationService;
use Modules\CoreAdmin\Services\PdpaService;
use Modules\CoreAdmin\Services\ReportService;
use Modules\CoreAdmin\Services\UserService;

final class DashboardController extends Controller
{
    public function home(): Response
    {
        return $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function index(): Response
    {
        $db = Database::instance();
        $academic = in_array('academic', (array) config('app.modules', []), true);
        LicenseReminderService::runDaily(); // ตรวจใบประกอบวิชาชีพครูวันละครั้ง (แจ้งเตือนเข้ากระดิ่ง)
        if ($academic) {
            \Modules\Academic\Services\CardReminderService::runDaily(); // บัตรประจำตัวคนพิการของนักเรียน
        }
        $stats = [
            'users' => Rbac::scope('core.users', 'read') !== null ? UserModel::countVisible() : null,
            'personnel' => (int) $db->fetchColumn("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL AND status = 'active'"),
            'departments' => (int) $db->fetchColumn('SELECT COUNT(*) FROM departments WHERE is_active = 1'),
            'rooms' => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM service_rooms sr JOIN academic_years ay ON ay.id = sr.academic_year_id AND ay.is_current = 1'
            ),
            'events_month' => (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM calendar_events WHERE deleted_at IS NULL AND visibility = 'public'
                   AND start_at <= ? AND COALESCE(end_at, start_at) >= ?",
                [date('Y-m-t 23:59:59'), date('Y-m-01 00:00:00')]
            ),
            'unread' => NotificationService::unreadCount(),
            // Phase 5A: นักเรียนที่กำลังรับบริการปีการศึกษานี้ (ตามสิทธิ์ของผู้ดู) null = ไม่มีสิทธิ์/ไม่ได้เปิดโมดูล
            'students' => in_array('academic', (array) config('app.modules', []), true)
                ? \Modules\Academic\Services\StudentService::activeEnrolledCount() : null,
            'academic_year' => $db->fetchColumn('SELECT name FROM academic_years WHERE is_current = 1 LIMIT 1'),
            'fiscal_year' => $db->fetchColumn('SELECT name FROM fiscal_years WHERE is_current = 1 LIMIT 1'),
            'evaluation' => $db->fetchColumn('SELECT name FROM evaluation_periods WHERE is_current = 1 LIMIT 1'),
        ];
        $canAudit = Rbac::can('core.audit.read_all');

        // สถิติรายฝ่ายเป็นตัวเลขสรุปเท่านั้น (ไม่มีข้อมูลรายบุคคล) จึงแสดงให้ทุกคนที่ดูแดชบอร์ดได้
        $departments = ReportService::mainDepartmentSummary(); // เฉพาะ 6 ฝ่ายหลัก (หน่วยบริการย่อยรวมเข้างานหน่วยบริการ)
        $types = ReportService::employmentTypes();
        $typeTotals = [];
        foreach (array_keys($types) as $t) {
            $typeTotals[$t] = array_sum(array_map(static fn (array $d): int => $d['by_type'][$t] ?? 0, $departments));
        }

        // ข้อมูลกราฟส่งให้ dashboard.js ผ่าน <script type="application/json"> (CSP ไม่อนุญาต inline script)
        $chart = [
            'departments' => array_column($departments, 'name'),
            'types' => array_map(static fn (string $t): array => [
                'label' => $types[$t],
                'color' => ReportService::EMPLOYMENT_COLORS[$t],
                'data' => array_map(static fn (array $d): int => $d['by_type'][$t] ?? 0, $departments),
                'total' => $typeTotals[$t],
            ], array_keys($types)),
            'trend' => ReportService::monthlyTrend(6, $canAudit),
        ];

        $recent = $canAudit
            ? $db->fetchAll(
                'SELECT a.action, a.ip_address, a.created_at, u.username
                 FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.id DESC LIMIT 8'
            )
            : [];

        return $this->view('core_admin::dashboard/index', [
            'title' => 'แดชบอร์ด',
            'stats' => $stats,
            'departments' => $departments,
            'typeTotals' => $typeTotals,
            'chart' => $chart,
            'upcoming' => Rbac::can('core.calendar.read_all') ? CalendarService::upcoming(5) : [],
            'pdpa' => PdpaService::canManage() ? PdpaService::counts() : null,
            'canReport' => Rbac::canAny(['core.reports.view_all', 'core.reports.view_department']),
            'recent' => $recent,
            'license' => self::licenseAlerts(),
            // บัตรประจำตัวคนพิการของนักเรียนในขอบเขตข้อมูลอ่อนไหวของผู้ดู (null = ไม่มีสิทธิ์)
            'cards' => $academic ? \Modules\Academic\Services\CardReminderService::countsForViewer() : null,
        ]);
    }

    /**
     * แจ้งเตือนใบประกอบวิชาชีพครูบนแดชบอร์ด: ของตนเอง (ถ้าหมดอายุ/ใกล้หมดอายุ) และจำนวนที่งานบุคคลต้องติดตาม
     * @return array{own: ?array, own_id: ?int, expired: int, expiring: int, can_manage: bool}
     */
    private static function licenseAlerts(): array
    {
        $db = Database::instance();
        $own = $db->fetch(
            "SELECT e.id, e.license_expiry_date FROM users u JOIN employees e ON e.id = u.employee_id
             WHERE u.id = ? AND e.deleted_at IS NULL AND e.status = 'active'",
            [(int) Auth::id()]
        );
        $ownStatus = $own !== null ? PersonnelService::licenseStatus($own['license_expiry_date']) : null;
        $canManage = Rbac::can('core.employees.update_all');
        $counts = $canManage ? $db->fetch(
            "SELECT COALESCE(SUM(license_expiry_date < CURDATE()), 0) AS expired,
                    COALESCE(SUM(license_expiry_date >= CURDATE()), 0) AS expiring
             FROM employees WHERE deleted_at IS NULL AND status = 'active'
               AND license_expiry_date <= CURDATE() + INTERVAL ? DAY",
            [PersonnelService::LICENSE_WARN_DAYS]
        ) : null;

        return [
            'own' => $ownStatus !== null && $ownStatus['state'] !== 'valid' ? $ownStatus : null,
            'own_id' => $own !== null ? (int) $own['id'] : null,
            'expired' => (int) ($counts['expired'] ?? 0),
            'expiring' => (int) ($counts['expiring'] ?? 0),
            'can_manage' => $canManage,
        ];
    }

    /** HMVC widget: เรียกจาก View อื่นผ่าน Hmvc::call() */
    public function profileWidget(): string
    {
        $userId = \Core\Session::get('_uid') ?? (\Core\Auth::id() ?? null);
        $user = null;

        if ($userId) {
            // ดึงข้อมูลผู้ใช้พร้อม JOIN ตาราง employees เพื่อให้ได้ avatar, ชื่อ, ตำแหน่ง, วิทยฐานะ ครบถ้วน
            $user = \Core\Database::instance()->fetch('
                SELECT u.*, e.*, u.id AS id, e.id AS employee_id 
                FROM users u 
                LEFT JOIN employees e ON e.id = u.employee_id
                WHERE u.id = ?
            ', [(int) $userId]);
        }

        // หากไม่พบข้อมูลใน employees ให้ fallback ใช้ข้อมูลจาก Auth เดิม
        if (!$user) {
            $user = \Core\Auth::user() ?? [];
        }

        $fullName = trim(($user['title_name'] ?? '') . ' ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($fullName === '') {
            $fullName = \Core\Auth::displayName() ?? 'ผู้ใช้งานระบบ';
        }

        return View::render('core_admin::dashboard/profile_widget', [
            'avatar' => $user['avatar'] ?? null,
            'user'   => $user,
            'name'   => $fullName,
            'roles'  => \Core\Rbac::roles(),
        ], null);
    }
}