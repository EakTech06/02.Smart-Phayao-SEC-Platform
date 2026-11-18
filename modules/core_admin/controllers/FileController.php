<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\GoogleWorkspace;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Services\FileService;

/** ไฟล์/เอกสารกลาง (เก็บบน Google Shared Drive) */
final class FileController extends Controller
{
    public function index(): Response
    {
        $q = $this->request->query('q', '');
        $scope = $this->request->query('scope', 'all');
        $filters = [
            'q' => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'scope' => $scope === 'mine' ? 'mine' : 'all',
        ];

        return $this->view('core_admin::files/index', [
            'title' => 'ไฟล์/เอกสาร',
            'files' => FileService::listVisible($filters),
            'filters' => $filters,
            'driveReady' => GoogleWorkspace::isConfigured(),
        ]);
    }

    public function store(): Response
    {
        $this->authorize('core.files.upload_own');
        $visibility = (string) $this->request->input('visibility', 'private');
        $row = FileService::upload($_FILES['file'] ?? [], [
            'module' => 'core',
            'visibility' => $visibility,
            'title' => (string) $this->request->input('title', ''),
        ]);
        Session::flash('success', 'อัปโหลด ' . $row['original_name'] . ' ไปยัง Google Shared Drive เรียบร้อยแล้ว');

        return $this->redirect('/files');
    }

    /** PHP proxy: ตรวจสิทธิ์ → ดึงจาก Google Drive → ส่งให้ผู้ใช้ (ไม่เปิดเผยลิงก์ Drive) */
    public function download(string $uuid): Response
    {
        $file = $this->findViewable($uuid);
        $data = FileService::content($file);
        $inline = $this->request->query('inline', '') === '1' && FileService::isInline($data['mime']);
        $name = (string) $file['original_name'];
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

        return (new Response($data['content'], 200, [
            'Content-Type' => $data['mime'],
            'Content-Length' => (string) strlen($data['content']),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name),
            // ห้ามโหลดสคริปต์/ทรัพยากรภายนอกแม้เปิดแบบ inline (ไม่ใช้ sandbox เพราะ Chrome จะบล็อกตัวแสดง PDF)
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]));
    }

    public function destroy(string $uuid): Response
    {
        $file = $this->findViewable($uuid);
        if (!FileService::canDelete($file)) {
            throw new HttpException(403);
        }
        FileService::delete($file);
        Session::flash('success', 'ลบไฟล์ ' . $file['original_name'] . ' เรียบร้อยแล้ว');

        return $this->redirect('/files');
    }

    /** ป้องกัน IDOR: ไม่มีสิทธิ์ = 404 เหมือนไม่พบไฟล์ */
    private function findViewable(string $uuid): array
    {
        $file = FileService::findByUuid($uuid);
        if ($file === null || !FileService::canView($file)) {
            throw new HttpException(404);
        }

        return $file;
    }
}
