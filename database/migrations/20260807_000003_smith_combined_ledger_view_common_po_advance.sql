-- Migration: Surface Common PO advance payments in the Smith / Supplier unified ledger
-- Date: 07-08-2026
-- Ticket: CPO-09 / CPO-13 (Common Purchase Order - Advance Rate Fixing for Bullion Purchase)
--
-- Companion to 20260807_000002_supplier_ledger_view_common_po_advance.sql. That one
-- fixed ret_view_supplier_ledger (Supplier Transaction Report). This one fixes the
-- SEPARATE view behind the Smith / Supplier Ledger Report
-- (admin_ret_reports/supplier_unified_transaction -> getSmithCombinedLedger).
--
-- Issue: branch 11 - the SUPPLIER payment branch - is filtered to
--        `pay.bill_type = 1`. A Common PO advance carries bill_type = 4, so it was
--        missing here too and the supplier outstanding was overstated by the whole
--        advance amount.
--
-- Fix: add UNION ALL branch 22 (ledger_type = 1, the supplier side). Branches 1-21
--      are reproduced from the LIVE definition and are not otherwise changed. The
--      only edit to existing text is that the view's trailing
--      `order by `unixtransdate` desc` moves from the end of branch 21 to the end of
--      branch 22 - it terminates the whole UNION and nothing may follow an ORDER BY.
--
-- Notes on branch 22:
--   * trans_amount reads `pay.pay_amt`, NOT `sum(po_bill.bill_amount)` like branch 11.
--     Branch 11's amount comes from ret_po_bill_payment_details, which for a Common PO
--     advance only exists AFTER the advance is adjusted against a bill (CPO-13) - an
--     unadjusted advance would report NULL. The payment header is always correct.
--   * po_ref_no / po_date carry the Common PO number and date, so the report's
--     "PO Ref No" and "PO Date" columns identify which agreement the advance belongs
--     to. Both columns are rendered by get_SupplierUnifiedTransactions() in
--     ret_reports.js.
--   * All weight columns are 0. An advance is money, not metal - the metal reaches
--     the ledger through the GRN / supplier bill in branch 10.
--   * id_metal resolves through the Common PO's first line category so the report's
--     metal filter keeps the advance.
--   * ledger_type = 1 keeps the advance on the SUPPLIER side, so the weight running
--     balances (which skip ledger_type = 1) are untouched.
--
-- CHARSET: this view is stored with character_set_client = latin1. `SET NAMES latin1`
-- below reproduces it exactly. Check the deployment first and match it:
--   SELECT CHARACTER_SET_CLIENT, COLLATION_CONNECTION FROM information_schema.VIEWS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_view_smith_combined_ledger';
--
-- Take a copy of the deployment's CURRENT definition before running - clients
-- customise this view and it drifts:
--   SHOW CREATE VIEW `ret_view_smith_combined_ledger`;
-- Do NOT use `CREATE VIEW backup AS SELECT * FROM ret_view_smith_combined_ledger`
-- (the pattern behind the _2026_07_11 backup already in the schema). A view over a
-- view follows the original, so it changes the moment this migration runs and
-- restores nothing.
--
-- Impact: Additive. With common_po_enabled = 0 no bill_type = 4 rows exist, so the
--         view returns exactly what it returned before.

-- UP
SET NAMES latin1;

