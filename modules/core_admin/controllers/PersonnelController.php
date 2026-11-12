<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;
use Core\Response;
use Core\Session;
use Modules\CoreAdmin\Models\EmployeeModel;
use Modules\CoreAdmin\Services\PersonnelImportService;
use Modules\CoreAdmin\Services\PersonnelService;
use Modules\CoreAdmin\Services\UserService;

/** Phase 2: Personnel Base & Account Sync */
final class PersonnelController extends Controller
{
    public function index(): Response
    {
        $filters = [
            'q' => $this->stringQuery('q', 100),
            'department_id' => (int) $this->stringQuery('department_id', 10),
            'status' => $this->stringQuery('status', 20, array_keys(PersonnelService::STATUSES)),
            'employment_type' => $this->stringQuery('employment_type', 30, array_keys(UserService::EMPLOYMENT_TYPES)),
            'account' => $this->stringQuery('account', 10, ['with', 'without']),
        ];
        $bulk = Session::getFlash('bulk_accounts');

        return $this->view('core_admin::personnel/index', [
            'title' => 'ข้อมูลบุคลากร',
            'employees' => EmployeeModel::listVisible($filters),
            'counts' => EmployeeModel::statusCounts(),
            'filters' => $filters,
            'departments' => Database::instance()->fetchAll('SELECT id, name_th FROM departments WHERE is_active = 1 ORDER BY sort_order, id'),
            'scope' => Rbac::scope('core.employees', 'read'),
            'canSync' => Rbac::can('core.users.create_all'),
            'roles' => Rbac::can('core.users.create_all') ? UserService::assignableRoles() : [],
            'bulkResult' => is_array($bulk) ? $bulk : null,
        ]);
    }

    public function show(string $id): Response
    {
        return $this->renderShow($this->findVisible($id));
    }

    public function create(): Response
    {
        $this->authorize('core.employees.create_all');

        return $this->renderForm(null);
    }

    public function store(): Response
    {
        $this->authorize('core.employees.create_all');
        $data = $this->validate(PersonnelService::rules(), PersonnelService::LABELS);
        PersonnelService::assertDepartmentAllowed((int) $data['department_id']);

        $id = PersonnelService::create($data);
        Session::flash('success', 'เพิ่มข้อมูลบุคลากรเรียบร้อยแล้ว');

        return $this->redirect('/personnel/' . $id);
    }

    public function edit(string $id): Response
    {
        $employee = $this->findUpdatable($id);

        return $this->renderForm($employee);
    }

    public function update(string $id): Response
    {
        $employee = $this->findUpdatable($id);
        $data = $this->validate(PersonnelService::rules(), PersonnelService::LABELS);
        PersonnelService::assertDepartmentAllowed((int) $data['department_id']);

        PersonnelService::update($employee, $data);
        Session::flash('success', 'บันทึกข้อมูลบุคลากรเรียบร้อยแล้ว'
            . ($data['status'] !== 'active' && $employee['user_id'] !== null && $employee['status'] === 'active'
                ? ' และระงับบัญชีผู้ใช้ที่ผูกไว้แล้ว' : ''));

        return $this->redirect('/personnel/' . (int) $employee['id']);
    }

    public function destroy(string $id): Response
    {
        $this->authorize('core.employees.delete_all');
        $employee = $this->findVisible($id);
        PersonnelService::delete($employee);
        Session::flash('success', 'ลบข้อมูลบุคลากร ' . PersonnelService::displayName($employee) . ' เรียบร้อยแล้ว');

        return $this->redirect('/personnel');
    }

    /** POST เพื่อไม่ให้เลขบัตรเต็มติดอยู่ใน URL/ประวัติเบราว์เซอร์ และแสดงผลครั้งเดียวโดยไม่เก็บใน Session */
    public function revealNationalId(string $id): Response
    {
        $this->authorize('core.employees.read_sensitive_all');
        $employee = $this->findVisible($id);

        return $this->renderShow($employee, PersonnelService::revealNationalId($employee));
    }

    // ------------------------------------------------------------------ Account Sync

    public function createAccount(string $id): Response
    {
        $this->authorize('core.users.create_all');
        $employee = $this->findVisible($id);
        $username = $this->request->input('username');

        [$userId, $temp] = UserService::createForEmployee(
            $employee,
            is_string($username) ? $username : null,
            $this->intList('roles')
        );
        Session::flash('success', 'สร้างบัญชีผู้ใช้จากข้อมูลบุคลากรเรียบร้อยแล้ว');
        Session::flash('temp_password', $temp);

        return $this->redirect('/users/' . $userId);
    }

    public function linkAccount(string $id): Response
    {
        $this->authorize('core.users.update_all');
        $employee = $this->findVisible($id);
        UserService::linkEmployee((int) $this->request->input('user_id', 0), $employee);
        Session::flash('success', 'ผูกบัญชีผู้ใช้กับบุคลากรเรียบร้อยแล้ว');

        return $this->redirect('/personnel/' . (int) $employee['id']);
    }

