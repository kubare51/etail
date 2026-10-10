<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

// ═══════════════════════════════════════════════════════════════════════════
// Template Chit Helper — Variable mapping for chit-side template printing.
// Chit counterpart of template_receipt_helper.php (which serves billing docs).
// Currently covers the chit passbook (print_templates categories 18 = Front,
// 19 = Back), printed via admin_manage::passbook_print() when
// ret_settings.passbook_template_based = 1.
//
// ═══════════════════════════════════════════════════════════════════════════
// VARIABLE REGISTRY — every passbook template variable is born here.
// Do NOT create new $tpl['key'] inline elsewhere; add it in this mapper.
//
// Scalars ........ the whole account_model::get_account_detail() row passes
//                  through flat (account_name, customer_name, mobile, email,
//                  pan_no, address1/2/3, city, pincode, state, country,
//                  scheme_name, scheme_code, scheme_acc_number,
//                  classification_name, start_date, maturity_date,
//                  total_installments, paid_installments, fixed_wgt,
//                  payment_amount, amount, receipt_no, pur_ref_no,
//                  remark_open, description, branch, brn_address1/2,
//                  brn_city, brn_state, brn_country, cus_img, ...)
//                  + all company columns prefixed company_*
//                  + is_closed, closing_date, nominee_name, nominee_mobile
// Flags .......... one_time_premium, is_weight_scheme,
//                  duplicate_passbook_issued (from data) and precomputed
//                  inverses (the engine has NO {{^negation}}): is_srinidhi,
//                  is_open, is_regular_scheme, is_amount_scheme, show_headers.
//                  Truthy (1) = section shown, '' = hidden.
// Loop {{#payment}} per paid installment: ins, date_payment, receipt_no,
//                  payment_amount, metal_rate, metal_weight, payment_mode,
//                  remark + precomputed acc_wgt (running weight), show_row,
//                  is_spacer (spacer-row mechanic), is_weight_scheme.
// ═══════════════════════════════════════════════════════════════════════════

