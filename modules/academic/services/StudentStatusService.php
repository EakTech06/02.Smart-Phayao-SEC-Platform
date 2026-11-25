<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Auth;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Rbac;

/**
 * Phase 5B: เปลี่ยนสถานะนักเรียน (พักการเรียน กลับมาเรียน จบ ย้าย จำหน่าย)
 * ทุกครั้งบันทึกลง academic_student_status_logs (วันที่มีผล เหตุผล เลขที่หนังสือ) แทนการแก้สถานะทับในฟอร์ม
 * นักเรียนที่ออกแล้วยังอยู่ในตารางเดิม แสดงผ่านตัวกรองสถานะ (แทนเมนู "นักเรียนที่จำหน่ายแล้ว" ของ SET)
 */
final class StudentStatusService
{
    public const PERMISSION = 'academic.students.change_status_all';

    public const RULES = [
        'to_status' => 'required|max:20',
        'effective_on' => 'required|date',
        'reason' => 'nullable|max:255',
        'reference_no' => 'nullable|max:100',
    ];

    public const LABELS = [
        'to_status' => 'สถานะใหม่', 'effective_on' => 'วันที่มีผล', 'reason' => 'เหตุผล', 'reference_no' => 'เลขที่หนังสือ/คำสั่งอ้างอิง',
    ];

    /** @return array<string,string> สถานะที่เปลี่ยนไปได้จากสถานะปัจจุบัน => ชื่อ */
    public static function allowedTargets(string $from): array
    {
        $out = [];
        foreach (StudentLookup::STATUS_TRANSITIONS[$from] ?? [] as $to) {
            $out[$to] = StudentLookup::statusLabel($to);
        }

        return $out;
    }

    public static function canChange(): bool
    {
        return Rbac::can(self::PERMISSION);
    }

    /** @param array $student แถวจาก StudentService::findVisible (ตรวจสิทธิ์เห็นนักเรียนแล้ว) */
    public static function change(array $student, array $data): void
    {
        if (!self::canChange()) {
            throw new HttpException(403);
        }
        $id = (int) $student['id'];
        $db = Database::instance();
        $from = (string) $student['status'];
        $to = (string) $data['to_status'];
        $effective = (string) $data['effective_on'];
        $reason = $data['reason'] ?? null;

        $errors = [];
        if (!isset(self::allowedTargets($from)[$to])) {
            $errors['to_status'][] = 'เปลี่ยนจากสถานะ "' . StudentLookup::statusLabel($from) . '" เป็นสถานะที่เลือกไม่ได้';
        }
        if ($effective > date('Y-m-d', strtotime('+1 year'))) {
            $errors['effective_on'][] = 'วันที่มีผลต้องไม่เกิน 1 ปีจากวันนี้';
        }
        $last = $db->fetchColumn('SELECT MAX(effective_on) FROM academic_student_status_logs WHERE student_id = ?', [$id]);
        if ($last !== null && $effective < $last) {
            $errors['effective_on'][] = 'วันที่มีผลต้องไม่ก่อนการเปลี่ยนสถานะครั้งล่าสุด (' . thai_date((string) $last, false, true) . ')';
        }
        // ออกจากการรับบริการ/พักการเรียน/กลับเข้ารับบริการใหม่ ต้องมีเหตุผลเพื่อใช้อ้างอิงในรายงาน
        $needsReason = in_array($to, StudentLookup::LEFT_STATUSES, true) || $to === 'suspended' || in_array($from, StudentLookup::LEFT_STATUSES, true);
        if ($needsReason && ($reason === null || $reason === '')) {
            $errors['reason'][] = 'กรุณาระบุเหตุผล';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $update = ['status' => $to, 'updated_by' => Auth::id()];
        if (in_array($to, StudentLookup::LEFT_STATUSES, true)) {
            $update += ['left_at' => $effective, 'left_reason' => $reason];
        } elseif ($to === 'active') {
            $update += ['left_at' => null, 'left_reason' => null];
            if ($from === 'applicant' || in_array($from, StudentLookup::LEFT_STATUSES, true) || empty($student['admitted_at'])) {
                $update['admitted_at'] = $effective; // เริ่ม/กลับเข้ารับบริการใหม่
            }
        }

        $db->transaction(static function (Database $db) use ($id, $from, $to, $effective, $reason, $data, $update): void {
            $db->update('academic_students', $update, ['id' => $id]);
            $db->insert('academic_student_status_logs', [
                'student_id' => $id,
                'from_status' => $from,
                'to_status' => $to,
                'effective_on' => $effective,
                'reason' => $reason,
                'reference_no' => $data['reference_no'] ?? null,
                'created_by' => Auth::id(),
                'created_at' => Database::now(),
            ]);
        });
        Audit::log('academic.student.status_changed', 'academic', 'academic_students', $id,
            ['status' => $from], ['status' => $to, 'effective_on' => $effective, 'reason' => $reason]);
    }

    /** รายการแรกของประวัติเมื่อเพิ่มนักเรียน (เรียกภายใน transaction ของ StudentService::create) */
    public static function logInitial(Database $db, int $studentId, string $status, ?string $admittedAt): void
    {
        $db->insert('academic_student_status_logs', [
            'student_id' => $studentId,
            'from_status' => null,
            'to_status' => $status,
            'effective_on' => $admittedAt ?: date('Y-m-d'),
            'reason' => 'เพิ่มนักเรียนเข้าทะเบียน',
            'created_by' => Auth::id(),
            'created_at' => Database::now(),
        ]);
    }

    /** @return list<array> ประวัติสถานะ (ใหม่สุดก่อน) */
    public static function logs(int $studentId): array
    {
        return Database::instance()->fetchAll(
            'SELECT l.*, u.username AS created_by_name
             FROM academic_student_status_logs l LEFT JOIN users u ON u.id = l.created_by
             WHERE l.student_id = ? ORDER BY l.effective_on DESC, l.id DESC',
            [$studentId]
        );
    }
}
