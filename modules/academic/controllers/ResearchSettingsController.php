<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Controller;
use Core\Exceptions\ValidationException;
use Core\Response;
use Core\Session;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\ResearchService;
use Modules\Academic\Services\ResearchSettingsService;

/** ผู้ดูแล: ประเภทงานวิจัย ประเภทสื่อนวัตกรรม รอบและกำหนดการส่ง */
final class ResearchSettingsController extends Controller
{
    private const BACK = '/academic/research/settings';

    public function index(): Response
    {
        return $this->view('academic::research/settings', [
            'title' => 'ตั้งค่างานวิจัยและนวัตกรรม',
            'types' => [
                'research' => ResearchService::types('research', false),
                'innovation' => ResearchService::types('innovation', false),
            ],
            'rounds' => ResearchSettingsService::rounds(),
            'years' => ResearchService::years(),
            'currentYearId' => ResearchService::currentYearId(),
        ]);
    }

    public function typeStore(string $kind): Response
    {
        ResearchSettingsService::saveType($kind, $this->typeData(), null);
        Session::flash('success', 'เพิ่ม' . (ResearchLookup::KINDS[$kind] ?? 'ประเภท') . 'แล้ว');

        return $this->redirect(self::BACK . '#types-' . $kind);
    }

    public function typeUpdate(string $kind, string $id): Response
    {
        ResearchSettingsService::saveType($kind, $this->typeData(), (int) $id);
        Session::flash('success', 'บันทึก' . (ResearchLookup::KINDS[$kind] ?? 'ประเภท') . 'แล้ว');

        return $this->redirect(self::BACK . '#types-' . $kind);
    }

    public function roundStore(): Response
    {
        ResearchSettingsService::saveRound($this->roundData(), null);
        Session::flash('success', 'เพิ่มรอบการส่งแล้ว');

        return $this->redirect(self::BACK . '#rounds');
    }

    public function roundUpdate(string $id): Response
    {
        ResearchSettingsService::saveRound($this->roundData(), (int) $id);
        Session::flash('success', 'บันทึกรอบการส่งแล้ว');

        return $this->redirect(self::BACK . '#rounds');
    }

    public function roundDestroy(string $id): Response
    {
        ResearchSettingsService::deleteRound((int) $id);
        Session::flash('success', 'ลบรอบการส่งแล้ว');

        return $this->redirect(self::BACK . '#rounds');
    }

    private function typeData(): array
    {
        $v = $this->validate(['name' => 'required|max:150', 'sort_order' => 'nullable|integer'], ['name' => 'ชื่อประเภท', 'sort_order' => 'ลำดับ']);
        $v['is_active'] = $this->request->input('is_active') === '1';

        return $v;
    }

    private function roundData(): array
    {
        $v = $this->validate([
            'kind' => 'required|in:' . implode(',', array_keys(ResearchLookup::KINDS)),
            'academic_year_id' => 'required|integer',
            'semester' => 'required|in:1,2',
            'title' => 'required|max:200',
            'opens_on' => 'nullable|date',
            'due_on' => 'required|date',
        ], [
            'kind' => 'ประเภทงาน', 'academic_year_id' => 'ปีการศึกษา', 'semester' => 'ภาคเรียน',
            'title' => 'ชื่อรอบ', 'opens_on' => 'วันเปิดรับ', 'due_on' => 'วันครบกำหนดส่ง',
        ]);
        $v['is_active'] = $this->request->input('is_active') === '1';
        if (!in_array((int) $v['academic_year_id'], array_map('intval', array_column(ResearchService::years(), 'id')), true)) {
            throw new ValidationException(['academic_year_id' => ['ปีการศึกษาไม่ถูกต้อง']]);
        }

        return $v;
    }
}