CREATE OR REPLACE VIEW `ret_view_smith_combined_ledger` AS
-- 1. SMITH - Purchase Order Items (grn_type = 2)
select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 1' AS `qry_type`,1 AS `trans_type`,`pitm`.`po_order_no` AS `trans_id`,`pitm`.`gross_wt` AS `gross_wt`,`pitm`.`net_wt` AS `net_wt`,`pitm`.`no_of_pcs` AS `no_of_pcs`,`pitm`.`purchase_touch` AS `purchase_touch`,`pitm`.`item_pure_wt` AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,1 AS `trans_rec_type`,sum(`pitm`.`item_cost`) AS `trans_amount`,`pitm`.`po_item_cat_id` AS `catid`,`pitm`.`po_item_pro_id` AS `proid`,1 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,concat(`pitm`.`fix_rate_per_grm`,if((`pitm`.`is_rate_fixed` = 1),'','(Un Fixed)')) AS `rate`,ifnull(`pitm`.`remark`,'') AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from ((((((`ret_purchase_order_items` `pitm` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pitm`.`po_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pitm`.`po_item_pro_id`)))
where ((`grn`.`grn_type` = 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 1))
group by `pitm`.`po_item_id`

UNION ALL

-- 2. SMITH - Payment / Rate Cut (rate_cut_type = 1)
select 2 AS `ledger_type`,'PAYMENT' AS `category`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_rate_cut/job_receipt/' AS `link`,`rf`.`id_supplier_rate_cut` AS `print_id`,'type 2' AS `qry_type`,2 AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,`rf`.`amount` AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,`rf`.`id_metal` AS `id_metal`,`rf`.`id_metal` AS `metal`,`rf`.`rate_per_gram` AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate`
from (`ret_supplier_rate_cut` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`po_id`)))
where ((`rf`.`rate_cut_type` = 1) and (`rf`.`status` = 1))
group by `rf`.`id_supplier_rate_cut`

UNION ALL

-- 3. SMITH - Rate Cut (rate_cut_type = 2)
select 2 AS `ledger_type`,if((`rf`.`weight` = 0),'Bill Conv(Amount)','Bill Conv(A to P)') AS `category`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_rate_cut/job_receipt/' AS `link`,`rf`.`id_supplier_rate_cut` AS `print_id`,'type 3' AS `qry_type`,(case when ((`rf`.`weight` = 0) and (`rf`.`amount_type` = 1)) then 2 when ((`rf`.`weight` = 0) and (`rf`.`amount_type` = 2)) then 1 when ((`rf`.`weight` <> 0) and (`rf`.`weight_type` = 1)) then 2 when ((`rf`.`weight` <> 0) and (`rf`.`weight_type` = 2)) then 1 end) AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,if((`rf`.`weight` = 0),'','100') AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,if((`rf`.`charges_amount` > 0),`rf`.`charges_amount`,'') AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`rf`.`id_metal` AS `id_metal`,`rf`.`id_metal` AS `metal`,`rf`.`rate_per_gram` AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate`
from (`ret_supplier_rate_cut` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`po_id`)))
where ((`rf`.`rate_cut_type` = 2) and (`rf`.`status` = 1))
group by `rf`.`id_supplier_rate_cut`

UNION ALL

-- 4. SMITH - Purchase Return (purchase_type = 0)
select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 4' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,1 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,`pret`.`pur_ret_rate` AS `rate`,ifnull(`ret`.`pur_ret_remark`,'') AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate`
from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
where ((`ret`.`pur_ret_convert_to` = 3) and (`ret`.`purchase_type` = 0) and (`ret`.`bill_status` = 1))
group by `pret`.`pur_ret_itm_id`

UNION ALL

-- 5. SMITH - Purchase Return (purchase_type = 1)
select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 5' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,1 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,`pret`.`pur_ret_rate` AS `rate`,ifnull(`ret`.`pur_ret_remark`,'') AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate`
from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
where ((`ret`.`pur_ret_convert_to` = 3) and (`ret`.`purchase_type` = 1) and (`ret`.`bill_status` = 1))
group by `pret`.`pur_ret_itm_id`

UNION ALL

