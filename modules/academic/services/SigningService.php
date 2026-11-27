<?php

declare(strict_types=1);

namespace Modules\Academic\Services;

use Core\Audit;
use Core\Config;
use Core\Database;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;
use Core\Password;
use Core\Pdf\PdfException;
use Core\Pdf\PdfReader;
use Core\Pdf\PdfStamper;
use Core\Pdf\QrCode;
use Core\Session;
use Core\TwoFactor;
use Modules\CoreAdmin\Services\SignatureService;

/**
 * ลงนาม 4 ลำดับใน PDF (ใช้ร่วมกันทั้งงานวิจัย คู่มือ และรายงานการใช้สื่อ)
 *
 * บริบทเอกสาร ($ctx) สร้างโดย ResearchService::signContext / InnovationService::signContext:
 *   type (research|manual|report), id, owner_user_id, owner_name, title, year_be, status,
 *   version (แถวไฟล์ต้นฉบับล่าสุด), dir, stem, base (URL หน้างาน)
 *
 * - ไฟล์ต้นฉบับเก็บไว้เสมอ แต่ละลำดับที่ลงนามได้ไฟล์ใหม่ (ประทับต่อจากไฟล์ของลำดับก่อนหน้า)
 * - ลงนามครบ 4 ลำดับ: เพิ่มเลขที่เอกสาร + QR Code ท้ายหน้าสุดท้าย และล็อกเอกสาร
 */
final class SigningService
{
    // ---------------------------------------------------------------- ตำแหน่งลายเซ็น

    /**
     * กรอบของทั้ง 4 ลำดับสำหรับไฟล์ต้นฉบับปัจจุบัน (ยังไม่บันทึก = คัดลอกจากเวอร์ชันก่อนหน้า หรือค่าเริ่มต้น)
     * @return array<int, array{page: int, x: float, y: float, w: float, h: float, saved: bool}>
     */
    public static function boxes(array $ctx, ?int $pageCount = null): array
    {
        $db = Database::instance();
        $versionId = (int) $ctx['version']['id'];
        $rows = $db->fetchAll(
            'SELECT * FROM research_sign_boxes WHERE doc_type = ? AND doc_id = ? AND version_id = ?',
            [$ctx['type'], (int) $ctx['id'], $versionId]
        );
        $saved = [];
        foreach ($rows as $r) {
            $saved[(int) $r['sign_order']] = self::boxRow($r, true);
        }
        $pageCount ??= self::pageCount($ctx);
        $previous = [];
        if (count($saved) < 4) {
            // ฉบับแก้ไข: ใช้ตำแหน่งของเวอร์ชันก่อนหน้าเป็นค่าตั้งต้น
            $prevVersion = $db->fetchColumn(
                'SELECT version_id FROM research_sign_boxes WHERE doc_type = ? AND doc_id = ? AND version_id <> ? ORDER BY version_id DESC LIMIT 1',
                [$ctx['type'], (int) $ctx['id'], $versionId]
            );
            if ($prevVersion !== null) {
                foreach ($db->fetchAll('SELECT * FROM research_sign_boxes WHERE doc_type = ? AND doc_id = ? AND version_id = ?', [$ctx['type'], (int) $ctx['id'], (int) $prevVersion]) as $r) {
                    $previous[(int) $r['sign_order']] = self::boxRow($r, false);
                }
            }
        }
        $boxes = [];
        foreach (array_keys(SigningLookup::ORDERS) as $order) {
            $box = $saved[$order] ?? $previous[$order] ?? (SigningLookup::defaultBox($order, $pageCount) + ['saved' => false]);
            $box['page'] = min(max(1, $box['page']), $pageCount);
            $boxes[$order] = $box;
        }

        return $boxes;
    }

