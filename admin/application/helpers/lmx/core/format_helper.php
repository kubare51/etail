<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Format Helper — Standardized Display Formats
 * ============================================================
 * Single Responsibility: Format dates, numbers, and currency consistently.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Format a date string safely.
 *
 * @param  string $value  Date string
 * @param  string $format Target format (default: d-m-Y)
 * @return string Formatted date or empty string if invalid
 */
function lmx_date($value, $format = 'd-m-Y')
{
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }
    $time = strtotime($value);
    return $time ? date($format, $time) : '';
}

/**
 * Format a number for database storage (no commas, fixed decimals).
 *
 * @param  mixed $value
 * @param  int   $decimals
 * @return string
 */
function lmx_num($value, $decimals = 2)
{
    return number_format((float)$value, $decimals, '.', '');
}

/**
 * Format currency for display (with commas).
 *
 * @param  mixed $value
 * @param  int   $decimals
 * @return string
 */
function lmx_money($value, $decimals = 2)
{
    return number_format((float)$value, $decimals);
}

/**
 * Format currency for display with symbol.
 *
 * @param  mixed $value
 * @param  string $symbol (default: ₹)
 * @return string
 */
function lmx_display_money($value, $symbol = '₹ ')
{
    return $symbol . lmx_money($value);
}