-- 6. SMITH - Metal Issue (metalissue_type = 2)
select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`iss`.`met_issue_date` AS `trans_date`,`iss`.`met_issue_ref_id` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/karigarmetalissue_acknowladgement/' AS `link`,`iitm`.`issue_met_parent_id` AS `print_id`,'type 6' AS `qry_type`,2 AS `trans_type`,`iitm`.`issue_met_parent_id` AS `trans_id`,if((ifnull(`uom`.`divided_by_value`,0) = 0),`iitm`.`issue_metal_wt`,round((`iitm`.`issue_metal_wt` / `uom`.`divided_by_value`),3)) AS `gross_wt`,if((ifnull(`uom`.`divided_by_value`,0) = 0),`iitm`.`issue_metal_wt`,round((`iitm`.`issue_metal_wt` / `uom`.`divided_by_value`),3)) AS `net_wt`,ifnull(`iitm`.`issue_pcs`,1) AS `no_of_pcs`,ifnull(`iitm`.`touch`,'') AS `purchase_touch`,if((`pr`.`stone_type` = 0),`iitm`.`issue_metal_pur_wt`,if((ifnull(`uom`.`divided_by_value`,0) = 0),`iitm`.`issue_metal_wt`,round((`iitm`.`issue_metal_wt` / `uom`.`divided_by_value`),3))) AS `purewt`,`iss`.`met_issue_karid` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,`iitm`.`issue_cat_id` AS `catid`,`iitm`.`issu_met_pro_id` AS `proid`,2 AS `trans_screen_id`,`iitm`.`issue_metal` AS `id_metal`,`met`.`metal` AS `metal`,'' AS `rate`,ifnull(`iss`.`remark`,'') AS `narration`,unix_timestamp(`iss`.`met_issue_date`) AS `unixtransdate`
from ((((((((`ret_karigar_metal_issue_details` `iitm` left join `ret_karigar_metal_issue` `iss` on((`iss`.`met_issue_id` = `iitm`.`issue_met_parent_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `iss`.`met_issue_karid`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `iitm`.`issue_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `iitm`.`issu_met_pro_id`))) left join `ret_uom` `uom` on((`uom`.`uom_id` = `iitm`.`issue_uom_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `iss`.`po_id`))) left join `ret_purchase_order_items` `poitm` on((`poitm`.`po_item_id` = `iitm`.`po_item_id`)))
where ((`iss`.`metalissue_type` = 2) and (`iss`.`bill_status` = 1))
group by `iitm`.`issue_met_id`,`iitm`.`issue_cat_id`,`iitm`.`issu_met_pro_id`,`iitm`.`issue_met_parent_id`

UNION ALL

-- 7. SMITH - Opening Balance (amount)
select 2 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 7' AS `qry_type`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate`
from `smith_company_op_balance` `pay`
where ((`pay`.`smith_type` = 3) and (`pay`.`stock_type` = 2) and (`pay`.`amount` > 0) and (`pay`.`status` = 1))
group by `pay`.`id_smith_company_op_balance`

UNION ALL

-- 8. SMITH - Opening Balance (weight)
select 2 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 8' AS `qry_type`,`pay`.`weight_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`weight`,0) AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,ifnull(`pay`.`weight`,0) AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,`pay`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate`
from `smith_company_op_balance` `pay`
where ((`pay`.`smith_type` = 3) and (`pay`.`stock_type` = 2) and (`pay`.`weight` > 0) and (`pay`.`status` = 1))
group by `pay`.`id_smith_company_op_balance`

UNION ALL

-- 9. SMITH - Credit / Debit Note
select 2 AS `ledger_type`,if((`pay`.`transtype` = 1),'Credit Note','Debit Note') AS `category`,'' AS `product`,`pay`.`transdate` AS `trans_date`,`pay`.`transbillno` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/credit_debit_acknolodgement/' AS `link`,`pay`.`crdrid` AS `print_id`,'type 9' AS `qry_type`,`pay`.`transtype` AS `trans_type`,`pay`.`transbillno` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`weight`,0) AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,ifnull(`pay`.`weight`,0) AS `purewt`,`pay`.`supid` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`transamount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`naration`,'') AS `narration`,unix_timestamp(`pay`.`transdate`) AS `unixtransdate`
from (((`ret_crdr_note` `pay` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pay`.`po_id`))) left join `ret_purchase_order_items` `order` on((`order`.`po_item_po_id` = `po`.`po_id`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `order`.`po_item_cat_id`)))
where ((`pay`.`accountto` = 3) and (`pay`.`weight` > 0) and (`pay`.`crdr_status` = 1))
group by `pay`.`crdrid`

UNION ALL

-- 10. SUPPLIER - Purchase Order Items (grn_type <> 2)
select 1 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 10' AS `qry_type`,1 AS `trans_type`,`pitm`.`po_order_no` AS `trans_id`,`pitm`.`gross_wt` AS `gross_wt`,`pitm`.`net_wt` AS `net_wt`,`pitm`.`no_of_pcs` AS `no_of_pcs`,`pitm`.`purchase_touch` AS `purchase_touch`,`pitm`.`item_pure_wt` AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`pitm`.`item_cost`) AS `trans_amount`,`pitm`.`po_item_cat_id` AS `catid`,`pitm`.`po_item_pro_id` AS `proid`,1 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,concat(`pitm`.`fix_rate_per_grm`,if((`pitm`.`is_rate_fixed` = 1),'','(Un Fixed)')) AS `rate`,ifnull(`pitm`.`remark`,'') AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from ((((((`ret_purchase_order_items` `pitm` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pitm`.`po_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pitm`.`po_item_pro_id`)))
where ((`grn`.`grn_type` <> 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0))
group by `pitm`.`po_item_id`

UNION ALL

-- 11. SUPPLIER - Payments (bill payments only - bill_type = 1)
select 1 AS `ledger_type`,'PAYMENT' AS `category`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_po_payment/paymentacknolodgement/' AS `link`,`pay`.`pay_id` AS `print_id`,'type 11' AS `qry_type`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po_bill`.`bill_amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,`pay`.`pay_narration` AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`
from (((((`ret_po_payment` `pay` left join `ret_po_payment_detail` `pd` on((`pd`.`pay_id` = `pay`.`pay_id`))) left join `ret_po_bill_payment_details` `po_bill` on((`po_bill`.`pay_id` = `pay`.`pay_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `po_bill`.`po_id`))) left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,min(`ret_purchase_order_items`.`po_item_cat_id`) AS `po_item_cat_id` from `ret_purchase_order_items` group by `ret_purchase_order_items`.`po_item_po_id`) `order` on((`order`.`po_item_po_id` = `po`.`po_id`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `order`.`po_item_cat_id`)))
where ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 1))
group by `po_bill`.`po_id`

UNION ALL

-- 12. SUPPLIER - Rate Fixing (PO based)
select 1 AS `ledger_type`,concat('RATE FIXING',if((`pitm`.`rate` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 12' AS `qry_type`,if((`pitm`.`rate` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,'' AS `gross_wt`,'' AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,'' AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`pitm`.`rate` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate`
from ((((`ret_po_rate_fix` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`rate_fix_po_item_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join (select `pitm`.`po_item_po_id` AS `poid`,`pitm`.`fix_rate_per_grm` AS `rate` from `ret_purchase_order_items` `pitm` group by `pitm`.`po_item_po_id`) `pitm` on((`pitm`.`poid` = `po`.`po_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
where ((`po`.`isratefixed` = 0) and (`po`.`is_suspense_stock` = 0) and (`rf`.`bill_status` = 1))
group by `rf`.`rate_fix_id`

UNION ALL

-- 13. SUPPLIER - Rate Fixing (Approval / Rate Cut based)
select 1 AS `ledger_type`,concat('RATE FIXING',if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 13' AS `qry_type`,if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,'' AS `gross_wt`,'' AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,'' AS `purewt`,`rc`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`rc`.`rate_per_gram` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,`rc`.`id_metal` AS `id_metal`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate`
from ((`ret_po_rate_fix` `rf` left join `ret_supplier_rate_cut` `rc` on((`rc`.`id_supplier_rate_cut` = `rf`.`id_approval_ratecut`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `rc`.`po_id`)))
where ((`rf`.`rate_fix_type` = 2) and (`rf`.`bill_status` = 1) and (`rc`.`conversion_type` = 1))
group by `rf`.`rate_fix_id`

UNION ALL

-- 14. SUPPLIER - Purchase Return (purchase_type = 0)
select 1 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 14' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate`
from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
where ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 0) and (`ret`.`bill_status` = 1))
group by `pret`.`pur_ret_itm_id`

UNION ALL

-- 15. SUPPLIER - Purchase Return (purchase_type = 1)
select 1 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 15' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate`
from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
where ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 1) and (`ret`.`bill_status` = 1))
group by `pret`.`pur_ret_itm_id`

UNION ALL

-- 16. SUPPLIER - Rate Cut (rate_cut_type = 2)
select 1 AS `ledger_type`,ifnull(`pr`.`product_name`,'PURE') AS `category`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_rate_cut/job_receipt/' AS `link`,`rf`.`id_supplier_rate_cut` AS `print_id`,'type 16' AS `qry_type`,1 AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'100' AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,`rf`.`amount` AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`rf`.`id_metal` AS `id_metal`,`rf`.`id_metal` AS `metal`,if((`rf`.`conversion_type` = 2),concat(`rf`.`rate_per_gram`,'(Unfix)'),`rf`.`rate_per_gram`) AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate`
from ((`ret_supplier_rate_cut` `rf` left join `ret_product_master` `pr` on((`pr`.`pro_id` = `rf`.`id_product`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`po_id`)))
where ((`rf`.`rate_cut_type` = 2) and (`rf`.`status` = 1))
group by `rf`.`id_supplier_rate_cut`

UNION ALL

-- 17. SUPPLIER - Opening Balance (amount)
select 1 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 17' AS `qry_type`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,sum(`pay`.`amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate`
from `smith_company_op_balance` `pay`
where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`amount` > 0) and (`pay`.`status` = 1))
group by `pay`.`id_smith_company_op_balance`

UNION ALL

-- 18. SUPPLIER - Opening Balance (weight)
select 1 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 18' AS `qry_type`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`weight`,0) AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate`
from `smith_company_op_balance` `pay`
where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`weight` > 0) and (`pay`.`status` = 1))
group by `pay`.`id_smith_company_op_balance`

UNION ALL

-- 19. SUPPLIER - Credit / Debit Note
select 1 AS `ledger_type`,if((`pay`.`transtype` = 1),'Credit Note','Debit Note') AS `category`,'' AS `product`,`pay`.`transdate` AS `trans_date`,`pay`.`transbillno` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/credit_debit_acknolodgement/' AS `link`,`pay`.`crdrid` AS `print_id`,'type 19' AS `qry_type`,`pay`.`transtype` AS `trans_type`,`pay`.`transbillno` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`supid` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`transamount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,`pay`.`naration` AS `narration`,unix_timestamp(`pay`.`transdate`) AS `unixtransdate`
from (((`ret_crdr_note` `pay` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pay`.`po_id`))) left join `ret_purchase_order_items` `order` on((`order`.`po_item_po_id` = `po`.`po_id`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `order`.`po_item_cat_id`)))
where ((`pay`.`accountto` = 1) and (`pay`.`transamount` > 0) and (`pay`.`crdr_status` = 1))
group by `pay`.`crdrid`

UNION ALL

-- 20. SUPPLIER - TDS
select 1 AS `ledger_type`,'TDS' AS `category`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 20' AS `qry_type`,2 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tds_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from ((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
where ((`grn`.`grn_type` <> 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tds_tax_value` > 0))
group by `po`.`po_id`

UNION ALL

-- 21. SUPPLIER - TCS
select 1 AS `ledger_type`,'TCS' AS `category`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 21' AS `qry_type`,1 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tcs_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from ((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
where ((`grn`.`grn_type` <> 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tcs_tax_value` > 0))
group by `po`.`po_id`

UNION ALL

-- 22. SUPPLIER - Common PO Advance (bill_type = 4) -- NEW in this migration
select 1 AS `ledger_type`,'COMMON PO ADVANCE' AS `category`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,`co`.`order_date` AS `po_date`,`co`.`pur_no` AS `po_ref_no`,'/supplier_po_payment/paymentacknolodgement/' AS `link`,`pay`.`pay_id` AS `print_id`,'type 22' AS `qry_type`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,`pay`.`pay_amt` AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,concat('Advance against Common PO ',ifnull(`co`.`pur_no`,'')) AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`
from (((`ret_po_payment` `pay` left join `customerorder` `co` on((`co`.`id_customerorder` = `pay`.`common_po_ref`))) left join (select `cod`.`id_customerorder` AS `id_customerorder`,min(`cod`.`id_category`) AS `id_category` from `customerorderdetails` `cod` group by `cod`.`id_customerorder`) `cpo` on((`cpo`.`id_customerorder` = `pay`.`common_po_ref`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `cpo`.`id_category`)))
where ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 4) and (`pay`.`common_po_ref` is not null))
order by `unixtransdate` desc;

-- DOWN
-- Re-run the UP statement above with block 22 and the `UNION ALL` preceding it
-- deleted, leaving `order by `unixtransdate` desc` at the end. Branches 1-21 in this
-- file are the pre-migration definition verbatim, so that restores it exactly.
--
-- Verify either direction with a full-column symmetric difference against a snapshot
-- of the old definition - it must return 0 rows:
--   SELECT COUNT(*) FROM (
--     SELECT <all 28 columns>, SUM(s) FROM (
--       SELECT v.*, 1 s FROM <old_definition_view> v
--       UNION ALL
--       SELECT v.*, -1 s FROM ret_view_smith_combined_ledger v
--        WHERE v.category <> 'COMMON PO ADVANCE'
--     ) u GROUP BY 1,2,...,28 HAVING SUM(s) <> 0
--   ) d;
--
-- KNOWN PRE-EXISTING DEFECT in this view - CONFIRMED LIVE, deliberately NOT fixed
-- here because fixing it moves existing client ledger figures. Needs its own ticket:
--
--   Branch 11 computes `sum(po_bill.bill_amount)` while ALSO joining
--   ret_po_payment_detail, and then groups by `po_bill.po_id` instead of `pay.pay_id`.
--   Two things go wrong:
--     (a) fan-out - a payment with N detail rows multiplies its allocated amount by N;
--     (b) collapse - all payments sharing a po_id (including the 0 used when a payment
--         is not tied to a PO) merge into ONE row under an arbitrary pay_refno, and
--         the rest disappear from the ledger entirely.
--
--   Observed on retail_qa, supplier 1, bill payments (bill_type = 1):
--     actual   00002 = 123,600   00003 = 1,138,150   00005 = 123,600  (total 1,385,350)
--     reported 00002 = 247,200   00003 = 3,414,450   00005 = MISSING  (total 3,661,650)
--   Debits are overstated by 2,276,300, so this report understates the supplier
--   outstanding by the same amount and disagrees with the Supplier Transaction Report
--   (ret_view_supplier_ledger), which reports all three payments correctly.
--
--   Branch 22 does not inherit either fault: it reads `pay.pay_amt` and joins nothing
--   that can fan out.
--   * `SHOW CREATE VIEW` / `DESCRIBE` on this view fail with an illegal-mix-of-
--     collations error unless the client charset is latin1. SELECT is unaffected, so
--     the application is not impacted.
