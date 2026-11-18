<?php

declare(strict_types=1);

namespace Core;

/** ส่งอีเมลผ่าน Gmail (Google Workspace) โดย Apps Script หรือเขียน log บนเครื่อง local */
final class Mailer
{
    /**
     * @param list<string> $to
     * @throws GoogleWorkspaceException เมื่อส่งไม่สำเร็จ
     */
    public static function send(array $to, string $subject, string $html, ?string $text = null): void
    {
        $to = array_values(array_unique(array_filter(
            array_map(static fn ($a): string => mb_strtolower(trim((string) $a)), $to),
            static fn (string $a): bool => filter_var($a, FILTER_VALIDATE_EMAIL) !== false
        )));
        if ($to === []) {
            return;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject); // กัน header injection
        $text ??= trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'));

        if (Config::get('mail.driver') === 'log') {
            $dir = BASE_PATH . '/storage/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($dir . '/mail.log', sprintf("[%s] to=%s subject=%s\n%s\n\n", date('c'), implode(',', $to), $subject, $text), FILE_APPEND | LOCK_EX);

            return;
        }

        GoogleWorkspace::call('mail.send', [
            'to' => $to,
            'subject' => $subject,
            'htmlBody' => $html,
            'body' => $text,
            'name' => (string) Config::get('mail.from_name', Config::get('app.name')),
        ]);
    }
}
