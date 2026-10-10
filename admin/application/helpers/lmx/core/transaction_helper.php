<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ============================================================
 * LMX Transaction Helper — Managed Transaction Wrapping
 * ============================================================
 * Single Responsibility: Wrap operations in DB transactions.
 * Auto-commits on success, auto-rollbacks on failure/exception.
 *
 * @version  1.0.0
 * @created  2026-07-01
 * ============================================================
 */

/**
 * Execute a callback inside a managed transaction.
 *
 * Usage (single insert):
 *   $result = lmx_transaction($this, function(&$CI) use ($data) {
 *       $id = lmx_insert($CI, 'ret_theme', $data);
 *       lmx_audit($CI, 'Theme', 'Add', $id);
 *       return $id;
 *   });
 *
 * Usage (multi-table — billing):
 *   $result = lmx_transaction($this, function(&$CI) use ($header, $items) {
 *       $bill_id = lmx_insert($CI, 'ret_billing', $header);
 *       foreach ($items as $item) {
 *           $item['bill_id'] = $bill_id;
 *           lmx_insert($CI, 'ret_billing_items', $item);
 *       }
 *       return ['bill_id' => $bill_id];
 *   });
 *
 * @param  object   &$CI        CodeIgniter instance
 * @param  callable $callback   Function to execute inside transaction.
 *                               Receives &$CI as argument, returns any value.
 * @param  string   $module     Module name for error logging (optional)
 * @return array    ['success' => bool, 'data' => mixed, 'error' => string]
 */
function lmx_transaction(&$CI, $callback, $module = '')
{
    $CI->db->trans_begin();

    try {
        $result = $callback($CI);

        if ($CI->db->trans_status() === TRUE) {
            $CI->db->trans_commit();
            return ['success' => true, 'data' => $result, 'error' => ''];
        }

        $CI->db->trans_rollback();
        $db_err = $CI->db->error();
        $err_msg = !empty($db_err['message']) ? $db_err['message'] : 'Transaction status FALSE';
        log_message('error', '[lmx_transaction] ' . $module . ' — ' . $err_msg);
        return ['success' => false, 'data' => null, 'error' => $err_msg];

    } catch (Exception $e) {
        $CI->db->trans_rollback();
        log_message('error', '[lmx_transaction] ' . $module . ' — Exception: ' . $e->getMessage());
        return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
    }
}
