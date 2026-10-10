<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX CRUD Helper — Generic Database Operations
 * ============================================================
 * Single Responsibility: Insert, update, delete, fetch records.
 * Transaction-AGNOSTIC — caller wraps with lmx_transaction().
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Insert a record. Does NOT manage transactions.
 * Auto-adds created_on/created_by (or their known variants) IF the table
 * actually has those columns — several master tables don't follow the
 * standard names (ret_tag_type_master uses create_by/created_time,
 * ret_old_metal_category uses date_add, ret_quality_code/ret_diamond_rate/
 * ret_selling_diamond_rate use created_by_id). Setting them unconditionally
 * throws "Unknown column" on every insert to those tables, so check the
 * real schema first. Also mirrors the acting user into `id_employee` where
 * that column exists (ret_color/ret_cut/ret_clarity) — confirmed via live
 * data that legacy always set it equal to created_by; left unhandled it
 * silently writes 0 (non-strict SQL mode), scrambling "who did this" audit
 * trail on 3 masters.
 *
 * @param  object &$CI    CodeIgniter instance
 * @param  string $table  Table name
 * @param  array  $data   Associative array of column => value
 * @return int    Insert ID (0 on failure)
 */
function lmx_insert(&$CI, $table, $data)
{
    if (!function_exists('lmx_schema_columns')) {
        $CI->load->helper('lmx/core/validation_helper');
    }
    $schema = lmx_schema_columns($CI, $table);
    $uid = $CI->session->userdata('uid');

    if (isset($schema['created_on'])) {
        $data['created_on'] = date('Y-m-d H:i:s');
    } elseif (isset($schema['updated_on'])) {
        // Some child tables (e.g. ret_diamond_cent_rates) have no created_on
        // at all — updated_on is the only timestamp column, NOT NULL with no
        // default. Stamp it on insert too, or every fresh row's "last
        // modified" silently zero-dates until the row is later edited.
        $data['updated_on'] = date('Y-m-d H:i:s');
    }
    if (isset($schema['created_by'])) {
        $data['created_by'] = $uid;
    } elseif (isset($schema['created_by_id'])) {
        $data['created_by_id'] = $uid;
    } elseif (isset($schema['updated_by_id'])) {
        $data['updated_by_id'] = $uid;
    }
    if (isset($schema['id_employee'])) {
        $data['id_employee'] = $uid;
    }

    $CI->db->insert($table, $data);
    $id = $CI->db->insert_id();

    if (!$id) {
        $err = $CI->db->error();
        log_message('error', '[lmx_insert] ' . $table . ' failed: ' . json_encode($err));
    }

    return $id;
}

/**
 * Update a record. Does NOT manage transactions.
 * Auto-adds updated_on/updated_by (or their known variants), IF the table
 * actually has those columns — see lmx_insert() for why this can't be
 * unconditional or assume one fixed naming convention.
 *
 * @param  object &$CI    CodeIgniter instance
 * @param  string $table  Table name
 * @param  string $pk     Primary key column name
 * @param  mixed  $id     Primary key value
 * @param  array  $data   Associative array of column => value
 * @return bool   TRUE on success
 */
function lmx_update(&$CI, $table, $pk, $id, $data)
{
    if (!function_exists('lmx_schema_columns')) {
        $CI->load->helper('lmx/core/validation_helper');
    }
    $schema = lmx_schema_columns($CI, $table);
    $uid = $CI->session->userdata('uid');

    if (isset($schema['updated_on'])) {
        $data['updated_on'] = date('Y-m-d H:i:s');
    }
    if (isset($schema['updated_by'])) {
        $data['updated_by'] = $uid;
    } elseif (isset($schema['updated_by_id'])) {
        $data['updated_by_id'] = $uid;
    }

    $CI->db->where($pk, $id);
    $result = $CI->db->update($table, $data);

    if (!$result) {
        log_message('error', '[lmx_update] ' . $table . '.' . $pk . '=' . $id . ' failed');
    }

    return $result;
}

/**
 * Delete a record. Supports hard delete and soft delete.
 *
 * @param  object &$CI       CodeIgniter instance
 * @param  string $table     Table name
 * @param  string $pk        Primary key column name
 * @param  mixed  $id        Primary key value
 * @param  string $strategy  'hard' | 'soft'
 * @param  string $soft_col  Column for soft delete flag (default: 'is_deleted')
 * @return bool   TRUE on success
 */
