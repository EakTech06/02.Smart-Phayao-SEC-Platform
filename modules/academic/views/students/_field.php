<?php

/**
 * helper สร้างช่องฟอร์ม Bootstrap ที่ใช้ร่วมกันในฟอร์มของโมดูลวิชาการ
 * คืนค่า closure: $field(name, options) → HTML
 *
 * options: label, type (text|select|textarea|date|number|tel|email), options (select), required, max, col,
 *          list (datalist id), placeholder, help, inputmode, value (บังคับค่า), attrs (เพิ่ม attribute ดิบที่ escape แล้ว)
 *
 * @var callable(string): string $val ฟังก์ชันอ่านค่าปัจจุบันของฟิลด์
 * @var array<string,string> $labels
 */
return static function (string $name, array $opt = []) use ($val, $labels): string {
    $label = $opt['label'] ?? ($labels[$name] ?? $name);
    $error = error_for($name);
    $required = !empty($opt['required']);
    $type = $opt['type'] ?? 'text';
    $col = $opt['col'] ?? 'col-12 col-md-6 col-lg-4';
    $id = 'f-' . $name;
    $attrs = ' id="' . e($id) . '" name="' . e($name) . '"'
        . ($required ? ' required' : '')
        . (isset($opt['max']) ? ' maxlength="' . (int) $opt['max'] . '"' : '')
        . (isset($opt['placeholder']) ? ' placeholder="' . e($opt['placeholder']) . '"' : '')
        . (isset($opt['list']) ? ' list="' . e($opt['list']) . '"' : '')
        . (isset($opt['inputmode']) ? ' inputmode="' . e($opt['inputmode']) . '"' : '')
        . ' autocomplete="off"'
        . ($opt['attrs'] ?? '');
    $value = array_key_exists('value', $opt) ? (string) $opt['value'] : $val($name);
    $class = ($type === 'select' ? 'form-select' : 'form-control') . ($error ? ' is-invalid' : '');

    if ($type === 'select') {
        $control = '<select' . $attrs . ' class="' . $class . '">';
        if (!$required || !empty($opt['placeholder_option'])) {
            $control .= '<option value="">' . e($opt['placeholder_option'] ?? '-- ไม่ระบุ --') . '</option>';
        }
        foreach ($opt['options'] as $key => $text) {
            $control .= '<option value="' . e($key) . '"' . ((string) $key === $value ? ' selected' : '') . '>' . e($text) . '</option>';
        }
        $control .= '</select>';
    } elseif ($type === 'textarea') {
        $control = '<textarea' . $attrs . ' rows="' . (int) ($opt['rows'] ?? 2) . '" class="' . $class . '">' . e($value) . '</textarea>';
    } else {
        $control = '<input' . $attrs . ' type="' . e($type) . '" value="' . e($value) . '" class="' . $class . '">';
    }

    return '<div class="' . e($col) . '">'
        . '<label class="form-label" for="' . e($id) . '">' . e($label) . ($required ? ' <span class="text-danger">*</span>' : '') . '</label>'
        . $control
        . ($error ? '<div class="invalid-feedback">' . e($error) . '</div>' : '')
        . (isset($opt['help']) ? '<div class="form-text">' . e($opt['help']) . '</div>' : '')
        . '</div>';
};
