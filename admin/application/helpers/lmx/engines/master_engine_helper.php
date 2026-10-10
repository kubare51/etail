<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Master Engine
 * ============================================================
 * Composes LMX core helpers to provide generic CRUD for simple masters.
 *
 * @version 1.1.0
 * @created 2026-07-01
 * @updated 2026-07-07 — Phase 1 hardening:
 *   - ID parameter validation (positive integer)
 *   - Status parameter validation (must be 0 or 1)
 *   - Before/after audit diff on update
 *   - Double-submit prevention (5-second window)
 *   - Improved dependency block messages (count + table)
 * ============================================================
 */

require_once dirname(__DIR__) . '/lmx_autoload_helper.php';

/**
 * Get entity configuration (Lazy Loaded).
 */
function _lmx_entity_config($entity)
{
    static $all_config = null;

    if ($all_config === null) {
        $CI =& get_instance();
        log_message('debug', '[MasterEngine Config] Loading master_entities.php');
        $CI->config->load('master_entities', TRUE);
        // Get the 'master_entities' item from the 'master_entities' config group
        $all_config = $CI->config->item('master_entities', 'master_entities') ?: [];
        log_message('debug', '[MasterEngine Config] Loaded ' . count($all_config) . ' entities: ' . implode(', ', array_keys($all_config)));
    }

    if (!isset($all_config[$entity])) {
        log_message('error', '[MasterEngine] No config found for entity: ' . $entity . ', available: ' . implode(', ', array_keys($all_config)));
        return null;
    }

    log_message('debug', '[MasterEngine Config] Returning config for entity: ' . $entity);
    return $all_config[$entity];
}

/**
 * Validate that $id is a positive integer. Returns sanitized int or false.
 *
 * @param  mixed $id  Raw ID parameter from URL
 * @return int|false   Sanitized positive integer, or false if invalid
 */
function _lmx_validate_id($id)
{
    $id = (int) $id;
    return ($id > 0) ? $id : false;
}

/**
 * Validate that $status is 0 or 1.
 *
 * @param  mixed $status  Raw status parameter from URL
 * @return int|false      Sanitized status (0 or 1), or false if invalid
 */
function _lmx_validate_status($status)
{
    $status = (int) $status;
    return in_array($status, [0, 1], true) ? $status : false;
}

/**
 * Resolve the form widget type for a config field.
 *
 * Precedence:
 *   1. Field appears in $config['dropdowns']            → 'select'
 *   2. Explicit 3rd element is a simple widget string   → that value (number|date|textarea|text|checkbox)
 *   3. 3rd element is an array with 'options' key       → 'fixed-select' (inline options)
 *   4. Sanitize rule == 'remarks'                       → 'textarea'
 *   5. Cast type 'date'                                 → 'date'
 *   6. Cast type 'bool'                                 → 'checkbox'
 *   7. Cast type 'int' | 'float'                        → 'number'
 *   8. Otherwise                                        → 'text'
 *
 * @param  string $field   DB column / POST field name
 * @param  mixed  $type    inputs spec: [cast, sanitize, widget?] or string
 * @param  array  $config  Full entity config
 * @return string          Widget key
 */
function _lmx_master_widget($field, $type, $config)
{
    if (!empty($config['dropdowns'][$field])) {
        return 'select';
    }
    if (is_array($type)) {
        if (isset($type[2])) {
            if (is_string($type[2]) && $type[2] !== '') {
                return $type[2];  // explicit: 'checkbox', 'text', 'number', etc.
            }
            if (is_array($type[2]) && isset($type[2]['options'])) {
                return 'fixed-select';  // inline options for this field
            }
        }
        $cast     = isset($type[0]) ? $type[0] : 'string';
        $sanitize = isset($type[1]) ? $type[1] : '';
        if ($sanitize === 'remarks')            return 'textarea';
        if ($cast === 'date')                   return 'date';
        if ($cast === 'bool')                   return 'checkbox';
        if ($cast === 'int' || $cast === 'float') return 'number';
    }
    return 'text';
}

/**
 * Main entry point for master controller methods.
 *
 * Usage in controller:
 *   public function theme($type = '', $id = '', $status = '') {
 *       master_crud($this, 'theme', $type, $id, $status);
 *   }
 */
