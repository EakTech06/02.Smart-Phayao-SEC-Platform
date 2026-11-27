<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Audit;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Response;
use Core\Session;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\ResearchReminderService;
use Modules\Academic\Services\ResearchService;
use Modules\Academic\Services\ResearchStorage;
use Modules\Academic\Services\StudentLookup;

/** งานวิจัยและพัฒนานวัตกรรม: แดชบอร์ด และระบบส่งงานวิจัย */
final class ResearchController extends Controller
{
    // ---------------------------------------------------------------- แดชบอร์ด

    public function dashboard(): Response
    {
        ResearchReminderService::runDaily();
        $filters = $this->filters();
        $mine = $this->mineView();

        $recent = [];
        if ($mine || self::canListAll()) {
            $recent = ResearchService::list($filters, $mine, 8);
        }

        return $this->view('academic::research/dashboard', [
            'title' => 'งานวิจัยและพัฒนานวัตกรรม',
            'filters' => $filters,
            'mine' => $mine,
            'stats' => ResearchService::dashboard($filters, $mine),
            'recent' => $recent,
            'canListAll' => self::canListAll(),
            'types' => ResearchService::types('research', false),
            'years' => ResearchService::years(),
            'round' => $filters['year_id'] > 0 && $filters['semester'] > 0
                ? ResearchService::round('research', $filters['year_id'], $filters['semester']) : null,
        ]);
    }

    /** ข้อมูลแดชบอร์ดล่าสุด (หน้าแดชบอร์ดดึงซ้ำเป็นระยะ ไม่ต้องรีเฟรชหน้า) */
    public function dashboardData(): Response
    {
        return Response::json(['data' => ResearchService::dashboard($this->filters(), $this->mineView()), 'at' => date('c')]);
    }

    // ---------------------------------------------------------------- รายการ

    public function index(): Response
    {
        $filters = $this->filters();
        $filters['q'] = mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
        $status = (string) $this->request->query('status', '');
        $filters['status'] = isset(ResearchLookup::STATUSES[$status]) ? $status : '';
        // ครูที่ไม่มีสิทธิ์ดูงานทั้งหมด เห็นรายการเฉพาะงานของตนเองเสมอ
        $mine = $this->mineView(!self::canListAll()) || !self::canListAll();

        return $this->view('academic::research/index', [
            'title' => $mine ? 'งานวิจัยของฉัน' : 'งานวิจัยทั้งหมด',
            'works' => ResearchService::list($filters, $mine),
            'filters' => $filters,
            'mine' => $mine,
            'canListAll' => self::canListAll(),
            'types' => ResearchService::types('research', false),
            'years' => ResearchService::years(),
        ]);
    }

    // ---------------------------------------------------------------- ส่งงาน

    public function create(): Response
    {
        $this->authorize(ResearchLookup::PERM_SUBMIT);

        return $this->renderForm(null);
    }

    public function store(): Response
    {
        $this->authorize(ResearchLookup::PERM_SUBMIT);
        $data = $this->validated(null);
        $file = $this->uploadedFile('file') ?? throw new ValidationException(['file' => ['กรุณาแนบไฟล์งานวิจัย (PDF หรือ DOCX)']]);
        $submit = $this->request->input('action') === 'submit';
        $id = ResearchService::create($data, $file, $submit);
        Session::flash('success', $submit ? 'ส่งงานวิจัยเรียบร้อยแล้ว ผู้ตรวจจะได้รับการแจ้งเตือน' : 'บันทึกร่างงานวิจัยเรียบร้อยแล้ว');

        return $this->redirect(ResearchService::link($id));
    }

    public function show(string $id): Response
    {
        $work = ResearchService::findVisible((int) $id);
        $isOwner = ResearchService::isOwner($work);

        return $this->view('academic::research/show', [
            'title' => $work['title'],
            'work' => $work,
            'isOwner' => $isOwner,
            'canReview' => ResearchService::canReview(),
            'versions' => ResearchService::versions((int) $work['id']),
            'logs' => ResearchService::statusLogs((int) $work['id']),
            'threads' => ResearchService::comments((int) $work['id']),
            'round' => ResearchService::round('research', (int) $work['academic_year_id'], (int) $work['semester']),
        ]);
    }

    public function edit(string $id): Response
    {
        $work = ResearchService::findOwned((int) $id);
        if (!in_array($work['status'], ResearchLookup::OWNER_EDITABLE, true)) {
            Session::flash('error', 'แก้ไขได้เฉพาะงานที่เป็นร่างหรือถูกส่งกลับแก้ไข');

            return $this->redirect(ResearchService::link((int) $work['id']));
        }

        return $this->renderForm($work);
    }

    public function update(string $id): Response
    {
        $work = ResearchService::findOwned((int) $id);
        ResearchService::updateMeta($work, $this->validated($work));
        Session::flash('success', 'บันทึกข้อมูลงานวิจัยเรียบร้อยแล้ว');

        return $this->redirect(ResearchService::link((int) $work['id']));
    }

