<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Response;
use Core\Session;
use Modules\Academic\Services\InnovationLookup;
use Modules\Academic\Services\InnovationService;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\ResearchService;
use Modules\Academic\Services\SigningLookup;
use Modules\Academic\Services\SigningService;
use Modules\CoreAdmin\Services\SignatureService;

/**
 * หน้ากำหนดตำแหน่งลายเซ็นและลงนาม 4 ลำดับ (ใช้ร่วมกัน: งานวิจัย /academic/research/works/{id}, สื่อนวัตกรรม /academic/innovation/media/{id})
 * สิทธิ์ดูตามงาน (findVisible) การลงนามตรวจสิทธิ์ลำดับใน SigningService
 */
final class SigningController extends Controller
{
    public function page(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $ctx = $this->context($kind, $record);
        $userId = (int) Auth::id();
        $isReviewer = ResearchService::canReview();
        $isPdf = ($ctx['version']['mime_type'] ?? '') === 'application/pdf';
        $editable = $isPdf ? SigningService::editableOrders($ctx, $userId, $isReviewer) : [];
        $mode = $this->mode($ctx, $userId);
        $signatures = SigningService::signatures($ctx['type'], (int) $ctx['id']);
        $current = array_filter($signatures, static fn (array $s): bool => (int) $s['version_id'] === (int) $ctx['version']['id']);
        $order = $mode === 'submit' ? 1 : ($mode === 'sign' ? (int) $ctx['awaiting'] : null);

        return $this->view('academic::signing/page', [
            'title' => 'ลงนามเอกสาร: ' . $ctx['title'],
            'kind' => $kind,
            'ctx' => $ctx,
            'isPdf' => $isPdf,
            'boxes' => $isPdf ? SigningService::boxes($ctx) : [],
            'editable' => $editable,
            'signed' => $current,
            'mode' => $mode,
            'order' => $order,
            'hasSignature' => SignatureService::has($userId),
            'problem' => $order !== null ? SigningService::problem($ctx, $order, $userId) : null,
            'docLabel' => SigningLookup::DOC_TYPES[$ctx['type']],
            'backUrl' => $ctx['base'],
            'signUrl' => $ctx['base'] . '/sign',
        ]);
    }

    /** ไฟล์ที่แสดงในหน้าลงนาม: ไฟล์ล่าสุดที่ลงนามในฉบับปัจจุบัน หรือไฟล์ต้นฉบับ (inline สำหรับ pdf.js) */
    public function file(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $ctx = $this->context($kind, $record);
        if (($ctx['version']['mime_type'] ?? '') !== 'application/pdf') {
            throw new HttpException(404);
        }
        $latest = SigningService::latestSignedFor($ctx);

        return $this->pdf($latest !== null ? SigningService::readSigned($latest) : \Modules\Academic\Services\ResearchStorage::read($ctx['version']), 'document.pdf', true);
    }

    public function boxes(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $ctx = $this->context($kind, $record);
        $editable = SigningService::editableOrders($ctx, (int) Auth::id(), ResearchService::canReview());
        SigningService::saveBoxes($ctx, $this->boxInput(), $editable, (int) Auth::id());
        Session::flash('success', 'บันทึกตำแหน่งลายเซ็นแล้ว');

        return $this->redirect($ctx['base'] . '/sign');
    }

    /** ตัวอย่างเอกสารที่ประทับลายเซ็นของผู้ใช้ในลำดับนี้ (ตำแหน่งกรอบที่ยังไม่บันทึกใช้ได้) ไม่บันทึกไฟล์ */
    public function preview(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $ctx = $this->context($kind, $record);
        $userId = (int) Auth::id();
        $mode = $this->mode($ctx, $userId);
        if ($mode !== 'submit' && $mode !== 'sign') {
            throw new HttpException(403);
        }
        $order = $mode === 'submit' ? 1 : (int) $ctx['awaiting'];
        try {
            $boxes = SigningService::boxes($ctx);
            $editable = SigningService::editableOrders($ctx, $userId, ResearchService::canReview());
            foreach (SigningService::cleanBoxes($ctx, $this->boxInput(), $editable) as $o => $b) {
                $boxes[$o] = $b + ['saved' => false];
            }
            $bytes = SigningService::preview($ctx, $order, $userId, $boxes);
        } catch (ValidationException $e) {
            $messages = array_merge(...array_values($e->errors()));

            return Response::json(['error' => implode(' ', $messages)], 422);
        }

        return $this->pdf($bytes, 'preview.pdf', true);
    }

