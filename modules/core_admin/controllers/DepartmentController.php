<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Services\DepartmentService;

/** ข้อมูลพื้นฐาน: ข้อมูลฝ่าย/งาน */
final class DepartmentController extends Controller
{
    public function index(): Response
    {
        $departments = DepartmentService::all();
        $canManage = can('core.departments.update_all');
        $usage = [];
        if ($canManage) {
            foreach ($departments as $d) {
                $usage[(int) $d['id']] = DepartmentService::usageText(DepartmentService::usage((int) $d['id']));
            }
        }

        return $this->view('core_admin::departments/index', [
            'title' => 'ข้อมูลฝ่าย/งาน',
            'departments' => $departments,
            'canManage' => $canManage,
            'usage' => $usage,
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm(null);
    }

    public function store(): Response
    {
        $data = $this->validate(DepartmentService::rules(), DepartmentService::LABELS);
        DepartmentService::create($data);
        Session::flash('success', 'เพิ่มฝ่าย/งานเรียบร้อยแล้ว');

        return $this->redirect('/departments');
    }

    public function edit(string $id): Response
    {
        return $this->renderForm($this->find($id));
    }

    public function update(string $id): Response
    {
        $department = $this->find($id);
        $data = $this->validate(DepartmentService::rules(), DepartmentService::LABELS);
        DepartmentService::update($department, $data);
        Session::flash('success', 'บันทึกข้อมูลฝ่าย/งานเรียบร้อยแล้ว');

        return $this->redirect('/departments');
    }

    /** เปิด/ปิดการใช้งาน (สวิตช์ในหน้ารายการ) */
    public function toggle(string $id): Response
    {
        $department = $this->find($id);
        $active = $this->request->input('is_active') === '1';
        DepartmentService::setActive($department, $active);
        Session::flash('success', ($active ? 'เปิด' : 'ปิด') . 'การใช้งาน ' . $department['name_th'] . ' แล้ว');

        return $this->redirect('/departments');
    }

    /** ลบได้เฉพาะฝ่าย/งานที่ไม่มีข้อมูลอ้างถึง (ไม่เช่นนั้นแจ้งเหตุผลและแนะนำให้ปิดการใช้งาน) */
    public function destroy(string $id): Response
    {
        $department = $this->find($id);
        try {
            DepartmentService::delete($department);
            Session::flash('success', 'ลบ ' . $department['name_th'] . ' เรียบร้อยแล้ว');
        } catch (ValidationException $e) {
            Session::flash('error', implode(' ', array_merge(...array_values($e->errors()))));
        }

        return $this->redirect('/departments');
    }

    private function find(string $id): array
    {
        return DepartmentService::find((int) $id) ?? throw new HttpException(404);
    }

    private function renderForm(?array $department): Response
    {
        return $this->view('core_admin::departments/form', [
            'title' => $department === null ? 'เพิ่มฝ่าย/งาน' : 'แก้ไขฝ่าย/งาน',
            'department' => $department,
            'parents' => DepartmentService::parentOptions($department !== null ? (int) $department['id'] : null),
            'nextSort' => DepartmentService::nextSortOrder(),
        ]);
    }
}
