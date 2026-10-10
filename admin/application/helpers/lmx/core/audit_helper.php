<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Audit Helper — Standardized Audit Logging
 * ============================================================
 * Single Responsibility: Write audit log entries.
 * Replaces 500+ identical 7-line log_data arrays.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Write a standardized audit log entry.
 *
 * @param  object &$CI        CodeIgniter instance
 * @param  string $module     Module name (e.g., 'Theme', 'Billing', 'Re-Order Settings')
 * @param  string $operation  Operation type (e.g., 'Add', 'Update', 'Delete', 'Status Change')
 * @param  mixed  $record_id  Record ID
 * @param  string $remark     Custom remark (auto-generated if empty)
 */
function lmx_audit(&$CI, $module, $operation, $record_id, $remark = '')
{
    if (empty($remark)) {
        $remark = $module . ' ' . strtolower($operation) . ($operation === 'Add' ? 'ed' : 'd') . ' successfully';
    }

    if (!isset($CI->log_model)) {
        $CI->load->model('log_model');
    }

    $CI->log_model->log_detail('insert', '', [
        'id_log'     => $CI->session->userdata('id_log'),
        'event_date' => date('Y-m-d H:i:s'),
        'module'     => $module,
        'operation'  => $operation,
        'record'     => $record_id,
        'remark'     => $remark,
    ]);
}