    /** เจ้าของ: ลงนามลำดับที่ 1 และส่ง / ผู้ลงนาม: ลงนามลำดับที่รอ (ยืนยันรหัสผ่าน) */
    public function sign(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $ctx = $this->context($kind, $record);
        $userId = (int) Auth::id();
        $mode = $this->mode($ctx, $userId);
        $password = (string) $this->request->input('password', '');
        $editable = SigningService::editableOrders($ctx, $userId, ResearchService::canReview());
        if ($editable !== [] && trim((string) $this->request->input('boxes', '')) !== '') {
            SigningService::saveBoxes($ctx, $this->boxInput(), $editable, $userId);
        }
        $service = $kind === 'research' ? ResearchService::class : InnovationService::class;
        if ($mode === 'submit') {
            $to = $service::submit($record, $password);
            Session::flash('success', 'ลงนามลำดับที่ 1 และส่งเรียบร้อยแล้ว: ' . $this->statusLabel($kind, $to) . ' ผู้ตรวจจะได้รับการแจ้งเตือน');

            return $this->redirect($ctx['base']);
        }
        if ($mode !== 'sign') {
            throw new HttpException(403);
        }
        $order = (int) $ctx['awaiting'];
        $to = $service::signStep($record, $order, $password);
        Session::flash('success', 'ลงนามลำดับที่ ' . $order . ' เรียบร้อยแล้ว: ' . $this->statusLabel($kind, $to));

        return $this->redirect($ctx['base']);
    }

    public function returnDoc(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 1000);
        $kind === 'research' ? ResearchService::returnFromSigning($record, $note) : InnovationService::returnFromSigning($record, $note);
        Session::flash('success', 'ส่งกลับแก้ไขแล้ว และแจ้งครูเจ้าของงานแล้ว');

        return $this->redirect($this->base($kind, $record));
    }

    public function revoke(string $area, string $sub, string $id): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $reason = mb_substr(trim((string) $this->request->input('reason', '')), 0, 500);
        $kind === 'research' ? ResearchService::revokeApproval($record, $reason) : InnovationService::revokeApproval($record, $reason);
        Session::flash('success', 'ยกเลิกการอนุมัติแล้ว ลายเซ็นและเลขที่เอกสารถูกยกเลิก');

        return $this->redirect($this->base($kind, $record));
    }

    /** ไฟล์ที่ลงนามแล้วของแต่ละลำดับ (ประวัติ) */
    public function signed(string $area, string $sub, string $id, string $sid): Response
    {
        [$kind, $record] = $this->resolve($area, $sub, $id);
        $types = $kind === 'research' ? ['research'] : ['manual', 'report'];
        $signature = null;
        foreach ($types as $type) {
            $row = \Core\Database::instance()->fetch('SELECT * FROM research_signatures WHERE id = ? AND doc_type = ? AND doc_id = ?', [(int) $sid, $type, (int) $record['id']]);
            if ($row !== null) {
                $signature = $row;
                break;
            }
        }
        if ($signature === null) {
            throw new HttpException(404);
        }
        $document = \Core\Database::instance()->fetch('SELECT doc_no FROM research_documents WHERE signature_id = ?', [(int) $signature['id']]);
        $name = ($document !== null ? $document['doc_no'] : SigningLookup::DOC_PREFIX[$signature['doc_type']] . '-' . $record['id'] . '-v' . $signature['version_no'] . '-s' . $signature['sign_order']) . '.pdf';
        Audit::log('research.signed_file_downloaded', 'academic', 'research_signatures', (int) $signature['id'], null, ['doc' => $signature['doc_type'], 'doc_id' => (int) $record['id']]);

        return $this->pdf(SigningService::readSigned($signature), $name, $this->request->query('inline', '') === '1');
    }

    // ---------------------------------------------------------------- ภายใน

    /** @return array{0: string, 1: array} */
    private function resolve(string $area, string $sub, string $id): array
    {
        return match ("{$area}/{$sub}") {
            'research/works' => ['research', ResearchService::findVisible((int) $id)],
            'innovation/media' => ['media', InnovationService::findVisible((int) $id)],
            default => throw new HttpException(404),
        };
    }

    private function context(string $kind, array $record): array
    {
        return $kind === 'research' ? ResearchService::signContext($record) : InnovationService::signContext($record);
    }

    private function base(string $kind, array $record): string
    {
        return $kind === 'research' ? ResearchService::link((int) $record['id']) : InnovationService::link((int) $record['id']);
    }

    /** submit = เจ้าของลงนามลำดับ 1 และส่ง / sign = ผู้มีสิทธิ์ลงนามลำดับที่รอ / view = ดูอย่างเดียว */
    private function mode(array $ctx, int $userId): string
    {
        if (!empty($ctx['owner_editable']) && (int) $ctx['owner_user_id'] === $userId) {
            return 'submit';
        }
        if ($ctx['awaiting'] !== null && SigningLookup::canSignOrder((int) $ctx['awaiting'], (int) $ctx['owner_user_id'], $userId)) {
            return 'sign';
        }

        return 'view';
    }

    private function statusLabel(string $kind, string $status): string
    {
        return $kind === 'research' ? ResearchLookup::statusLabel($status) : InnovationLookup::statusLabel($status);
    }

    /** ตำแหน่งกรอบจากฟอร์ม: boxes = JSON {"1": {page, x, y, w, h}, ...} */
    private function boxInput(): array
    {
        $raw = (string) $this->request->input('boxes', '');
        if (strlen($raw) > 4000) {
            throw new ValidationException(['boxes' => ['ข้อมูลตำแหน่งลายเซ็นไม่ถูกต้อง']]);
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function pdf(string $bytes, string $name, bool $inline): Response
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name),
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
