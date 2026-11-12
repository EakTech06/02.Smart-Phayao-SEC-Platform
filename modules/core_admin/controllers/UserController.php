<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Models\EmployeeModel;
use Modules\CoreAdmin\Models\UserModel;
use Modules\CoreAdmin\Services\UserService;

final class UserController extends Controller
{
    private const LABELS = [
        'username' => 'ชื่อผู้ใช้', 'email' => 'อีเมล', 'status' => 'สถานะ',
        'employee_code' => 'รหัสบุคลากร', 'title_name' => 'คำนำหน้า', 'first_name' => 'ชื่อ',
        'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'position' => 'ตำแหน่ง',
        'academic_rank' => 'วิทยฐานะ', 'employment_type' => 'ประเภทบุคลากร', 'department_id' => 'ฝ่าย/งาน',
        'phone' => 'เบอร์โทรศัพท์', 'national_id' => 'เลขประจำตัวประชาชน', 'start_date' => 'วันที่เริ่มปฏิบัติงาน',
    ];

    public function index(): Response
    {
        return $this->view('core_admin::users/index', [
            'title' => 'ผู้ใช้งาน',
            'users' => UserModel::listVisible(),
            'scope' => Rbac::scope('core.users', 'read'),
        ]);
    }

    public function show(string $id): Response
    {
        $user = UserModel::findVisible((int) $id) ?? throw new HttpException(404);
        $temp = Session::getFlash('temp_password');

        return $this->view('core_admin::users/show', [
            'title' => 'ข้อมูลผู้ใช้งาน',
            'user' => $user,
            'canManage' => UserService::canManage($user),
            'tempPassword' => is_string($temp) ? $temp : null,
        ]);
    }

    /**
     * เพิ่มผู้ใช้งาน: ต้องเลือกจาก "ข้อมูลบุคลากร" ที่ยังไม่มีบัญชีเท่านั้น (1 บุคลากร = 1 บัญชี)
     * ยกเว้น Super Admin ที่กรอกข้อมูลเองได้ (?mode=manual) เช่น บัญชีระบบ/กรณีพิเศษ
     */
    public function create(): Response
    {
        if ($this->request->query('mode') === 'manual' && UserService::isSuperAdmin()) {
            return $this->view('core_admin::users/form', [
                'title' => 'เพิ่มผู้ใช้งาน (กรอกข้อมูลเอง)',
                'user' => null,
                'roles' => UserService::assignableRoles(),
                'departments' => UserService::departmentOptions(),
                'selectedRoles' => [],
            ]);
        }

        $employees = Rbac::scope('core.employees', 'read') !== null
            ? EmployeeModel::listVisible(['status' => 'active', 'account' => 'without'])
            : [];
        $selected = (int) $this->request->query('employee_id', 0);

        return $this->view('core_admin::users/create', [
            'title' => 'เพิ่มผู้ใช้งาน',
            'employees' => $employees,
            'selectedEmployeeId' => $selected,
            'roles' => UserService::assignableRoles(),
            'isSuperAdmin' => UserService::isSuperAdmin(),
        ]);
    }

    public function store(): Response
    {
        $employeeId = (int) $this->request->input('employee_id', 0);
        if ($employeeId > 0) {
            $employee = EmployeeModel::findVisible($employeeId);
            if ($employee === null) {
                throw new ValidationException(['employee_id' => ['ไม่พบบุคลากรที่เลือก หรือคุณไม่มีสิทธิ์เข้าถึง']]);
            }
            $username = $this->request->input('username');
            [$id, $temp] = UserService::createForEmployee($employee, is_string($username) ? $username : null, $this->roleInput());
            Session::flash('success', 'สร้างบัญชีผู้ใช้จากข้อมูลบุคลากรเรียบร้อยแล้ว');
            Session::flash('temp_password', $temp);

            return $this->redirect('/users/' . $id);
        }

        // กรอกข้อมูลเอง (สร้างบุคลากร + บัญชีพร้อมกัน) — เฉพาะ Super Admin
        if (!UserService::isSuperAdmin()) {
            throw new ValidationException(['employee_id' => ['กรุณาเลือกบุคลากรจากข้อมูลบุคลากร']]);
        }
        $data = $this->validateForm(true);
        $data['username'] = mb_strtolower((string) $data['username']);
        $this->assertDepartmentAllowed((int) $data['department_id']);

        [$id, $temp] = UserService::create($data, $this->roleInput());
        Session::flash('success', 'สร้างบัญชีผู้ใช้เรียบร้อยแล้ว');
        Session::flash('temp_password', $temp);

        return $this->redirect('/users/' . $id);
    }

