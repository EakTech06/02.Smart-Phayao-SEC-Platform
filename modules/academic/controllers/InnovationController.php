<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Audit;
use Core\Controller;
use Core\Exceptions\ValidationException;
use Core\Response;
use Core\Session;
use Core\Validator;
use Modules\Academic\Services\InnovationLookup;
use Modules\Academic\Services\InnovationService;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\ResearchService;
use Modules\Academic\Services\ResearchStorage;
use Modules\Academic\Services\StudentLookup;

/** สื่อนวัตกรรม: ระยะที่ 1 คู่มือการใช้สื่อ / ระยะที่ 2 รายงานการใช้สื่อ */
final class InnovationController extends Controller
{
    public function index(): Response
    {
        $canListAll = ResearchService::canReadAll() || ResearchService::canReview();
        $view = (string) $this->request->query('view', '');
        // ครูที่ไม่มีสิทธิ์ดูงานทั้งหมด เห็นรายการเฉพาะของตนเองเสมอ
        $mine = !$canListAll || $view === 'mine';
        $filters = $this->filters();
        $filters['q'] = mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
        $status = (string) $this->request->query('status', '');
        $filters['status'] = isset(InnovationLookup::STATUSES[$status]) ? $status : '';

        return $this->view('academic::innovation/index', [
            'title' => $mine ? 'สื่อนวัตกรรมของฉัน' : 'สื่อนวัตกรรมทั้งหมด',
            'items' => InnovationService::list($filters, $mine),
            'filters' => $filters,
            'mine' => $mine,
            'canListAll' => $canListAll,
            'types' => ResearchService::types('innovation', false),
            'years' => ResearchService::years(),
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm(null);
    }

    public function store(): Response
    {
        $data = $this->manualData(null);
        $file = $this->uploadedFile() ?? throw new ValidationException(['file' => ['กรุณาแนบคู่มือการใช้สื่อนวัตกรรม (PDF)']]);
        $submit = $this->request->input('action') === 'submit';
        $id = InnovationService::create($data, $file, $submit);
        Session::flash('success', $submit ? 'ส่งคู่มือการใช้สื่อนวัตกรรมเรียบร้อยแล้ว ผู้ตรวจจะได้รับการแจ้งเตือน' : 'บันทึกร่างสื่อนวัตกรรมเรียบร้อยแล้ว');

        return $this->redirect(InnovationService::link($id));
    }

    public function show(string $id): Response
    {
        $media = InnovationService::findVisible((int) $id);

        return $this->view('academic::innovation/show', [
            'title' => $media['title'],
            'media' => $media,
            'isOwner' => InnovationService::isOwner($media),
            'canReview' => ResearchService::canReview(),
            'versions' => InnovationService::versions((int) $media['id']),
            'logs' => InnovationService::statusLogs((int) $media['id']),
            'threads' => InnovationService::comments((int) $media['id']),
            'reportDue' => InnovationService::reportDueFor($media),
        ]);
    }

    /** แก้ไข: ร่าง/คู่มือถูกส่งกลับ = ข้อมูลคู่มือ, รายงานถูกส่งกลับ = ข้อมูลการใช้จริง */
    public function edit(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        if ($media['status'] === 'report_revision') {
            return $this->renderReport($media);
        }
        if (!in_array($media['status'], ['draft', 'manual_revision'], true)) {
            Session::flash('error', 'แก้ไขได้เฉพาะร่าง หรือฉบับที่ถูกส่งกลับแก้ไข');

            return $this->redirect(InnovationService::link((int) $media['id']));
        }

        return $this->renderForm($media);
    }

    public function update(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        if ($media['status'] === 'report_revision') {
            InnovationService::updateReport($media, $this->reportData($media));
            Session::flash('success', 'บันทึกข้อมูลการใช้สื่อเรียบร้อยแล้ว');
        } else {
            InnovationService::updateManual($media, $this->manualData($media));
            Session::flash('success', 'บันทึกข้อมูลสื่อนวัตกรรมเรียบร้อยแล้ว');
        }

        return $this->redirect(InnovationService::link((int) $media['id']));
    }

    /** ระยะที่ 2: ฟอร์มส่งรายงานการใช้สื่อ (เฉพาะสื่อที่คู่มืออนุมัติแล้ว) */
    public function reportForm(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        if ($media['status'] !== 'in_use') {
            Session::flash('error', 'ส่งรายงานได้เมื่อคู่มือได้รับอนุมัติแล้ว (สถานะอยู่ระหว่างใช้สื่อ) เท่านั้น');

            return $this->redirect(InnovationService::link((int) $media['id']));
        }

        return $this->renderReport($media);
    }

    public function reportStore(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        $data = $this->reportData($media);
        $file = $this->uploadedFile() ?? throw new ValidationException(['file' => ['กรุณาแนบรายงานการใช้สื่อนวัตกรรม (PDF)']]);
        InnovationService::submitReport($media, $data, $file);
        Session::flash('success', 'ส่งรายงานการใช้สื่อนวัตกรรมเรียบร้อยแล้ว ผู้ตรวจจะได้รับการแจ้งเตือน');

        return $this->redirect(InnovationService::link((int) $media['id']));
    }

    public function versionStore(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        $file = $this->uploadedFile() ?? throw new ValidationException(['file' => ['กรุณาเลือกไฟล์ฉบับใหม่']]);
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 500);
        $no = InnovationService::addVersion($media, $file, $note !== '' ? $note : null);
        $message = "อัปโหลดไฟล์เวอร์ชันที่ {$no} เรียบร้อยแล้ว";
        if ($this->request->input('submit') === '1') {
            InnovationService::submit(InnovationService::findOwned((int) $id));
            $message .= ' และส่งแล้ว';
        }
        Session::flash('success', $message);

        return $this->redirect(InnovationService::link((int) $media['id']));
    }

    public function submit(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        $to = InnovationService::submit($media);
        Session::flash('success', 'ส่งเรียบร้อยแล้ว: ' . InnovationLookup::statusLabel($to));

        return $this->redirect(InnovationService::link((int) $media['id']));
    }

    public function review(string $id): Response
    {
        $media = InnovationService::findVisible((int) $id);
        $action = (string) $this->request->input('action', '');
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 1000);
        $to = InnovationService::review($media, $action, $note !== '' ? $note : null);
        Session::flash('success', 'เปลี่ยนสถานะเป็น "' . InnovationLookup::statusLabel($to) . '" แล้ว และแจ้งครูเจ้าของงานแล้ว');

        return $this->redirect(InnovationService::link((int) $media['id']));
    }

