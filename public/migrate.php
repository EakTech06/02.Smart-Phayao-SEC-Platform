<?php
declare(strict_types=1);

/**
 * Migration Script สำหรับ Shared Hosting
 * วิธีใช้งาน: เรียกผ่านเบราว์เซอร์ เช่น http://yourdomain.com/migrate.php?token=YOUR_SECRET_TOKEN
 */

// 1. ตั้งค่าความปลอดภัย (เปลี่ยนค่า MIGRATION_TOKEN ให้รัดกุมก่อนใช้งานจริง)
define('MIGRATION_TOKEN', 'SmartPhayaoSEC_Migrate_2024');

// ตรวจสอบ Token ป้องกันการเข้าถึงจากบุคคลภายนอก
$token = $_GET['token'] ?? '';
if ($token !== MIGRATION_TOKEN) {
    header('HTTP/1.0 403 Forbidden');
    die('403 Forbidden: Invalid Migration Token.');
}

// 2. โหลด Config เพื่อเชื่อมต่อฐานข้อมูล
$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    die('Error: Database config file not found.');
}
$dbConfig = require $configPath;

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// 3. เตรียมโฟลเดอร์ Migrations
$migrationsDir = __DIR__ . '/../database/migrations/';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
    die("Migration directory created at {$migrationsDir}. Please put your .sql files there.");
}

// 4. ดึงรายการ Migration ที่เคยรันไปแล้วจากตาราง migrations
$executedMigrations = [];
try {
    $stmt = $pdo->query("SELECT version FROM migrations");
    $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // ถ้ายังไม่มีตาราง migrations ให้หยุดการทำงาน (ตารางนี้ควรมาจาก schema.sql แล้ว)
    die("Error: Table 'migrations' does not exist. Please run schema.sql first.");
}

// 5. อ่านไฟล์ .sql ทั้งหมดในโฟลเดอร์ (เรียงตามชื่อไฟล์)
$files = scandir($migrationsDir);
$sqlFiles = array_filter($files, function($file) {
    return pathinfo($file, PATHINFO_EXTENSION) === 'sql';
});
sort($sqlFiles);

$appliedCount = 0;

echo "<h2>Smart Phayao SEC - Migration System</h2>";
echo "<ul>";

foreach ($sqlFiles as $file) {
    // ตรวจสอบว่าเคยรันไฟล์นี้ไปหรือยัง
    if (!in_array($file, $executedMigrations)) {
        $filePath = $migrationsDir . $file;
        $sqlContent = file_get_contents($filePath);
        
        if (empty(trim($sqlContent))) {
            continue;
        }

        try {
            // เริ่ม Transaction
            $pdo->beginTransaction();
            
            // รันคำสั่ง SQL
            $pdo->exec($sqlContent);
            
            // บันทึกประวัติการ Migration
            $stmt = $pdo->prepare("INSERT INTO migrations (version) VALUES (:version)");
            $stmt->execute(['version' => $file]);
            
            $pdo->commit();
            
            echo "<li style='color: green;'>Migrated: {$file}</li>";
            $appliedCount++;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<li style='color: red;'>Error migrating {$file}: " . $e->getMessage() . "</li>";
            die("Migration aborted due to error.");
        }
    }
}

if ($appliedCount === 0) {
    echo "<li>No new migrations to apply. Database is up to date.</li>";
} else {
    echo "<li><strong>Successfully applied {$appliedCount} migrations.</strong></li>";
}
echo "</ul>";