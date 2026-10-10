-- =============================================================================
-- VIEW: ret_view_supplier_ledger
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================
--
-- SPS-04 (Partial Supplier Payment & Settlement Management) -- READ BEFORE DEPLOY.
--
-- THIS EDIT CHANGES REPORTED LEDGER FIGURES on clients that have multi-bill or
-- multi-mode supplier payments. Two changes are folded in:
--
--   1. The PAYMENT branch cartesian double count is fixed (see the note on that branch).
--      Amounts that were overstated become correct -- they go DOWN, never up.
--   2. The Common PO Advance branch, which migration 20260807_000002 added to the live
--      view but never wrote back here, is restored. Without it this file would have
--      DELETED that branch from every deployed view the moment its checksum changed,
--      because scripts/run-migrations.sh PHASE 2 re-applies canonical view files on
--      checksum change.
--
-- Run this on a client BEFORE deploying, to list the payments whose ledger figure will
-- move and by how much. Every row it returns is currently overstated:
--
--   SELECT p.pay_id, p.pay_refno, p.pay_amt,
--          (SELECT COUNT(*) FROM ret_po_payment_detail d WHERE d.pay_id = p.pay_id) AS modes,
--          (SELECT COUNT(*) FROM ret_po_bill_payment_details b WHERE b.pay_id = p.pay_id) AS bills
--     FROM ret_po_payment p
--    WHERE p.pay_status = 1 AND p.bill_type = 1
--   HAVING modes > 1 OR bills > 1;
--
-- If the client has already reconciled or filed against those overstated figures, agree
-- the correction with them before deploying.
--
-- ret_view_smith_ledger is deliberately NOT changed: its PAYMENT branch filters
-- bill_type = 2 and never joins ret_po_bill_payment_details, so it has neither defect.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `ret_view_supplier_ledger` AS select ifnull(`pr`.`product_name`,'Product Unavailable') AS `category`,`po`.`po_id` AS `po_id`,`met`.`id_metal` AS `id_metal`,ifnull(`pr`.`product_name`,'') AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,1 AS `trans_type`,`pitm`.`po_order_no` AS `trans_id`,`pitm`.`gross_wt` AS `gross_wt`,`pitm`.`net_wt` AS `net_wt`,`pitm`.`no_of_pcs` AS `no_of_pcs`,`pitm`.`purchase_touch` AS `purchase_touch`,`pitm`.`item_pure_wt` AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,(sum(`pitm`.`item_cost`) + if((`pitm`.`po_item_id` = `radj`.`max_po_item_id`),ifnull(`radj`.`round_off_delta`,0),0)) AS `trans_amount`,`pitm`.`po_item_cat_id` AS `catid`,`pitm`.`po_item_pro_id` AS `proid`,1 AS `trans_screen_id`,`met`.`metal` AS `metal`,concat(`pitm`.`fix_rate_per_grm`,if((`pitm`.`is_rate_fixed` = 1),'','(Un Fixed)')) AS `rate`,ifnull(`pitm`.`remark`,'') AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from `ret_purchase_order_items` `pitm` left join `ret_purchase_order` `po` on(`po`.`po_id` = `pitm`.`po_item_po_id`) left join `ret_grn_entry` `grn` on(`grn`.`grn_id` = `po`.`po_grn_id`) left join `ret_karigar` `kr` on(`kr`.`id_karigar` = `po`.`po_karigar_id`) left join `ret_category` `cat` on(`cat`.`id_ret_category` = `pitm`.`po_item_cat_id`) left join `metal` `met` on(`met`.`id_metal` = `cat`.`id_metal`) left join `ret_product_master` `pr` on(`pr`.`pro_id` = `pitm`.`po_item_pro_id`) left join (select `p`.`po_id` AS `po_id`,`i`.`max_po_item_id` AS `max_po_item_id`,(case when (abs(round((`p`.`tot_purchase_amt` - `i`.`item_cost_total` + `p`.`tds_tax_value` - `p`.`tcs_tax_value`),2)) < 1) then round((`p`.`tot_purchase_amt` - `i`.`item_cost_total` + `p`.`tds_tax_value` - `p`.`tcs_tax_value`),2) else 0 end) AS `round_off_delta` from `ret_purchase_order` `p` join (select `po_item_po_id`,sum(`item_cost`) AS `item_cost_total`,max(`po_item_id`) AS `max_po_item_id` from `ret_purchase_order_items` group by `po_item_po_id`) `i` on(`i`.`po_item_po_id` = `p`.`po_id`)) `radj` on(`radj`.`po_id` = `po`.`po_id`)
WHERE ((`grn`.`grn_type` not in (2,4)) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0))
GROUP BY `pitm`.`po_item_id`

