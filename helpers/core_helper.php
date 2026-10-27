<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e(?string $string): string {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}