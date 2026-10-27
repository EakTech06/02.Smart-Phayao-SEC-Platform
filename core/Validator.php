<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\ValidationException;

final class Validator
{
    /**
     * Rules: required|nullable|raw|string|email|min:n|max:n|integer|digits:n|in:a,b|same:field|alpha_dash|date|thai_id
     * "raw" = ไม่ trim/clean (ใช้กับรหัสผ่าน)
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $definition) {
            $ruleList = is_array($definition) ? $definition : explode('|', $definition);
            $label = $labels[$field] ?? $field;
            $value = $data[$field] ?? null;

            if (is_string($value) && !in_array('raw', $ruleList, true)) {
                $value = Security::clean($value);
            }
            $isEmpty = $value === null || $value === '' || $value === [];

            foreach ($ruleList as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'nullable' || $name === 'raw') {
                    continue;
                }
                if ($name === 'required') {
                    if ($isEmpty) {
                        $errors[$field][] = "กรุณากรอก{$label}";
                        break;
                    }
                    continue;
                }
                if ($isEmpty) {
                    continue;
                }
                $message = self::check($name, $param, $value, $data, $label);
                if ($message !== null) {
                    $errors[$field][] = $message;
                    break;
                }
            }
            $clean[$field] = $isEmpty ? null : $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $clean;
    }

    public static function isDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** ตรวจ Checksum เลขประจำตัวประชาชนไทย 13 หลัก */
    public static function isThaiId(string $value): bool
    {
        $digits = (string) preg_replace('/\D/', '', $value);
        if (strlen($digits) !== 13) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * (13 - $i);
        }

        return (11 - $sum % 11) % 10 === (int) $digits[12];
    }

    private static function check(string $rule, ?string $param, mixed $value, array $data, string $label): ?string
    {
        $str = is_scalar($value) ? (string) $value : '';

        return match ($rule) {
            'string' => is_string($value) ? null : "{$label} ต้องเป็นข้อความ",
            'email' => filter_var($str, FILTER_VALIDATE_EMAIL) !== false ? null : "{$label} ต้องเป็นอีเมลที่ถูกต้อง",
            'min' => mb_strlen($str) >= (int) $param ? null : "{$label} ต้องมีอย่างน้อย {$param} ตัวอักษร",
            'max' => mb_strlen($str) <= (int) $param ? null : "{$label} ต้องไม่เกิน {$param} ตัวอักษร",
            'integer' => filter_var($str, FILTER_VALIDATE_INT) !== false ? null : "{$label} ต้องเป็นจำนวนเต็ม",
            'digits' => preg_match('/^\d{' . (int) $param . '}$/', $str) === 1 ? null : "{$label} ต้องเป็นตัวเลข {$param} หลัก",
            'in' => in_array($str, explode(',', (string) $param), true) ? null : "{$label} ไม่อยู่ในตัวเลือกที่กำหนด",
            'same' => $str === (string) ($data[$param] ?? '') ? null : "{$label} ไม่ตรงกัน",
            'alpha_dash' => preg_match('/^[A-Za-z0-9_.\-]+$/', $str) === 1 ? null : "{$label} ใช้ได้เฉพาะ a-z 0-9 _ . -",
            'date' => self::isDate($str) ? null : "{$label} ต้องเป็นวันที่ที่ถูกต้อง",
            'thai_id' => self::isThaiId($str) ? null : "{$label} ไม่ถูกต้อง",
            default => throw new \InvalidArgumentException('Unknown validation rule: ' . $rule),
        };
    }
}