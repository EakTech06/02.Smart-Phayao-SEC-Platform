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

        Audit::log('settings.updated', 'core', 'settings', null, null, $inputs);
        Session::flash('success', 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
        return $this->redirect('/settings');
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