    /** อัปโหลดไฟล์ฉบับใหม่ (submit=1 ส่งต่อทันที) */
    public function versionStore(string $id): Response
    {
        $work = ResearchService::findOwned((int) $id);
        $file = $this->uploadedFile('file') ?? throw new ValidationException(['file' => ['กรุณาเลือกไฟล์ฉบับใหม่']]);
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 500);
        $no = ResearchService::addVersion($work, $file, $note !== '' ? $note : null);
        $message = "อัปโหลดไฟล์เวอร์ชันที่ {$no} เรียบร้อยแล้ว";
        if ($this->request->input('submit') === '1') {
            $to = ResearchService::submit(ResearchService::findOwned((int) $id));
            $message .= ' และ' . ($to === 'resubmitted' ? 'ส่งฉบับแก้ไข' : 'ส่งงาน') . 'แล้ว';
        }
        Session::flash('success', $message);

        return $this->redirect(ResearchService::link((int) $work['id']));
    }

    public function submit(string $id): Response
    {
        $work = ResearchService::findOwned((int) $id);
        $to = ResearchService::submit($work);
        Session::flash('success', $to === 'resubmitted' ? 'ส่งฉบับแก้ไขเรียบร้อยแล้ว' : 'ส่งงานวิจัยเรียบร้อยแล้ว ผู้ตรวจจะได้รับการแจ้งเตือน');

        return $this->redirect(ResearchService::link((int) $work['id']));
    }

    /** ผู้ตรวจเปลี่ยนสถานะ */
    public function review(string $id): Response
    {
        $this->authorize(ResearchLookup::PERM_REVIEW);
        $work = ResearchService::findVisible((int) $id);
        $to = (string) $this->request->input('to_status', '');
        if (!isset(ResearchLookup::REVIEW_TRANSITIONS[$to])) {
            throw new ValidationException(['to_status' => ['กรุณาเลือกผลการตรวจ']]);
        }
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 1000);
        ResearchService::review($work, $to, $note !== '' ? $note : null);
        Session::flash('success', 'เปลี่ยนสถานะเป็น "' . ResearchLookup::statusLabel($to) . '" แล้ว และแจ้งครูเจ้าของงานแล้ว');

        return $this->redirect(ResearchService::link((int) $work['id']));
    }

    public function cancel(string $id): Response
    {
        $work = ResearchService::findOwned((int) $id);
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 1000);
        ResearchService::cancelByOwner($work, $note !== '' ? $note : null);
        Session::flash('success', 'ยกเลิกงานวิจัยแล้ว');

        return $this->redirect(ResearchService::link((int) $work['id']));
    }

    public function destroy(string $id): Response
    {
        $work = ResearchService::findOwned((int) $id);
        ResearchService::deleteDraft($work);
        Session::flash('success', 'ลบร่าง "' . $work['title'] . '" แล้ว');

        return $this->redirect('/academic/research/works?view=mine');
    }

    public function commentStore(string $id): Response
    {
        $work = ResearchService::findVisible((int) $id);
        $body = trim((string) $this->request->input('body', ''));
        if ($body === '') {
            throw new ValidationException(['body' => ['กรุณาพิมพ์ข้อความ']]);
        }
        if (mb_strlen($body) > 3000) {
            throw new ValidationException(['body' => ['ข้อความต้องไม่เกิน 3000 ตัวอักษร']]);
        }
        $parent = (int) $this->request->input('parent_id', 0);
        ResearchService::addComment($work, $body, $parent > 0 ? $parent : null);
        Session::flash('success', $parent > 0 ? 'ตอบกลับแล้ว' : 'บันทึกข้อเสนอแนะแล้ว');

        return $this->redirect(ResearchService::link((int) $work['id']) . '#feedback');
    }

    /** ไฟล์ของแต่ละเวอร์ชัน (ตรวจสิทธิ์ตามงาน) */
    public function download(string $id, string $vid): Response
    {
        $work = ResearchService::findVisible((int) $id);
        $version = ResearchService::version((int) $work['id'], (int) $vid);
        $content = ResearchStorage::read($version);
        $inline = $this->request->query('inline', '') === '1' && $version['mime_type'] === 'application/pdf';
        $name = (string) $version['original_name'];
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        Audit::log('research.file_downloaded', 'academic', 'research_versions', (int) $version['id'], null, ['work_id' => (int) $work['id']]);

        return new Response($content, 200, [
            'Content-Type' => (string) $version['mime_type'],
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name),
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    // ---------------------------------------------------------------- ภายใน

    private function renderForm(?array $work): Response
    {
        $types = ResearchService::types('research');
        // งานเดิมที่ใช้ประเภทซึ่งถูกปิดไปแล้ว ยังแสดงประเภทเดิมให้เลือกได้
        if ($work !== null && !in_array((int) $work['type_id'], array_map('intval', array_column($types, 'id')), true)) {
            $types[] = ['id' => $work['type_id'], 'name' => $work['type_name'] . ' (ปิดใช้งาน)'];
        }

        return $this->view('academic::research/form', [
            'title' => $work === null ? 'ส่งงานวิจัย' : 'แก้ไขงานวิจัย',
            'work' => $work,
            'types' => $types,
            'years' => ResearchService::years(),
            'currentYearId' => ResearchService::currentYearId(),
        ]);
    }

    /** @return array{title: string, type_id: int, academic_year_id: int, semester: int, target_count: int, keywords: ?string, disabilities: list<string>} */
    private function validated(?array $work): array
    {
        $v = $this->validate([
            'title' => 'required|max:300',
            'type_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'semester' => 'required|in:1,2',
            'target_count' => 'required|integer',
            'keywords' => 'nullable|max:500',
        ], [
            'title' => 'ชื่องานวิจัย', 'type_id' => 'ประเภทงานวิจัย', 'academic_year_id' => 'ปีการศึกษา',
            'semester' => 'ภาคเรียน', 'target_count' => 'จำนวนกลุ่มเป้าหมาย', 'keywords' => 'คำสำคัญ',
        ]);
        $errors = [];
        $typeId = (int) $v['type_id'];
        $activeTypes = array_map('intval', array_column(ResearchService::types('research'), 'id'));
        if (!in_array($typeId, $activeTypes, true) && ($work === null || $typeId !== (int) $work['type_id'])) {
            $errors['type_id'][] = 'ประเภทงานวิจัยไม่ถูกต้อง';
        }
        $yearId = (int) $v['academic_year_id'];
        if (!in_array($yearId, array_map('intval', array_column(ResearchService::years(), 'id')), true)) {
            $errors['academic_year_id'][] = 'ปีการศึกษาไม่ถูกต้อง';
        }
        $count = (int) $v['target_count'];
        if ($count < 1 || $count > 9999) {
            $errors['target_count'][] = 'จำนวนกลุ่มเป้าหมายต้องอยู่ระหว่าง 1 - 9999 คน';
        }
        $disabilities = array_values(array_unique(array_filter(
            array_map('strval', (array) $this->request->input('disabilities', [])),
            static fn (string $d): bool => isset(StudentLookup::DISABILITY_TYPES[$d])
        )));
        if ($disabilities === []) {
            $errors['disabilities'][] = 'กรุณาเลือกประเภทความพิการของกลุ่มเป้าหมายอย่างน้อย 1 ประเภท';
        }
        $keywords = self::keywords((string) ($v['keywords'] ?? ''));
        if (count($keywords) > ResearchLookup::MAX_KEYWORDS) {
            $errors['keywords'][] = 'คำสำคัญไม่เกิน ' . ResearchLookup::MAX_KEYWORDS . ' คำ';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'title' => trim((string) preg_replace('/\s+/u', ' ', (string) $v['title'])),
            'type_id' => $typeId,
            'academic_year_id' => $yearId,
            'semester' => (int) $v['semester'],
            'target_count' => $count,
            'keywords' => $keywords !== [] ? implode(', ', $keywords) : null,
            'disabilities' => $disabilities,
        ];
    }

    /** @return list<string> คำสำคัญ คั่นด้วย , หรือขึ้นบรรทัดใหม่ */
    private static function keywords(string $raw): array
    {
        $words = [];
        foreach (preg_split('/[,\n;]+/u', $raw) ?: [] as $w) {
            $w = trim((string) preg_replace('/\s+/u', ' ', $w));
            if ($w !== '' && !in_array(mb_strtolower($w), array_map('mb_strtolower', $words), true)) {
                $words[] = mb_substr($w, 0, 50);
            }
        }

        return $words;
    }

    private function uploadedFile(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
    }

    /** มุมมอง "เฉพาะของฉัน" / "ทั้งหมด" (?view=mine|all) */
    private function mineView(bool $default = false): bool
    {
        $view = (string) $this->request->query('view', '');

        return $view === 'mine' || ($view !== 'all' && $default);
    }

    /** ดูรายการ/รายละเอียดงานของผู้อื่นได้ (ครูทั่วไปเห็นเฉพาะตัวเลขสรุปและกราฟในมุมมอง "ทั้งหมด") */
    private static function canListAll(): bool
    {
        return ResearchService::canReadAll() || ResearchService::canReview();
    }

    /** @return array{year_id: int, semester: int, type_id: int, disability: string} */
    private function filters(): array
    {
        $year = $this->request->query('year_id', null);
        $disability = (string) $this->request->query('disability', '');

        return [
            // ไม่ระบุ = ปีการศึกษาปัจจุบัน, 0 = ทุกปี
            'year_id' => $year === null || $year === '' ? (int) (ResearchService::currentYearId() ?? 0) : max(0, (int) $year),
            'semester' => in_array((int) $this->request->query('semester', 0), [1, 2], true) ? (int) $this->request->query('semester') : 0,
            'type_id' => max(0, (int) $this->request->query('type_id', 0)),
            'disability' => isset(StudentLookup::DISABILITY_TYPES[$disability]) ? $disability : '',
        ];
    }
}