function master_crud(&$CI, $entity_key, $type = '', $id = '', $status = '')
{
    // Debug: Log entry
    log_message('debug', '[MasterEngine] ENTRY: entity=' . $entity_key . ', type=' . ($type ?: 'list') . ', id=' . $id . ', status=' . $status);

    $config = _lmx_entity_config($entity_key);
    if (!$config) {
        log_message('error', '[MasterEngine] Config NOT found for entity: ' . $entity_key);
        show_error("Configuration for '{$entity_key}' not found.");
        return;
    }
    log_message('debug', '[MasterEngine] Config loaded for entity: ' . $entity_key . ', table=' . $config['table']);

    // Get route path (used for permission checking)
    $route_path = isset($config['access_path']) ? $config['access_path'] : 'admin_ret_catalog/' . $entity_key . '/list';
    log_message('debug', '[MasterEngine] Route path for access check: ' . $route_path);
    
    switch ($type) {
        case 'add':
            if (!lmx_can($CI, $route_path, 'add')) {
                lmx_legacy_error('Access Denied');
                return;
            }
            _master_save($CI, $config, null);
            break;

        case 'edit':
            if (!lmx_can($CI, $route_path, 'edit')) {
                lmx_legacy_error('Access Denied');
                return;
            }
            // Validate ID parameter
            $id = _lmx_validate_id($id);
            if ($id === false) {
                lmx_legacy_error('Invalid record ID');
                return;
            }
            _master_save($CI, $config, $id);
            break;

        case 'delete':
            if (!lmx_can($CI, $route_path, 'delete')) {
                lmx_flash_error($CI, 'Access Denied', 'Error', 'admin_ret_catalog/' . $entity_key);
                return;
            }
            // Validate ID parameter
            $id = _lmx_validate_id($id);
            if ($id === false) {
                lmx_flash_error($CI, 'Invalid record ID', 'Error', 'admin_ret_catalog/' . $entity_key);
                return;
            }
            _master_delete($CI, $config, $id, $entity_key);
            break;

        case 'update_status':
            if (!lmx_can($CI, $route_path, 'edit')) {
                lmx_flash_error($CI, 'Access Denied', 'Error', 'admin_ret_catalog/' . $entity_key);
                return;
            }
            // Validate ID parameter
            $id = _lmx_validate_id($id);
            if ($id === false) {
                lmx_flash_error($CI, 'Invalid record ID', 'Error', 'admin_ret_catalog/' . $entity_key);
                return;
            }
            // Validate status parameter
            $status = _lmx_validate_status($status);
            if ($status === false) {
                lmx_flash_error($CI, 'Invalid status value. Must be 0 or 1.', 'Error', 'admin_ret_catalog/' . $entity_key);
                return;
            }
            _master_update_status($CI, $config, $id, $status, $entity_key);
            break;

        case 'check_unique':
            // Live duplicate check for fields with a 'unique' rule — lets the
            // add/edit form say "already exists" on blur instead of only after
            // a full submit. Not authoritative: lmx_validate() re-checks the
            // same thing server-side on save regardless of what this returns
            // (a stale/tampered client response can't get a duplicate saved).
            if (!lmx_can($CI, $route_path, 'view')) {
                echo json_encode(['exists' => false]);
                return;
            }
            $field = $CI->input->post('field');
            $value = $CI->input->post('value');
            $excludeId = _lmx_validate_id($CI->input->post('exclude_id'));
            $rule = isset($config['rules'][$field]['unique']) ? $config['rules'][$field]['unique']
                : (isset($config['child_table']['rules'][$field]['unique']) ? $config['child_table']['rules'][$field]['unique'] : null);
            if ($rule === null || $value === null || $value === '') {
                echo json_encode(['exists' => false]);
                return;
            }
            $CI->load->helper('lmx/core/crud_helper');
            $u_table = $rule['table'];
            $column = isset($rule['column']) ? $rule['column'] : $field;
            $pk = isset($rule['pk']) ? $rule['pk'] : $config['pk'];
            $extraWhere = [];
            if (!empty($rule['extra_where_field'])) {
                $extraVal = $CI->input->post('extra_value');
                if ($extraVal !== null && $extraVal !== '') {
                    $extraWhere[$rule['extra_where_field']] = $extraVal;
                }
            }
            $exists = lmx_duplicate_exists($CI, $u_table, $column, $value, $excludeId, $pk, $extraWhere);
            echo json_encode(['exists' => $exists]);
            break;

        case 'child_rows':
            // Fetch a parent-detail master's existing child rows for the Edit
            // modal — the DataTable row only has the parent's own columns, so
            // child_table masters need this extra round trip on Edit click.
            if (!lmx_can($CI, $route_path, 'view')) {
                lmx_legacy_error('Access Denied');
                return;
            }
            $id = _lmx_validate_id($id);
            if ($id === false || empty($config['child_table'])) {
                echo json_encode([]);
                return;
            }
            $ct = $config['child_table'];
            $CI->db->where($ct['fk_column'], $id);
            echo json_encode($CI->db->get($ct['table'])->result_array());
            break;

        case 'list':
        default:
            log_message('debug', '[MasterEngine] LIST action: checking view permission for ' . $route_path);
            $access = lmx_access($CI, $route_path);
            log_message('debug', '[MasterEngine] Access result: ' . json_encode($access));

            if (!lmx_can($CI, $route_path, 'view')) {
                log_message('error', '[MasterEngine] Access DENIED for view action on ' . $route_path);
                show_error('Access Denied');
                return;
            }
            log_message('debug', '[MasterEngine] Access granted for view action');

            // Check if AJAX request for DataTables (with serverSide: false, DataTables doesn't send 'draw')
            // Detect AJAX by POST + JSON response expectation, or explicit ajax marker
            $is_ajax = $CI->input->is_ajax_request();
            log_message('debug', '[MasterEngine] Is AJAX request: ' . ($is_ajax ? 'YES' : 'NO'));
            if ($is_ajax) {
                log_message('debug', '[MasterEngine] Calling _master_ajax_list for AJAX request');
                _master_ajax_list($CI, $config, $access);
                return;
            }

            log_message('debug', '[MasterEngine] Rendering view for list');
            // Render View
            $data = [
                'access'           => $access,
                'entity_key'       => $entity_key,
                'entity_name'      => $config['label'],
                'config'           => $config,
                'dropdown_options' => _master_load_dropdown_options($CI, $config),
                'field_meta'       => _master_field_meta($CI, $config),
                'rule_patterns'    => _master_rule_patterns($CI),
            ];
            $view = isset($config['views']['list']) ? $config['views']['list'] : 'ret_catalog/master_list';
            log_message('debug', '[MasterEngine] View to render: ' . $view . ' (layout/template)');
            $data['main_content'] = $view;
            $CI->load->view('layout/template', $data);
            log_message('debug', '[MasterEngine] View rendering complete');
            break;
    }
}

