-- =============================================================================
-- VIEW: ret_view_smith_combined_ledger
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================
--
-- SPS-04 (Partial Supplier Payment & Settlement Management) -- READ BEFORE DEPLOY.
--
-- THIS EDIT CHANGES REPORTED LEDGER FIGURES. The supplier PAYMENT branch (qry_type 11)
-- carried two defects, both fixed below: an amount multiplied by the number of payment
-- modes, and a GROUP BY that merged separate payments against the same PO into one
-- line. Corrected amounts go DOWN, and payments that were being swallowed by the merge
-- reappear as their own lines.
--
-- The Common PO Advance branch (qry_type 22), added to the live view by migration
-- 20260807_000003 but never written back here, is also restored -- otherwise this file
-- would have deleted it from every deployed view as soon as its checksum changed.
--
-- See the header of ret_view_supplier_ledger.sql for the pre-deploy verification query;
-- the same query identifies the affected payments for this view.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `ret_view_smith_combined_ledger` AS select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 1' AS `qry_type`,1 AS `trans_type`,`pitm`.`po_order_no` AS `trans_id`,`pitm`.`gross_wt` AS `gross_wt`,`pitm`.`net_wt` AS `net_wt`,`pitm`.`no_of_pcs` AS `no_of_pcs`,`pitm`.`purchase_touch` AS `purchase_touch`,`pitm`.`item_pure_wt` AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,1 AS `trans_rec_type`,sum(`pitm`.`item_cost`) AS `trans_amount`,`pitm`.`po_item_cat_id` AS `catid`,`pitm`.`po_item_pro_id` AS `proid`,1 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,concat(`pitm`.`fix_rate_per_grm`,if((`pitm`.`is_rate_fixed` = 1),'','(Un Fixed)')) AS `rate`,ifnull(`pitm`.`remark`,'') AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from ((((((`ret_purchase_order_items` `pitm` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pitm`.`po_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pitm`.`po_item_pro_id`)))
WHERE ((`grn`.`grn_type` = 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 1))
GROUP BY `pitm`.`po_item_id`

UNION ALL

select 2 AS `ledger_type`,'PAYMENT' AS `category`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_rate_cut/job_receipt/' AS `link`,`rf`.`id_supplier_rate_cut` AS `print_id`,'type 2' AS `qry_type`,2 AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,`rf`.`amount` AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,`rf`.`id_metal` AS `id_metal`,`rf`.`id_metal` AS `metal`,`rf`.`rate_per_gram` AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate` from (`ret_supplier_rate_cut` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`po_id`)))
WHERE ((`rf`.`rate_cut_type` = 1) and (`rf`.`status` = 1))
GROUP BY `rf`.`id_supplier_rate_cut`

UNION ALL

select 2 AS `ledger_type`,if((`rf`.`weight` = 0),'Bill Conv(Amount)','Bill Conv(A to P)') AS `category`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_rate_cut/job_receipt/' AS `link`,`rf`.`id_supplier_rate_cut` AS `print_id`,'type 3' AS `qry_type`,(case when ((`rf`.`weight` = 0) and (`rf`.`amount_type` = 1)) then 2 when ((`rf`.`weight` = 0) and (`rf`.`amount_type` = 2)) then 1 when ((`rf`.`weight` <> 0) and (`rf`.`weight_type` = 1)) then 2 when ((`rf`.`weight` <> 0) and (`rf`.`weight_type` = 2)) then 1 end) AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,if((`rf`.`weight` = 0),'','100') AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,if((`rf`.`charges_amount` > 0),`rf`.`charges_amount`,'') AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`rf`.`id_metal` AS `id_metal`,`rf`.`id_metal` AS `metal`,`rf`.`rate_per_gram` AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate` from (`ret_supplier_rate_cut` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`po_id`)))
WHERE ((`rf`.`rate_cut_type` = 2) and (`rf`.`status` = 1))
GROUP BY `rf`.`id_supplier_rate_cut`

UNION ALL

select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 4' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,1 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,`pret`.`pur_ret_rate` AS `rate`,ifnull(`ret`.`pur_ret_remark`,'') AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate` from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
WHERE ((`ret`.`pur_ret_convert_to` = 3) and (`ret`.`purchase_type` = 0) and (`ret`.`bill_status` = 1))
GROUP BY `pret`.`pur_ret_itm_id`

