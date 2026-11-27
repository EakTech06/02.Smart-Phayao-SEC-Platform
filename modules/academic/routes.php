<?php

declare(strict_types=1);

use Core\Router;
use Modules\Academic\Controllers\EnrollmentPromotionController;
use Modules\Academic\Controllers\InnovationController;
use Modules\Academic\Controllers\ResearchController;
use Modules\Academic\Controllers\ResearchSettingsController;
use Modules\Academic\Controllers\StudentController;
use Modules\Academic\Controllers\StudentImportController;
use Modules\Academic\Controllers\StudentReportController;
use Modules\Academic\Services\ResearchLookup;
use Modules\Academic\Services\StudentLookup;
use Modules\Academic\Services\StudentService;
use Modules\Academic\Services\StudentStatusService;
use Modules\CoreAdmin\Services\FileService;

/** @var Router $router */

// Phase 5A: ไฟล์แนบของนักเรียนใช้สิทธิ์ตามข้อมูลนักเรียน (เอกสารทางการแพทย์ต้องมีสิทธิ์ข้อมูลอ่อนไหว)
FileService::registerAttachmentPolicy(StudentLookup::REF_TYPE,
    static fn (int $studentId, int $userId): bool => StudentService::canViewDocument($studentId, $userId, false));
FileService::registerAttachmentPolicy(StudentLookup::REF_TYPE_SENSITIVE,
    static fn (int $studentId, int $userId): bool => StudentService::canViewDocument($studentId, $userId, true));

$studentRead = 'perm:academic.students.read_all,academic.students.read_service_unit,academic.students.read_department,academic.students.read_own';
$update = 'perm:academic.students.update_all';
// แก้ไขข้อมูลนักเรียน: update_all (งานทะเบียนนักเรียน) หรือ update_own (ครูผู้รับผิดชอบ) ตรวจรายคนใน StudentController::findEditable
$edit = 'perm:academic.students.update_all,academic.students.update_own';
$editHealth = 'perm:academic.students.update_sensitive_all,academic.students.update_sensitive_own';

$router->group(['middleware' => ['auth', 'password.fresh']], static function (Router $r) use ($studentRead, $update, $edit, $editHealth): void {
    // ทะเบียนนักเรียน
    $r->get('/academic/students', [StudentController::class, 'index'], [$studentRead]);
    $r->get('/academic/students/export', [StudentController::class, 'export'], ['perm:academic.students.export_all']);
    $r->get('/academic/students/create', [StudentController::class, 'create'], ['perm:academic.students.create_all']);
    $r->post('/academic/students', [StudentController::class, 'store'], ['csrf', 'perm:academic.students.create_all']);
    $r->get('/academic/students/{id:\d+}', [StudentController::class, 'show'], [$studentRead]);
    $r->get('/academic/students/{id:\d+}/print', [StudentController::class, 'printProfile'], [$studentRead]);
    $r->get('/academic/students/{id:\d+}/excel', [StudentController::class, 'exportProfile'], [$studentRead]);
    $r->get('/academic/students/{id:\d+}/edit', [StudentController::class, 'edit'], [$edit]);
    $r->put('/academic/students/{id:\d+}', [StudentController::class, 'update'], ['csrf', $edit]);
    $r->delete('/academic/students/{id:\d+}', [StudentController::class, 'destroy'], ['csrf', 'perm:academic.students.delete_all']);
    $r->post('/academic/students/{id:\d+}/reveal-id', [StudentController::class, 'revealNationalId'], [
        'csrf', 'perm:academic.students.read_sensitive_all,academic.students.read_sensitive_service_unit,academic.students.read_sensitive_own',
    ]);

    // Phase 5B: เปลี่ยนสถานะ ตรวจความครบถ้วน ยกยอดปีการศึกษา
    $r->post('/academic/students/{id:\d+}/status', [StudentController::class, 'statusStore'], ['csrf', 'perm:' . StudentStatusService::PERMISSION]);
    $r->get('/academic/students/completeness', [StudentReportController::class, 'completeness'], [$studentRead]);

    // นำเข้าจาก CSV (แก้ไขข้อมูลเดิมต้องมีสิทธิ์ update_all เพิ่ม ตรวจใน StudentImportService)
    $import = 'perm:academic.students.create_all';
    $r->get('/academic/students/import', [StudentImportController::class, 'form'], [$import]);
    $r->get('/academic/students/import/template', [StudentImportController::class, 'template'], [$import]);
    $r->post('/academic/students/import', [StudentImportController::class, 'preview'], ['csrf', $import]);
    $r->post('/academic/students/import/confirm', [StudentImportController::class, 'commit'], ['csrf', $import]);
    $r->get('/academic/enrollments/promote', [EnrollmentPromotionController::class, 'index'], [$update]);
    $r->post('/academic/enrollments/promote', [EnrollmentPromotionController::class, 'store'], ['csrf', $update]);

    // ข้อมูลความพิการและสุขภาพ (เข้ารหัส)
    $r->get('/academic/students/{id:\d+}/health/edit', [StudentController::class, 'healthEdit'], [$editHealth]);
    $r->put('/academic/students/{id:\d+}/health', [StudentController::class, 'healthUpdate'], ['csrf', $editHealth]);

    // การรับบริการรายปีการศึกษา
    $r->get('/academic/students/{id:\d+}/enrollments/create', [StudentController::class, 'enrollmentCreate'], [$update]);
    $r->post('/academic/students/{id:\d+}/enrollments', [StudentController::class, 'enrollmentStore'], ['csrf', $update]);
    $r->get('/academic/students/{id:\d+}/enrollments/{eid:\d+}/edit', [StudentController::class, 'enrollmentEdit'], [$update]);
    $r->put('/academic/students/{id:\d+}/enrollments/{eid:\d+}', [StudentController::class, 'enrollmentUpdate'], ['csrf', $update]);
    $r->delete('/academic/students/{id:\d+}/enrollments/{eid:\d+}', [StudentController::class, 'enrollmentDestroy'], ['csrf', $update]);

    // เอกสารแนบ (Google Shared Drive)
    $r->post('/academic/students/{id:\d+}/documents', [StudentController::class, 'documentStore'], ['csrf', $edit]);
    $r->delete('/academic/students/{id:\d+}/documents/{fid:\d+}', [StudentController::class, 'documentDestroy'], ['csrf', $edit]);
});

