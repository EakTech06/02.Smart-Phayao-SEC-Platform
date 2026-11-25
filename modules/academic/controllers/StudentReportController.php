<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Audit;
use Core\Controller;
use Core\Response;
use Modules\Academic\Services\StudentCompleteness;
use Modules\Academic\Services\StudentService;

/** Phase 5B: ตรวจความครบถ้วนของข้อมูลนักเรียน และบัตรประจำตัวคนพิการใกล้หมดอายุ */
final class StudentReportController extends Controller
{
    public function completeness(): Response
    {
        $category = $this->request->query('category', '');
        $group = $this->request->query('status_group', 'open');
        $filters = [
            'status_group' => $group === 'active' ? 'active' : 'open',
            'department_id' => (int) $this->request->query('department_id', 0),
            'category' => is_string($category) && isset(StudentCompleteness::CATEGORIES[$category]) ? $category : '',
            'only_incomplete' => $this->request->query('all', '') !== '1',
        ];
        $report = StudentCompleteness::report($filters);
        if ($report['cards'] !== []) {
            // รายการบัตรคนพิการมาจากข้อมูลอ่อนไหวที่ถอดรหัส: บันทึกการเปิดดูแบบรวม
            Audit::log('academic.students.card_expiry_viewed', 'academic', 'academic_students', null, null, ['count' => count($report['cards'])]);
        }

        return $this->view('academic::students/completeness', [
            'title' => 'ตรวจความครบถ้วนข้อมูลนักเรียน',
            'report' => $report,
            'filters' => $filters,
            'units' => StudentService::enrollmentOptions()['units'],
        ]);
    }
}