UNION ALL

select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 5' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,1 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,`pret`.`pur_ret_rate` AS `rate`,ifnull(`ret`.`pur_ret_remark`,'') AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate` from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
WHERE ((`ret`.`pur_ret_convert_to` = 3) and (`ret`.`purchase_type` = 1) and (`ret`.`bill_status` = 1))
GROUP BY `pret`.`pur_ret_itm_id`

UNION ALL

select 2 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`iss`.`met_issue_date` AS `trans_date`,`iss`.`met_issue_ref_id` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/karigarmetalissue_acknowladgement/' AS `link`,`iitm`.`issue_met_parent_id` AS `print_id`,'type 6' AS `qry_type`,2 AS `trans_type`,`iitm`.`issue_met_parent_id` AS `trans_id`,if((ifnull(`uom`.`divided_by_value`,0) = 0),`iitm`.`issue_metal_wt`,round((`iitm`.`issue_metal_wt` / `uom`.`divided_by_value`),3)) AS `gross_wt`,if((ifnull(`uom`.`divided_by_value`,0) = 0),`iitm`.`issue_metal_wt`,round((`iitm`.`issue_metal_wt` / `uom`.`divided_by_value`),3)) AS `net_wt`,ifnull(`iitm`.`issue_pcs`,1) AS `no_of_pcs`,ifnull(`iitm`.`touch`,'') AS `purchase_touch`,if((`pr`.`stone_type` = 0),`iitm`.`issue_metal_pur_wt`,if((ifnull(`uom`.`divided_by_value`,0) = 0),`iitm`.`issue_metal_wt`,round((`iitm`.`issue_metal_wt` / `uom`.`divided_by_value`),3))) AS `purewt`,`iss`.`met_issue_karid` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,`iitm`.`issue_cat_id` AS `catid`,`iitm`.`issu_met_pro_id` AS `proid`,2 AS `trans_screen_id`,`iitm`.`issue_metal` AS `id_metal`,`met`.`metal` AS `metal`,'' AS `rate`,ifnull(`iss`.`remark`,'') AS `narration`,unix_timestamp(`iss`.`met_issue_date`) AS `unixtransdate` from ((((((((`ret_karigar_metal_issue_details` `iitm` left join `ret_karigar_metal_issue` `iss` on((`iss`.`met_issue_id` = `iitm`.`issue_met_parent_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `iss`.`met_issue_karid`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `iitm`.`issue_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `iitm`.`issu_met_pro_id`))) left join `ret_uom` `uom` on((`uom`.`uom_id` = `iitm`.`issue_uom_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `iss`.`po_id`))) left join `ret_purchase_order_items` `poitm` on((`poitm`.`po_item_id` = `iitm`.`po_item_id`)))
WHERE ((`iss`.`metalissue_type` = 2) and (`iss`.`bill_status` = 1))
GROUP BY `iitm`.`issue_met_id`,`iitm`.`issue_cat_id`,`iitm`.`issu_met_pro_id`,`iitm`.`issue_met_parent_id`

UNION ALL

select 2 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 7' AS `qry_type`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate` from `smith_company_op_balance` `pay` where ((`pay`.`smith_type` = 3) and (`pay`.`stock_type` = 2) and (`pay`.`amount` > 0) and (`pay`.`status` = 1))
GROUP BY `pay`.`id_smith_company_op_balance`

UNION ALL

select 2 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 8' AS `qry_type`,`pay`.`weight_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`weight`,0) AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,ifnull(`pay`.`weight`,0) AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,`pay`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate` from `smith_company_op_balance` `pay` where ((`pay`.`smith_type` = 3) and (`pay`.`stock_type` = 2) and (`pay`.`weight` > 0) and (`pay`.`status` = 1))
GROUP BY `pay`.`id_smith_company_op_balance`

UNION ALL

