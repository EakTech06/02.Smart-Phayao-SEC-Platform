<?php

declare(strict_types=1);

namespace Modules\Academic\Controllers;

use Core\Controller;
use Core\Response;
use Modules\Academic\Services\SigningService;

/** ตรวจสอบเอกสารที่ลงนามครบ 4 ลำดับจาก QR Code (สาธารณะ: แสดงเฉพาะข้อมูลยืนยันเอกสาร ไม่ให้ดาวน์โหลดไฟล์) */
final class VerifyController extends Controller
{
    public function show(string $token): Response
    {
        $doc = SigningService::findByToken($token);

        return $this->view('academic::signing/verify', [
            'title' => 'ตรวจสอบเอกสาร',
            'doc' => $doc,
        ], 'public_layout');
    }
}
