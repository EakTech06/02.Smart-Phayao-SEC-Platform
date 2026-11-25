<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Controller;
use Core\Database;
use Core\Response;
use Core\Session;
use Modules\Academic\Services\EnrollmentPromotionService;
use Modules\Academic\Services\StudentService;

/** Phase 5B: ยกยอดการรับบริการไปปีการศึกษาใหม่ (เลื่อนชั้น/ย้ายห้องทั้งห้อง) */
final class EnrollmentPromotionController extends Controller
{
    public function index(): Response
    {
        [$defaultFrom, $defaultTo] = EnrollmentPromotionService::defaultYears();
        $from = (int) $this->request->query('from_year_id', 0) ?: (int) $defaultFrom;
        $to = (int) $this->request->query('to_year_id', 0) ?: (int) $defaultTo;
        $room = (int) $this->request->query('from_room_id', 0) ?: null;
        $unit = (int) $this->request->query('department_id', 0) ?: null;
        $options = StudentService::enrollmentOptions();

        return $this->view('academic::students/promote', [
            'title' => 'ยกยอดการรับบริการปีการศึกษาใหม่',
            'fromYearId' => $from,
            'toYearId' => $to,
            'fromRoomId' => $room,
            'departmentId' => $unit,
            'options' => $options,
            'candidates' => $from > 0 && $to > 0 && $from !== $to
                ? EnrollmentPromotionService::candidates($from, $to, $room, $unit) : [],
        ]);
    }

    public function store(): Response
    {
        $raw = $this->request->input('student_ids', []);
        $ids = is_array($raw) ? array_values(array_filter($raw, 'is_scalar')) : [];
        $from = (int) $this->request->input('from_year_id', 0);
        $to = (int) $this->request->input('to_year_id', 0);
        $room = (string) $this->request->input('target_room', '');
        $result = EnrollmentPromotionService::promote(
            $from, $to, $ids, $room,
            (string) $this->request->input('grade_mode', 'same'),
            $this->request->input('copy_teacher', '') === '1'
        );
        $year = Database::instance()->fetchColumn('SELECT name FROM academic_years WHERE id = ?', [$to]);
        $message = "ยกยอดไป{$year} {$result['created']} คน";
        if ($result['skipped'] > 0) {
            $message .= " · ข้าม {$result['skipped']} คน (มีข้อมูลปีปลายทางแล้ว/ไม่อยู่ในสถานะที่ยกยอดได้)";
        }
        if ($result['no_room'] > 0) {
            $message .= " · {$result['no_room']} คนยังไม่มีห้องบริการในปีใหม่ (ไม่พบห้องรหัสเดียวกัน)";
        }
        Session::flash($result['created'] > 0 ? 'success' : 'warning', $message);

        return $this->redirect('/academic/enrollments/promote?' . http_build_query(['from_year_id' => $from, 'to_year_id' => $to]));
    }
}