select 2 AS `ledger_type`,if((`pay`.`transtype` = 1),'Credit Note','Debit Note') AS `category`,'' AS `product`,`pay`.`transdate` AS `trans_date`,`pay`.`transbillno` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/credit_debit_acknolodgement/' AS `link`,`pay`.`crdrid` AS `print_id`,'type 9' AS `qry_type`,`pay`.`transtype` AS `trans_type`,`pay`.`transbillno` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`weight`,0) AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,ifnull(`pay`.`weight`,0) AS `purewt`,`pay`.`supid` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`transamount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`naration`,'') AS `narration`,unix_timestamp(`pay`.`transdate`) AS `unixtransdate` from (((`ret_crdr_note` `pay` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pay`.`po_id`))) left join `ret_purchase_order_items` `order` on((`order`.`po_item_po_id` = `po`.`po_id`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `order`.`po_item_cat_id`)))
WHERE ((`pay`.`accountto` = 3) and (`pay`.`weight` > 0) and (`pay`.`crdr_status` = 1))
GROUP BY `pay`.`crdrid`

UNION ALL

select 1 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 10' AS `qry_type`,1 AS `trans_type`,`pitm`.`po_order_no` AS `trans_id`,`pitm`.`gross_wt` AS `gross_wt`,`pitm`.`net_wt` AS `net_wt`,`pitm`.`no_of_pcs` AS `no_of_pcs`,`pitm`.`purchase_touch` AS `purchase_touch`,`pitm`.`item_pure_wt` AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`pitm`.`item_cost`) AS `trans_amount`,`pitm`.`po_item_cat_id` AS `catid`,`pitm`.`po_item_pro_id` AS `proid`,1 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,concat(`pitm`.`fix_rate_per_grm`,if((`pitm`.`is_rate_fixed` = 1),'','(Un Fixed)')) AS `rate`,ifnull(`pitm`.`remark`,'') AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from ((((((`ret_purchase_order_items` `pitm` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pitm`.`po_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pitm`.`po_item_pro_id`)))
WHERE ((`grn`.`grn_type` <> 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0))
GROUP BY `pitm`.`po_item_id`

UNION ALL

-- NOTE (SPS-04): two defects fixed in this branch.
--
-- 1. `ret_po_payment_detail` `pd` was joined but contributes NO column to the select
--    list. It existed only to multiply rows: with M payment modes it turned
--    sum(po_bill.bill_amount) into M times the true allocation. Observed on QA before
--    the fix -- pay_id 3, allocated 1,138,150.00, reported 3,414,450.00 across its 3
--    modes. The join is removed outright.
--
-- 2. `GROUP BY po_bill.po_id` alone merged EVERY payment ever made against a PO into a
--    single ledger line carrying an arbitrary trans_id -- on QA, pay_id 2 and pay_id 6
--    collapsed into one 247,200.00 line and pay_id 6 disappeared. Under partial
--    settlement that is the normal case, not an edge case: three instalments against
--    one PO must render as three lines (AC #6, #7). Grouping is now pay_id + po_id,
--    i.e. one line per instalment per bill, which is exactly the allocation grain that
--    ret_po_bill_payment_details stores.
select 1 AS `ledger_type`,'PAYMENT' AS `category`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_po_payment/paymentacknolodgement/' AS `link`,`pay`.`pay_id` AS `print_id`,'type 11' AS `qry_type`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po_bill`.`bill_amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,`pay`.`pay_narration` AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate` from ((((`ret_po_payment` `pay` left join `ret_po_bill_payment_details` `po_bill` on((`po_bill`.`pay_id` = `pay`.`pay_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `po_bill`.`po_id`))) left join (select `ret_purchase_order_items`.`po_item_po_id` AS `po_item_po_id`,min(`ret_purchase_order_items`.`po_item_cat_id`) AS `po_item_cat_id` from `ret_purchase_order_items`
GROUP BY `ret_purchase_order_items`.`po_item_po_id`) `order` on((`order`.`po_item_po_id` = `po`.`po_id`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `order`.`po_item_cat_id`)))
WHERE ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 1))
GROUP BY `pay`.`pay_id`,`po_bill`.`po_id`

UNION ALL

