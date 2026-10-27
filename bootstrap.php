<?php
declare(strict_types=1);

// 1. โหลดการตั้งค่าระบบ
$appConfig = require_once __DIR__ . '/config/app.php';
define('APP_ENV', $appConfig['env']);
define('BASE_URL', $appConfig['base_url']);

// 2. ตั้งค่า Timezone เป็น Asia/Bangkok เสมอ
date_default_timezone_set($appConfig['timezone']);

// 3. ตั้งค่าการแสดง Error
if (APP_ENV === 'local') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 4. Autoloader สำหรับโหลดคลาสอัตโนมัติตาม Namespace (PSR-4)
spl_autoload_register(function (string $class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/core/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // หากไม่ใช่ Core ให้ลองตรวจสอบ Modules
        $modulePrefix = 'Modules\\';
        $moduleBaseDir = __DIR__ . '/modules/';
        $moduleLen = strlen($modulePrefix);
        
        if (strncmp($modulePrefix, $class, $moduleLen) === 0) {
            $relative_class = substr($class, $moduleLen);
            $file = $moduleBaseDir . str_replace('\\', '/', strtolower($relative_class)) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 5. เริ่มต้น Session (ในเฟสต่อไปจะเปลี่ยนไปใช้ Database Session Handler)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    \Core\Session::start();
}