    public static function boxesComplete(array $ctx): bool
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM research_sign_boxes WHERE doc_type = ? AND doc_id = ? AND version_id = ?',
            [$ctx['type'], (int) $ctx['id'], (int) $ctx['version']['id']]
        ) === count(SigningLookup::ORDERS);
    }

    /**
     * บันทึกตำแหน่งกรอบเฉพาะลำดับที่อนุญาต
     * @param array<int|string, mixed> $input ลำดับ => {page, x, y, w, h}
     * @param list<int> $orders ลำดับที่ผู้ใช้นี้แก้ได้
     */
    public static function saveBoxes(array $ctx, array $input, array $orders, int $userId): void
    {
        if ($orders === []) {
            throw new HttpException(403);
        }
        $clean = self::cleanBoxes($ctx, $input, $orders);
        if ($clean === []) {
            return;
        }
        $db = Database::instance();
        foreach ($clean as $order => $b) {
            $db->query(
                'INSERT INTO research_sign_boxes (doc_type, doc_id, version_id, sign_order, page, x, y, w, h, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE page = VALUES(page), x = VALUES(x), y = VALUES(y), w = VALUES(w), h = VALUES(h), updated_by = VALUES(updated_by)',
                [$ctx['type'], (int) $ctx['id'], (int) $ctx['version']['id'], $order, $b['page'], $b['x'], $b['y'], $b['w'], $b['h'], $userId]
            );
        }
        Audit::log('research.sign_boxes_saved', 'academic', self::table($ctx), (int) $ctx['id'], null,
            ['doc' => $ctx['type'], 'version' => (int) $ctx['version']['version_no'], 'orders' => array_keys($clean)]);
    }

    /**
     * ตรวจตำแหน่งกรอบที่ส่งมา (เฉพาะลำดับใน $orders) ต้องอยู่ภายในหน้าเอกสาร
     * @return array<int, array{page: int, x: float, y: float, w: float, h: float}>
     */
    public static function cleanBoxes(array $ctx, array $input, array $orders): array
    {
        $pageCount = self::pageCount($ctx);
        $clean = [];
        foreach ($orders as $order) {
            $b = $input[$order] ?? $input[(string) $order] ?? null;
            if (!is_array($b)) {
                continue;
            }
            $page = (int) ($b['page'] ?? 0);
            $x = (float) ($b['x'] ?? -1);
            $y = (float) ($b['y'] ?? -1);
            $w = (float) ($b['w'] ?? 0);
            $h = (float) ($b['h'] ?? 0);
            if ($page < 1 || $page > $pageCount || $w < SigningLookup::MIN_W || $h < SigningLookup::MIN_H
                || $x < 0 || $y < 0 || $x + $w > 1.0001 || $y + $h > 1.0001 || !is_finite($x + $y + $w + $h)) {
                throw new ValidationException(['boxes' => ['ตำแหน่งลายเซ็นลำดับที่ ' . $order . ' ไม่ถูกต้อง (ต้องอยู่ภายในหน้าเอกสาร)']]);
            }
            $clean[$order] = ['page' => $page, 'x' => round($x, 5), 'y' => round($y, 5), 'w' => round(min($w, 1 - $x), 5), 'h' => round(min($h, 1 - $y), 5)];
        }

        return $clean;
    }

    // ---------------------------------------------------------------- ลายเซ็น

    /** ลายเซ็นที่ยังมีผลของเอกสาร (ลำดับ => แถว) */
    public static function signatures(string $type, int $docId): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT * FROM research_signatures WHERE doc_type = ? AND doc_id = ? AND revoked_at IS NULL ORDER BY sign_order',
            [$type, $docId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['sign_order']] = $r;
        }

        return $out;
    }

    /** ประวัติการลงนามทั้งหมด (รวมที่ถูกยกเลิก) ล่าสุดก่อน */
    public static function history(string $type, int $docId): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM research_signatures WHERE doc_type = ? AND doc_id = ? ORDER BY id DESC',
            [$type, $docId]
        );
    }

    public static function signature(string $type, int $docId, int $signatureId): array
    {
        return Database::instance()->fetch(
            'SELECT * FROM research_signatures WHERE id = ? AND doc_type = ? AND doc_id = ?',
            [$signatureId, $type, $docId]
        ) ?? throw new HttpException(404);
    }

    /** สรุปการลงนามสำหรับหน้ารายละเอียด: ลายเซ็นที่มีผล เอกสารที่ออกเลข และประวัติ */
    public static function summary(string $type, int $docId): array
    {
        return [
            'type' => $type,
            'signatures' => self::signatures($type, $docId),
            'document' => self::document($type, $docId),
            'history' => self::history($type, $docId),
        ];
    }

    /** เอกสารที่ลงนามครบแล้ว (ยังไม่ถูกยกเลิก) */
    public static function document(string $type, int $docId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM research_documents WHERE doc_type = ? AND doc_id = ? AND revoked_at IS NULL ORDER BY id DESC LIMIT 1',
            [$type, $docId]
        );
    }

    /** ตรวจความพร้อมก่อนลงนามลำดับ $order (คืนข้อความปัญหา หรือ null) */
    public static function problem(array $ctx, int $order, int $userId): ?string
    {
        if (!SigningLookup::canSignOrder($order, (int) $ctx['owner_user_id'], $userId)) {
            return 'คุณไม่มีสิทธิ์ลงนามลำดับที่ ' . $order;
        }
        if (($ctx['version']['mime_type'] ?? '') !== 'application/pdf') {
            return 'ไฟล์ฉบับล่าสุดไม่ใช่ PDF กรุณาอัปโหลดไฟล์ PDF ก่อนลงนาม';
        }
        if (!SignatureService::has($userId)) {
            return 'ยังไม่มีภาพลายเซ็นในโปรไฟล์ กรุณาบันทึกลายเซ็นที่หน้าโปรไฟล์ก่อน';
        }
        if ($order > 1) {
            $signed = self::signatures($ctx['type'], (int) $ctx['id']);
            for ($i = 1; $i < $order; $i++) {
                if (!isset($signed[$i]) || (int) $signed[$i]['version_id'] !== (int) $ctx['version']['id']) {
                    return 'ลำดับที่ ' . $i . ' ยังไม่ได้ลงนามในไฟล์ฉบับนี้';
                }
            }
            if (isset($signed[$order])) {
                return 'ลำดับที่ ' . $order . ' ลงนามแล้ว';
            }
        }

        return null;
    }

    public static function assertCanSign(array $ctx, int $order, int $userId): void
    {
        $problem = self::problem($ctx, $order, $userId);
        if ($problem !== null) {
            throw new ValidationException(['status' => [$problem]]);
        }
    }

    /** ก่อนครูลงนามลำดับที่ 1 และส่ง: PDF + ตำแหน่งครบ 4 ลำดับ + มีลายเซ็นในโปรไฟล์ */
    public static function assertSubmittable(array $ctx): void
    {
        if (($ctx['version']['mime_type'] ?? '') !== 'application/pdf') {
            throw new ValidationException(['file' => ['ไฟล์ .docx ใช้เป็นฉบับร่างเท่านั้น กรุณาอัปโหลดไฟล์ PDF ฉบับสมบูรณ์ก่อนลงนามและส่ง']]);
        }
        if (!self::boxesComplete($ctx)) {
            throw new ValidationException(['boxes' => ['กรุณากำหนดตำแหน่งลายเซ็นให้ครบทั้ง 4 ลำดับและบันทึกก่อนส่ง']]);
        }
        self::assertCanSign($ctx, 1, (int) $ctx['owner_user_id']);
    }

    /** ก่อนผู้ตรวจให้ "ผ่านการตรวจ": ไฟล์ฉบับล่าสุดต้องลงนามลำดับที่ 1 แล้ว */
    public static function assertFirstSigned(array $ctx, string $field = 'to_status'): void
    {
        $first = self::signatures($ctx['type'], (int) $ctx['id'])[1] ?? null;
        if ($first === null || (int) $first['version_id'] !== (int) $ctx['version']['id']) {
            throw new ValidationException([$field => ['ไฟล์ฉบับล่าสุดยังไม่ได้ลงนามลำดับที่ 1 โดยครูผู้จัดทำ']]);
        }
    }

    /**
     * ลำดับที่ผู้ใช้แก้ตำแหน่งกรอบได้ในสถานะนี้
     * เจ้าของ: ทุกลำดับขณะแก้ไขได้ / ผู้ตรวจ: ลำดับที่ยังไม่ลงนามระหว่างตรวจหรือลงนาม / ผู้ลงนาม: กรอบของตนขณะรอลงนาม
     * @return list<int>
     */
    public static function editableOrders(array $ctx, int $userId, bool $isReviewer): array
    {
        if (($ctx['version']['mime_type'] ?? '') !== 'application/pdf') {
            return [];
        }
        if (!empty($ctx['owner_editable'])) {
            return (int) $ctx['owner_user_id'] === $userId ? array_keys(SigningLookup::ORDERS) : [];
        }
        $signed = array_keys(array_filter(self::signatures($ctx['type'], (int) $ctx['id']),
            static fn (array $s): bool => (int) $s['version_id'] === (int) $ctx['version']['id']));
        $orders = [];
        if ($isReviewer && (!empty($ctx['in_review']) || $ctx['awaiting'] !== null)) {
            $orders = [2, 3, 4];
        }
        if ($ctx['awaiting'] !== null && SigningLookup::canSignOrder((int) $ctx['awaiting'], (int) $ctx['owner_user_id'], $userId)) {
            $orders[] = (int) $ctx['awaiting'];
        }

        return array_values(array_diff(array_unique($orders), $signed));
    }

    /** ยืนยันตัวตนด้วยรหัสผ่านก่อนลงนาม (จำกัดการใส่ผิด) */
    public static function confirmPassword(int $userId, string $password): void
    {
        $now = time();
        $fails = array_values(array_filter((array) Session::get('sign_password_fails', []), static fn ($t): bool => is_int($t) && $t > $now - 900));
        if (count($fails) >= SigningLookup::MAX_PASSWORD_FAILS) {
            throw new ValidationException(['password' => ['ใส่รหัสผ่านผิดหลายครั้ง กรุณารอ 15 นาทีแล้วลองใหม่']]);
        }
        if ($password === '') {
            throw new ValidationException(['password' => ['กรุณาใส่รหัสผ่านเพื่อยืนยันการลงนาม']]);
        }
        $hash = (string) Database::instance()->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        $ok = $hash !== '' && Password::verify($password, $hash);
        // ผู้ที่เปิดใช้ 2FA ใส่รหัส OTP 6 หลักจากแอปยืนยันตัวตนแทนรหัสผ่านได้
        if (!$ok && preg_match('/^\d{6}$/', $password) === 1 && TwoFactor::isEnabled($userId)) {
            $ok = TwoFactor::verify($userId, $password);
        }
        if (!$ok) {
            $fails[] = $now;
            Session::set('sign_password_fails', $fails);
            Audit::log('research.sign_password_failed', 'academic', 'users', $userId);
            throw new ValidationException(['password' => ['รหัสผ่าน (หรือรหัส OTP) ไม่ถูกต้อง']]);
        }
        Session::forget('sign_password_fails');
    }

    /** ตัวอย่างเอกสารพร้อมลายเซ็นของลำดับนี้ (ไม่บันทึก) */
    public static function preview(array $ctx, int $order, int $userId, ?array $boxesOverride = null): string
    {
        $problem = self::problem($ctx, $order, $userId);
        if ($problem !== null) {
            throw new ValidationException(['status' => [$problem]]);
        }
        $boxes = $boxesOverride ?? self::boxes($ctx);

        return self::stamped($ctx, $order, $userId, $boxes, $order === 4 ? ['doc_no' => 'ตัวอย่าง', 'url' => self::verifyUrl(str_repeat('0', 32))] : null);
    }

    /**
     * ลงนามลำดับ $order: ประทับลงไฟล์ของลำดับก่อนหน้า บันทึกไฟล์ใหม่และประวัติ (ลำดับ 4 ออกเลขที่เอกสาร + QR)
     * ผู้เรียกต้องเรียกภายในธุรกรรมเดียวกับการเปลี่ยนสถานะ
     * @return array{signature_id: int, document: ?array}
     */
    public static function sign(array $ctx, int $order, int $userId): array
    {
        $problem = self::problem($ctx, $order, $userId);
        if ($problem !== null) {
            throw new ValidationException(['status' => [$problem]]);
        }
        if (!self::boxesComplete($ctx)) {
            throw new ValidationException(['boxes' => ['กรุณากำหนดตำแหน่งลายเซ็นให้ครบทั้ง 4 ลำดับก่อนลงนาม']]);
        }
        $db = Database::instance();
        $boxes = self::boxes($ctx);
        $document = null;
        $footer = null;
        if ($order === 4) {
            $token = bin2hex(random_bytes(16));
            $docId = $db->insert('research_documents', [
                'doc_type' => $ctx['type'],
                'doc_id' => (int) $ctx['id'],
                'version_id' => (int) $ctx['version']['id'],
                'signature_id' => 0,
                'doc_no' => 'TMP-' . $token,
                'verify_token' => $token,
                'title' => mb_substr((string) $ctx['title'], 0, 300),
                'owner_name' => mb_substr((string) $ctx['owner_name'], 0, 200),
                'checksum_sha256' => str_repeat('0', 64),
                'approved_at' => Database::now(),
            ]);
            $docNo = sprintf('%s%s-%05d', SigningLookup::DOC_PREFIX[$ctx['type']], (string) $ctx['year_be'], $docId);
            $footer = ['doc_no' => $docNo, 'url' => self::verifyUrl($token)];
            $document = ['id' => $docId, 'doc_no' => $docNo, 'verify_token' => $token];
        }
        [$name, $position] = self::signer($userId, $order);
        $bytes = self::stamped($ctx, $order, $userId, $boxes, $footer);
        $stored = ResearchStorage::storeBytes($bytes, (string) $ctx['dir'], $ctx['stem'] . '_s' . $order);
        $signatureId = $db->insert('research_signatures', $stored + [
            'doc_type' => $ctx['type'],
            'doc_id' => (int) $ctx['id'],
            'version_id' => (int) $ctx['version']['id'],
            'version_no' => (int) $ctx['version']['version_no'],
            'sign_order' => $order,
            'user_id' => $userId,
            'signer_name' => mb_substr($name, 0, 200),
            'signer_position' => mb_substr($position, 0, 200),
            'signed_at' => Database::now(),
            'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);
        if ($document !== null) {
            $db->update('research_documents', [
                'signature_id' => $signatureId,
                'doc_no' => $document['doc_no'],
                'checksum_sha256' => $stored['checksum_sha256'],
            ], ['id' => $document['id']]);
        }
        Audit::log('research.signed', 'academic', self::table($ctx), (int) $ctx['id'], null,
            ['doc' => $ctx['type'], 'order' => $order, 'version' => (int) $ctx['version']['version_no'], 'signature_id' => $signatureId, 'doc_no' => $document['doc_no'] ?? null]);

        return ['signature_id' => $signatureId, 'document' => $document];
    }

    /** ยกเลิกลายเซ็นทั้งหมดของเอกสาร (ส่งฉบับแก้ไข / ผู้ดูแลยกเลิกการอนุมัติ) และเอกสารที่ออกเลขแล้ว */
    public static function revokeAll(string $type, int $docId, ?int $userId, string $reason): int
    {
        $db = Database::instance();
        $now = Database::now();
        $n = $db->query(
            'UPDATE research_signatures SET revoked_at = ?, revoked_by = ?, revoke_reason = ? WHERE doc_type = ? AND doc_id = ? AND revoked_at IS NULL',
            [$now, $userId, mb_substr($reason, 0, 500), $type, $docId]
        )->rowCount();
        $db->query(
            'UPDATE research_documents SET revoked_at = ?, revoked_by = ?, revoke_reason = ? WHERE doc_type = ? AND doc_id = ? AND revoked_at IS NULL',
            [$now, $userId, mb_substr($reason, 0, 500), $type, $docId]
        );

        return $n;
    }

    /** ไฟล์ที่ลงนามแล้ว (ตรวจ checksum) */
    public static function readSigned(array $signature): string
    {
        return ResearchStorage::read($signature);
    }

    /** ไฟล์ที่จะแสดงในหน้าลงนาม: ไฟล์ล่าสุดที่ลงนามในฉบับปัจจุบัน หรือไฟล์ต้นฉบับ */
    public static function latestSignedFor(array $ctx): ?array
    {
        $latest = null;
        foreach (self::signatures($ctx['type'], (int) $ctx['id']) as $sig) {
            if ((int) $sig['version_id'] === (int) $ctx['version']['id']) {
                $latest = $sig;
            }
        }

        return $latest;
    }

    // ---------------------------------------------------------------- ตรวจสอบเอกสาร (QR)

    public static function verifyUrl(string $token): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($base === '') {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $base = ($https ? 'https' : 'http') . '://' . $host . base_url();
        }

        return $base . '/verify/' . $token;
    }

    public static function findByToken(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $doc = Database::instance()->fetch('SELECT * FROM research_documents WHERE verify_token = ?', [$token]);
        if ($doc === null) {
            return null;
        }
        $doc['signatures'] = Database::instance()->fetchAll(
            'SELECT sign_order, signer_name, signer_position, signed_at FROM research_signatures
             WHERE doc_type = ? AND doc_id = ? AND version_id = ? AND id <= ? ORDER BY sign_order, id',
            [$doc['doc_type'], (int) $doc['doc_id'], (int) $doc['version_id'], (int) $doc['signature_id']]
        );
        // ลายเซ็นลำดับละ 1 รายการ (รายการล่าสุดของลำดับนั้นก่อนออกเลข)
        $byOrder = [];
        foreach ($doc['signatures'] as $s) {
            $byOrder[(int) $s['sign_order']] = $s;
        }
        $doc['signatures'] = array_values($byOrder);

        return $doc;
    }

    // ---------------------------------------------------------------- ภายใน

    /** ชื่อ-สกุล และตำแหน่งที่ประทับ: ลำดับ 1 ใช้ตำแหน่งในข้อมูลบุคลากร ลำดับ 2-4 ใช้ฐานะผู้ลงนาม */
    public static function signer(int $userId, int $order): array
    {
        $row = Database::instance()->fetch(
            "SELECT u.username, TRIM(CONCAT(COALESCE(e.title_name, ''), COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS full_name, e.position
             FROM users u LEFT JOIN employees e ON e.id = u.employee_id WHERE u.id = ?",
            [$userId]
        ) ?? throw new HttpException(403);
        $name = trim((string) $row['full_name']) !== '' ? trim((string) $row['full_name']) : (string) $row['username'];
        $position = $order === 1
            ? (trim((string) $row['position']) !== '' ? trim((string) $row['position']) : SigningLookup::ORDERS[1])
            : SigningLookup::ORDERS[$order];

        return [$name, $position];
    }

    private static function pageCount(array $ctx): int
    {
        try {
            return (new PdfReader(ResearchStorage::read($ctx['version'])))->pageCount();
        } catch (PdfException $e) {
            throw new ValidationException(['file' => ['อ่านไฟล์ PDF ไม่ได้: ' . $e->getMessage()]]);
        }
    }

    /** ไฟล์ต้นทางของลำดับ $order: ลำดับ 1 = ต้นฉบับ, ลำดับอื่น = ไฟล์ของลำดับก่อนหน้า */
    private static function sourceFor(array $ctx, int $order): string
    {
        if ($order === 1) {
            return ResearchStorage::read($ctx['version']);
        }
        $prev = self::signatures($ctx['type'], (int) $ctx['id'])[$order - 1] ?? null;
        if ($prev === null || (int) $prev['version_id'] !== (int) $ctx['version']['id']) {
            throw new ValidationException(['status' => ['ลำดับที่ ' . ($order - 1) . ' ยังไม่ได้ลงนาม']]);
        }

        return ResearchStorage::read($prev);
    }

    private static function stamped(array $ctx, int $order, int $userId, array $boxes, ?array $footer): string
    {
        $image = SignatureService::image($userId);
        if ($image === null) {
            throw new ValidationException(['status' => ['อ่านภาพลายเซ็นไม่ได้ กรุณาบันทึกลายเซ็นใหม่ที่หน้าโปรไฟล์']]);
        }
        $box = $boxes[$order] ?? throw new ValidationException(['boxes' => ['ไม่พบตำแหน่งลายเซ็นลำดับที่ ' . $order]]);
        try {
            $stamper = new PdfStamper(self::sourceFor($ctx, $order));
            $reader = $stamper->reader();
            $geo = $reader->pageGeometry((int) $box['page'] - 1);
            [$name, $position] = self::signer($userId, $order);
            $items = [[
                'page' => (int) $box['page'],
                'x' => $box['x'], 'y' => $box['y'], 'w' => $box['w'], 'h' => $box['h'],
                'image' => StampRenderer::signature($image, $name, $position, 'ลงนาม ' . thai_date(Database::now()) . ' น.',
                    $box['w'] * $geo['width'], $box['h'] * $geo['height']),
            ]];
            if ($footer !== null) {
                array_push($items, ...self::footerItems($reader, $footer, (string) $ctx['type']));
            }

            return $stamper->stamp($items);
        } catch (PdfException $e) {
            throw new ValidationException(['file' => ['ประทับลายเซ็นลง PDF ไม่สำเร็จ: ' . $e->getMessage()]]);
        }
    }

    /** เลขที่เอกสาร + QR Code มุมขวาล่างของหน้าสุดท้าย */
    private static function footerItems(PdfReader $reader, array $footer, string $type): array
    {
        $last = $reader->pageCount();
        $geo = $reader->pageGeometry($last - 1);
        $W = $geo['width'];
        $H = $geo['height'];
        $margin = 10.0;
        $qr = 46.0;
        $textW = min(250.0, $W * 0.5);
        $lines = [
            'เลขที่เอกสาร ' . $footer['doc_no'],
            SigningLookup::DOC_TYPES[$type] . ' · ลงนามครบ 4 ลำดับ ' . thai_date(Database::now(), false),
            'ตรวจสอบฉบับจริงจากระบบ: สแกน QR Code',
        ];

        return [
            [
                'page' => $last,
                'x' => ($W - $margin - $qr) / $W, 'y' => ($H - $margin - $qr) / $H, 'w' => $qr / $W, 'h' => $qr / $H,
                'qr' => QrCode::matrix($footer['url']),
            ],
            [
                'page' => $last,
                'x' => ($W - $margin - $qr - 4 - $textW) / $W, 'y' => ($H - $margin - $qr + 6) / $H, 'w' => $textW / $W, 'h' => ($qr - 12) / $H,
                'image' => StampRenderer::footer($lines, $textW, $qr - 12),
            ],
        ];
    }

    private static function boxRow(array $r, bool $saved): array
    {
        return ['page' => (int) $r['page'], 'x' => (float) $r['x'], 'y' => (float) $r['y'], 'w' => (float) $r['w'], 'h' => (float) $r['h'], 'saved' => $saved];
    }

    private static function table(array $ctx): string
    {
        return $ctx['type'] === 'research' ? 'research_works' : 'innovation_media';
    }
}