/**
 * Handle Add/Edit save logic.
 */
function _master_save(&$CI, $config, $id = null)
{
    // 1. Read & Sanitize Inputs
    $data = lmx_inputs($CI, $config['inputs']);

    // 1.1 Status checkbox — NOT part of $config['inputs'], so it was previously
    // never read from POST at all: Edit's status switch silently did nothing
    // (missing key just left the column untouched), and Add unconditionally
    // forced status=1 further down regardless of what was submitted.
    //
    // A real HTML form ALWAYS sends this field explicitly (add_generic.php
    // pairs the checkbox with a hidden fallback input, so unchecked -> "0",
    // checked -> "0" then "1", last value wins) — so genuine absence from
    // POST only happens for programmatic callers (API/test helpers) that
    // don't manage status at all. Those need the OLD implicit defaults
    // preserved (Add -> active; Edit -> leave whatever it already was
    // untouched) rather than an arbitrary 0/1 forced on them, or dependent
    // masters' in_use_check ends up checking a row that was silently created
    // inactive and never gets flagged as "in use".
    if (isset($config['status_col'])) {
        $statusRaw = $CI->input->post($config['status_col']);
        if ($statusRaw !== null && $statusRaw !== false) {
            $data[$config['status_col']] = ($statusRaw === '1' || $statusRaw === 1) ? 1 : 0;
        } elseif (!$id) {
            $data[$config['status_col']] = 1; // New record, status not submitted at all -> default active
        }
        // Edit with status truly absent (no form field sent at all): leave
        // $data[status_col] unset so lmx_update() doesn't touch the column.
    }

    // 1.5 Derived fields — values computed from a lookup on another submitted
    // field, not typed by the user (e.g. old_metal_cat's id_metal is looked
    // up from the selected id_old_metal_type, matching the legacy behavior
    // of denormalizing the metal type's own metal reference).
    if (!empty($config['derive'])) {
        foreach ($config['derive'] as $d) {
            $sourceVal = isset($data[$d['from_field']]) ? $data[$d['from_field']] : null;
            $data[$d['field']] = null;
            if (!empty($sourceVal)) {
                // lmx_get($CI, $table, $whereCol, $whereVal, $columns='*') — omit
                // $columns so the full row comes back and source_value_col is there.
                $sourceRow = lmx_get($CI, $d['source_table'], $d['source_pk'], $sourceVal);
                if ($sourceRow && isset($sourceRow[$d['source_value_col']])) {
                    $data[$d['field']] = $sourceRow[$d['source_value_col']];
                }
            }
        }
    }

    // 1.55 Mirror fields — a second column that always stores the exact same
    // value as another submitted field (e.g. Metal Purity Rate's
    // market_rate_field, which legacy always set equal to rate_field on every
    // save; not a lookup like 'derive', just a direct copy).
    if (!empty($config['mirror_fields'])) {
        foreach ($config['mirror_fields'] as $targetField => $sourceField) {
            if (isset($data[$sourceField])) {
                $data[$targetField] = $data[$sourceField];
            }
        }
    }

    // 1.6 Field-level transforms — legacy normalization (e.g. ret_qc_cancel_reason's
    // strtoupper() on cancel_reason) applied before validation/save so stored
    // data matches existing rows saved by the old controller code.
    if (!empty($config['transforms'])) {
        foreach ($config['transforms'] as $field => $fn) {
            if (isset($data[$field]) && function_exists($fn)) {
                $data[$field] = $fn($data[$field]);
            }
        }
    }

    // 1.7 System-set date fields — e.g. Diamond/Selling Diamond Rate's
    // effective_date, which legacy always sets to today() on every save
    // (add AND edit) rather than reading it from the form.
    if (!empty($config['set_today'])) {
        foreach ($config['set_today'] as $field) {
            $data[$field] = date('Y-m-d');
        }
    }

    // 2. Validate
    $rules = $config['rules'];
    
    // Auto-inject exclude_id and pk into unique rules if editing
    if ($id) {
        foreach ($rules as $field => &$rule) {
            if (isset($rule['unique'])) {
                $rule['unique']['exclude_id'] = $id;
                if (!isset($rule['unique']['pk'])) {
                    $rule['unique']['pk'] = $config['pk'];
                }
            }
        }
    }

    // I-GMSJ-005: fk_check's active-row requirement is correct for a NEW
    // reference, but was blocking edits outright — if this field's own FK
    // was active when the record was originally saved and has since been
    // deactivated elsewhere, resubmitting the SAME unchanged value (the user
    // isn't touching this field) got rejected with "no longer active",
    // making the record permanently un-editable. Only skip the active-check
    // when the submitted value truly matches what's already stored.
    if ($id) {
        $existingRow = lmx_get($CI, $config['table'], $config['pk'], $id);
        if ($existingRow) {
            foreach ($rules as $field => &$rule) {
                if (!empty($rule['fk_check']) && !empty($rule['fk_check']['status_col'])
                    && array_key_exists($field, $existingRow) && isset($data[$field])
                    && (string)$data[$field] === (string)$existingRow[$field]) {
                    $rule['fk_check']['skip_active_check'] = true;
                }
            }
            unset($rule);
        }
    }

    $validation = lmx_validate($CI, $data, $rules, $config['table'], $config['inputs']);
    if (!$validation['valid']) {
        lmx_validation_error($validation['errors']);
        return;
    }

    // Cross-field checks (e.g. date ranges) — can't be expressed by a single
    // field's rule entry, run after per-field validation passes.
    if (!empty($config['cross_field_rules'])) {
        $crossValidation = lmx_validate_cross_fields($data, $config['cross_field_rules']);
        if (!$crossValidation['valid']) {
            lmx_validation_error($crossValidation['errors']);
            return;
        }
    }

    // Multi-column composite uniqueness (e.g. Diamond Master's real DB-level
    // unique index on cut_id+color_id+clarity_id+shape_id together) — not
    // expressible as a single field's 'unique' rule. Without this check the
    // save falls through to a raw, unhandled SQL duplicate-key error instead
    // of a clean validation message.
    if (!empty($config['composite_unique'])) {
        foreach ($config['composite_unique'] as $cu) {
            $cuPk = isset($cu['pk']) ? $cu['pk'] : $config['pk'];
            if (lmx_composite_unique_exists($CI, $cu['table'], $cu['columns'], $data, $id, $cuPk)) {
                lmx_validation_error([$cu['message']]);
                return;
            }
        }
    }

    // 2.6 Child table (parent/detail masters — e.g. Tax Group's tax components,
    // Attribute's value list). Read + validate rows before touching the DB so
    // a bad child row fails the whole save cleanly, same as parent validation.
    $childRows = [];
    if (!empty($config['child_table'])) {
        $childRows = _master_read_child_rows($CI, $config['child_table']);
        if (!empty($config['child_table']['min_rows']) && count($childRows) < $config['child_table']['min_rows']) {
            lmx_validation_error([$config['child_table']['label'] . ': at least ' . $config['child_table']['min_rows'] . ' row(s) required']);
            return;
        }
        foreach ($childRows as $i => $row) {
            $rowValidation = lmx_validate($CI, $row, $config['child_table']['rules'], $config['child_table']['table']);
            $rowErrors = $rowValidation['errors'];
            if (!empty($config['child_table']['cross_field_rules'])) {
                $rowCross = lmx_validate_cross_fields($row, $config['child_table']['cross_field_rules']);
                $rowErrors = array_merge($rowErrors, $rowCross['errors']);
            }
            if (!empty($rowErrors)) {
                $label = $config['child_table']['label'];
                lmx_validation_error(array_map(function($e) use ($label, $i) {
                    return $label . ' row ' . ($i + 1) . ': ' . $e;
                }, $rowErrors));
                return;
            }
        }

        // Cross-row duplicate check (e.g. Attribute's value list, Tax Group's
        // tax components) — a single field's 'unique' DB rule only catches
        // conflicts against already-saved rows; child rows are fully
        // resubmitted on every save, so two NEW rows with the same value in
        // one submission need an in-memory check instead.
        if (!empty($config['child_table']['unique_within_parent'])) {
            $ucField = $config['child_table']['unique_within_parent'];
            $seen = [];
            foreach ($childRows as $i => $row) {
                $val = isset($row[$ucField]) ? strtolower(trim((string)$row[$ucField])) : '';
                if ($val === '') continue;
                if (isset($seen[$val])) {
                    lmx_validation_error([$config['child_table']['label'] . ': duplicate value not allowed (row ' . ($seen[$val] + 1) . ' and row ' . ($i + 1) . ')']);
                    return;
                }
                $seen[$val] = $i;
            }
        }

        // Cross-row range-overlap check (e.g. Diamond Rate's cent-weight
        // ranges) — cross_field_rules above only validates a single row's own
        // from/to order, not whether two rows' ranges overlap each other.
        if (!empty($config['child_table']['no_overlap'])) {
            $ov = $config['child_table']['no_overlap'];
            for ($i = 0; $i < count($childRows); $i++) {
                for ($j = $i + 1; $j < count($childRows); $j++) {
                    $aFrom = (float)$childRows[$i][$ov['from_field']];
                    $aTo   = (float)$childRows[$i][$ov['to_field']];
                    $bFrom = (float)$childRows[$j][$ov['from_field']];
                    $bTo   = (float)$childRows[$j][$ov['to_field']];
                    if ($aFrom <= $bTo && $aTo >= $bFrom) {
                        lmx_validation_error([$config['child_table']['label'] . ': row ' . ($i + 1) . ' and row ' . ($j + 1) . ' have overlapping ranges']);
                        return;
                    }
                }
            }
        }

        // Child rows never hard-delete either, same rule as masters: a row
        // removed from the repeater is about to be DELETEd for real (see
        // _master_save_child_rows()) — if it's still referenced elsewhere
        // (e.g. ret_attribute_values.attr_val_id on real tagged inventory),
        // block the whole save instead of silently orphaning that reference.
        $childRemovalBlocker = _master_check_child_removal($CI, $config['child_table'], $id, $childRows);
        if ($childRemovalBlocker !== null) {
            lmx_validation_error([$childRemovalBlocker]);
            return;
        }
    }

    // 2.5 Double-submit prevention (add only — same user, same data, within 5 seconds)
    // Not every master's table uses created_by/created_on (e.g. ret_tag_type_master
    // has create_by/created_time, ret_old_metal_category has date_add, ret_quality_code
    // has created_by_id) — check the real schema rather than assuming the convention
    // holds everywhere, or this throws "Unknown column" on every single Add.
    if (!$id) {
        $tableSchema = lmx_schema_columns($CI, $config['table']);
        if (isset($tableSchema['created_by']) && isset($tableSchema['created_on'])) {
            $dedupe_data = $data;
            $CI->db->where($dedupe_data);
            $CI->db->where('created_by', $CI->session->userdata('uid'));
            $CI->db->where('created_on >', date('Y-m-d H:i:s', time() - 5));
            if ($CI->db->count_all_results($config['table']) > 0) {
                lmx_legacy_error('Duplicate submission detected. Record already saved.');
                return;
            }
        }
    }

    // 3. Save inside transaction
    $result = lmx_transaction($CI, function(&$CI) use ($config, $data, $id, $childRows) {
        if ($id) {
            // Capture old values BEFORE update for audit diff
            $old = lmx_get($CI, $config['table'], $config['pk'], $id);

            // Editing a row to Active must enforce exclusive_active too — not
            // just Add and the dedicated status-toggle. Only fires when status
            // was genuinely submitted (see 1.1 above); an edit that doesn't
            // touch status shouldn't touch exclusivity either.
            if (!empty($config['exclusive_active']) && isset($data[$config['status_col']]) && (int)$data[$config['status_col']] === 1) {
                $matchRow = $old ? array_merge($old, $data) : $data;
                _master_enforce_exclusive_active($CI, $config, $id, $matchRow);
            }

            lmx_update($CI, $config['table'], $config['pk'], $id, $data);

            // Compute before/after diff for audit trail
            $changes = [];
            if ($old) {
                foreach ($data as $key => $new_val) {
                    if (isset($old[$key]) && (string)$old[$key] !== (string)$new_val) {
                        $changes[$key] = ['from' => $old[$key], 'to' => $new_val];
                    }
                }
            }
            $remark = !empty($changes) ? 'Changes: ' . json_encode($changes) : 'No fields changed';
            lmx_audit($CI, $config['label'], 'Update', $id, $remark);
            $parent_id = $id;
        } else {
            // status_col is already correctly set from the submitted checkbox
            // by the read at the top of this function (1.1) — no override here
            // anymore. The Add form's checkbox defaults to checked in the
            // markup, so a normal Add without touching it still comes in as 1.

            // Rate-versioning masters (e.g. Selling Diamond Rate — multiple
            // historical rows per quality_code_id are expected, only one may
            // be "active" at a time): deactivate any existing row(s) sharing
            // the same key BEFORE inserting the new active one, same as the
            // legacy controller did. Only when the new row is itself active —
            // adding an explicitly Inactive row must never wipe out someone
            // else's active row.
            if (!empty($config['exclusive_active']) && (int)$data[$config['status_col']] === 1) {
                _master_enforce_exclusive_active($CI, $config, null, $data);
            }

            $parent_id = lmx_insert($CI, $config['table'], $data);
            lmx_audit($CI, $config['label'], 'Add', $parent_id);
        }

        if (!empty($config['child_table'])) {
            _master_save_child_rows($CI, $config['child_table'], $parent_id, $childRows);
        }

        return $parent_id;
    }, $config['label']);

    // 4. Respond
    if ($result['success']) {
        // Using legacy success for now to not break existing catalog_master.js
        lmx_legacy_success($config['label'] . ($id ? ' updated' : ' added') . ' successfully');
    } else {
        lmx_legacy_error('Failed to save ' . $config['label']);
    }
}

