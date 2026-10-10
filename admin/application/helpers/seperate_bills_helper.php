<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Separate Bills Helper — BIL-2606242023
 * 
 * When ret_settings.split_bill = 1 and template_based = 3,
 * splits a multi-type bill (Sales+Purchase, Sales+Return, Sales+Purchase+Return, Purchase+Return)
 * into separate browser tabs, each rendered independently with its own bill_type.
 * 
 * Called from admin_ret_billing::billing_invoice() BEFORE the normal rendering pipeline.
 */

/**
 * Determine which sub-types exist for this bill based on actual data.
 * 
 * @param array $data Full receipt data from get_receipt_data()
 * @return array List of sub-type keys (e.g., ['sales', 'purchase']) or empty if no split needed
 */
function get_bill_sub_types($data)
{
    $has_sales   = !empty($data['est_other_item']['item_details']);
    $has_purchase = !empty($data['est_other_item']['old_matel_details']);
    $has_return  = !empty($data['est_other_item']['sales_ret_trans_details']) || !empty($data['est_other_item']['return_details']);

    $sub_types = [];
    if ($has_sales)    $sub_types[] = 'sales';
    if ($has_purchase) $sub_types[] = 'purchase';
    if ($has_return)   $sub_types[] = 'return';

    // If only one type exists, no splitting needed
    if (count($sub_types) <= 1) {
        return [];
    }

    return $sub_types;
}

/**
 * Generate a launcher HTML page that opens each sub-type in a separate browser tab.
 * 
 * @param int   $bill_id   The bill ID
 * @param array $sub_types List of sub-type keys to open
 * @param string $type     The original $type parameter from billing_invoice
 * @return string HTML with JS that opens tabs and closes itself
 */
function render_split_launcher($bill_id, $sub_types, $type = '')
{
    $CI =& get_instance();
    $base_url = $CI->config->item('base_url');
    $branch_id = isset($CI->session) ? $CI->session->userdata('id_branch') : null;
    
    // Build URLs for remaining sub-types (skip the first — it renders in current tab)
    $remaining_urls = [];
    for ($i = 1; $i < count($sub_types); $i++) {
        $remaining_urls[] = $base_url . 'index.php/admin_ret_billing/billing_invoice/' . $bill_id . '/' . $type . '?split_render=' . $sub_types[$i];
    }
    
    // Render the first sub-type directly in this tab
    // We need the full data, so we call get_receipt_data again via the CI instance
    // But since data is already loaded in the controller, we pass it through a different approach:
    // Redirect the current tab to the first split URL
    $first_url = $base_url . 'index.php/admin_ret_billing/billing_invoice/' . $bill_id . '/' . $type . '?split_render=' . $sub_types[0];
    
    $remaining_json = json_encode($remaining_urls);
    
    // Redirect current tab to first split bill, open remaining in new tabs
    return '<!DOCTYPE html>
<html>
<head><title>Loading...</title></head>
<body>
<script>
(function() {
    var remaining = ' . $remaining_json . ';
    for (var i = 0; i < remaining.length; i++) {
        window.open(remaining[i], "_blank");
    }
    // Redirect current tab to first split bill
    window.location.replace("' . $first_url . '");
})();
</script>
</body>
</html>';
}

/**
 * Render a single sub-type bill by filtering the data and re-mapping.
 * Called when ?split_render=sales|purchase|return is present in the URL.
 * 
 * @param array  $data          Full receipt data from get_receipt_data()
 * @param string $split_render  The sub-type to render ('sales', 'purchase', 'return')
 * @param int    $branch_id     Branch ID for template lookup
 * @param object $CI            CodeIgniter instance
 * @return string|false         Rendered HTML or false if rendering failed
 */