-- NOTE: The `radj` join above folds the invoice round-off (tot_purchase_amt vs SUM(item_cost),
-- guarded to genuine sub-rupee gaps < 1) into the LAST item row of each purchase order, so the
-- ledger shows one line per item with no separate "ROUND OFF" row, while still reconciling
-- exactly to tot_purchase_amt (the figure Supplier Payment Entry uses).

UNION ALL

-- NOTE (SPS-04): ret_po_payment_detail (one row per payment MODE) and
-- ret_po_bill_payment_details (one row per ALLOCATION) are both 1:N off
-- ret_po_payment. Joining both in one FROM clause is a cartesian product -- M modes x
-- B bills = M*B rows -- over which sum(pd.payment_amount) returned B times the true
-- payment. One cash payment settling three bills was counted three times.
-- 20260807_000002 documented the defect and routed its new branch 13 around it rather
-- than fixing it; it is fixed here by pre-aggregating BOTH sides to exactly one row per
-- pay_id, which makes the product 1x1 and the surviving sum() a no-op.
-- `bill`.`po_id` is min() because a payment spanning several bills has no single PO.
-- That was already the effective behaviour (an unaggregated column under GROUP BY
-- picks an arbitrary row); it is only made explicit. Nothing filters PAYMENT rows of
-- this view by po_id -- per-PO attribution comes from ret_view_smith_combined_ledger
-- and from getPending_payment_po_bills_for_payment().
select 'PAYMENT' AS `category`,ifnull(`bill`.`po_id`,'') AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`pd`.`payment_amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate` from ((((`ret_po_payment` `pay` left join (select `ret_po_payment_detail`.`pay_id` AS `pay_id`,sum(`ret_po_payment_detail`.`payment_amount`) AS `payment_amount` from `ret_po_payment_detail` group by `ret_po_payment_detail`.`pay_id`) `pd` on((`pd`.`pay_id` = `pay`.`pay_id`))) left join (select `ret_po_bill_payment_details`.`pay_id` AS `pay_id`,min(`ret_po_bill_payment_details`.`po_id`) AS `po_id` from `ret_po_bill_payment_details` group by `ret_po_bill_payment_details`.`pay_id`) `bill` on((`bill`.`pay_id` = `pay`.`pay_id`))) left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,`ret_purchase_order_items`.`po_item_cat_id` AS `po_item_cat_id` from `ret_purchase_order_items`
GROUP BY `ret_purchase_order_items`.`po_item_po_id`) `po` on((`bill`.`po_id` = `po`.`po_item_po_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `po`.`po_item_cat_id`)))
WHERE ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 1))
GROUP BY `pay`.`pay_id`

UNION ALL

select concat('RATE FIXING',if((`pitm`.`rate` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,ifnull(`po`.`po_id`,'') AS `po_id`,`pitm`.`id_metal` AS `id_metal`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,if((`pitm`.`rate` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,'' AS `gross_wt`,'' AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,'' AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`pitm`.`rate` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate` from (((`ret_po_rate_fix` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`rate_fix_po_item_id`))) left join (select `pitm`.`po_item_po_id` AS `poid`,`pitm`.`fix_rate_per_grm` AS `rate`,`cate`.`id_metal` AS `id_metal` from (`ret_purchase_order_items` `pitm` left join `ret_category` `cate` on((`cate`.`id_ret_category` = `pitm`.`po_item_cat_id`)))
GROUP BY `pitm`.`po_item_po_id`) `pitm` on((`pitm`.`poid` = `po`.`po_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`po`.`isratefixed` = 0) and (`po`.`is_suspense_stock` = 0) and (`rf`.`bill_status` = 1) and (`rf`.`is_approved` = 1))
GROUP BY `rf`.`rate_fix_id`

UNION ALL

select concat('RATE FIXING',if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,ifnull(`rc`.`po_id`,'') AS `po_id`,`rc`.`id_metal` AS `id_metal`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,`rc`.`weight` AS `gross_wt`,`rc`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,`rc`.`weight` AS `purewt`,`rc`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`rc`.`rate_per_gram` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate` from (`ret_po_rate_fix` `rf` left join `ret_supplier_rate_cut` `rc` on((`rc`.`id_supplier_rate_cut` = `rf`.`id_approval_ratecut`)))
WHERE ((`rf`.`rate_fix_type` = 2) and (`rf`.`bill_status` = 1) and (`rf`.`is_approved` = 1))
GROUP BY `rf`.`rate_fix_id`

UNION ALL

select ifnull(max(`pr`.`product_name`),'Product Unavailable') AS `category`,max(ifnull(`po`.`po_id`,'')) AS `po_id`,max(`met`.`id_metal`) AS `id_metal`,ifnull(max(`pr`.`product_name`),'') AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,max(`pret`.`pur_ret_purchase_touch`) AS `purchase_touch`,sum(`pret`.`pur_ret_pur_wt`) AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,(case when abs(max(`ret`.`return_total_cost`) - (sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) + ifnull(max(`ret`.`pur_ret_round_off`),0))) < 1 then max(`ret`.`return_total_cost`) else (sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) + ifnull(max(`ret`.`pur_ret_round_off`),0)) end) AS `trans_amount`,max(`cat`.`id_ret_category`) AS `catid`,max(`pret`.`id_product`) AS `proid`,5 AS `trans_screen_id`,max(`met`.`metal`) AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate` from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
WHERE ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 0) and (`ret`.`bill_status` = 1))
GROUP BY `ret`.`pur_return_id`

UNION ALL

select ifnull(max(`pr`.`product_name`),'Product Unavailable') AS `category`,max(ifnull(`po`.`po_id`,'')) AS `po_id`,max(`met`.`id_metal`) AS `id_metal`,ifnull(max(`pr`.`product_name`),'') AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,max(`pret`.`pur_ret_purchase_touch`) AS `purchase_touch`,sum(`pret`.`pur_ret_pur_wt`) AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,(case when abs(max(`ret`.`return_total_cost`) - (sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) + ifnull(max(`ret`.`pur_ret_round_off`),0))) < 1 then max(`ret`.`return_total_cost`) else (sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) + ifnull(max(`ret`.`pur_ret_round_off`),0)) end) AS `trans_amount`,max(`cat`.`id_ret_category`) AS `catid`,max(`pret`.`id_product`) AS `proid`,5 AS `trans_screen_id`,max(`met`.`metal`) AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate` from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
WHERE ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 1) and (`ret`.`bill_status` = 1))
GROUP BY `ret`.`pur_return_id`

UNION ALL

select ifnull(`pr`.`product_name`,'RATE FIXING') AS `category`,ifnull(`rf`.`po_id`,'') AS `po_id`,`rf`.`id_metal` AS `id_metal`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,1 AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'100' AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,`rf`.`amount` AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`rf`.`id_metal` AS `metal`,if((`rf`.`conversion_type` = 2),concat('(Unfix)',`rf`.`rate_per_gram`),`rf`.`rate_per_gram`) AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate` from (`ret_supplier_rate_cut` `rf` left join `ret_product_master` `pr` on((`pr`.`pro_id` = `rf`.`id_product`)))
WHERE ((`rf`.`rate_cut_type` = 2) and (`rf`.`status` = 1))
GROUP BY `rf`.`id_supplier_rate_cut`

UNION ALL

select 'OPENING' AS `category`,'' AS `po_id`,`pay`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,`pay`.`weight` AS `gross_wt`,`pay`.`net_wt` AS `net_wt`,`pay`.`pieces` AS `no_of_pcs`,'' AS `purchase_touch`,`pay`.`pure_wt` AS `purewt`,`pay`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,sum(`pay`.`amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate` from `smith_company_op_balance` `pay` where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`amount` > 0) and (`pay`.`status` = 1))
GROUP BY `pay`.`id_smith_company_op_balance`

UNION ALL

select 'OPENING' AS `category`,'' AS `po_id`,`pay`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`net_wt`,0) AS `net_wt`,`pay`.`pieces` AS `no_of_pcs`,'' AS `purchase_touch`,`pay`.`pure_wt` AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate` from `smith_company_op_balance` `pay` where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`weight` > 0) and (`pay`.`status` = 1))
GROUP BY `pay`.`id_smith_company_op_balance`

