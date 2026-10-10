<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Kallarackals — Bill Number Format Override Helper
 *
 * This function is called instead of the core get_bill_no_format_detail()
 * when CLIENT_ID = 'kallarackals'.
 *
 * The helper is loaded automatically by MY_Loader (three-layer override system).
 * Core models (ret_billing_model, ret_estimation_model, ret_reports_model) all
 * check function_exists('client_get_bill_no_format_detail') before their SQL.
 *
 * @param  object $db       CI3 database object (passed from model)
 * @param  int    $bill_id  The bill ID to format
 * @param  string $type     '' = sales, 'p' = purchase, 'sr' = sales return,
 *                          3 (int) = purchase (ret_reports_model convention)
 * @return string           Formatted bill number string
 *
 * TODO: Modify the SQL below to apply Kallarackals-specific bill format logic.
 */
function client_get_bill_no_format_detail($db, $bill_id, $type = "")
{
    // -------------------------------------------------------------------------
    // Currently identical to core logic — modify below for Kallarackals
    // -------------------------------------------------------------------------

    $format1 = $db->query("SELECT b.bill_type,bf.bill_no_format,b.bill_id,b.pur_ref_no,b.order_adv_ref_no,b.s_ret_refno,b.credit_coll_refno,b.approval_ref_no,b.chit_preclose_refno,br.short_name as '@@branch_code@@',b.fin_year_code as '@@fin_year@@',

        IFNULL(m.metal_code,'') as '@@metal_code@@',

            CASE

                WHEN b.bill_type = 4 then ifNULL(b.pur_ref_no, b.bill_no)

                " . ($type == 'p' ? 'WHEN b.bill_type = 5 or b.bill_type = 9 then ifNULL(b.pur_ref_no,b.bill_no)' : '') . "

                WHEN b.bill_type = 5 then ifNULL(b.order_adv_ref_no, b.bill_no)

                WHEN b.bill_type = 7 then ifNULL(b.s_ret_refno, b.bill_no)

                WHEN b.bill_type = 8 then ifNULL(b.credit_coll_refno, b.bill_no)

                WHEN b.bill_type = 9 then ifNULL(b.sales_ref_no, b.bill_no)

                WHEN b.bill_type = 15 then ifNULL(b.approval_ref_no, b.bill_no)

                WHEN b.bill_type = 10 then ifNULL(b.chit_preclose_refno, b.bill_no)

                WHEN b.bill_type = 14 then b.bill_no

                " . ($type == 'sr' ? 'WHEN b.bill_type = 1 or b.bill_type = 2 or b.bill_type = 3  then ifNULL(b.s_ret_refno,b.bill_no)' : '') . "

                " . ($type == 'p' ? 'WHEN b.bill_type = 1 or b.bill_type = 2 or b.bill_type = 3  then ifNULL(b.pur_ref_no,b.bill_no)' : '') . "

                " . ($type == '' ? 'WHEN b.bill_type = 1 or  b.bill_type = 13 or b.bill_type = 2 or b.bill_type = 3 then ifNULL(b.sales_ref_no,b.bill_no)' : '') . "

            ELSE b.bill_no

            END as '@@bill_no@@',

            CASE

                    " . ($type == 'p' ? "WHEN b.bill_type = 1 or b.bill_type = 2 or b.bill_type = 3 or b.bill_type = 5 or b.bill_type = 9 then 'PIN'" : '') . "
                    " . ($type == 'sr' ? "WHEN b.bill_type = 1 or b.bill_type = 2 or b.bill_type = 3 then 'CS'" : '') . "

                WHEN b.bill_type = 1 or b.bill_type = 13  then ifNULL('S','')

                WHEN b.bill_type = 2 then ifNULL('S','')

                WHEN b.bill_type = 9 then ifNULL('S','')

                WHEN b.bill_type = 3 then ifNULL('S','')

                WHEN b.bill_type = 4 then ifNULL('PIN','')

                WHEN b.bill_type = 5 then ifNULL('S','')

                WHEN b.bill_type = 7 then ifNULL('CS','')

                WHEN b.bill_type = 14 then ifNULL('CS','')

                WHEN b.bill_type = 8 then ifNULL('CC','')

                WHEN b.bill_type = 11 then ifNULL('RE','')

                WHEN b.bill_type = 15 then NULL

                WHEN b.bill_type = 10 then NULL

            ELSE NULL

            END as '@@short_code@@'

                    FROM bill_no_format bf

                    LEFT JOIN ret_billing b ON b.bill_type = b.bill_type

                    LEFT JOIN metal m ON m.id_metal = b.metal_type

                    LEFT JOIN branch br on br.id_branch=b.id_branch

                    WHERE b.bill_id is not null

                    " . ($bill_id != '' && $bill_id > 0 ? 'and b.bill_id=' . $bill_id . '' : '') . " ");

    $query1 = $format1->row_array();

    $format2 = $db->query("SELECT bill_no_format,

            CASE

                WHEN bill_type = 1 or bill_type = 13  then ifNULL('S','')

                WHEN bill_type = 2 then ifNULL('S','')

                WHEN bill_type = 3 then ifNULL('S','')

                WHEN bill_type = 4 then ifNULL('PIN','')

                WHEN bill_type = 5 then ifNULL('S','')

                WHEN bill_type = 7 then ifNULL('CS','')

                WHEN bill_type = 14 then ifNULL('CS','')

                WHEN bill_type = 8 then ifNULL('CC','')

                WHEN bill_type = 11 then ifNULL('RE','')

                WHEN bill_type = 15 then NULL

                WHEN bill_type = 10 then NULL

            ELSE NULL

            END as '@@short_code@@'

                from bill_no_format

                " . ($query1['bill_type'] != '' ? 'where bill_type=' . $query1['bill_type'] : '') . "");


    $row2 = $format2->row();
    if ($row2 && !empty($row2->bill_no_format)) {
        $query2 = $row2->bill_no_format;
    } else {
        $query2 = "-@@branch_code@@-@@fin_year@@-@@short_code@@-@@bill_no@@";
    }

    $query2 = substr($query2, 1, strlen($query2) - 1);

    $billno = strtr($query2, $query1);

    if (substr($billno, 0, 1) === '-') {
        $billno = ltrim($billno, '-');
        return $billno;
    } else {
        return $billno;
    }
}

// =============================================================================
// TEMPLATE POST-PROCESS HOOK — Kallarackals-specific display overrides
// =============================================================================

/**
 * Kallarackals — Template Post-Process Hook
 *
 * Called automatically by tpl_map_billing_data_to_template() (template_receipt_helper.php)
 * after all core mapping is complete. Applies Kallarackals-specific display rules:
 *
 *  1. Header metal rates ({{gold_rate}}, {{gold_rate_18ct}}, {{gold_rate_24ct}}, {{silver_rate}})
 *     → displayed WITHOUT decimal places (e.g. "6,500" not "6,500.00")
 *
 *  2. Sales item rate per gram ({{rate}} in each item row)
 *     → displayed WITHOUT decimal places (e.g. "6,500" not "6,500.00")
 *
 *  3. {{stone_amount}} in sales rows stays UNCHANGED — it is already fixed/calculated
 *     by the core helper from stone_details. No override is applied.
 *
 * @param  array &$mapped  The fully-mapped template variable array (passed by reference)
 * @return void
 */
if (!function_exists('tpl_client_post_process')) {
    function tpl_client_post_process(&$mapped)
    {
        // ── 1. Header Rates — strip decimals ──────────────────────────────────
        // Values arrive as India-formatted strings like "6,500.00"
        // We strip commas, round to integer, then re-format with Indian grouping.
        $rate_header_keys = ['gold_rate', 'gold_rate_18ct', 'gold_rate_24ct', 'silver_rate'];
        foreach ($rate_header_keys as $key) {
            if (!empty($mapped[$key])) {
                $numeric_val = floatval(str_replace(',', '', $mapped[$key]));
                if ($numeric_val > 0) {
                    $mapped[$key] = _kallarackals_money_no_decimal(intval(round($numeric_val)));
                }
            }
        }

        // ── 2. Sales Item Rate per Gram — strip decimals ──────────────────────
        // Applies to all item-loop arrays. stone_amount is intentionally untouched.
        $item_array_keys = ['items', 'sales_items', 'sales', 'order_items', 'return_items', 'return_details'];
        foreach ($item_array_keys as $arr_key) {
            if (!empty($mapped[$arr_key]) && is_array($mapped[$arr_key])) {
                foreach ($mapped[$arr_key] as &$item) {
                    if (isset($item['rate']) && $item['rate'] !== '' && $item['rate'] !== '-') {
                        $numeric_rate = floatval(str_replace(',', '', $item['rate']));
                        if ($numeric_rate > 0) {
                            $item['rate'] = _kallarackals_money_no_decimal(intval(round($numeric_rate)));
                        }
                    }
                }
                unset($item); // Break reference to avoid accidental mutation
            }
        }
    }
}

/**
 * Format an integer with Indian comma grouping — no decimal part.
 * e.g. 6500 → "6,500"  |  150000 → "1,50,000"
 *
 * @param  int    $num
 * @return string
 */
if (!function_exists('_kallarackals_money_no_decimal')) {
    function _kallarackals_money_no_decimal($num)
    {
        $num = (string)intval($num);
        if (strlen($num) > 3) {
            $lastthree    = substr($num, -3);
            $restunits    = substr($num, 0, strlen($num) - 3);
            $restunits    = (strlen($restunits) % 2 == 1) ? '0' . $restunits : $restunits;
            $expunit      = str_split($restunits, 2);
            $explrestunits = '';
            foreach ($expunit as $i => $chunk) {
                $explrestunits .= ($i == 0 ? (int)$chunk : $chunk) . ',';
            }
            return $explrestunits . $lastthree;
        }
        return $num;
    }
}
