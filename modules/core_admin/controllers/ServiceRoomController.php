<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Models\ServiceRoomModel;
use Modules\CoreAdmin\Services\ServiceRoomService;

/** ข้อมูลห้องบริการ */
final class ServiceRoomController extends Controller
{
    public function index(): Response
    {
        $yearParam = $this->request->query('year', '');
        $years = ServiceRoomModel::academicYears();
        // ค่าเริ่มต้น = ปีการศึกษาปัจจุบัน, year=all = ทุกปี
        $yearId = $yearParam === 'all' ? 0 : (is_string($yearParam) && ctype_digit($yearParam)
            ? (int) $yearParam : (int) ServiceRoomModel::currentAcademicYearId());
        $q = $this->request->query('q', '');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        return $this->view('core_admin::service_rooms/index', [
            'title' => 'ข้อมูลห้องบริการ',
            'rooms' => ServiceRoomModel::list(['academic_year_id' => $yearId, 'q' => $q]),
            'years' => $years,
            'filters' => ['year' => $yearId, 'q' => $q],
            'canManage' => can('core.service_rooms.manage_all'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('core.service_rooms.manage_all');

        return $this->renderForm(null);
    }

    public function store(): Response
    {
        $this->authorize('core.service_rooms.manage_all');
        $data = $this->validate(ServiceRoomService::rules(), ServiceRoomService::LABELS);
        ServiceRoomService::create($data);
        Session::flash('success', 'เพิ่มห้องบริการเรียบร้อยแล้ว');

        return $this->redirect('/service-rooms?year=' . (int) $data['academic_year_id']);
    }

    public function edit(string $id): Response
    {
        $this->authorize('core.service_rooms.manage_all');

        return $this->renderForm($this->find($id));
    }

    public function update(string $id): Response
    {
        $this->authorize('core.service_rooms.manage_all');
        $room = $this->find($id);
        $data = $this->validate(ServiceRoomService::rules(), ServiceRoomService::LABELS);
        ServiceRoomService::update($room, $data);
        Session::flash('success', 'บันทึกข้อมูลห้องบริการเรียบร้อยแล้ว');

        return $this->redirect('/service-rooms?year=' . (int) $data['academic_year_id']);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('core.service_rooms.manage_all');
        $room = $this->find($id);
        ServiceRoomService::delete($room);
        Session::flash('success', 'ลบห้องบริการ ' . $room['room_code'] . ' เรียบร้อยแล้ว');

        return $this->redirect('/service-rooms?year=' . (int) $room['academic_year_id']);
    }

    private function find(string $id): array
    {
        return ServiceRoomModel::findDetail((int) $id) ?? throw new HttpException(404);
    }

    private function renderForm(?array $room): Response
    {
        return $this->view('core_admin::service_rooms/form', [
            'title' => $room === null ? 'เพิ่มห้องบริการ' : 'แก้ไขห้องบริการ',
            'room' => $room,
            'years' => ServiceRoomModel::academicYears(),
            'currentYearId' => ServiceRoomModel::currentAcademicYearId(),
            'nextCodes' => ServiceRoomService::nextCodes(),
            'teachers' => ServiceRoomModel::teacherOptions($room !== null ? (int) ($room['homeroom_employee_id'] ?? 0) : null),
        ]);
    }
}
