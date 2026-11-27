<?php

declare(strict_types=1);

use Core\Router;
use Modules\Academic\Controllers\EnrollmentPromotionController;
use Modules\Academic\Controllers\StudentController;
use Modules\Academic\Controllers\StudentImportController;
use Modules\Academic\Controllers\StudentReportController;
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