/**
 * Run all of a master's in_use_check entries against a given record id.
 * Returns null if nothing blocks, or a ready-to-display error message if a
 * dependency exists. Shared by _master_delete() and _master_update_status()
 * since both must apply the identical check before deactivating a record —
 * "Delete" and the status toggle both just set status_col=0.
 *
 * Most checks compare a dependent table's column directly against the
 * record's own PK. Some masters are instead referenced elsewhere by a
 * business-key column rather than the numeric PK — e.g. Financial Year's
 * `fin_id` is never stored downstream; billing/estimation/purchase-order/etc.
 * all store `fin_year_code` instead. Set 'match_column' on that check entry
 * to compare against that column's value on the record being deactivated,
 * instead of the raw id.
 *
 * @param  object &$CI
 * @param  array  $config
 * @param  mixed  $id
 * @param  string $verb  'delete' | 'deactivate' — only affects the message wording
 * @return string|null
 */
function _master_check_in_use(&$CI, $config, $id, $verb = 'deactivate')
{
    if (empty($config['in_use_check'])) {
        return null;
    }
    foreach ($config['in_use_check'] as $check) {
        $matchValue = $id;
        if (!empty($check['match_column'])) {
            $row = lmx_get($CI, $config['table'], $config['pk'], $id);
            $matchValue = ($row && isset($row[$check['match_column']])) ? $row[$check['match_column']] : null;
            if ($matchValue === null) {
                continue; // Nothing to match against (e.g. code column blank) — skip this check.
            }
        }
        $CI->db->where($check['column'], $matchValue);
        // Optionally restrict check to only "active" dependent records
        if (!empty($check['active_only'])) {
            $check_status_col = isset($check['status_column']) ? $check['status_column'] : 'status';
            $CI->db->where($check_status_col, 1);
        }
        $count = $CI->db->count_all_results($check['table']);
        if ($count > 0) {
            $active_label = !empty($check['active_only']) ? 'active ' : '';
            return "Cannot {$verb} {$config['label']}. It is referenced by {$count} {$active_label}record(s) in {$check['table']}.";
        }
    }
    return null;
}