if (!function_exists('tpl_map_passbook_data_to_template')) {
    /**
     * Flatten passbook print data for the template engine (categories 18/19).
     * Engine constraints handled here: no negated sections (precomputed inverse
     * flags), no accumulators (precomputed acc_wgt), key collisions between
     * customer and company (company_* prefix).
     *
     * @param string $page       'F' (front), 'B' (back) or 'PAY' (back, receipt reprint)
     * @param array  $data       passbook_print() data: customer, acc, payment, company
     * @param mixed  $id_payment receipt selection: single raw id ('B') or array of ids ('PAY')
     * @return array             flat array for print_template_model::render()
     */
    function tpl_map_passbook_data_to_template($page, $data, $id_payment = "")
    {
        $CI = &get_instance();
        $CI->load->helper('metal_wgt_digit'); // formatMetalWeight() — idempotent load

        $customer = is_array($data['customer']) ? $data['customer'] : array();
        $company  = is_array($data['company'])  ? $data['company']  : array();
        $acc      = is_array($data['acc'])      ? $data['acc']      : array();
        $payments = is_array($data['payment'])  ? $data['payment']  : array();

        $tpl = $customer;
        foreach ($company as $k => $v) {
            if (!is_array($v)) {
                $tpl['company_' . $k] = $v;
            }
        }

        $tpl['is_closed']      = isset($acc['is_closed']) ? $acc['is_closed'] : 0;
        $tpl['closing_date']   = isset($acc['closing_date']) ? $acc['closing_date'] : '';
        $tpl['nominee_name']   = isset($acc['nominee_name']) ? $acc['nominee_name'] : '';
        $tpl['nominee_mobile'] = isset($acc['nominee_mobile']) ? $acc['nominee_mobile'] : '';

        // Inverse flags: engine keeps {{#flag}} blocks only for truthy values; '' = hidden
        $is_weight = (isset($customer['is_weight_scheme']) && $customer['is_weight_scheme'] == 1);
        $tpl['is_srinidhi']       = (isset($customer['classification_name']) && $customer['classification_name'] == 'SRINIDHI') ? 1 : '';
        $tpl['is_open']           = ($tpl['is_closed'] != 1) ? 1 : '';
        $tpl['is_regular_scheme'] = (empty($customer['one_time_premium'])) ? 1 : '';
        $tpl['is_amount_scheme']  = $is_weight ? '' : 1;

        // Legacy parity: receipt selection (in-memory only, no DB write).
        // 'B' passes one raw id; 'PAY' (Reprint) passes an array of ids — only the
        // selected receipts print, every other row renders as a blank spacer.
        $sel_ids = is_array($id_payment)
            ? array_values(array_filter($id_payment))
            : (($id_payment > 0) ? array($id_payment) : array());
        if (($page == 'B' || $page == 'PAY') && !empty($sel_ids)) {
            foreach ($payments as $i => $pay) {
                $payments[$i]['is_print_taken'] = in_array($pay['id_payment'], $sel_ids) ? 0 : 1;
            }
        }

        // Loop rows: running weight over ALL rows incl. printed ones (matches passbook_back.php)
        $tot_wgt = 0;
        $rows = array();
        foreach ($payments as $pay) {
            $tot_wgt += floatval($pay['metal_weight']);
            // Display formatting — same formulas as views/scheme/print/passbook_back.php
            $pay['payment_amount']   = number_format((float)$pay['payment_amount'], 0, '.', '');
            $pay['metal_rate']       = round(floatval($pay['metal_rate']));
            $pay['metal_weight']     = (floatval($pay['metal_weight']) > 0) ? formatMetalWeight($pay['metal_weight']) : '';
            $pay['acc_wgt']          = ($tot_wgt > 0) ? formatMetalWeight($tot_wgt) : '';
            $pay['show_row']         = ($pay['is_print_taken'] == 0) ? 1 : '';
            $pay['is_spacer']        = ($pay['is_print_taken'] == 1) ? 1 : '';
            $pay['is_weight_scheme'] = $is_weight ? 1 : '';
            if ($pay['is_print_taken'] == 1) {
                // Already in the physical book: keep the row (preserves table row
                // height, so the next unprinted line lands on the correct ruled
                // line) but blank its display values. Serves the Konva table path;
                // V1 templates may additionally use show_row/is_spacer sections.
                foreach (array('ins', 'date_payment', 'receipt_no', 'payment_amount',
                               'metal_rate', 'metal_weight', 'acc_wgt', 'payment_mode',
                               'remark') as $pb_blank_k) {
                    if (isset($pay[$pb_blank_k])) {
                        $pay[$pb_blank_k] = '';
                    }
                }
            }
            $rows[] = $pay;
        }
        $tpl['payment']      = $rows;
        // Aliases for the V2/Konva designer's "Items Table" element, which reads
        // sales_items/items (konva_receipt_helper::_konva_get_table_items). Lets the
        // installment rows be designed as a standard table with columns bound to
        // ins / date_payment / receipt_no / payment_amount / metal_rate /
        // metal_weight / acc_wgt / payment_mode.
        $tpl['items']        = $rows;
        $tpl['sales_items']  = $rows;
        $tpl['show_headers'] = (isset($payments[0]) && $payments[0]['is_print_taken'] == 0) ? 1 : '';

        // Reprint pass on page B (book already carries earlier print runs):
        // standalone identity text must not overstrike what is already printed
        // in the physical book. Closing fields stay printable (closing happens once).
        if (($page == 'B' || $page == 'PAY') && $tpl['show_headers'] !== 1) {
            foreach (array('scheme_acc_number', 'account_name') as $pb_id_k) {
                if (isset($tpl[$pb_id_k])) {
                    $tpl[$pb_id_k] = '';
                }
            }
        }

        // Client post-process hook (mirrors tpl_client_post_process in the billing helper):
        // define tpl_chit_client_post_process(&$tpl) in a client helper to adjust output.
        if (function_exists('tpl_chit_client_post_process')) {
            tpl_chit_client_post_process($tpl);
        }

        return $tpl;
    }
}