    public function cancel(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 1000);
        InnovationService::cancelByOwner($media, $note !== '' ? $note : null);
        Session::flash('success', 'ยกเลิกสื่อนวัตกรรมแล้ว');

        return $this->redirect(InnovationService::link((int) $media['id']));
    }

    public function destroy(string $id): Response
    {
        $media = InnovationService::findOwned((int) $id);
        InnovationService::deleteDraft($media);
        Session::flash('success', 'ลบร่าง "' . $media['title'] . '" แล้ว');

        return $this->redirect('/academic/innovation/media?view=mine');
    }

    public function commentStore(string $id): Response
    {
        $media = InnovationService::findVisible((int) $id);
        $body = trim((string) $this->request->input('body', ''));
        if ($body === '' || mb_strlen($body) > 3000) {
            throw new ValidationException(['body' => [$body === '' ? 'กรุณาพิมพ์ข้อความ' : 'ข้อความต้องไม่เกิน 3000 ตัวอักษร']]);
        }
        $parent = (int) $this->request->input('parent_id', 0);
        InnovationService::addComment($media, $body, $parent > 0 ? $parent : null);
        Session::flash('success', $parent > 0 ? 'ตอบกลับแล้ว' : 'บันทึกข้อเสนอแนะแล้ว');

        return $this->redirect(InnovationService::link((int) $media['id']) . '#feedback');
    }

    public function download(string $id, string $vid): Response
    {
        $media = InnovationService::findVisible((int) $id);
        $version = InnovationService::version((int) $media['id'], (int) $vid);
        $content = ResearchStorage::read($version);
        $inline = $this->request->query('inline', '') === '1';
        $name = (string) $version['original_name'];
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        Audit::log('innovation.file_downloaded', 'academic', 'innovation_versions', (int) $version['id'], null, ['media_id' => (int) $media['id']]);

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name),
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    // ---------------------------------------------------------------- ภายใน

    private function renderForm(?array $media): Response
    {
        $types = ResearchService::types('innovation');
        if ($media !== null && !in_array((int) $media['type_id'], array_map('intval', array_column($types, 'id')), true)) {
            $types[] = ['id' => $media['type_id'], 'name' => $media['type_name'] . ' (ปิดใช้งาน)'];
        }

        return $this->view('academic::innovation/form', [
            'title' => $media === null ? 'ส่งคู่มือการใช้สื่อนวัตกรรม' : 'แก้ไขข้อมูลสื่อนวัตกรรม',
            'media' => $media,
            'types' => $types,
            'years' => ResearchService::years(),
            'currentYearId' => ResearchService::currentYearId(),
            'dueDays' => InnovationLookup::reportDueDays(),
        ]);
    }

    private function renderReport(array $media): Response
    {
        return $this->view('academic::innovation/report', [
            'title' => 'รายงานการใช้สื่อนวัตกรรม',
            'media' => $media,
            'isRevision' => $media['status'] === 'report_revision',
            'reportDue' => InnovationService::reportDueFor($media),
        ]);
    }

    /** @return array{title: string, type_id: int, academic_year_id: int, semester: int, plan_start: string, plan_end: string, planned: array<string, int>} */
    private function manualData(?array $media): array
    {
        $v = $this->validate([
            'title' => 'required|max:300',
            'type_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'semester' => 'required|in:1,2',
            'plan_start' => 'required|date',
            'plan_end' => 'required|date',
        ], [
            'title' => 'ชื่อสื่อนวัตกรรม', 'type_id' => 'ประเภทสื่อนวัตกรรม', 'academic_year_id' => 'ปีการศึกษา',
            'semester' => 'ภาคเรียน', 'plan_start' => 'วันเริ่มใช้สื่อตามแผน', 'plan_end' => 'วันสิ้นสุดการใช้สื่อตามแผน',
        ]);
        $errors = [];
        $typeId = (int) $v['type_id'];
        $active = array_map('intval', array_column(ResearchService::types('innovation'), 'id'));
        if (!in_array($typeId, $active, true) && ($media === null || $typeId !== (int) $media['type_id'])) {
            $errors['type_id'][] = 'ประเภทสื่อนวัตกรรมไม่ถูกต้อง';
        }
        $yearId = (int) $v['academic_year_id'];
        if (!in_array($yearId, array_map('intval', array_column(ResearchService::years(), 'id')), true)) {
            $errors['academic_year_id'][] = 'ปีการศึกษาไม่ถูกต้อง';
        }
        if ($v['plan_end'] < $v['plan_start']) {
            $errors['plan_end'][] = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มใช้สื่อ';
        }
        [$planned, $countError] = $this->counts('planned');
        if ($countError !== null) {
            $errors['planned'][] = $countError;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'title' => trim((string) preg_replace('/\s+/u', ' ', (string) $v['title'])),
            'type_id' => $typeId,
            'academic_year_id' => $yearId,
            'semester' => (int) $v['semester'],
            'plan_start' => (string) $v['plan_start'],
            'plan_end' => (string) $v['plan_end'],
            'planned' => $planned,
        ];
    }

    /** @return array{actual_start: string, actual_end: string, actual: array<string, int>} */
    private function reportData(array $media): array
    {
        $v = $this->validate(['actual_start' => 'required|date', 'actual_end' => 'required|date'], [
            'actual_start' => 'วันเริ่มใช้สื่อจริง', 'actual_end' => 'วันสิ้นสุดการใช้สื่อจริง',
        ]);
        $errors = [];
        if ($v['actual_end'] < $v['actual_start']) {
            $errors['actual_end'][] = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มใช้สื่อ';
        }
        if ($v['actual_start'] > date('Y-m-d')) {
            $errors['actual_start'][] = 'วันเริ่มใช้สื่อจริงต้องไม่เป็นวันในอนาคต';
        }
        [$actual, $countError] = $this->counts('actual');
        if ($countError !== null) {
            $errors['actual'][] = $countError;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['actual_start' => (string) $v['actual_start'], 'actual_end' => (string) $v['actual_end'], 'actual' => $actual];
    }

    /** จำนวนกลุ่มเป้าหมายแยกตาม 9 ประเภทความพิการ @return array{0: array<string, int>, 1: ?string} */
    private function counts(string $key): array
    {
        $input = (array) $this->request->input($key, []);
        $counts = [];
        foreach (array_keys(StudentLookup::DISABILITY_TYPES) as $type) {
            $raw = trim((string) ($input[$type] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (preg_match('/^\d{1,3}$/', $raw) !== 1) {
                return [[], 'จำนวนกลุ่มเป้าหมายต้องเป็นจำนวนเต็ม 0 - 999'];
            }
            if ((int) $raw > 0) {
                $counts[$type] = (int) $raw;
            }
        }

        return [$counts, $counts === [] ? 'กรุณากรอกจำนวนกลุ่มเป้าหมายอย่างน้อย 1 ประเภทความพิการ' : null];
    }

    private function uploadedFile(): ?array
    {
        $file = $_FILES['file'] ?? null;

        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
    }

    /** @return array{year_id: int, semester: int, media_type_id: int, disability: string} */
    private function filters(): array
    {
        $year = $this->request->query('year_id', null);
        $disability = (string) $this->request->query('disability', '');

        return [
            'year_id' => $year === null || $year === '' ? (int) (ResearchService::currentYearId() ?? 0) : max(0, (int) $year),
            'semester' => in_array((int) $this->request->query('semester', 0), [1, 2], true) ? (int) $this->request->query('semester') : 0,
            'media_type_id' => max(0, (int) $this->request->query('media_type_id', 0)),
            'disability' => isset(StudentLookup::DISABILITY_TYPES[$disability]) ? $disability : '',
        ];
    }
}
