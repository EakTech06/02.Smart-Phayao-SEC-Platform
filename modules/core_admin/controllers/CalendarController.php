<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Services\CalendarService;

/** ปฏิทินกลาง */
final class CalendarController extends Controller
{
    public function index(): Response
    {
        $month = (string) $this->request->query('month', '');
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m) !== 1 || (int) $m[1] < 2000 || (int) $m[1] > 2100) {
            $month = date('Y-m');
        }
        $first = strtotime($month . '-01');
        // ตารางเริ่มวันอาทิตย์ แสดง 6 สัปดาห์ (42 วัน)
        $gridStart = strtotime('-' . (int) date('w', $first) . ' days', $first);
        $gridEnd = strtotime('+42 days', $gridStart);

        return $this->view('core_admin::calendar/index', [
            'title' => 'ปฏิทินกลาง',
            'month' => $month,
            'first' => $first,
            'gridStart' => $gridStart,
            'events' => CalendarService::between(date('Y-m-d 00:00:00', $gridStart), date('Y-m-d 00:00:00', $gridEnd)),
            'canCreate' => CalendarService::canCreateAny(),
        ]);
    }

    public function show(string $id): Response
    {
        $event = $this->find($id);

        return $this->view('core_admin::calendar/show', [
            'title' => $event['title'],
            'event' => $event,
            'canEdit' => CalendarService::canEdit($event),
        ]);
    }

    public function create(): Response
    {
        if (!CalendarService::canCreateAny()) {
            throw new HttpException(403);
        }
        $date = (string) $this->request->query('date', '');

        return $this->renderForm(null, preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : date('Y-m-d'));
    }

    public function store(): Response
    {
        $data = $this->validate(CalendarService::rules(), CalendarService::LABELS) + $this->flags();
        $result = CalendarService::create($data, !empty($data['notify']));
        $this->flashResult('เพิ่มกิจกรรมเรียบร้อยแล้ว', $result['warning']);

        return $this->redirect('/calendar/' . $result['id']);
    }

    public function edit(string $id): Response
    {
        $event = $this->find($id);
        if (!CalendarService::canEdit($event)) {
            throw new HttpException(403);
        }

        return $this->renderForm($event, null);
    }

    public function update(string $id): Response
    {
        $event = $this->find($id);
        $data = $this->validate(CalendarService::rules(), CalendarService::LABELS) + $this->flags();
        $warning = CalendarService::update($event, $data, !empty($data['notify']));
        $this->flashResult('บันทึกกิจกรรมเรียบร้อยแล้ว', $warning);

        return $this->redirect('/calendar/' . (int) $event['id']);
    }

    public function destroy(string $id): Response
    {
        $event = $this->find($id);
        $warning = CalendarService::delete($event);
        $this->flashResult('ลบกิจกรรม ' . $event['title'] . ' เรียบร้อยแล้ว', $warning);

        return $this->redirect('/calendar?month=' . substr((string) $event['start_at'], 0, 7));
    }

    private function find(string $id): array
    {
        return CalendarService::findVisible((int) $id) ?? throw new HttpException(404);
    }

    /** checkbox ไม่ผ่าน Validator (ไม่มีค่าเมื่อไม่ติ๊ก) */
    private function flags(): array
    {
        return [
            'is_all_day' => $this->request->input('is_all_day') === '1',
            'notify' => $this->request->input('notify') === '1',
        ];
    }

    private function flashResult(string $success, ?string $warning): void
    {
        Session::flash('success', $success);
        if ($warning !== null) {
            Session::flash('warning', $warning);
        }
    }

    private function renderForm(?array $event, ?string $date): Response
    {
        $options = CalendarService::options();

        return $this->view('core_admin::calendar/form', [
            'title' => $event === null ? 'เพิ่มกิจกรรม' : 'แก้ไขกิจกรรม',
            'event' => $event,
            'date' => $date,
            'visibilities' => $options['visibilities'],
            'departments' => $options['departments'],
        ]);
    }
}