select 1 AS `ledger_type`,concat('RATE FIXING',if((`pitm`.`rate` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 12' AS `qry_type`,if((`pitm`.`rate` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,'' AS `gross_wt`,'' AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,'' AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`pitm`.`rate` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate` from ((((`ret_po_rate_fix` `rf` left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`rate_fix_po_item_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join (select `pitm`.`po_item_po_id` AS `poid`,`pitm`.`fix_rate_per_grm` AS `rate` from `ret_purchase_order_items` `pitm`
GROUP BY `pitm`.`po_item_po_id`) `pitm` on((`pitm`.`poid` = `po`.`po_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`po`.`isratefixed` = 0) and (`po`.`is_suspense_stock` = 0) and (`rf`.`bill_status` = 1))
GROUP BY `rf`.`rate_fix_id`

UNION ALL

select 1 AS `ledger_type`,concat('RATE FIXING',if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),'(Dr)','(Cr)')) AS `category`,'' AS `product`,`rf`.`rate_fix_created_on` AS `trans_date`,`rf`.`rate_fix_id` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 13' AS `qry_type`,if((`rc`.`rate_per_gram` > `rf`.`rate_fix_rate`),2,1) AS `trans_type`,`rf`.`rate_fix_id` AS `trans_id`,'' AS `gross_wt`,'' AS `net_wt`,'' AS `no_of_pcs`,'' AS `purchase_touch`,'' AS `purewt`,`rc`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,round(abs((((`rc`.`rate_per_gram` - `rf`.`rate_fix_rate`) * `rf`.`rate_fix_wt`) * 1.03)),2) AS `trans_amount`,'' AS `catid`,'' AS `proid`,7 AS `trans_screen_id`,`rc`.`id_metal` AS `id_metal`,'' AS `metal`,`rf`.`rate_fix_rate` AS `rate`,'' AS `narration`,unix_timestamp(`rf`.`rate_fix_created_on`) AS `unixtransdate` from ((`ret_po_rate_fix` `rf` left join `ret_supplier_rate_cut` `rc` on((`rc`.`id_supplier_rate_cut` = `rf`.`id_approval_ratecut`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `rc`.`po_id`)))
WHERE ((`rf`.`rate_fix_type` = 2) and (`rf`.`bill_status` = 1) and (`rc`.`conversion_type` = 1))
GROUP BY `rf`.`rate_fix_id`

UNION ALL

select 1 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 14' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate` from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
WHERE ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 0) and (`ret`.`bill_status` = 1))
GROUP BY `pret`.`pur_ret_itm_id`

UNION ALL

select 1 AS `ledger_type`,`pr`.`product_name` AS `category`,`pr`.`product_name` AS `product`,`ret`.`bill_date` AS `trans_date`,`ret`.`pur_ret_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/return_receipt_acknowladgement/' AS `link`,`ret`.`pur_return_id` AS `print_id`,'type 15' AS `qry_type`,2 AS `trans_type`,`ret`.`pur_return_id` AS `trans_id`,sum(`pret`.`pur_ret_gwt`) AS `gross_wt`,sum(`pret`.`pur_ret_nwt`) AS `net_wt`,sum(`pret`.`pur_ret_pcs`) AS `no_of_pcs`,`pret`.`pur_ret_purchase_touch` AS `purchase_touch`,`pret`.`pur_ret_pur_wt` AS `purewt`,`ret`.`pur_ret_supplier_id` AS `customer_id`,2 AS `trans_rec_type`,sum(ifnull(`pret`.`pur_ret_debit_note_amt`,0)) AS `trans_amount`,`cat`.`id_ret_category` AS `catid`,`pret`.`id_product` AS `proid`,5 AS `trans_screen_id`,`met`.`id_metal` AS `id_metal`,`met`.`metal` AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`ret`.`bill_date`) AS `unixtransdate` from ((((((((`ret_purchase_return_items` `pret` left join `ret_purchase_return` `ret` on((`ret`.`pur_return_id` = `pret`.`pur_ret_id`))) left join `ret_purchase_order_items` `pitm` on((`pitm`.`po_item_id` = `pret`.`pur_ret_po_item_id`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `pitm`.`po_item_po_id`))) left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `ret`.`pur_ret_supplier_id`))) left join `ret_product_master` `pr` on((`pr`.`pro_id` = `pret`.`id_product`))) left join `ret_category` `cat` on((`cat`.`id_ret_category` = `pr`.`cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `cat`.`id_metal`)))
WHERE ((`ret`.`pur_ret_convert_to` = 1) and (`ret`.`purchase_type` = 1) and (`ret`.`bill_status` = 1))
GROUP BY `pret`.`pur_ret_itm_id`

