<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Input Helper — Safe Input Reading
 * ============================================================
 * Single Responsibility: Read and sanitize user input.
 * Replaces all raw $_POST / inconsistent $this->input->post().
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Read a single POST value with type casting and sanitization.
 *
 * @param  object &$CI            CodeIgniter instance
 * @param  string $key            POST key
 * @param  string $type           Type: string|int|float|bool|date|array
 * @param  mixed  $default        Default value if key missing
 * @param  string $sanitize_rule  Optional rule name from sanitize_by_rule()
 * @return mixed  Typed, sanitized value
 */
function lmx_input(&$CI, $key, $type = 'string', $default = '', $sanitize_rule = '')
{
    $value = $CI->input->post($key);

    if ($value === false || $value === null) {
        return $default;
    }

    switch ($type) {
        case 'int':
            return (int) $value;

        case 'float':
            return round((float) $value, 4);

        case 'bool':
            return ($value === '1' || $value === 'true' || $value === true) ? 1 : 0;

        case 'date':
            $trimmed = trim($value);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) ? $trimmed : $default;

        case 'array':
            return is_array($value) ? $value : [];

        case 'string':
        default:
            $value = trim($CI->security->xss_clean($value));
            if ($sanitize_rule !== '' && function_exists('sanitize_by_rule')) {
                $value = sanitize_by_rule($value, $sanitize_rule);
            }
            return $value;
    }
}

/**
 * Read multiple POST values at once with a type+rule map.
 *
 * Usage:
 *   $data = lmx_inputs($this, [
 *       'theme_name'   => ['string', 'nameField'],
 *       'theme_code'   => ['string', 'shortCode'],
 *       'theme_status' => ['int'],
 *       'rate'         => ['float'],
 *   ]);
 *
 * @param  object &$CI   CodeIgniter instance
 * @param  array  $map   Key => [type, sanitize_rule] or Key => type
 * @return array  Associative array of clean values
 */
function lmx_inputs(&$CI, $map)
{
    $data = [];
    foreach ($map as $key => $spec) {
        if (is_array($spec)) {
            $type = isset($spec[0]) ? $spec[0] : 'string';
            $rule = isset($spec[1]) ? $spec[1] : '';
        } else {
            $type = $spec;
            $rule = '';
        }
        $data[$key] = lmx_input($CI, $key, $type, '', $rule);
    }
    return $data;
}

/**
 * Read nested array input safely (for grid forms: billing items, reorder settings).
 * Applies XSS clean to all string values recursively.
 *
 * @param  object &$CI  CodeIgniter instance
 * @param  string $key  POST key containing the array
 * @return array  Clean nested array
 */
function lmx_input_array(&$CI, $key)
{
    $raw = $CI->input->post($key);
    if (!is_array($raw)) return [];
    return _lmx_clean_array($CI, $raw);
}

/**
 * @internal Recursively clean array values.
 */
function _lmx_clean_array(&$CI, $arr)
{
    $clean = [];
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            $clean[$k] = _lmx_clean_array($CI, $v);
        } else {
            $clean[$k] = trim($CI->security->xss_clean($v));
        }
    }
    return $clean;
}
