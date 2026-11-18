<?php

declare(strict_types=1);

namespace Core;

/**
 * ไคลเอนต์ Google Apps Script Web App (server-to-server)
 *
 * คำขอ: POST JSON {action, payload, ts, nonce, sig}
 *   sig = HMAC-SHA256(shared_secret, "{ts}.{nonce}.{action}.{payloadJson}") แบบ hex
 * Apps Script ปฏิเสธคำขอที่ลายเซ็นไม่ถูกต้อง, ts ต่างจากเวลาจริงเกิน 5 นาที หรือ nonce ซ้ำ (กัน replay)
 * คำตอบ: JSON {success, message, data}
 */
final class GoogleWorkspace
{
    public static function isConfigured(): bool
    {
        return (string) Config::get('google.apps_script_url', '') !== ''
            && strlen((string) Config::get('google.shared_secret', '')) >= 32;
    }

    /**
     * @return array<string, mixed> data จาก Apps Script
     * @throws GoogleWorkspaceException
     */
    public static function call(string $action, array $payload = []): array
    {
        if (!self::isConfigured()) {
            throw new GoogleWorkspaceException('ยังไม่ได้ตั้งค่าการเชื่อมต่อ Google Workspace (config google.apps_script_url / shared_secret)');
        }
        $url = (string) Config::get('google.apps_script_url');
        if (!str_starts_with($url, 'https://') && !(Config::get('app.env') === 'local' && str_starts_with($url, 'http://127.0.0.1'))) {
            throw new GoogleWorkspaceException('apps_script_url ต้องเป็น https://');
        }

        $payloadJson = json_encode((object) $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $ts = time();
        $nonce = bin2hex(random_bytes(16));
        $sig = hash_hmac('sha256', "{$ts}.{$nonce}.{$action}.{$payloadJson}", (string) Config::get('google.shared_secret'));
        // ส่ง payload เป็นสตริง JSON เพื่อให้ฝั่ง Apps Script ตรวจลายเซ็นกับข้อความเดียวกันทุกไบต์
        $body = json_encode(['action' => $action, 'payload' => $payloadJson, 'ts' => $ts, 'nonce' => $nonce, 'sig' => $sig],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        // DNS/เชื่อมต่อล้มเหลว = คำขอยังไปไม่ถึง Google (nonce ยังไม่ถูกใช้) จึงส่งซ้ำได้อย่างปลอดภัย
        $retryable = [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT];
        for ($attempt = 1; ; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                // Apps Script ตอบ 302 ไปยัง script.googleusercontent.com ต้องตามไปอ่านผล (cURL เปลี่ยนเป็น GET ให้เอง)
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => (int) Config::get('google.timeout', 90),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw !== false || $attempt >= 3 || !in_array($errno, $retryable, true)) {
                break;
            }
            usleep(500000 * $attempt);
        }

        if ($raw === false) {
            $hint = $errno === CURLE_COULDNT_RESOLVE_HOST
                ? ' (เครื่องเซิร์ฟเวอร์หาที่อยู่ของ Google ไม่เจอ: ตรวจอินเทอร์เน็ต/DNS ของเครื่อง หรือแจ้งผู้ให้บริการโฮสต์ว่าต้องเชื่อมต่อออกไป script.google.com ได้)'
                : '';
            throw new GoogleWorkspaceException('เชื่อมต่อ Google Apps Script ไม่สำเร็จ: ' . $error . $hint);
        }
        $result = json_decode((string) $raw, true);
        if (!is_array($result)) {
            throw new GoogleWorkspaceException("Google Apps Script ตอบกลับไม่ใช่ JSON (HTTP {$status}) ตรวจสอบการ Deploy ว่าเปิดสิทธิ์ 'Anyone'");
        }
        if (($result['success'] ?? false) !== true) {
            throw new GoogleWorkspaceException('Google Apps Script: ' . (string) ($result['message'] ?? 'ไม่ทราบสาเหตุ'));
        }

        return is_array($result['data'] ?? null) ? $result['data'] : [];
    }
}