function render_single_split_bill($data, $split_render, $branch_id, &$CI)
{
    // Clone data to avoid mutating original
    $sub_data = $data;

    // For the 'sales' split copy, capture the full (unfiltered) bill's purchase and
    // return totals before they get zeroed out below, so the sales copy can still
    // reference {{sales_purchase_total_amount}} / {{sales_total_return_amount}}.
    $sales_full_mapped = null;

    // Captured BEFORE the switch below clears any item arrays — the sales copy's
    // cross-reference footnote ("By Sales Return Ref Bill no" / "By Old silver
    // Ref Bill no") needs to reflect whether the ORIGINAL combined bill actually
    // had a linked return/old-metal exchange, even though the sales copy's own
    // item tables intentionally omit those items.
    $orig_has_return_items = !empty($data['est_other_item']['return_details']) || !empty($data['est_other_item']['sales_ret_trans_details']);
    $orig_has_old_metal = !empty($data['est_other_item']['old_matel_details']);
    $orig_has_sales_items = !empty($data['est_other_item']['item_details']);

    switch ($split_render) {
        case 'sales':
            $sales_full_mapped = tpl_map_billing_data_to_template($data);

            // Keep only sales items, remove purchase and return
            $sub_data['est_other_item']['old_matel_details'] = [];
            $sub_data['est_other_item']['sales_ret_trans_details'] = [];
            $sub_data['est_other_item']['return_details'] = [];
            // Override bill_type to pure Sales (1)
            $sub_data['billing']['bill_type'] = 1;
            $sub_data['type'] = '';
            break;

        case 'purchase':
            // Keep only purchase/old metal items, remove sales and return
            $sub_data['est_other_item']['item_details'] = [];
            $sub_data['est_other_item']['sales_ret_trans_details'] = [];
            $sub_data['est_other_item']['return_details'] = [];
            $sub_data['est_other_item']['sales_trasnfer_details'] = [];
            // Purchase copy shows the purchase table and purchase amount line only —
            // payment records (cash/card/cheque), amount-in-words and advance
            // details all belong to the whole original bill, not this slice, so
            // drop the source data here.
            $sub_data['payment']['pay_details'] = [];
            $sub_data['billing']['advance_deposit'] = 0;
            $sub_data['billing']['adv_adj_amt'] = 0;
            $sub_data['receiptDetails'] = [];
            // Override bill_type to pure Purchase (4)
            $sub_data['billing']['bill_type'] = 4;
            $sub_data['type'] = 'p';
            break;

        case 'return':
            // Keep only return items, remove sales and purchase
            $sub_data['est_other_item']['item_details'] = [];
            $sub_data['est_other_item']['old_matel_details'] = [];
            $sub_data['est_other_item']['sales_trasnfer_details'] = [];
            // Return copy shows the return table and return amount line only — same
            // reasoning as the purchase case above.
            $sub_data['payment']['pay_details'] = [];
            $sub_data['billing']['advance_deposit'] = 0;
            $sub_data['billing']['adv_adj_amt'] = 0;
            $sub_data['receiptDetails'] = [];
            // Override bill_type to pure Sales Return (7)
            $sub_data['billing']['bill_type'] = 7;
            $sub_data['type'] = 'sr';
            break;

        default:
            return false;
    }

    // Rebuild invoicesplit/oldmetalsplit for this sub-type's filtered data
    $sub_data['invoicesplit'] = [];
    $sub_data['oldmetalsplit'] = [];

    foreach ($sub_data['est_other_item']['item_details'] as $key => $item) {
        $sub_data['invoicesplit'][$item['invoiceno']][$key] = $item;
    }
    ksort($sub_data['invoicesplit'], SORT_NUMERIC);

    foreach ($sub_data['est_other_item']['old_matel_details'] as $key => $item) {
        $sub_data['oldmetalsplit'][$item['pur_refId']][$key] = $item;
    }
    ksort($sub_data['oldmetalsplit'], SORT_NUMERIC);

    // Re-map template data with filtered data
    $sub_mapped = tpl_map_billing_data_to_template($sub_data);

    // Sales copy: pull the Purchase Amount / Sales Return Amount totals from the
    // ORIGINAL (unfiltered) bill — the sales copy's own $sub_mapped has these at
    // 0 because old_matel_details/return_details were cleared above for its item
    // table. This is informational only (the sales copy's table still shows sales
    // items alone); mirrors how the cross-reference footnote above already reads
    // from the original data.
    if ($split_render === 'sales' && ($orig_has_old_metal || $orig_has_return_items)) {
        // $sales_full_mapped is already the same unfiltered re-map (computed
        // above for sales_purchase_total_amount/sales_total_return_amount) —
        // reuse it instead of running tpl_map_billing_data_to_template() twice.
        $orig_mapped = $sales_full_mapped ?? tpl_map_billing_data_to_template($data);
        if ($orig_has_old_metal) {
            $sub_mapped['purchase_total_amount'] = $orig_mapped['purchase_total_amount'] ?? '0.00';
            $sub_mapped['total_old_amount'] = $orig_mapped['total_old_amount'] ?? '0.00';
        }
        if ($orig_has_return_items) {
            $sub_mapped['total_return_amount'] = $orig_mapped['total_return_amount'] ?? '0.00';
            $sub_mapped['total_return_amount_with_gst'] = $orig_mapped['total_return_amount_with_gst'] ?? '0.00';
            $sub_mapped['sales_return_total'] = $orig_mapped['sales_return_total'] ?? '0.00';
        }
        // net_amount above was computed against the FILTERED sub_data (old-metal/
        // return arrays already cleared for the sales-only item table), so it never
        // actually nets out the Purchase Amount / Sales Return figures shown on this
        // copy — it just falls back to grand_total + round_off. ret_billing.tot_bill_amount
        // is the actual saved net amount for the whole bill (already correctly signed
        // negative when the company owes the customer) — use that directly rather than
        // re-deriving it from a formula.
        $sub_mapped['net_amount'] = moneyFormatIndia(number_format($data['billing']['tot_bill_amount'], 2, '.', ''));
    }

    // Cross-reference footnote block ("Sales Ref Bill no" / "By Sales Return Ref
    // Bill no" / "By Old silver Ref Bill no") — each of the three needs different
    // visibility per split copy:
    //   - Sales Ref Bill no:       shows on Sales, Return, AND Purchase copies
    //   - By Sales Return Ref no:  shows on the Sales copy only
    //   - By Old silver Ref no:    shows on the Sales copy only
    // The Sales/Purchase copies' own has_return_items/has_old_metal are false
    // (their item arrays are cleared for other types), so the note uses the
    // ORIGINAL bill's presence instead.
    // "SM :" (Sales Manager code) — shows on the Sales copy only.
    switch ($split_render) {
        case 'sales':
            // "Sales Ref Bill no" is bound to {{invoice_no}}, which for the sales
            // copy is just this bill's OWN reference number — already shown at
            // the top of the page as INVOICE NO. Showing it again as a footnote
            // is a plain duplicate here, so it stays off on the sales copy.
            $sub_mapped['show_sales_ref_note'] = false;
            $sub_mapped['show_return_crossref_note'] = $orig_has_return_items;
            $sub_mapped['show_oldmetal_crossref_note'] = $orig_has_old_metal;
            $sub_mapped['show_sales_man_field'] = true;
            // Purchase Amount / Sales Return Amount lines — informational only on
            // the sales copy, reflecting the original bill's other components.
            $sub_mapped['show_purchase_amount_line'] = $orig_has_old_metal;
            $sub_mapped['show_return_amount_line'] = $orig_has_return_items;
            break;
        case 'return':
            // The return copy already shows its own "Sales Ref Bill no" natively
            // inside the return-details block (conditioned on has_return_items,
            // untouched here) — this footnote copy of the same {{invoice_no}}
            // value would just print it a second time, so it stays off.
            $sub_mapped['show_sales_ref_note'] = false;
            $sub_mapped['show_return_crossref_note'] = false;
            $sub_mapped['show_oldmetal_crossref_note'] = false;
            $sub_mapped['show_sales_man_field'] = false;
            // This IS the return copy — its own Sales Return Amount always shows;
            // Purchase Amount doesn't belong here.
            $sub_mapped['show_purchase_amount_line'] = false;
            $sub_mapped['show_return_amount_line'] = true;
            break;
        case 'purchase':
            // Purchase copy references the accompanying sales bill, if this
            // combined bill actually had one (not every purchase split has a
            // sales component — e.g. a Purchase+Return-only bill).
            $sub_mapped['show_sales_ref_note'] = $orig_has_sales_items;
            $sub_mapped['show_return_crossref_note'] = false;
            $sub_mapped['show_oldmetal_crossref_note'] = false;
            $sub_mapped['show_sales_man_field'] = false;
            // This IS the purchase copy — its own Purchase Amount always shows;
            // Sales Return Amount doesn't belong here.
            $sub_mapped['show_purchase_amount_line'] = true;
            $sub_mapped['show_return_amount_line'] = false;
            break;
    }

    // Purchase/Return copies: strip amount-in-words and payment mode details.
    // amount_in_words is computed from the whole bill's tot_bill_amount regardless
    // of split type, and payment_methods/payment_breakdown can still get entries
    // injected (e.g. chit adjustment) even with pay_details cleared above — blank
    // them here so neither shows up next to the purchase/return table.
    if ($split_render === 'purchase' || $split_render === 'return') {
        $sub_mapped['amount_in_words'] = '';
        $sub_mapped['payment_methods'] = [];
        $sub_mapped['payment_breakdown'] = [];
        $sub_mapped['has_payment_details'] = false;
        $sub_mapped['pay_method_amount_total'] = '0.00';
        $sub_mapped['advance_details'] = [];
        $sub_mapped['order_advance_adj'] = '0.00';
        $sub_mapped['advance_paid'] = '0.00';
        $sub_mapped['has_advance'] = false;

        // Received/Paid whole-bill flag — Sales copy only.
        $sub_mapped['final_transaction_type'] = '';
        $sub_mapped['final_transaction_type_total'] = '';

        // "Sales Amount / Exchange Amount / Net Amount" summary block — built for
        // the combined Sales+Return view (sales_total_amount / total_sales_amount
        // end up holding the whole original bill's sales-side total regardless of
        // which sub-type is being rendered). Not part of a purchase-only or
        // return-only copy, so blank it; the actual purchase/return amount line
        // (purchase_total_amount / total_return_amount / sales_return_total) is
        // untouched.
        $sub_mapped['lbl_sales_amount'] = '';
        $sub_mapped['lbl_exchange_amount'] = '';
        $sub_mapped['lbl_net_amount'] = '';
        $sub_mapped['net_amount'] = '';
        $sub_mapped['sales_total_amount'] = '';
        $sub_mapped['total_sales_amount'] = '';
        $sub_mapped['sales_sub_total'] = '';

        // Round-off is applied once to the whole original bill's total
        // (billing.round_off_amt), not per split slice — blank it so it doesn't
        // show as a stray line under the purchase/return amount.
        $sub_mapped['round_off'] = '';
        $sub_mapped['round_off_amt'] = '';
        $sub_mapped['return_round_off_amt'] = '';

        // Top-of-invoice GST summary box (cgst_amount/sgst_amount/igst_amount and
        // their cgst/sgst/igst aliases) is the whole-bill tax summary, normally
        // shown once near the header. For a return-only copy this ends up holding
        // the exact same figures as the return table's own footer totals
        // (total_return_cgst/sgst/igst), so it renders as a duplicate GST block.
        // Blank it here; the table's own GST footer is untouched.
        $sub_mapped['cgst_amount'] = '';
        $sub_mapped['sgst_amount'] = '';
        $sub_mapped['igst_amount'] = '';
        $sub_mapped['cgst'] = '';
        $sub_mapped['sgst'] = '';
        $sub_mapped['igst'] = '';
    }

    // Build combined data (same merge logic as controller)
    $sub_combined = $sub_data;
    if (isset($sub_data['billing']) && is_array($sub_data['billing'])) {
        $sub_combined = array_merge($sub_combined, $sub_data['billing']);
    }
    if (isset($sub_data['comp_details']) && is_array($sub_data['comp_details'])) {
        $sub_combined = array_merge($sub_combined, $sub_data['comp_details']);
    }
    $sub_combined = array_merge($sub_combined, $sub_mapped);
    $sub_combined['qr_code_image'] = true;

    if ($sales_full_mapped !== null) {
        $sub_combined['sales_purchase_total_amount'] = $sales_full_mapped['purchase_total_amount'];
        $sub_combined['sales_total_return_amount'] = $sales_full_mapped['total_return_amount'];
    }

    $sub_bill_type_id = $sub_data['billing']['bill_type'];

    // Try Konva template first — same 'billing' group template the main invoice
    // flow uses for every bill type (one template covers all categories here;
    // its own conditional blocks show/hide sections based on the data we feed
    // it). Looking this up by category instead of by 'billing' code was why the
    // purchase/return copies fell through to the unfiltered full-bill render.
    $CI->load->helper('konva_receipt');
    $sub_html = false;
    $konva_tpl = find_konva_template($sub_bill_type_id, $branch_id, 'billing');
    if ($konva_tpl) {
        $sub_html = render_konva_template($konva_tpl['id_template'], $sub_combined);
        if ($sub_html === false || empty(trim(strip_tags($sub_html)))) {
            $sub_html = false;
        }
    }

    // Fallback to HTML designer template
    if ($sub_html === false) {
        $sub_html = $CI->print_template_model->render($sub_bill_type_id, $sub_combined, $branch_id);
    }

    if ($sub_html === false || empty(trim(strip_tags($sub_html)))) {
        return false;
    }

    // Wrap with auto-print script that closes tab on print/cancel
    $sub_html .= '
<script>
window.onload = function() {
    window.onafterprint = function() { window.close(); };
    window.print();
};
</script>';

    return $sub_html;
}
