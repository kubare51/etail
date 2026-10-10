<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Dropdown Helper — Standardized Select Lists
 * ============================================================
 * Single Responsibility: Fetch active records for <select> inputs.
 * Replaces dozens of getActiveXxx() model methods.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Get a list of active records for a dropdown.
 *
 * @param  object &$CI         CodeIgniter instance
 * @param  string $table       Table name
 * @param  string $id_col      Column name for option value (e.g., 'id_theme')
 * @param  string $name_col    Column name for option text (e.g., 'theme_name')
 * @param  string $status_col  Status column name (default: 'status')
 * @param  mixed  $active_val  Value representing 'active' state (default: 1)
 * @param  array  $filters     Additional WHERE filters
 * @return array  Array of objects or empty array
 */
function lmx_active_list(&$CI, $table, $id_col, $name_col, $status_col = 'status', $active_val = 1, $filters = [])
{
    $CI->db->select("$id_col as id, $name_col as name");
    
    if ($status_col) {
        $CI->db->where($status_col, $active_val);
    }
    
    if (!empty($filters)) {
        foreach ($filters as $k => $v) {
            $CI->db->where($k, $v);
        }
    }
    
    $CI->db->order_by($name_col, 'ASC');
    return $CI->db->get($table)->result();
}

/**
 * Format active list into key-value pairs suitable for CI's form_dropdown().
 *
 * @param  array  $list        List from lmx_active_list()
 * @param  string $empty_text  Text for empty option (default: 'Select')
 * @return array  Associative array of ID => Name
 */
function lmx_dropdown_options($list, $empty_text = 'Select')
{
    $options = [];
    if ($empty_text !== false) {
        $options[''] = $empty_text;
    }
    foreach ($list as $item) {
        $options[$item->id] = $item->name;
    }
    return $options;
}
