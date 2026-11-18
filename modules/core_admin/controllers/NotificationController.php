<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Services\NotificationService;

final class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): Response
    {
        $page = max(1, (int) $this->request->query('page', 1));
        $unreadOnly = $this->request->query('filter', '') === 'unread';
        $result = NotificationService::paginate((int) Auth::id(), $page, self::PER_PAGE, $unreadOnly);

        return $this->view('core_admin::notifications/index', [
            'title' => 'การแจ้งเตือน',
            'notifications' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'unreadOnly' => $unreadOnly,
            'unread' => NotificationService::unreadCount(),
        ]);
    }

    /** เปิดการแจ้งเตือน: ทำเครื่องหมายว่าอ่านแล้วและไปยังลิงก์ (POST เพื่อไม่ให้เปลี่ยนสถานะด้วย GET) */
    public function open(string $id): Response
    {
        $userId = (int) Auth::id();
        $notification = NotificationService::findOwn((int) $id, $userId) ?? throw new HttpException(404);
        NotificationService::markRead((int) $notification['id'], $userId);
        $link = NotificationService::safeLink($notification['link']);

        return $this->redirect($link ?? '/notifications');
    }

    public function readAll(): Response
    {
        $count = NotificationService::markAllRead((int) Auth::id());
        Session::flash('success', $count > 0 ? "ทำเครื่องหมายอ่านแล้ว {$count} รายการ" : 'ไม่มีรายการที่ยังไม่อ่าน');

        return $this->redirect('/notifications');
    }

    /** JSON สำหรับกระดิ่งบนแถบด้านบน (โพลทุก 60 วินาที) */
    public function unreadCount(): Response
    {
        return Response::success(['unread' => NotificationService::unreadCount()]);
    }
}
