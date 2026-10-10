<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Validation Helper — Config-Driven Validation
 * ============================================================
 * Single Responsibility: Validate associative arrays against rules.
 * Works for both masters and entry screens.
 * Reuses existing validate_master_field() for core logic.
 *
 * @version  1.1.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Validate data against a rules array.
 *
 * Usage:
 *   $validation = lmx_validate($this, $data, [
 *       'theme_name' => ['label' => 'Theme Name', 'required' => true, 'rule' => 'nameField', 'unique' => ['table' => 'ret_theme', 'column' => 'theme_name', 'pk' => 'id_theme', 'exclude_id' => $id]],
 *       'rate'       => ['label' => 'Rate', 'required' => true, 'min_val' => 0.01],
 *   ], 'ret_theme', $config['inputs']);
 *   if (!$validation['valid']) { lmx_validation_error($validation['errors']); return; }
 *
 * @param  object &$CI    CodeIgniter instance
 * @param  array  $data   Associative array of data to validate
 * @param  array  $rules  Validation rules configuration
 * @param  string $table  Optional: the entity's own table, for DB-length auto-derivation
 * @param  array  $inputs Optional: config['inputs'], for fixed-option/enum validation
 * @return array  ['valid' => bool, 'errors' => array]
 */
function lmx_validate(&$CI, $data, $rules, $table = null, $inputs = [])
{
    $errors = [];

    if (!function_exists('validate_master_field')) {
        $CI->load->helper('field_validation');
    }
    if (!function_exists('lmx_duplicate_exists')) {
        $CI->load->helper('lmx/core/crud_helper');
    }

    $schema = ($table !== null) ? lmx_schema_columns($CI, $table) : [];

    foreach ($rules as $field => $rule) {
        $value = isset($data[$field]) ? $data[$field] : '';
        $label = isset($rule['label']) ? $rule['label'] : $field;

        // An unselected FK dropdown casts to int 0 (lmx_input('int')), not ''.
        // Treat '0' as empty specifically for fk_check fields — 0 is never a
        // legitimate row id in this schema (all PKs are auto-increment from 1).
        // Without this, a blank optional FK field fails fk_check with a
        // confusing "references a record that does not exist" instead of
        // either passing (optional) or saying "required" (required).
        $isEmpty = trim((string)$value) === '' || (!empty($rule['fk_check']) && (string)$value === '0');

        // 1. Required Check
        if (!empty($rule['required']) && $isEmpty) {
            $errors[] = $label . ' is required';
            continue; // Stop further validation for this empty field
        }

        // Skip remaining checks if optional and empty
        if ($isEmpty) {
            continue;
        }

        // 2. Type/Format Validation — a field can set 'pattern' alone (a
        // one-off custom regex, no named rule) instead of or alongside 'rule'.
        if (!empty($rule['rule']) || !empty($rule['pattern'])) {
            $result = validate_master_field(isset($rule['rule']) ? $rule['rule'] : null, $label, $value, $rule);
            if (!$result['valid']) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        // 2.5 Max Length Enforcement — DB column width is authoritative; config
        // can only be stricter, never looser. Prevents "validation passes,
        // DB insert truncates/fails" (found in 4 masters: config allowed more
        // characters than the actual varchar width).
        $effective_max = lmx_effective_max_length($rule, isset($schema[$field]) ? $schema[$field] : null);
        if ($effective_max !== null && mb_strlen((string)$value) > $effective_max) {
            $errors[] = $label . ' must not exceed ' . $effective_max . ' characters';
        }

        // 2.6 Min Length Enforcement — the legacy per-master validate_*_data()
        // functions this engine is replacing (Retail_1.1.1.0001's parallel
        // customer_validation_helper.php) enforce a minimum length (commonly
        // 2) via validate_master_field(); this engine only had a max-length
        // floor until now, so migrating a master silently dropped it. Opt in
        // per field via 'min_length' rather than defaulting it, since not
        // every field this engine already covers had a legacy minimum.
        if (isset($rule['min_length']) && mb_strlen((string)$value) < (int)$rule['min_length']) {
            $errors[] = $label . ' must be at least ' . $rule['min_length'] . ' characters';
        }

        // 2.7 Security Pattern Blacklist (opt-in) — some legacy masters
        // (e.g. financial_year, via sanitize_and_validate_text() on
        // Retail_1.1.1.0001) reject input containing common SQL-injection /
        // XSS substrings outright, in addition to xss_clean() + parameterized
        // Active Record queries. Opt in per field via 'security_blacklist' —
        // this must never be the default, since blacklisting substrings like
        // "SELECT " or "<script" rejects legitimate text (e.g. a business
        // name containing "United Select Corp") on fields that never had
        // this check and don't need it.
        if (!empty($rule['security_blacklist']) && lmx_contains_blacklisted_pattern((string)$value)) {
            $errors[] = $label . ' contains invalid content';
        }

        // 3. Range Validation (Numeric)
        if (isset($rule['min_val']) && (float)$value < (float)$rule['min_val']) {
            $errors[] = $label . ' must be at least ' . $rule['min_val'];
        }
        if (isset($rule['max_val']) && (float)$value > (float)$rule['max_val']) {
            $errors[] = $label . ' must not exceed ' . $rule['max_val'];
        }

        // 3.5 Fixed-Option / Enum Check — validates against the SAME inline
        // options array already declared in inputs[$field][2]['options'] for
        // rendering the <select>, so there's one source of truth for the
        // allowed set rather than duplicating it into a separate rule.
        if (isset($inputs[$field][2]) && is_array($inputs[$field][2]) && isset($inputs[$field][2]['options'])) {
            $allowed = array_map('strval', array_column($inputs[$field][2]['options'], 0));
            if (!in_array((string)$value, $allowed, true)) {
                $errors[] = $label . ' must be one of the allowed values';
            }
        }

        // 4. Unique (Duplicate) Check
        if (!empty($rule['unique'])) {
            $u = $rule['unique'];
            $u_table    = $u['table'];
            $column     = isset($u['column']) ? $u['column'] : $field;
            $exclude_id = isset($u['exclude_id']) ? $u['exclude_id'] : null;
            $pk         = isset($u['pk']) ? $u['pk'] : 'id';

            // Composite-key uniqueness (e.g. metal+purity must be unique as a
            // pair, not id_purity alone) — extra_where_field names another
            // submitted field whose current value narrows the duplicate check.
            $extraWhere = [];
            if (!empty($u['extra_where_field']) && isset($data[$u['extra_where_field']])) {
                $extraWhere[$u['extra_where_field']] = $data[$u['extra_where_field']];
            }

            if (lmx_duplicate_exists($CI, $u_table, $column, $value, $exclude_id, $pk, $extraWhere)) {
                $errors[] = $label . ' "' . $value . '" already exists';
            }
        }

        // 5. FK Existence Check (validate foreign key references actually exist)
        if (!empty($rule['fk_check'])) {
            $fk = $rule['fk_check'];
            $fk_row = lmx_get($CI, $fk['table'], $fk['pk'], $value, $fk['pk']);
            if (!$fk_row) {
                $errors[] = $label . ' references a record that does not exist';
            } elseif (!empty($fk['status_col']) && empty($fk['skip_active_check'])) {
                // Row exists but may have been deactivated after this form's
                // dropdown was populated (e.g. in another browser tab) —
                // since masters are only ever deactivated, not hard-deleted,
                // existence alone doesn't prove the reference is still valid
                // to save against. Found 2026-07-17 via a real cross-tab race
                // reported against the legacy Product->Category flow; this
                // closes the same gap for every config-driven fk_check.
                $CI->db->where($fk['pk'], $value);
                $CI->db->where($fk['status_col'], 1);
                if ($CI->db->count_all_results($fk['table']) === 0) {
                    $errors[] = $label . ' is no longer active — please refresh and choose again';
                }
            }
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Validate relationships that span more than one field — a single field's
 * rule entry can't express "this must be before that". Called separately
 * from lmx_validate() by _master_save(), after per-field checks pass.
 *
 * Usage:
 *   $config['cross_field_rules'] = [
 *       ['type' => 'date_range', 'from' => 'fin_year_from', 'to' => 'fin_year_to',
 *        'message' => 'Start date must be before end date'],
 *   ];
 *
 * @param  array $data
 * @param  array $crossFieldRules
 * @return array  ['valid' => bool, 'errors' => array]
 */
function lmx_validate_cross_fields($data, $crossFieldRules)
{
    $errors = [];

    foreach ($crossFieldRules as $rule) {
        if ($rule['type'] === 'date_range') {
            $from = isset($data[$rule['from']]) ? $data[$rule['from']] : '';
            $to   = isset($data[$rule['to']]) ? $data[$rule['to']] : '';
            if ($from !== '' && $to !== '' && strtotime($from) >= strtotime($to)) {
                $errors[] = isset($rule['message']) ? $rule['message'] : 'Start date must be before end date';
            }
        }

        // Numeric range, e.g. touch_from..touch_to — unlike date_range, equal
        // values are valid (a single-point range), so this uses > not >=.
        if ($rule['type'] === 'range_order') {
            $from = isset($data[$rule['from']]) ? $data[$rule['from']] : '';
            $to   = isset($data[$rule['to']]) ? $data[$rule['to']] : '';
            if ($from !== '' && $to !== '' && (float)$from > (float)$to) {
                $errors[] = isset($rule['message']) ? $rule['message'] : $rule['from'] . ' must not exceed ' . $rule['to'];
            }
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Substring-blacklist check for 'security_blacklist' opt-in fields — ported
 * from Retail_1.1.1.0001's sanitize_and_validate_text() (customer_validation_helper.php)
 * so masters that had this check keep identical behavior after migrating to
 * the generic engine. Case-insensitive substring match, same pattern lists
 * as the source (SQL-injection keywords/comment markers, then common XSS
 * markup/event-handler/script-URI patterns).
 *
 * @param  string $value
 * @return bool   true if a blacklisted pattern is present
 */
function lmx_contains_blacklisted_pattern($value)
{
    $lower = strtolower($value);

    $sql_patterns = ['--', '/*', '*/', 'xp_', 'union ', 'select ', 'insert ', 'update ', 'delete ',
        'drop ', 'truncate ', 'alter ', 'exec(', 'execute(', 'cast(', 'convert(', 'char(',
        'declare ', 'benchmark(', 'sleep(', 'load_file(', 'into outfile'];
    foreach ($sql_patterns as $pattern) {
        if (strpos($lower, $pattern) !== false) {
            return true;
        }
    }

    $xss_patterns = ['<script', '<\\script', 'javascript:', 'onload=', 'onerror=', 'onclick=',
        'onmouseover=', 'onfocus=', 'onblur=', '<iframe', '</iframe', '<object', '<embed',
        '<form', '<input', 'eval(', 'expression(', 'alert(', 'confirm(', 'prompt(',
        'document.cookie', 'document.write', 'window.location', '&#', '%3c', '%3e', 'vbscript:'];
    foreach ($xss_patterns as $pattern) {
        if (strpos($lower, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Compute the effective max length for a field: config's max_length clamped
 * to the DB column's real width, whichever is stricter. Shared by
 * lmx_validate() (server-side enforcement) and the master engine's view data
 * (so the add/edit form's maxlength= attribute matches exactly what the
 * server will accept, instead of drifting out of sync as two hand-maintained
 * numbers).
 *
 * @param  array      $rule         The field's rule entry (may have 'max_length')
 * @param  array|null $schemaColumn lmx_schema_columns()'s entry for this column, or null
 * @return int|null   Effective max length, or null if neither source sets one
 */
function lmx_effective_max_length($rule, $schemaColumn)
{
    $effective_max = isset($rule['max_length']) ? (int) $rule['max_length'] : null;
    if ($schemaColumn !== null && isset($schemaColumn['CHARACTER_MAXIMUM_LENGTH']) && $schemaColumn['CHARACTER_MAXIMUM_LENGTH'] !== null) {
        $db_max = (int) $schemaColumn['CHARACTER_MAXIMUM_LENGTH'];
        if ($effective_max === null || $effective_max > $db_max) {
            $effective_max = $db_max;
        }
    }
    return $effective_max;
}

/**
 * Look up a table's column metadata from the live schema (max length, data
 * type, nullability) via INFORMATION_SCHEMA — so validation self-updates
 * when the DB changes instead of drifting out of sync with hand-typed
 * config numbers. Cached per-request: schema doesn't change mid-request,
 * and re-querying on every field would add needless round trips.
 *
 * @param  object &$CI
 * @param  string $table
 * @return array  column_name => ['CHARACTER_MAXIMUM_LENGTH' => ..., 'DATA_TYPE' => ..., 'IS_NULLABLE' => ..., 'NUMERIC_PRECISION' => ..., 'NUMERIC_SCALE' => ...]
 */
function lmx_schema_columns(&$CI, $table)
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $db_name = $CI->db->database;
    $result = $CI->db->query(
        "SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH, DATA_TYPE, IS_NULLABLE, NUMERIC_PRECISION, NUMERIC_SCALE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
        [$db_name, $table]
    );

    $columns = [];
    foreach ($result->result_array() as $row) {
        $columns[$row['COLUMN_NAME']] = $row;
    }

    $cache[$table] = $columns;
    return $columns;
}