UNION ALL

select 1 AS `ledger_type`,ifnull(`pr`.`product_name`,'PURE') AS `category`,'' AS `product`,`rf`.`date_add` AS `trans_date`,`rf`.`id_supplier_rate_cut` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/supplier_rate_cut/job_receipt/' AS `link`,`rf`.`id_supplier_rate_cut` AS `print_id`,'type 16' AS `qry_type`,1 AS `trans_type`,`rf`.`id_supplier_rate_cut` AS `trans_id`,`rf`.`weight` AS `gross_wt`,`rf`.`weight` AS `net_wt`,'' AS `no_of_pcs`,'100' AS `purchase_touch`,`rf`.`weight` AS `purewt`,`rf`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,`rf`.`amount` AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`rf`.`id_metal` AS `id_metal`,`rf`.`id_metal` AS `metal`,if((`rf`.`conversion_type` = 2),concat(`rf`.`rate_per_gram`,'(Unfix)'),`rf`.`rate_per_gram`) AS `rate`,ifnull(`rf`.`narration`,'') AS `narration`,unix_timestamp(`rf`.`date_add`) AS `unixtransdate` from ((`ret_supplier_rate_cut` `rf` left join `ret_product_master` `pr` on((`pr`.`pro_id` = `rf`.`id_product`))) left join `ret_purchase_order` `po` on((`po`.`po_id` = `rf`.`po_id`)))
WHERE ((`rf`.`rate_cut_type` = 2) and (`rf`.`status` = 1))
GROUP BY `rf`.`id_supplier_rate_cut`

UNION ALL

select 1 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 17' AS `qry_type`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`id_karigar` AS `customer_id`,2 AS `trans_rec_type`,sum(`pay`.`amount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate` from `smith_company_op_balance` `pay` where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`amount` > 0) and (`pay`.`status` = 1))
GROUP BY `pay`.`id_smith_company_op_balance`

UNION ALL

select 1 AS `ledger_type`,'OPENING' AS `category`,'' AS `product`,`pay`.`createdon` AS `trans_date`,`pay`.`id_smith_company_op_balance` AS `referenceno`,'' AS `po_date`,'' AS `po_ref_no`,'' AS `link`,'' AS `print_id`,'type 18' AS `qry_type`,`pay`.`amount_type` AS `trans_type`,`pay`.`id_smith_company_op_balance` AS `trans_id`,ifnull(`pay`.`weight`,0) AS `gross_wt`,ifnull(`pay`.`weight`,0) AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`id_karigar` AS `customer_id`,1 AS `trans_rec_type`,0 AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,ifnull(`pay`.`remarks`,'') AS `narration`,unix_timestamp(`pay`.`createdon`) AS `unixtransdate` from `smith_company_op_balance` `pay` where (((`pay`.`smith_type` = 1) or (`pay`.`smith_type` = 4)) and (`pay`.`stock_type` = 2) and (`pay`.`weight` > 0) and (`pay`.`status` = 1))
GROUP BY `pay`.`id_smith_company_op_balance`

UNION ALL

select 1 AS `ledger_type`,if((`pay`.`transtype` = 1),'Credit Note','Debit Note') AS `category`,'' AS `product`,`pay`.`transdate` AS `trans_date`,`pay`.`transbillno` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/credit_debit_acknolodgement/' AS `link`,`pay`.`crdrid` AS `print_id`,'type 19' AS `qry_type`,`pay`.`transtype` AS `trans_type`,`pay`.`transbillno` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`supid` AS `customer_id`,1 AS `trans_rec_type`,sum(`pay`.`transamount`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,`pay`.`naration` AS `narration`,unix_timestamp(`pay`.`transdate`) AS `unixtransdate` from (((`ret_crdr_note` `pay` left join `ret_purchase_order` `po` on((`po`.`po_id` = `pay`.`po_id`))) left join `ret_purchase_order_items` `order` on((`order`.`po_item_po_id` = `po`.`po_id`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `order`.`po_item_cat_id`)))
WHERE ((`pay`.`accountto` = 1) and (`pay`.`transamount` > 0) and (`pay`.`crdr_status` = 1))
GROUP BY `pay`.`crdrid`