/**
 * Child rows never hard-delete either — same rule as masters (see
 * _master_check_in_use()), just applied to a child_table's own removed rows
 * instead of the parent record. _master_save_child_rows() genuinely deletes
 * any existing child row not resubmitted (the user removed it from the
 * repeater); for a child_table with its own 'in_use_check' configured (e.g.
 * Attribute's values — ret_attribute_values.attr_val_id is a real FK on
 * ret_design_attributes/ret_tagging_attributes), a removed row that's still
 * referenced elsewhere must block the WHOLE save instead of silently
 * orphaning that reference. Runs before the save transaction so a blocked
 * removal never partially saves.
 *
 * @param  object &$CI
 * @param  array  $childConfig    config['child_table']
 * @param  mixed  $parentId       null on Add (nothing exists yet to remove)
 * @param  array  $submittedRows  From _master_read_child_rows()
 * @return string|null
 */
function _master_check_child_removal(&$CI, $childConfig, $parentId, $submittedRows)
{
    if (empty($childConfig['in_use_check']) || !$parentId) {
        return null;
    }

    $CI->db->select($childConfig['pk']);
    $CI->db->where($childConfig['fk_column'], $parentId);
    $existingIds = array_column($CI->db->get($childConfig['table'])->result_array(), $childConfig['pk']);

    $submittedPks = array_filter(array_column($submittedRows, '_pk'), function ($v) { return $v !== null; });
    $removed = array_diff($existingIds, $submittedPks);

    $adHocConfig = [
        'table'        => $childConfig['table'],
        'pk'           => $childConfig['pk'],
        'label'        => isset($childConfig['label']) ? $childConfig['label'] : 'row',
        'in_use_check' => $childConfig['in_use_check'],
    ];
    foreach ($removed as $removedId) {
        $blocker = _master_check_in_use($CI, $adHocConfig, $removedId, 'remove');
        if ($blocker !== null) {
            return $blocker;
        }
    }
    return null;
}

