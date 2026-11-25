<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Controller;
use Core\Response;
use Core\Session;
use Modules\Academic\Services\StudentImportService;
use Modules\Academic\Services\StudentService;

/** นำเข้าทะเบียนนักเรียนจากไฟล์ CSV (อัปโหลด → ตรวจสอบ/ตัวอย่าง → ยืนยัน) */
final class StudentImportController extends Controller
{
    public function form(): Response
    {
        StudentImportService::discard();

        return $this->render(null);
    }

    public function template(): Response
    {
        return new Response(StudentImportService::templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="students_template.csv"',
        ]);
    }

    public function preview(): Response
    {
        $file = $_FILES['file'] ?? [];

        return $this->render(StudentImportService::preview(is_array($file) ? $file : [], $this->request->input('update_existing') === '1'));
    }

    public function commit(): Response
    {
        if ($this->request->input('cancel') === '1') {
            StudentImportService::discard();
            Session::flash('info', 'ยกเลิกการนำเข้าแล้ว');

            return $this->redirect('/academic/students/import');
        }
        $result = StudentImportService::commit();
        Session::flash('success', "นำเข้าเรียบร้อย: เพิ่มนักเรียนใหม่ {$result['created']} คน, แก้ไข {$result['updated']} คน");

        return $this->redirect('/academic/students/completeness?all=1');
    }

    private function render(?array $preview): Response
    {
        return $this->view('academic::students/import', [
            'title' => 'นำเข้าข้อมูลนักเรียน (CSV)',
            'preview' => $preview,
            'units' => StudentService::enrollmentOptions()['units'],
            'currentYearId' => StudentService::currentYearId(),
        ]);
    }
}
