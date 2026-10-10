<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Generic field-level audit trail helper.
 * Writes old->new changes into `ret_field_audit` (see migration 20260722_000009).
 * First user: Stone Weight Markup % (field_name = 'stone_markup_percent').
 * Reusable for future old/new tracking (discount, wastage, rate overrides) — only module/field_name change.
 */

if (!function_exists('_audit_norm')) {
    /**
     * Normalise a value for change-detection so NULL / '' / '0' / '0.00' all read as "no value".
     */
    function _audit_norm($v) {
        if ($v === null || $v === '' || $v === false) return '';
        if (is_numeric($v)) {
            // 0, 0.00, '0' -> '' (no markup); otherwise a canonical numeric string
            $f = (float)$v;
            return ($f == 0.0) ? '' : rtrim(rtrim(sprintf('%.4f', $f), '0'), '.');
        }
        return (string)$v;
    }
}

if (!function_exists('audit_field_changes')) {
    /**
     * @param string $module      e.g. 'tag', 'estimation'
     * @param string $table_name  physical table the changed row lives in, e.g. 'ret_taging_stone'
     * @param int    $ref_id      main record id (tag_id)
     * @param array  $changes     list of ['sub_ref_id'=>, 'field_name'=>, 'old_value'=>, 'new_value'=>]
     * @param int|null $branch_id  optional branch id (falls back to session)
     * Only rows whose old != new (after normalisation) are written. All rows from one call share one event_id.
     */
    function audit_field_changes($module, $table_name, $ref_id, $changes, $branch_id = null) {
        if (empty($changes)) return;
        $CI =& get_instance();

        $event_id = uniqid('', true);
        $now      = date('Y-m-d H:i:s');
        $emp      = (int)$CI->session->userdata('uid');
        if ($branch_id === null) {
            $branch_id = $CI->session->userdata('branch');
            $branch_id = ($branch_id !== null && $branch_id !== '') ? (int)$branch_id : null;
        }

        $rows = array();
        foreach ($changes as $c) {
            $old = isset($c['old_value']) ? $c['old_value'] : null;
            $new = isset($c['new_value']) ? $c['new_value'] : null;
            if (_audit_norm($old) === _audit_norm($new)) continue; // no real change

            $rows[] = array(
                'event_id'   => $event_id,
                'module'     => $module,
                'table_name' => $table_name,
                'ref_id'     => (int)$ref_id,
                'sub_ref_id' => (isset($c['sub_ref_id']) && $c['sub_ref_id'] !== '') ? (int)$c['sub_ref_id'] : null,
                'field_name' => $c['field_name'],
                'old_value'  => ($old === null || $old === '') ? null : (string)$old,
                'new_value'  => ($new === null || $new === '') ? null : (string)$new,
                'changed_by' => $emp,
                'changed_on' => $now,
                'branch_id'  => $branch_id,
            );
        }
        if (!empty($rows)) {
            $CI->db->insert_batch('ret_field_audit', $rows);
        }
    }
}