/**
 * Handle Delete logic.
 */
function _master_delete(&$CI, $config, $id, $entity_key = '')
{
    if (!isset($config['status_col'])) {
        lmx_legacy_error('Status column not configured for soft delete');
        return;
    }

    // [LMX-MIGRATION] Dependency check before soft delete (deactivation)
    $blocker = _master_check_in_use($CI, $config, $id, 'delete');
    if ($blocker !== null) {
        lmx_legacy_error($blocker);
        return;
    }

    $result = lmx_transaction($CI, function(&$CI) use ($config, $id) {
        // [LMX-MIGRATION] Soft Delete strategy: just mark as inactive
        lmx_update($CI, $config['table'], $config['pk'], $id, [$config['status_col'] => 0]);
        lmx_audit($CI, $config['label'], 'Deactivate', $id);
        return true;
    }, $config['label']);

    if ($result['success']) {
        lmx_legacy_success($config['label'] . ' deactivated successfully');
    } else {
        lmx_legacy_error('Failed to deactivate ' . $config['label']);
    }
}

/**
 * When a row is being made Active and its master config declares
 * 'exclusive_active', deactivate every sibling row that would otherwise
 * also be active — either within the same key_field group (e.g. Selling
 * Diamond Rate: only one active rate per quality_code_id) or, when
 * key_field is omitted, GLOBALLY across the whole table (e.g. Financial
 * Year: only one active row, period — no grouping column exists to scope
 * it to). Called from all three places a row can become active: Add,
 * Edit, and the dedicated status-toggle — a master that only wires this
 * into one of the three still lets the invariant be broken via the other
 * two, which is exactly how Financial Year ended up with 3 simultaneously
 * active rows in the same table.
 *
 * @param  mixed      $excludeId  This row's own pk value, or null on Add
 *                                 (nothing to exclude — the row doesn't
 *                                 exist yet).
 * @param  array      $matchRow   Row data to read the key_field's value
 *                                 from (submitted $data on Add/Edit, the
 *                                 fetched row on status-toggle).
 */
function _master_enforce_exclusive_active(&$CI, $config, $excludeId, $matchRow)
{
    $keyField = isset($config['exclusive_active']['key_field']) ? $config['exclusive_active']['key_field'] : null;

    if ($excludeId !== null) {
        $CI->db->where($config['pk'] . ' !=', $excludeId);
    }
    if ($keyField !== null) {
        if (!isset($matchRow[$keyField])) {
            return; // Nothing to group by — skip rather than guess.
        }
        $CI->db->where($keyField, $matchRow[$keyField]);
    }
    $CI->db->update($config['table'], [$config['status_col'] => 0]);
}

/**
 * Handle Status Update logic.
 */
function _master_update_status(&$CI, $config, $id, $status, $entity_key = '')
{
    if (!isset($config['status_col'])) {
        lmx_legacy_error('Status column not configured');
        return;
    }

    $new_status = ($status == 1) ? 0 : 1;

    // [LMX-MIGRATION] Dependency check before deactivation
    if ($new_status == 0) {
        $blocker = _master_check_in_use($CI, $config, $id, 'deactivate');
        if ($blocker !== null) {
            lmx_legacy_error($blocker);
            return;
        }
    }

    $result = lmx_transaction($CI, function(&$CI) use ($config, $id, $new_status) {
        // Reactivating a rate-versioning row: deactivate any sibling row(s)
        // sharing the same key first, same as the legacy Add path, so at
        // most one stays active per key.
        if ($new_status == 1 && !empty($config['exclusive_active'])) {
            $row = lmx_get($CI, $config['table'], $config['pk'], $id);
            _master_enforce_exclusive_active($CI, $config, $id, $row ?: []);
        }

        lmx_update($CI, $config['table'], $config['pk'], $id, [$config['status_col'] => $new_status]);
        $status_label = ($new_status == 1) ? 'Activated' : 'Deactivated';
        lmx_audit($CI, $config['label'], 'Status Change', $id, $config['label'] . ' ' . $status_label);
        return true;
    }, $config['label']);

    if ($result['success']) {
        lmx_legacy_success('Status updated successfully');
    } else {
        lmx_legacy_error('Failed to update status');
    }
}