UNION ALL

select 1 AS `ledger_type`,'TDS' AS `category`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 20' AS `qry_type`,2 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tds_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from ((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`grn`.`grn_type` <> 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tds_tax_value` > 0))
GROUP BY `po`.`po_id`

UNION ALL

select 1 AS `ledger_type`,'TCS' AS `category`,'' AS `product`,`po`.`po_date` AS `trans_date`,`po`.`po_ref_no` AS `referenceno`,`po`.`po_date` AS `po_date`,`po`.`po_ref_no` AS `po_ref_no`,'/purchase/job_receipt/' AS `link`,`po`.`po_id` AS `print_id`,'type 21' AS `qry_type`,1 AS `trans_type`,`po`.`po_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`po`.`po_karigar_id` AS `customer_id`,2 AS `trans_rec_type`,sum(`po`.`tcs_tax_value`) AS `trans_amount`,'' AS `catid`,'' AS `proid`,1 AS `trans_screen_id`,'' AS `id_metal`,'' AS `metal`,'' AS `rate`,'' AS `narration`,unix_timestamp(`po`.`po_date`) AS `unixtransdate` from ((`ret_purchase_order` `po` left join `ret_grn_entry` `grn` on((`grn`.`grn_id` = `po`.`po_grn_id`))) left join `ret_karigar` `kr` on((`kr`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`grn`.`grn_type` <> 2) and (`po`.`is_approved` = 1) and (`po`.`bill_status` = 1) and (`po`.`is_suspense_stock` = 0) and (`po`.`tcs_tax_value` > 0))
GROUP BY `po`.`po_id`

UNION ALL

-- SUPPLIER - Common PO Advance (bill_type = 4), qry_type 22.
--
-- BACK-PORTED (SPS-04). Added to the live view by migration 20260807_000003
-- (CPO-09/CPO-13) but never written back into this canonical file, so file and
-- deployed view had silently diverged. scripts/run-migrations.sh PHASE 2 re-applies a
-- canonical view file whenever its checksum changes, so the next edit to this file --
-- such as the PAYMENT branch fix above -- would have re-created the view WITHOUT this
-- branch and dropped every Common PO advance out of the combined ledger. Reproduced
-- verbatim from that migration.
select 1 AS `ledger_type`,'COMMON PO ADVANCE' AS `category`,'' AS `product`,`pay`.`pay_create_on` AS `trans_date`,`pay`.`pay_refno` AS `referenceno`,`co`.`order_date` AS `po_date`,`co`.`pur_no` AS `po_ref_no`,'/supplier_po_payment/paymentacknolodgement/' AS `link`,`pay`.`pay_id` AS `print_id`,'type 22' AS `qry_type`,2 AS `trans_type`,`pay`.`pay_id` AS `trans_id`,0 AS `gross_wt`,0 AS `net_wt`,0 AS `no_of_pcs`,'' AS `purchase_touch`,0 AS `purewt`,`pay`.`pay_sup_id` AS `customer_id`,2 AS `trans_rec_type`,`pay`.`pay_amt` AS `trans_amount`,'' AS `catid`,'' AS `proid`,3 AS `trans_screen_id`,`cate`.`id_metal` AS `id_metal`,'' AS `metal`,'' AS `rate`,concat('Advance against Common PO ',ifnull(`co`.`pur_no`,'')) AS `narration`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`
from (((`ret_po_payment` `pay` left join `customerorder` `co` on((`co`.`id_customerorder` = `pay`.`common_po_ref`))) left join (select `cod`.`id_customerorder` AS `id_customerorder`,min(`cod`.`id_category`) AS `id_category` from `customerorderdetails` `cod` group by `cod`.`id_customerorder`) `cpo` on((`cpo`.`id_customerorder` = `pay`.`common_po_ref`))) left join `ret_category` `cate` on((`cate`.`id_ret_category` = `cpo`.`id_category`)))
WHERE ((`pay`.`pay_status` = 1) and (`pay`.`bill_type` = 4) and (`pay`.`common_po_ref` is not null))
ORDER BY `unixtransdate` desc;
