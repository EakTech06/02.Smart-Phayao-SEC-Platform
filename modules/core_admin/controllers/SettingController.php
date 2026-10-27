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
        $inputs = $this->request->input('settings', []);
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
}