/**
 * Fetch DataTables list.
 *
 * Backward compatible: if the config declares a legacy `list_method` on
 * ret_catalog_model, use it (unchanged behaviour for already-migrated masters).
 * Otherwise build the list generically from config (`list_columns` + `dropdowns`),
 * requiring zero model code for new masters.
 */
function _master_ajax_list(&$CI, $config, $access)
{
    $method = isset($config['list_method']) ? $config['list_method'] : null;

    if ($method && isset($CI->ret_catalog_model) && method_exists($CI->ret_catalog_model, $method)) {
        $from_date = lmx_input($CI, 'from_date', 'string');
        $to_date   = lmx_input($CI, 'to_date', 'string');

        $data = $CI->ret_catalog_model->$method($from_date, $to_date);
    } elseif (!empty($config['table'])) {
        $data = _master_generic_list($CI, $config);
    } else {
        lmx_error('List method not implemented');
        return;
    }

    lmx_list_response($data, $access);
}

/**
 * Config-driven DataTable data source. Selects `list_columns` (or *) from the
 * entity table and decorates any dropdown FK columns with a `<field>_label`.
 *
 * @param  object &$CI
 * @param  array  $config
 * @return array  Rows for the DataTable
 */
function _master_generic_list(&$CI, $config)
{
    $cols = isset($config['list_columns']) && !empty($config['list_columns'])
        ? implode(',', $config['list_columns'])
        : '*';

    $CI->db->select($cols);
    $rows = $CI->db->get($config['table'])->result_array();

    if (!empty($config['dropdowns'])) {
        $rows = _resolve_fk_labels($CI, $rows, $config['dropdowns']);
    }

    return $rows;
}

/**
 * Decorate rows with human-readable FK labels. One lookup query per dropdown
 * builds an id => label map (no N+1). Adds `<field>_label` to each row.
 *
 * @param  object &$CI
 * @param  array  $rows
 * @param  array  $dropdowns  field => ['table','pk','label',...]
 * @return array
 */
function _resolve_fk_labels(&$CI, $rows, $dropdowns)
{
    foreach ($dropdowns as $field => $d) {
        $map = [];
        $CI->db->select($d['pk'] . ',' . $d['label']);
        foreach ($CI->db->get($d['table'])->result_array() as $r) {
            $map[$r[$d['pk']]] = $r[$d['label']];
        }
        foreach ($rows as &$row) {
            if (array_key_exists($field, $row)) {
                $row[$field . '_label'] = isset($map[$row[$field]]) ? $map[$row[$field]] : $row[$field];
            }
        }
        unset($row);
    }
    return $rows;
}

/**
 * Load option lists for every dropdown declared on the entity, for the add/edit
 * modals. Applies an optional per-dropdown `filter` (e.g. status = 1).
 *
 * @param  object &$CI
 * @param  array  $config
 * @return array  field => [ ['id'=>.., 'label'=>..], ... ]
 */
function _master_load_dropdown_options(&$CI, $config)
{
    $out = [];
    $dropdowns = isset($config['dropdowns']) ? $config['dropdowns'] : [];
    // Child-table fields (e.g. Tax Group's per-row Tax select) share the same
    // dropdown-options loader and the same _field_widget.php renderer as
    // top-level fields — merge them in so the repeater's <select> options
    // come from one place.
    if (!empty($config['child_table']['dropdowns'])) {
        $dropdowns = array_merge($dropdowns, $config['child_table']['dropdowns']);
    }
    if (empty($dropdowns)) {
        return $out;
    }
    foreach ($dropdowns as $field => $d) {
        $CI->db->select($d['pk'] . ' AS id, ' . $d['label'] . ' AS label');
        if (!empty($d['filter'])) {
            $CI->db->where($d['filter']);
        }
        $out[$field] = $CI->db->get($d['table'])->result_array();
    }
    return $out;
}

/**
 * Per-field metadata for client-side validation — the same rule name and
 * effective max length the SERVER will enforce, so the add/edit form's
 * keystroke filtering and maxlength= attribute can't drift out of sync with
 * two hand-maintained numbers (one in PHP, one in JS). Frontend validation
 * here is UX only — lmx_validate() on the server remains the actual gate.
 *
 * @param  object &$CI
 * @param  array  $config
 * @return array  field => ['rule','max_length','required','min_val','max_val','has_unique']
 */
function _master_field_meta(&$CI, $config)
{
    if (!function_exists('lmx_effective_max_length')) {
        $CI->load->helper('lmx/core/validation_helper');
    }
    $out = [];
    $schema = !empty($config['table']) ? lmx_schema_columns($CI, $config['table']) : [];
    foreach ($config['rules'] as $field => $rule) {
        $out[$field] = _master_one_field_meta($rule, isset($schema[$field]) ? $schema[$field] : null);
    }
    if (!empty($config['child_table'])) {
        $childSchema = !empty($config['child_table']['table']) ? lmx_schema_columns($CI, $config['child_table']['table']) : [];
        foreach ($config['child_table']['rules'] as $field => $rule) {
            $out[$field] = _master_one_field_meta($rule, isset($childSchema[$field]) ? $childSchema[$field] : null);
        }
    }
    return $out;
}

