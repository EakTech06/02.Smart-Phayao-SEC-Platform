<?php

declare(strict_types=1);

namespace Modules\CoreAdmin\Controllers;

use Core\Controller;
use Core\Database;
use Core\Response;
use Core\Session;
use Core\Audit;

final class SettingController extends Controller
{
    public function index(): Response
    {
        $this->authorize('core.settings.manage');
        $settings = Database::instance()->fetchAll('SELECT * FROM settings');
        
        return $this->view('core_admin::settings/index', [
            'title' => 'ตั้งค่าระบบ',
            'settings' => $settings,
        ]);
    }

    public function update(): Response
    {
        $this->authorize('core.settings.manage');
        $inputs = (array) $this->request->input('settings', []);
        $inputs = self::validateBranding($inputs);
        unset($inputs[self::LOGO_KEY]); // โลโก้เปลี่ยนได้ผ่านการอัปโหลด/ลบเท่านั้น
        $logo = $this->processLogo(); // null = ไม่เปลี่ยน, '' = ลบ, ชื่อไฟล์ = อัปโหลดใหม่
        $oldLogo = basename((string) \Core\Setting::get(self::LOGO_KEY, ''));
        if ($logo !== null) {
            $inputs[self::LOGO_KEY] = $logo;
        }
        $db = Database::instance();

        $db->transaction(static function (Database $db) use ($inputs): void {
            foreach ($inputs as $key => $val) {
                $db->update(
                    'settings', 
                    ['setting_value' => is_scalar($val) ? (string) $val : ''], 
                    ['setting_key' => $key]
                );
            }
        });

        if ($logo !== null && $oldLogo !== '' && $oldLogo !== $logo && is_file(self::logoDir() . $oldLogo)) {
            @unlink(self::logoDir() . $oldLogo);
        }

        Audit::log('settings.updated', 'core', 'settings', null, null, $inputs);
        Session::flash('success', 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
        return $this->redirect('/settings');
    }

    public const LOGO_KEY = 'app.logo';
    private const LOGO_MAX_SIZE = 1024 * 1024; // 1MB
    private const LOGO_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    private static function logoDir(): string
    {
        return BASE_PATH . '/public/uploads/branding/';
    }

    /**
     * โลโก้ระบบ: ตรวจว่าเป็นไฟล์ภาพจริง (PNG/JPG/WebP ไม่เกิน 1MB) ตั้งชื่อไฟล์ใหม่แบบสุ่ม
     * ไม่รับ SVG เพราะเปิดตรงแล้วรันสคริปต์ได้
     * @return ?string null = ไม่เปลี่ยน, '' = ลบโลโก้, อื่น ๆ = ชื่อไฟล์ใหม่
     */
    private function processLogo(): ?string
    {
        $f = $_FILES['logo'] ?? null;
        if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fail = static fn (string $msg) => throw new \Core\Exceptions\ValidationException(['logo' => [$msg]]);
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
                $fail('อัปโหลดโลโก้ไม่สำเร็จ');
            }
            if ((int) $f['size'] > self::LOGO_MAX_SIZE) {
                $fail('ขนาดไฟล์โลโก้ต้องไม่เกิน 1MB');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name']);
            $size = @getimagesize((string) $f['tmp_name']);
            if (!isset(self::LOGO_MIME[$mime]) || $size === false) {
                $fail('โลโก้ต้องเป็นไฟล์ภาพ PNG, JPG หรือ WebP');
            }
            if ($size[0] < 32 || $size[1] < 32 || $size[0] > 4096 || $size[1] > 4096) {
                $fail('ขนาดภาพโลโก้ต้องอยู่ระหว่าง 32-4096 พิกเซล');
            }
            if (!is_dir(self::logoDir()) && !mkdir(self::logoDir(), 0755, true)) {
                $fail('ไม่สามารถสร้างโฟลเดอร์เก็บโลโก้ได้');
            }
            $name = 'logo_' . bin2hex(random_bytes(8)) . '.' . self::LOGO_MIME[$mime];
            if (!move_uploaded_file((string) $f['tmp_name'], self::logoDir() . $name)) {
                $fail('บันทึกไฟล์โลโก้ไม่สำเร็จ');
            }

            return $name;
        }

        return $this->request->input('remove_logo') === '1' ? '' : null;
    }

    /** ชื่อระบบ: key => [ชื่อช่อง, ความยาวสูงสุด, บังคับกรอก] */
    public const BRANDING = [
        'app.name' => ['ชื่อระบบ (เต็ม)', 120, true],
        'app.brand_main' => ['ชื่อบรรทัดหลัก', 40, true],
        'app.brand_sub' => ['ชื่อบรรทัดรอง', 40, false],
        'app.short_name' => ['ชื่อย่อ (มือถือ)', 20, true],
        'app.tagline' => ['คำอธิบายระบบ', 150, false],
    ];

    /** ตัดช่องว่างหัวท้าย ตรวจความยาว/ค่าว่าง ของช่องชื่อระบบ (ช่องอื่นผ่านไปตามเดิม) */
    private static function validateBranding(array $inputs): array
    {
        $errors = [];
        foreach (self::BRANDING as $key => [$label, $max, $required]) {
            if (!array_key_exists($key, $inputs)) {
                continue;
            }
            $value = trim(preg_replace('/\s+/u', ' ', is_scalar($inputs[$key]) ? (string) $inputs[$key] : '') ?? '');
            $field = 'settings.' . $key;
            if ($required && $value === '') {
                $errors[$field][] = "กรุณากรอก{$label}";
            } elseif (mb_strlen($value) > $max) {
                $errors[$field][] = "{$label} ต้องไม่เกิน {$max} ตัวอักษร";
            }
            $inputs[$key] = $value;
        }
        if ($errors !== []) {
            throw new \Core\Exceptions\ValidationException($errors);
        }

        return $inputs;
    }

    /** ping Apps Script: ตรวจลายเซ็น, สิทธิ์ Shared Drive, Calendar และโควตาอีเมล */
    public function testGoogle(): Response
    {
        $this->authorize('core.settings.manage');
        try {
            $info = \Core\GoogleWorkspace::call('ping');
            Session::flash('success', sprintf(
                'เชื่อมต่อ Google Workspace สำเร็จ — บัญชี: %s · โฟลเดอร์ Shared Drive: %s · ปฏิทิน: %s · โควตาอีเมลวันนี้: %s',
                (string) ($info['account'] ?? '-'),
                (string) ($info['folder'] ?? '-'),
                (string) ($info['calendar'] ?? 'ไม่ได้ตั้ง CALENDAR_ID'),
                (string) ($info['mailQuota'] ?? '-')
            ));
        } catch (\Core\GoogleWorkspaceException $e) {
            Session::flash('error', 'เชื่อมต่อไม่สำเร็จ: ' . $e->getMessage());
        }

        return $this->redirect('/settings');
    }
}