if (!function_exists('tpl_chit_placeholders')) {
    /**
     * Code-defined field-picker placeholders for the chit print categories.
     * Same row shape as the print_template_placeholders table. The model merges
     * these AFTER any DB rows (DB rows take precedence), so the designer picker
     * works on every install with no DB seeding, while a client can still
     * override labels/grouping with DB rows.
     */
    function tpl_chit_placeholders($category) {
        $category = (string)(int)$category;

        $make = function ($defs, $group, $is_loop = 0, $parent = null) use ($category) {
            $out = array();
            $order = 1;
            foreach ($defs as $d) {
                $out[] = array(
                    'placeholder_key'   => $d[0],
                    'placeholder_label' => $d[1],
                    'placeholder_group' => $group,
                    'sample_value'      => isset($d[2]) ? $d[2] : '',
                    'category'          => $category,
                    'field_type'        => 'text',
                    'is_loop_field'     => $is_loop,
                    'parent_loop'       => $parent,
                    'display_order'     => $order++,
                );
            }
            return $out;
        };

        if ($category === '18') { // Passbook Front
            return array_merge(
                $make(array(
                    array('account_name',  'Account Name',  'LAKSHMI DEVI'),
                    array('customer_name', 'Customer Name', 'LAKSHMI DEVI'),
                    array('mobile',        'Mobile',        '9876543210'),
                    array('email',         'Email',         'demo@mail.com'),
                    array('pan_no',        'PAN No',        'ABCDE1234F'),
                    array('address1',      'Address 1',     '12 Bazaar St'),
                    array('address2',      'Address 2',     'T Nagar'),
                    array('address3',      'Address 3'),
                    array('city',          'City',          'Chennai'),
                    array('pincode',       'Pincode',       '600017'),
                    array('state',         'State',         'Tamil Nadu'),
                    array('country',       'Country',       'India'),
                ), 'Customer'),
                $make(array(
                    array('scheme_name',        'Scheme Name',        'GOLD SAVINGS'),
                    array('scheme_code',        'Scheme Code',        'GS'),
                    array('scheme_acc_number',  'Scheme A/c Number',  'GS/0025'),
                    array('classification_name','Classification',     'CLASSIC'),
                    array('start_date',         'Start Date',         '01-01-2026'),
                    array('maturity_date',      'Maturity Date',      '01-12-2026'),
                    array('total_installments', 'Total Installments', '12'),
                    array('paid_installments',  'Paid Installments',  '3'),
                    array('fixed_wgt',          'Fixed Weight',       '10.000'),
                    array('payment_amount',     'Payment Amount',     '5000'),
                    array('amount',             'Installment Amount', '5000'),
                    array('receipt_no',         'Receipt No',         'RC/101'),
                    array('pur_ref_no',         'Purchase Ref No'),
                    array('remark_open',        'Opening Remark'),
                    array('description',        'Scheme Terms',       'Terms and conditions text'),
                ), 'Scheme'),
                $make(array(
                    array('branch',       'Branch Name',      'MAIN'),
                    array('brn_address1', 'Branch Address 1', '1 Main Rd'),
                    array('brn_address2', 'Branch Address 2'),
                    array('brn_city',     'Branch City',      'Chennai'),
                    array('brn_state',    'Branch State',     'Tamil Nadu'),
                    array('brn_country',  'Branch Country',   'India'),
                ), 'Branch'),
                $make(array(
                    array('company_company_name', 'Company Name', 'ABC Jewellers'),
                ), 'Company'),
                $make(array(
                    array('one_time_premium',         'Is One-Time Premium (flag)', '1'),
                    array('is_regular_scheme',        'Is Regular Scheme (flag)',   '1'),
                    array('is_weight_scheme',         'Is Weight Scheme (flag)',    '1'),
                    array('duplicate_passbook_issued','Is Duplicate (flag)'),
                ), 'Conditions')
            );
        }

        if ($category === '19') { // Passbook Back
            return array_merge(
                $make(array(
                    array('scheme_acc_number', 'Scheme A/c Number', 'GS/0025'),
                    array('account_name',      'Account Name',      'LAKSHMI DEVI'),
                ), 'Customer'),
                $make(array(
                    array('closing_date',   'Closing Date'),
                    array('nominee_name',   'Nominee Name'),
                    array('nominee_mobile', 'Nominee Mobile'),
                ), 'Closing'),
                $make(array(
                    array('is_open',           'Is Open (flag)',           '1'),
                    array('is_closed',         'Is Closed (flag)'),
                    array('show_headers',      'Show Headers (flag)',      '1'),
                    array('is_weight_scheme',  'Is Weight Scheme (flag)',  '1'),
                    array('is_amount_scheme',  'Is Amount Scheme (flag)'),
                    array('is_regular_scheme', 'Is Regular Scheme (flag)', '1'),
                    array('is_srinidhi',       'Is SRINIDHI (flag)'),
                ), 'Conditions'),
                $make(array(
                    array('ins',            'Installment No',            '1'),
                    array('date_payment',   'Payment Date',              '05-01-26'),
                    array('receipt_no',     'Receipt No',                'RC/101'),
                    array('payment_amount', 'Amount',                    '5000'),
                    array('metal_rate',     'Metal Rate',                '5600'),
                    array('metal_weight',   'Weight',                    '0.893'),
                    array('acc_wgt',        'Accumulated Weight',        '2.679'),
                    array('payment_mode',   'Payment Mode',              'CASH'),
                    array('show_row',       'Show Row (per-row flag)',   '1'),
                    array('is_spacer',      'Is Spacer (per-row flag)'),
                ), 'Installment Rows (payment loop)', 1, 'payment')
            );
        }

        return array();
    }
}