function lmx_delete(&$CI, $table, $pk, $id, $strategy = 'hard', $soft_col = 'is_deleted')
{
    if ($strategy === 'soft') {
        return lmx_update($CI, $table, $pk, $id, [$soft_col => 1]);
    }

    $CI->db->where($pk, $id);
    $result = $CI->db->delete($table);

    if (!$result) {
        log_message('error', '[lmx_delete] ' . $table . '.' . $pk . '=' . $id . ' failed');
    }

    return $result;
}

/**
 * Fetch a single record by primary key.
 *
 * @param  object &$CI      CodeIgniter instance
 * @param  string $table    Table name
 * @param  string $pk       Primary key column name
 * @param  mixed  $id       Primary key value
 * @param  string $columns  Comma-separated column names (default: '*')
 * @return array|null  Row array or null
 */
function lmx_get(&$CI, $table, $pk, $id, $columns = '*')
{
    if ($columns !== '*') {
        $CI->db->select($columns);
    }
    $CI->db->where($pk, $id);
    $row = $CI->db->get($table)->row_array();
    return $row ?: null;
}

/**
 * Check if a value already exists (for duplicate checking).
 *
 * @param  object &$CI         CodeIgniter instance
 * @param  string $table       Table name
 * @param  string $column      Column to check
 * @param  mixed  $value       Value to check
 * @param  mixed  $exclude     ID to exclude (for edit mode, null for add)
 * @param  string $pk          Primary key column for exclusion
 * @param  array  $extraWhere  Optional column => value pairs for composite-key
 *                              uniqueness (e.g. metal+purity combination must
 *                              be unique together, not id_purity alone)
 * @return bool   TRUE if duplicate exists
 */
function lmx_duplicate_exists(&$CI, $table, $column, $value, $exclude = null, $pk = 'id', $extraWhere = [])
{
    // Numeric columns (e.g. ret_purity.purity, a decimal(10,4)) must compare
    // as NUMBERS, not strings — LOWER(TRIM()) below forces a string compare,
    // under which a decimal column's stored "55.5500" never matches a
    // resubmitted "55.55", silently letting real duplicates through. Only
    // take this path for a value that's actually numeric; a numeric-looking
    // VARCHAR (e.g. a "2024" theme code) still wants the string/trim path
    // below for its case-insensitivity, which numeric compare doesn't need
    // anyway.
    if (is_numeric($value)) {
        $CI->db->where($column, $value);
    } else {
        // Case-insensitive + trim-aware comparison
        // Prevents "Gold" and "GOLD" or "Gold " and "Gold" being treated as different
        // NOTE: escape must stay default (TRUE) here — the third-param FALSE this
        // used to carry disabled *value* escaping too (CI's where($key,$val,$escape)
        // applies $escape to both), which put the raw value into the SQL string
        // unquoted and threw a syntax error on every single call. protect_identifiers()
        // is still applied manually to the column so `LOWER(TRIM(...))` targets the
        // right identifier without CI trying to re-wrap the whole expression.
        $CI->db->where('LOWER(TRIM(' . $CI->db->protect_identifiers($column) . '))', strtolower(trim($value)));
    }
    foreach ($extraWhere as $extraCol => $extraVal) {
        $CI->db->where($extraCol, $extraVal);
    }
    if ($exclude !== null) {
        $CI->db->where($pk . ' !=', $exclude);
    }
    return $CI->db->count_all_results($table) > 0;
}

/**
 * Check a multi-column composite unique constraint (e.g. ret_quality_code's
 * real DB-level unique index on cut_id+color_id+clarity_id+shape_id) — for
 * the rare case where 3+ columns together must be unique, not just a pair
 * (lmx_duplicate_exists()'s single-column + extra_where_field already covers
 * pairs, e.g. ret_metalpurity's id_metal+id_purity). Unlike a per-field
 * 'unique' rule, this isn't tied to any one field, so it doesn't interact
 * with check_unique's live-blur AJAX endpoint or field_meta.
 *
 * @param  object &$CI
 * @param  string $table
 * @param  array  $columns   Column names — all must match for a row to count as a duplicate
 * @param  array  $data      Submitted field values, keyed by column name
 * @param  mixed  $exclude   PK value to exclude (edit mode), null for add
 * @param  string $pk
 * @return bool   TRUE if a different row already has this exact combination
 */
function lmx_composite_unique_exists(&$CI, $table, $columns, $data, $exclude = null, $pk = 'id')
{
    foreach ($columns as $col) {
        $CI->db->where($col, isset($data[$col]) ? $data[$col] : null);
    }
    if ($exclude !== null) {
        $CI->db->where($pk . ' !=', $exclude);
    }
    return $CI->db->count_all_results($table) > 0;
}