    /** สร้างบัญชีให้บุคลากรหลายคนพร้อมกัน (ชื่อผู้ใช้ = รหัสบุคลากร) แสดงรหัสผ่านชั่วคราวครั้งเดียว */
    public function bulkAccounts(): Response
    {
        $this->authorize('core.users.create_all');
        $ids = array_slice($this->intList('employee_ids'), 0, 200);
        $roleId = (int) $this->request->input('role_id', 0);
        if ($ids === []) {
            throw new ValidationException(['employee_ids' => ['กรุณาเลือกบุคลากรอย่างน้อย 1 คน']]);
        }

        $created = [];
        $failed = [];
        foreach ($ids as $employeeId) {
            $employee = EmployeeModel::findVisible($employeeId);
            if ($employee === null) {
                continue;
            }
            $name = PersonnelService::displayName($employee);
            try {
                [, $temp] = UserService::createForEmployee($employee, null, [$roleId]);
                $created[] = ['name' => $name, 'username' => UserService::defaultUsername($employee), 'password' => $temp];
            } catch (ValidationException $e) {
                $failed[] = ['name' => $name, 'reason' => implode(', ', array_merge(...array_values($e->errors())))];
            }
        }

        Session::flash('bulk_accounts', ['created' => $created, 'failed' => $failed]);
        Session::flash($created !== [] ? 'success' : 'warning', 'สร้างบัญชีสำเร็จ ' . count($created) . ' รายการ'
            . ($failed !== [] ? ' / ไม่สำเร็จ ' . count($failed) . ' รายการ' : ''));

        return $this->redirect('/personnel');
    }

    // ------------------------------------------------------------------ CSV Import

    public function importForm(): Response
    {
        $this->authorize('core.employees.import_all');
        PersonnelImportService::discard();

        return $this->view('core_admin::personnel/import', [
            'title' => 'นำเข้าข้อมูลบุคลากร',
            'preview' => null,
            'departments' => PersonnelService::departmentOptions(),
        ]);
    }

    public function importTemplate(): Response
    {
        $this->authorize('core.employees.import_all');

        return new Response(PersonnelImportService::templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="personnel_template.csv"',
        ]);
    }

    public function importPreview(): Response
    {
        $this->authorize('core.employees.import_all');
        $file = $_FILES['file'] ?? [];
        $preview = PersonnelImportService::preview(is_array($file) ? $file : [], $this->request->input('update_existing') === '1');

        return $this->view('core_admin::personnel/import', [
            'title' => 'นำเข้าข้อมูลบุคลากร',
            'preview' => $preview,
            'departments' => PersonnelService::departmentOptions(),
        ]);
    }

    public function importCommit(): Response
    {
        $this->authorize('core.employees.import_all');
        if ($this->request->input('cancel') === '1') {
            PersonnelImportService::discard();
            Session::flash('info', 'ยกเลิกการนำเข้าแล้ว');

            return $this->redirect('/personnel/import');
        }
        $result = PersonnelImportService::commit();
        Session::flash('success', "นำเข้าเรียบร้อย: เพิ่มใหม่ {$result['created']} รายการ, แก้ไข {$result['updated']} รายการ");

        return $this->redirect('/personnel');
    }

    // ------------------------------------------------------------------ ภายใน

    private function renderShow(array $employee, ?string $nationalId = null): Response
    {
        $canSync = Rbac::can('core.users.create_all');

        return $this->view('core_admin::personnel/show', [
            'title' => 'ข้อมูลบุคลากร',
            'employee' => $employee,
            'nationalId' => $nationalId,
            'canUpdate' => PersonnelService::canUpdate($employee),
            'canDelete' => PersonnelService::canDelete($employee),
            'canReveal' => Rbac::can('core.employees.read_sensitive_all') && (int) $employee['has_national_id_encrypted'] === 1,
            'canSync' => $canSync && $employee['user_id'] === null && $employee['status'] === 'active',
            'canLink' => Rbac::can('core.users.update_all') && $employee['user_id'] === null,
            'roles' => $canSync ? UserService::assignableRoles() : [],
            'unlinkedUsers' => Rbac::can('core.users.update_all') && $employee['user_id'] === null ? EmployeeModel::unlinkedUsers() : [],
            'defaultUsername' => UserService::defaultUsername($employee),
        ]);
    }

    private function renderForm(?array $employee): Response
    {
        return $this->view('core_admin::personnel/form', [
            'title' => $employee === null ? 'เพิ่มบุคลากร' : 'แก้ไขข้อมูลบุคลากร',
            'employee' => $employee,
            'departments' => PersonnelService::departmentOptions(),
            'canChangeStatus' => $employee === null || PersonnelService::canManageLinkedAccount($employee),
        ]);
    }

    private function findVisible(string $id): array
    {
        return EmployeeModel::findVisible((int) $id) ?? throw new HttpException(404);
    }

    private function findUpdatable(string $id): array
    {
        $employee = $this->findVisible($id);
        if (!PersonnelService::canUpdate($employee)) {
            throw new HttpException(403);
        }

        return $employee;
    }

    /** @return list<int> */
    private function intList(string $key): array
    {
        $raw = $this->request->input($key, []);
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', array_filter($raw, 'is_scalar')), static fn (int $v): bool => $v > 0)));
    }

    /** @param list<string>|null $allowed */
    private function stringQuery(string $key, int $max, ?array $allowed = null): string
    {
        $value = $this->request->query($key, '');
        $value = is_string($value) ? mb_substr(trim($value), 0, $max) : '';
        if ($allowed !== null && !in_array($value, $allowed, true)) {
            return '';
        }

        return $value;
    }
}
