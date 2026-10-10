-- Migration: Surface Common PO advance payments in the supplier ledger
-- Date: 07-08-2026
-- Ticket: CPO-09 / CPO-13 (Common Purchase Order - Advance Rate Fixing for Bullion Purchase)
--
-- Issue: The PAYMENT branch of ret_view_supplier_ledger is filtered to
--        `pay.bill_type = 1` (payments made against a supplier bill). A Common PO
--        advance is written to ret_po_payment with `bill_type = 4`, so it never
--        reached the view. The Supplier Transaction Report
--        (admin_ret_reports/suppliertransaction) reads this view directly, so the
--        advance was invisible there: the Common PO bill showed as a Credit with no
--        matching Debit, overstating the supplier outstanding by the whole advance.
--
-- Fix: Add UNION ALL branch 13 for bill_type = 4. The existing 12 branches are
--      reproduced from the LIVE view definition and are not otherwise changed, so
--      this migration cannot regress an existing ledger figure. The only edit to
--      existing text is that the view's trailing `order by unixtransdate desc`
--      moves from the end of branch 12 to the end of branch 13 - it terminates the
--      whole UNION, and nothing may follow an ORDER BY.
--
-- Notes on branch 13:
--   * trans_type = 2 (Debit) / trans_screen_id = 3 - the same treatment the report
--     already gives an ordinary payment, so the running balance nets the advance
--     against the Common PO bill and settles to zero.
--   * trans_amount reads `pay.pay_amt` (the payment header) rather than
--     sum(pd.payment_amount). Branch 2 sums the detail rows while also joining
--     ret_po_bill_payment_details, so a payment allocated across N bills is
--     multiplied by N. A Common PO advance CAN be adjusted across more than one
--     bill (CPO-13), so this branch avoids that join entirely.
--   * All weight columns are 0. An advance is money, not metal - the metal reaches
--     the ledger through the GRN / supplier bill in branch 1.
--   * id_metal resolves through the Common PO's first line category so the report's
--     metal filter keeps the advance. A Common PO spanning two metals reports under
--     the first; branch 2 already has the same limitation.
--   * The adjustment itself (ret_common_po_adjustment) deliberately produces NO
--     ledger row. It links an existing Debit to an existing Credit; posting it
--     again would double-count the advance.
--
-- Impact: Additive. With common_po_enabled = 0 no bill_type = 4 rows exist, so the
--         view returns exactly what it returned before.

-- CHARSET: `SET NAMES latin1` below is not cosmetic. A view stores the client
-- charset in force when it was created, and that charset fixes the collation of every
-- string literal inside it. Every ledger view in this schema is latin1, so recreating
-- this one under a different charset (the mysql CLI on Windows defaults to cp850)
-- silently changes it and can produce illegal-mix-of-collations errors later. Confirm
-- the deployment's value first and match it:
--   SELECT CHARACTER_SET_CLIENT, COLLATION_CONNECTION FROM information_schema.VIEWS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ret_view_supplier_ledger';
--
-- Before running this on a deployment, keep a copy of that deployment's CURRENT
-- definition - clients customise this view and it drifts:
--     SHOW CREATE VIEW `ret_view_supplier_ledger`;
-- Do NOT use `CREATE VIEW backup AS SELECT * FROM ret_view_supplier_ledger` as a
-- backup (the pattern in 20260430_000001). A view over a view follows the original,
-- so it changes the moment this migration runs and restores nothing.

-- UP
SET NAMES latin1;