    public function edit(string $id): Response
    {
        $user = $this->findManageable($id);

        return $this->view('core_admin::users/form', [
            'title' => 'แก้ไขผู้ใช้งาน',
            'user' => $user,
            'roles' => UserService::assignableRoles(),
            'departments' => UserService::departmentOptions(),
            'selectedRoles' => UserService::roleIdsOf((int) $user['id']),
        ]);
    }

    public function update(string $id): Response
    {
        $target = $this->findManageable($id);
        $data = $this->validateForm(false);
        $this->assertDepartmentAllowed((int) $data['department_id']);

        UserService::update($target, $data, $this->roleInput(), (string) $data['status']);
        Session::flash('success', 'บันทึกการแก้ไขเรียบร้อยแล้ว');

        return $this->redirect('/users/' . (int) $target['id']);
    }

    public function toggleStatus(string $id): Response
    {
        $target = $this->findManageable($id);
        $status = $target['status'] === 'active' ? 'inactive' : 'active';
        UserService::setStatus($target, $status);
        Session::flash('success', $status === 'active' ? 'เปิดใช้งานบัญชีแล้ว' : 'ระงับบัญชีและบังคับออกจากระบบแล้ว');

        return $this->redirect('/users/' . (int) $target['id']);
    }

    public function resetPassword(string $id): Response
    {
        $target = $this->findManageable($id);
        $temp = UserService::resetPassword($target);
        Session::flash('success', 'รีเซ็ตรหัสผ่านและปลดล็อกบัญชีเรียบร้อยแล้ว');
        Session::flash('temp_password', $temp);

        return $this->redirect('/users/' . (int) $target['id']);
    }

    public function destroy(string $id): Response
    {
        $target = $this->findManageable($id);
        UserService::delete($target);
        Session::flash('success', 'ลบบัญชีผู้ใช้ ' . $target['username'] . ' เรียบร้อยแล้ว');

        return $this->redirect('/users');
    }

    private function findManageable(string $id): array
    {
        $target = UserModel::findForEdit((int) $id) ?? throw new HttpException(404);
        UserService::assertCanManage($target);

        return $target;
    }

    private function validateForm(bool $creating): array
    {
        $rules = [
            'email' => 'nullable|email|max:191',
            'title_name' => 'nullable|max:50',
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'nickname' => 'nullable|max:50',
            'position' => 'nullable|max:150',
            'academic_rank' => 'nullable|max:100',
            'employment_type' => 'required|in:' . implode(',', array_keys(UserService::EMPLOYMENT_TYPES)),
            'department_id' => 'required|integer',
            'phone' => 'nullable|max:30',
            'national_id' => 'nullable|thai_id',
            'start_date' => 'nullable|date',
        ];
        if ($creating) {
            $rules = ['username' => 'required|alpha_dash|min:3|max:100'] + $rules;
        } else {
            $rules['status'] = 'required|in:active,inactive';
        }

        return $this->validate($rules, self::LABELS);
    }

    /** @return list<int> */
    private function roleInput(): array
    {
        $raw = $this->request->input('roles', []);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($raw, 'is_scalar'))));
    }

    private function assertDepartmentAllowed(int $departmentId): void
    {
        $allowed = array_map('intval', array_column(UserService::departmentOptions(), 'id'));
        if (!in_array($departmentId, $allowed, true)) {
            throw new ValidationException(['department_id' => ['คุณไม่มีสิทธิ์เลือกฝ่าย/งานนี้']]);
        }
    }
}