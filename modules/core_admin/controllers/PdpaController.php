<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Auth;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\GoogleWorkspace;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Services\FileService;
use Modules\CoreAdmin\Services\PdpaService;

/** คำขอใช้สิทธิ์ PDPA ของเจ้าของข้อมูล + หน้าจัดการของ DPO */
final class PdpaController extends Controller
{
    public function index(): Response
    {
        return $this->view('core_admin::pdpa/index', [
            'title' => 'คำขอใช้สิทธิ์ PDPA',
            'requests' => PdpaService::listOwn((int) Auth::id()),
            'driveReady' => GoogleWorkspace::isConfigured(),
        ]);
    }

    public function store(): Response
    {
        $request = PdpaService::create(
            (string) $this->request->input('request_type', ''),
            (string) $this->request->input('details', ''),
            $_FILES['attachment'] ?? null
        );
        Session::flash('success', 'ยื่นคำขอ ' . $request['request_no'] . ' เรียบร้อยแล้ว ผู้ดูแลจะดำเนินการภายใน ' . PdpaService::DUE_DAYS . ' วัน');

        return $this->redirect('/pdpa/' . (int) $request['id']);
    }

    public function show(string $id): Response
    {
        $request = $this->find($id);

        return $this->view('core_admin::pdpa/show', [
            'title' => 'คำขอ ' . $request['request_no'],
            'req' => $request,
            'attachments' => FileService::attachments('pdpa_requests', (int) $request['id']),
            'canHandle' => PdpaService::canManage() && in_array($request['status'], ['pending', 'in_progress'], true),
            'isOwner' => (int) $request['user_id'] === Auth::id(),
            'driveReady' => GoogleWorkspace::isConfigured(),
        ]);
    }

    public function cancel(string $id): Response
    {
        PdpaService::cancel($this->find($id));
        Session::flash('success', 'ยกเลิกคำขอเรียบร้อยแล้ว');

        return $this->redirect('/pdpa');
    }

    public function manage(): Response
    {
        $status = (string) $this->request->query('status', 'open');
        $q = $this->request->query('q', '');
        $filters = ['status' => $status, 'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : ''];

        return $this->view('core_admin::pdpa/manage', [
            'title' => 'จัดการคำขอ PDPA',
            'requests' => PdpaService::listAll($filters),
            'counts' => PdpaService::counts(),
            'filters' => $filters,
        ]);
    }

    public function handle(string $id): Response
    {
        $request = $this->find($id);
        $status = (string) $this->request->input('status', '');
        PdpaService::handle($request, $status, (string) $this->request->input('response', ''), $_FILES['attachment'] ?? null);
        Session::flash('success', 'บันทึกผลและแจ้งผู้ยื่นคำขอแล้ว');

        return $this->redirect('/pdpa/' . (int) $request['id']);
    }

    /** ดาวน์โหลดสำเนาข้อมูลส่วนบุคคลของตนเอง (JSON) */
    public function export(): Response
    {
        $data = PdpaService::exportFor((int) Auth::id());
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="my-personal-data-' . date('Ymd') . '.json"',
        ]);
    }

    private function find(string $id): array
    {
        return PdpaService::findVisible((int) $id) ?? throw new HttpException(404);
    }
}