CREATE OR REPLACE VIEW `ret_view_supplier_ledger` AS
-- 1. Purchase Order Items (grn_type NOT IN (2,4) - excludes smith receipt & job work)
select ifnull(`pr`.`product_name`,'Product Unavailable') AS `category`,`po`.`po_id` AS `po_id`,`met`.`id_metal` AS `id_metal`,ifnull(`pr`.`product_name`,'') AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,1 AS `trans_type`,`pitm`.`po_order_no` AS `trans_id`,`pitm`.`gross_wt` AS `gross_wt`,`pitm`.`net_wt` AS `net_wt`,`pitm`.`no_of_pcs` AS `no_of_pcs`,`pitm`.`purchase_touch` AS `purchase_touch`,`pitm`.`item_pure_wt` AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,(sum(`pitm`.`item_cost`) + if((`pitm`.`po_item_id` = `radj`.`max_po_item_id`),ifnull(`radj`.`round_off_delta`,0),0)) AS `trans_amount`,`pitm`.`po_item_cat_id` AS `catid`,`pitm`.`po_item_pro_id` AS `proid`,1 AS `trans_screen_id`,`met`.`metal` AS `metal`,concat(`pitm`.`fix_rate_per_grm`,if((`pitm`.`is_rate_fixed` = 1),'','(Un Fixed)')) AS `rate`,ifnull(`pitm`.`remark`,'') AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from (((((((`ret_purchase_order_items` `pitm` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pitm`.`po_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pitm`.`po_item_pro_id`))) left join (select `p`.`po_id` AS `po_id`,`i`.`max_po_item_id` AS `max_po_item_id`,(case when (abs(round((((`p`.`tot_purchase_amt` - `i`.`item_cost_total`) + `p`.`tds_tax_value`) - `p`.`tcs_tax_value`),2)) < 1) then round((((`p`.`tot_purchase_amt` - `i`.`item_cost_total`) + `p`.`tds_tax_value`) - `p`.`tcs_tax_value`),2) else 0 end) AS `round_off_delta` from (`ret_purchase_order` `p` join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,sum(`ret_purchase_order_items`.`item_cost`) AS `item_cost_total`,max(`ret_purchase_order_items`.`po_item_id`) AS `max_po_item_id` from `ret_purchase_order_items` group by `ret_purchase_order_items`.`po_item_po_id`) `i` on((`i`.`po_item_po_id` = `p`.`po_id`)))) `radj` on((`radj`.`po_id` = `po`.`po_id`)))
where ((`grn`.`grn_type` not in (2,4)) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0))
group by `pitm`.`po_item_id`

UNION ALL

-- 2. Payments (bill payments only - bill_type = 1)
select 'PAYMENT' AS `category`,ifnull(`bill`.`po_id`,'') AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`pd`.`payment_amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`
from ((((`ret_po_payment` `pay` left join `ret_po_payment_detail` `pd` on((`pd`.`pay_id` = `pay`.`pay_id`))) left join `ret_po_bill_payment_details` `bill` on((`bill`.`pay_id` = `pay`.`pay_id`))) left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,`ret_purchase_order_items`.`po_item_cat_id` AS `po_item_cat_id` from `ret_purchase_order_items` group by `ret_purchase_order_items`.`po_item_po_id`) `po` on((`bill`.`po_id` = `po`.`po_item_po_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `po`.`po_item_cat_id`)))
where ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 1))
group by `pay`.`pay_id`

UNION ALL

-- 3. Rate Fixing (PO based)
select concat('RATE FIXING',if((`pitm`.`rate` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,ifnull(`po`.`po_id`,'') AS `po_id`,`pitm`.`id_metal` AS `id_metal`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,if((`pitm`.`rate` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,'' AS `gross_wt`,'' AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,'' AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`pitm`.`rate` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate`
from (((`ret_po_rate_fix` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`rate_fix_po_item_id`))) left join (select `pitm`.`po_item_po_id` AS `poid`,`pitm`.`fix_rate_per_grm` AS `rate`,`cate`.`id_metal` AS `id_metal` from (`ret_purchase_order_items` `pitm` left join `ret_category` `cate` on((`cate`.`id_ret_category` = `pitm`.`po_item_cat_id`))) group by `pitm`.`po_item_po_id`) `pitm` on((`pitm`.`poid` = `po`.`po_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
where ((`po`.`isratefixed` = 0) and (`po`.`is_suspense_stock` = 0) and (`rf`.`bill_status` = 1) and (`rf`.`is_approved` = 1))
group by `rf`.`rate_fix_id`

UNION ALL

-- 4. Rate Fixing (Approval / Rate Cut based)
select concat('RATE FIXING',if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,ifnull(`rc`.`po_id`,'') AS `po_id`,`rc`.`id_metal` AS `id_metal`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,`rc`.`weight` AS `gross_wt`,`rc`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,`rc`.`weight` AS `purewt`,`rc`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`rc`.`rate_per_gram` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate`
from (`ret_po_rate_fix` `rf` left join `ret_supplier_rate_cut` `rc` on((`rc`.`id_supplier_rate_cut` = `rf`.`id_approval_ratecut`)))
where ((`rf`.`rate_fix_type` = 2) and (`rf`.`bill_status` = 1) and (`rf`.`is_approved` = 1))
group by `rf`.`rate_fix_id`

UNION ALL

-- 5. Purchase Return (purchase_type = 0)
select ifnull(max(`pr`.`product_name`),'Product Unavailable') AS `category`,max(ifnull(`po`.`po_id`,'')) AS `po_id`,max(`met`.`id_metal`) AS `id_metal`,ifnull(max(`pr`.`product_name`),'') AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,max(`pret`.`pur_ret_purchase_touch`) AS `purchase_touch`,sum(`pret`.`pur_ret_pur_wt`) AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,(sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) + ifnull(max(`ret`.`pur_ret_round_off`),0)) AS `trans_amount`,max(`cat`.`id_ret_category`) AS `catid`,max(`pret`.`id_product`) AS `proid`,5 AS `trans_screen_id`,max(`met`.`metal`) AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate`
from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
where ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 0) and (`ret`.`bill_status` = 1))
group by `ret`.`pur_return_id`

UNION ALL

-- 6. Purchase Return (purchase_type = 1)
select ifnull(max(`pr`.`product_name`),'Product Unavailable') AS `category`,max(ifnull(`po`.`po_id`,'')) AS `po_id`,max(`met`.`id_metal`) AS `id_metal`,ifnull(max(`pr`.`product_name`),'') AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,max(`pret`.`pur_ret_purchase_touch`) AS `purchase_touch`,sum(`pret`.`pur_ret_pur_wt`) AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,(sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) + ifnull(max(`ret`.`pur_ret_round_off`),0)) AS `trans_amount`,max(`cat`.`id_ret_category`) AS `catid`,max(`pret`.`id_product`) AS `proid`,5 AS `trans_screen_id`,max(`met`.`metal`) AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate`
from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
where ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 1) and (`ret`.`bill_status` = 1))
group by `ret`.`pur_return_id`

UNION ALL

-- 7. Rate Cut (rate_cut_type = 2)
select ifnull(`pr`.`product_name`,'RATE FIXING') AS `category`,ifnull(`rf`.`po_id`,'') AS `po_id`,`rf`.`id_metal` AS `id_metal`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,1 AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'100' AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,`rf`.`amount` AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`rf`.`id_metal` AS `metal`,if((`rf`.`conversion_type` = 2),concat('(Unfix)',`rf`.`rate_per_gram`),`rf`.`rate_per_gram`) AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate`
from (`ret_supplier_rate_cut` `rf` left join `ret_product_master` `pr` on((`pr`.`pro_id` = `rf`.`id_product`)))
where ((`rf`.`rate_cut_type` = 2) and (`rf`.`status` = 1))
group by `rf`.`id_supplier_rate_cut`

UNION ALL

-- 8. Opening Balance - amount
select 'OPENING' AS `category`,'' AS `po_id`,`pay`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,`pay`.`weight` AS `gross_wt`,`pay`.`net_wt` AS `net_wt`,`pay`.`pieces` AS `no_of_pcs`,'' AS `purchase_touch`,`pay`.`pure_wt` AS `purewt`,`pay`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,sum(`pay`.`amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate`
from `smith_company_op_balance` `pay`
where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`amount` > 0) and (`pay`.`status` = 1))
group by `pay`.`id_smith_company_op_balance`

UNION ALL

-- 9. Opening Balance - weight
select 'OPENING' AS `category`,'' AS `po_id`,`pay`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`net_wt`,0) AS `net_wt`,`pay`.`pieces` AS `no_of_pcs`,'' AS `purchase_touch`,`pay`.`pure_wt` AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate`
from `smith_company_op_balance` `pay`
where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`weight` > 0) and (`pay`.`status` = 1))
group by `pay`.`id_smith_company_op_balance`

UNION ALL

-- 10. Credit Note / Debit Note
select if((`pay`.`transtype` = 1),'Credit Note','Debit Note') AS `category`,`pay`.`po_id` AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`transdate` AS `trans_date`,`pay`.`transbillno` AS `referenceno`,`pay`.`transtype` AS `trans_type`,`pay`.`transbillno` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`supid` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`transamount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,`pay`.`naration` AS `narration`,unix_timestamp(`pay`.`transdate`) AS `unixtransdate`
from ((`ret_crdr_note` `pay` left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,`ret_purchase_order_items`.`po_item_cat_id` AS `po_item_cat_id` from `ret_purchase_order_items` group by `ret_purchase_order_items`.`po_item_po_id`) `po` on((`pay`.`po_id` = `po`.`po_item_po_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `po`.`po_item_cat_id`)))
where ((`pay`.`accountto` = 1) and (`pay`.`transamount` > 0) and (`pay`.`crdr_status` = 1))
group by `pay`.`crdrid`

UNION ALL

-- 11. TDS
select 'TDS' AS `category`,`po`.`po_id` AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,2 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tds_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from ((((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,`ret_purchase_order_items`.`po_item_cat_id` AS `po_item_cat_id` from `ret_purchase_order_items` group by `ret_purchase_order_items`.`po_item_po_id`) `item` on((`po`.`po_id` = `item`.`po_item_po_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `item`.`po_item_cat_id`)))
where ((`grn`.`grn_type` not in (2,4)) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tds_tax_value` > 0))
group by `po`.`po_id`

UNION ALL

-- 12. TCS
select 'TCS' AS `category`,`po`.`po_id` AS `po_id`,'' AS `id_metal`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,2 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tcs_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`
from ((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
where ((`grn`.`grn_type` not in (2,4)) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tcs_tax_value` > 0))
group by `po`.`po_id`

UNION ALL

-- 13. Common PO Advance (bill_type = 4) -- NEW in this migration
select 'COMMON PO ADVANCE' AS `category`,ifnull(`pay`.`common_po_ref`,'') AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,`pay`.`pay_amt` AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,concat('Advance against Common PO ',ifnull(`co`.`pur_no`,'')) AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`
from ((`ret_po_payment` `pay` left join `customerorder` `co` on((`co`.`id_customerorder` = `pay`.`common_po_ref`))) left join (select `cod`.`id_customerorder` AS `id_customerorder`,min(`cod`.`id_category`) AS `id_category` from `customerorderdetails` `cod` group by `cod`.`id_customerorder`) `cpo` on((`cpo`.`id_customerorder` = `pay`.`common_po_ref`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `cpo`.`id_category`))
where ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 4) and (`pay`.`common_po_ref` is not null))
order by `unixtransdate` desc;

-- DOWN
-- Re-run the UP statement above with block 13 and the `UNION ALL` preceding it
-- deleted, leaving `order by `unixtransdate` desc` at the end. Branches 1-12 in
-- this file are the pre-migration definition verbatim, so that restores it exactly.
--
-- Verify either direction with a full-column symmetric difference against a
-- snapshot of the old definition - it must return 0:
--   SELECT COUNT(*) FROM (
--     SELECT v.*, 1 s FROM <old_definition_view> v
--     UNION ALL
--     SELECT v.*, -1 s FROM ret_view_supplier_ledger v WHERE v.category <> 'COMMON PO ADVANCE'
--   ) u GROUP BY 1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23
--     HAVING SUM(s) <> 0;
--
-- KNOWN PRE-EXISTING DEFECT, deliberately NOT fixed here (needs its own ticket):
-- branch 2 computes sum(pd.payment_amount) while joining ret_po_bill_payment_details,
-- so a bill payment allocated across N supplier bills is reported N times over in the
-- ledger. No current data hits it (every payment has exactly one allocation row), and
-- changing branch 2 would alter existing client ledger figures.