UNION ALL

select if((`pay`.`transtype` = 1),'Credit Note','Debit Note') AS `category`,`pay`.`po_id` AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`transdate` AS `trans_date`,`pay`.`transbillno` AS `referenceno`,`pay`.`transtype` AS `trans_type`,`pay`.`transbillno` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`supid` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`transamount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,`pay`.`naration` AS `narration`,unix_timestamp(`pay`.`transdate`) AS `unixtransdate` from ((`ret_crdr_note` `pay` left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,`ret_purchase_order_items`.`po_item_cat_id` AS `po_item_cat_id` from `ret_purchase_order_items`
GROUP BY `ret_purchase_order_items`.`po_item_po_id`) `po` on((`pay`.`po_id` = `po`.`po_item_po_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `po`.`po_item_cat_id`)))
WHERE ((`pay`.`accountto` = 1) and (`pay`.`transamount` > 0) and (`pay`.`crdr_status` = 1))
GROUP BY `pay`.`crdrid`

UNION ALL

select 'TDS' AS `category`,`po`.`po_id` AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,2 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tds_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from ((((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,`ret_purchase_order_items`.`po_item_cat_id` AS `po_item_cat_id` from `ret_purchase_order_items`
GROUP BY `ret_purchase_order_items`.`po_item_po_id`) `item` on((`po`.`po_id` = `item`.`po_item_po_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `item`.`po_item_cat_id`)))
WHERE ((`grn`.`grn_type` not in (2,4)) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tds_tax_value` > 0))
GROUP BY `po`.`po_id`

UNION ALL

select 'TCS' AS `category`,`po`.`po_id` AS `po_id`,'' AS `id_metal`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,2 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tcs_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from ((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`grn`.`grn_type` not in (2,4)) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tcs_tax_value` > 0))
GROUP BY `po`.`po_id`

UNION ALL

-- Common PO Advance (bill_type = 4).
--
-- BACK-PORTED (SPS-04). This branch was added to the live view by migration
-- 20260807_000002 (CPO-09/CPO-13) but was never written back into this canonical file,
-- so the file and the deployed view had silently diverged. That matters because
-- scripts/run-migrations.sh PHASE 2 re-applies a canonical view file whenever its
-- checksum changes -- so the next edit to this file, for any reason, would have
-- re-created the view WITHOUT this branch and made every Common PO advance vanish from
-- the Supplier Transaction Report again, overstating supplier outstanding by the whole
-- advance. Reproduced verbatim from that migration.
--
-- trans_amount reads pay.pay_amt rather than sum(pd.payment_amount): an advance can be
-- adjusted across more than one bill, and this branch avoids the allocation join
-- entirely. (The cartesian that made that necessary is itself fixed in the PAYMENT
-- branch above, but this branch has no need of either join, so it is left as written.)
select 'COMMON PO ADVANCE' AS `category`,ifnull(`pay`.`common_po_ref`,'') AS `po_id`,`cat`.`id_metal` AS `id_metal`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,`pay`.`pay_amt` AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `metal`,'' AS `rate`,concat('Advance against Common PO ',ifnull(`co`.`pur_no`,'')) AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`
from ((`ret_po_payment` `pay` left join `customerorder` `co` on((`co`.`id_customerorder` = `pay`.`common_po_ref`))) left join (select `cod`.`id_customerorder` AS `id_customerorder`,min(`cod`.`id_category`) AS `id_category` from `customerorderdetails` `cod` group by `cod`.`id_customerorder`) `cpo` on((`cpo`.`id_customerorder` = `pay`.`common_po_ref`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `cpo`.`id_category`))
WHERE ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 4) and (`pay`.`common_po_ref` is not null))
ORDER BY `unixtransdate` desc;