/**
 * JS-ready format-rule regex patterns for the add/edit form's keystroke
 * filter and pre-submit check — see lmx_rule_js_patterns() (field_validation_helper.php)
 * for why these are the SAME character sets validate_master_field() enforces
 * server-side, not a hand-copied second definition.
 *
 * @param  object &$CI
 * @return array  ruleName => ['strict', 'keystroke', 'flags', 'message']
 */
function _master_rule_patterns(&$CI)
{
    if (!function_exists('lmx_rule_js_patterns')) {
        $CI->load->helper('field_validation');
    }
    return lmx_rule_js_patterns();
}

/**
 * @internal Builds one field's metadata entry for _master_field_meta().
 */
function _master_one_field_meta($rule, $schemaColumn)
{
    return [
        'rule'          => isset($rule['rule']) ? $rule['rule'] : null,
        // Escape-hatch custom regex (see validate_master_field()) — when set,
        // takes precedence over 'rule' for the strict pre-submit check; 'rule'
        // (if also set) still drives keystroke filtering.
        'pattern'       => isset($rule['pattern']) ? $rule['pattern'] : null,
        'pattern_flags' => isset($rule['pattern_flags']) ? $rule['pattern_flags'] : '',
        'pattern_message' => isset($rule['pattern_message']) ? $rule['pattern_message'] : null,
        'max_length'    => lmx_effective_max_length($rule, $schemaColumn),
        'required'      => !empty($rule['required']),
        'min_val'       => isset($rule['min_val']) ? $rule['min_val'] : null,
        'max_val'       => isset($rule['max_val']) ? $rule['max_val'] : null,
        'has_unique'    => !empty($rule['unique']),
        'extra_where_field' => isset($rule['unique']['extra_where_field']) ? $rule['unique']['extra_where_field'] : null,
    ];
}

/**
 * Read submitted child rows for a parent/detail master (e.g. Tax Group's tax
 * components, Attribute's value list) from $_POST[post_key][n][field...].
 * Each row's own PK travels under the reserved key '_pk' — present (non-empty)
 * for an existing row being edited, empty/absent for a new row. This is how
 * _master_save_child_rows() below tells "update this row" from "insert new".
 *
 * @param  object &$CI
 * @param  array  $childConfig  config['child_table']
 * @return array  List of ['_pk' => .., field => value, ...]
 */
function _master_read_child_rows(&$CI, $childConfig)
{
    $raw = $CI->input->post($childConfig['post_key']);
    if (!is_array($raw)) {
        return [];
    }
    $rows = [];
    foreach ($raw as $rawRow) {
        if (!is_array($rawRow)) {
            continue;
        }
        $row = ['_pk' => isset($rawRow['_pk']) && $rawRow['_pk'] !== '' ? (int) $rawRow['_pk'] : null];
        foreach ($childConfig['inputs'] as $field => $spec) {
            $type = is_array($spec) ? (isset($spec[0]) ? $spec[0] : 'string') : $spec;
            $value = isset($rawRow[$field]) ? $rawRow[$field] : '';
            switch ($type) {
                case 'int':   $row[$field] = ($value === '' ? null : (int) $value); break;
                case 'float': $row[$field] = ($value === '' ? null : round((float) $value, 4)); break;
                default:      $row[$field] = trim($CI->security->xss_clean((string) $value));
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Diff-merge a parent/detail master's child rows against what's already in
 * the DB. Matches by the child table's own PK (submitted under '_pk') rather
 * than blindly delete-all-then-reinsert, because some child rows are
 * themselves referenced elsewhere (e.g. ret_attribute_values.attr_val_id is
 * a real FK on ret_design_attributes/ret_tagging_attributes) — reinserting
 * would hand an unrelated row the old id and silently dangle those references.
 * Rows with no matching existing PK are inserted fresh; existing PKs not
 * present in the submission are removed (the user deleted that row in the UI).
 *
 * @param  object &$CI
 * @param  array  $childConfig  config['child_table']
 * @param  int    $parent_id
 * @param  array  $rows         From _master_read_child_rows()
 */
function _master_save_child_rows(&$CI, $childConfig, $parent_id, $rows)
{
    $table  = $childConfig['table'];
    $fk     = $childConfig['fk_column'];
    $pk     = $childConfig['pk'];
    $fields = array_keys($childConfig['inputs']);

    $CI->db->select($pk);
    $CI->db->where($fk, $parent_id);
    $existingIds = array_column($CI->db->get($table)->result_array(), $pk);

    $seen = [];
    foreach ($rows as $row) {
        $rowData = array_intersect_key($row, array_flip($fields));
        if ($row['_pk'] !== null && in_array($row['_pk'], $existingIds, false)) {
            lmx_update($CI, $table, $pk, $row['_pk'], $rowData);
            $seen[] = $row['_pk'];
        } else {
            $rowData[$fk] = $parent_id;
            lmx_insert($CI, $table, $rowData);
        }
    }

    $removed = array_diff($existingIds, $seen);
    foreach ($removed as $removedId) {
        $CI->db->where($pk, $removedId);
        $CI->db->delete($table);
    }
}