// งานวิจัยและพัฒนานวัตกรรม
$research = 'perm:' . implode(',', [ResearchLookup::PERM_SUBMIT, ResearchLookup::PERM_READ_ALL, ResearchLookup::PERM_REVIEW, ResearchLookup::PERM_MANAGE]);
$researchWorks = 'perm:' . implode(',', [ResearchLookup::PERM_SUBMIT, ResearchLookup::PERM_READ_ALL, ResearchLookup::PERM_REVIEW]);
$researchSubmit = 'perm:' . ResearchLookup::PERM_SUBMIT;
$researchManage = 'perm:' . ResearchLookup::PERM_MANAGE;
$router->group(['middleware' => ['auth', 'password.fresh']], static function (Router $r) use ($research, $researchWorks, $researchSubmit, $researchManage): void {
    $r->get('/academic/research', [ResearchController::class, 'dashboard'], [$research]);
    $r->get('/academic/research/dashboard-data', [ResearchController::class, 'dashboardData'], [$research]);
    $r->get('/academic/research/works', [ResearchController::class, 'index'], [$researchWorks]);
    $r->get('/academic/research/works/create', [ResearchController::class, 'create'], [$researchSubmit]);
    $r->post('/academic/research/works', [ResearchController::class, 'store'], ['csrf', $researchSubmit]);
    // รายละเอียด/ไฟล์: ตรวจสิทธิ์รายงานใน ResearchService::findVisible (เจ้าของ หรือ read_all/review_all)
    $r->get('/academic/research/works/{id:\d+}', [ResearchController::class, 'show'], [$researchWorks]);
    $r->get('/academic/research/works/{id:\d+}/edit', [ResearchController::class, 'edit'], [$researchSubmit]);
    $r->put('/academic/research/works/{id:\d+}', [ResearchController::class, 'update'], ['csrf', $researchSubmit]);
    $r->delete('/academic/research/works/{id:\d+}', [ResearchController::class, 'destroy'], ['csrf', $researchSubmit]);
    $r->post('/academic/research/works/{id:\d+}/versions', [ResearchController::class, 'versionStore'], ['csrf', $researchSubmit]);
    $r->get('/academic/research/works/{id:\d+}/versions/{vid:\d+}', [ResearchController::class, 'download'], [$researchWorks]);
    $r->post('/academic/research/works/{id:\d+}/submit', [ResearchController::class, 'submit'], ['csrf', $researchSubmit]);
    $r->post('/academic/research/works/{id:\d+}/cancel', [ResearchController::class, 'cancel'], ['csrf', $researchSubmit]);
    $r->post('/academic/research/works/{id:\d+}/review', [ResearchController::class, 'review'], ['csrf', 'perm:' . ResearchLookup::PERM_REVIEW]);
    $r->post('/academic/research/works/{id:\d+}/comments', [ResearchController::class, 'commentStore'], ['csrf', $researchWorks]);

    // ผู้ดูแล: ประเภท และรอบการส่ง
    $r->get('/academic/research/settings', [ResearchSettingsController::class, 'index'], [$researchManage]);
    $r->post('/academic/research/settings/types/{kind:research|innovation}', [ResearchSettingsController::class, 'typeStore'], ['csrf', $researchManage]);
    $r->put('/academic/research/settings/types/{kind:research|innovation}/{id:\d+}', [ResearchSettingsController::class, 'typeUpdate'], ['csrf', $researchManage]);
    $r->post('/academic/research/settings/rounds', [ResearchSettingsController::class, 'roundStore'], ['csrf', $researchManage]);
    $r->put('/academic/research/settings/rounds/{id:\d+}', [ResearchSettingsController::class, 'roundUpdate'], ['csrf', $researchManage]);
    $r->delete('/academic/research/settings/rounds/{id:\d+}', [ResearchSettingsController::class, 'roundDestroy'], ['csrf', $researchManage]);
    $r->put('/academic/research/settings/innovation', [ResearchSettingsController::class, 'innovationUpdate'], ['csrf', $researchManage]);

    // สื่อนวัตกรรม: คู่มือการใช้สื่อ (ระยะที่ 1) → รายงานการใช้สื่อ (ระยะที่ 2) ตรวจสิทธิ์รายชิ้นใน InnovationService::findVisible
    $r->get('/academic/innovation/media', [InnovationController::class, 'index'], [$researchWorks]);
    $r->get('/academic/innovation/media/create', [InnovationController::class, 'create'], [$researchSubmit]);
    $r->post('/academic/innovation/media', [InnovationController::class, 'store'], ['csrf', $researchSubmit]);
    $r->get('/academic/innovation/media/{id:\d+}', [InnovationController::class, 'show'], [$researchWorks]);
    $r->get('/academic/innovation/media/{id:\d+}/edit', [InnovationController::class, 'edit'], [$researchSubmit]);
    $r->put('/academic/innovation/media/{id:\d+}', [InnovationController::class, 'update'], ['csrf', $researchSubmit]);
    $r->delete('/academic/innovation/media/{id:\d+}', [InnovationController::class, 'destroy'], ['csrf', $researchSubmit]);
    $r->get('/academic/innovation/media/{id:\d+}/report', [InnovationController::class, 'reportForm'], [$researchSubmit]);
    $r->post('/academic/innovation/media/{id:\d+}/report', [InnovationController::class, 'reportStore'], ['csrf', $researchSubmit]);
    $r->post('/academic/innovation/media/{id:\d+}/versions', [InnovationController::class, 'versionStore'], ['csrf', $researchSubmit]);
    $r->get('/academic/innovation/media/{id:\d+}/versions/{vid:\d+}', [InnovationController::class, 'download'], [$researchWorks]);
    $r->post('/academic/innovation/media/{id:\d+}/submit', [InnovationController::class, 'submit'], ['csrf', $researchSubmit]);
    $r->post('/academic/innovation/media/{id:\d+}/cancel', [InnovationController::class, 'cancel'], ['csrf', $researchSubmit]);
    $r->post('/academic/innovation/media/{id:\d+}/review', [InnovationController::class, 'review'], ['csrf', 'perm:' . ResearchLookup::PERM_REVIEW]);
    $r->post('/academic/innovation/media/{id:\d+}/comments', [InnovationController::class, 'commentStore'], ['csrf', $researchWorks]);
});
