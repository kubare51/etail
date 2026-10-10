<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Access Helper — Standardized Access Control
 * ============================================================
 * Single Responsibility: Check user permissions for routes.
 * Replaces ~200 copy-pasted get_access() calls.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Get all access rights for a controller/method path.
 *
 * @param  object &$CI    CodeIgniter instance
 * @param  string $path   Route path (e.g., 'admin_ret_catalog/theme/list')
 * @return array  ['add' => 1/0, 'edit' => 1/0, 'delete' => 1/0, 'view' => 1/0]
 */
function lmx_access(&$CI, $path)
{
    if (!isset($CI->admin_settings_model)) {
        $CI->load->model('admin_settings_model');
    }
    $access = $CI->admin_settings_model->get_access($path);
    log_message('debug', '[LMX Access] Path: ' . $path . ' → Access: ' . json_encode($access));
    return $access;
}

/**
 * Check if the user has a specific permission for a path.
 *
 * Usage:
 *   if (!lmx_can($this, 'admin_ret_catalog/theme/list', 'add')) {
 *       lmx_error('Permission denied');
 *       return;
 *   }
 *
 * @param  object &$CI    CodeIgniter instance
 * @param  string $path   Route path
 * @param  string $action Action ('add', 'edit', 'delete', 'view')
 * @return bool   TRUE if allowed
 */
function lmx_can(&$CI, $path, $action = 'view')
{
    $access = lmx_access($CI, $path);
    $result = !empty($access[$action]);
    log_message('debug', '[LMX Can] Path: ' . $path . ', Action: ' . $action . ' → Result: ' . ($result ? 'YES' : 'NO'));
    return $result;
}
