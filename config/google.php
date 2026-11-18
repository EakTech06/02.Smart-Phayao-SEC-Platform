<?php

declare(strict_types=1);

/**
 * Google Workspace ผ่าน Google Apps Script Web App (server-to-server ด้วย cURL)
 * ค่าจริงให้ใส่ใน config/local.php (ไม่เก็บใน Git) ดูวิธีติดตั้งที่ tools/google-apps-script/README.md
 */
return [
    // URL ของ Web App ที่ Deploy แล้ว (ลงท้ายด้วย /exec)
    'apps_script_url' => '',
    // คีย์ลับร่วม (ต้องตรงกับ Script Property SHARED_SECRET) ใช้เซ็น HMAC-SHA256 ทุกคำขอ
    'shared_secret' => '',
    'timeout' => 90,
    // ซิงก์กิจกรรมสาธารณะของปฏิทินกลางไปยัง Google Calendar ขององค์กร
    'calendar_sync' => true,
];
