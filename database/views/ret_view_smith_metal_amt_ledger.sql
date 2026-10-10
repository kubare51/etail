-- =============================================================================
-- VIEW: ret_view_smith_metal_amt_ledger
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `ret_view_smith_metal_amt_ledger` AS select `ms`.`met_issue_ref_id` AS `ref_no`,`ms`.`met_issue_karid` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`ms`.`met_issue_date` AS `transdate`,unix_timestamp(`ms`.`met_issue_date`) AS `unixtransdate`,0 AS `purchase_wt`,sum(`msdet`.`issue_metal_pur_wt`) AS `issue_wt`,'Material Issue' AS `accType`,0 AS `met_credit`,sum(`msdet`.`issue_metal_pur_wt`) AS `met_debit`,0 AS `amt_credit`,0 AS `amt_debit` from ((((`ret_karigar_metal_issue` `ms` left join `ret_karigar_metal_issue_details` `msdet` on((`msdet`.`issue_met_parent_id` = `ms`.`met_issue_id`))) left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `ms`.`met_issue_karid`))) left join `ret_category` `retcat` on((`retcat`.`id_ret_category` = `msdet`.`issue_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `retcat`.`id_metal`)))
WHERE (`msdet`.`issue_metal_pur_wt` > 0)
GROUP BY `ms`.`met_issue_ref_id`,`msdet`.`issue_cat_id`

UNION

select `grn`.`grn_ref_no` AS `ref_no`,`grn`.`grn_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`grn`.`grn_date` AS `transdate`,unix_timestamp(`grn`.`grn_date`) AS `unixtransdate`,sum(`grni`.`grn_net_wt`) AS `purchase_wt`,0 AS `issue_wt`,'Material Receipt' AS `accType`,sum(`grni`.`grn_net_wt`) AS `met_credit`,0 AS `met_debit`,0 AS `amt_credit`,0 AS `amt_debit` from ((((`ret_grn_entry` `grn` left join `ret_grn_items` `grni` on((`grni`.`grn_item_grn_id` = `grn`.`grn_id`))) left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `grn`.`grn_karigar_id`))) left join `ret_category` `retcat` on((`retcat`.`id_ret_category` = `grni`.`grn_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `retcat`.`id_metal`)))
WHERE ((`grn`.`grn_type` = 2) and (`grni`.`grn_net_wt` > 0))
GROUP BY `grni`.`grn_item_grn_id`,`grni`.`grn_item_cat_id`

UNION

select `grn`.`grn_ref_no` AS `ref_no`,`grn`.`grn_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`grn`.`grn_date` AS `transdate`,unix_timestamp(`grn`.`grn_date`) AS `unixtransdate`,sum(`grni`.`grn_wastage`) AS `purchase_wt`,0 AS `issue_wt`,'Material Receipt' AS `accType`,sum(`grni`.`grn_wastage`) AS `met_credit`,0 AS `met_debit`,0 AS `amt_credit`,0 AS `amt_debit` from ((((`ret_grn_entry` `grn` left join `ret_grn_items` `grni` on((`grni`.`grn_item_grn_id` = `grn`.`grn_id`))) left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `grn`.`grn_karigar_id`))) left join `ret_category` `retcat` on((`retcat`.`id_ret_category` = `grni`.`grn_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `retcat`.`id_metal`)))
WHERE ((`grn`.`grn_type` = 2) and (`grni`.`grn_wastage` > 0))
GROUP BY `grni`.`grn_item_grn_id`,`grni`.`grn_item_cat_id`

UNION

select `po`.`grn_ref_no` AS `ref_no`,`po`.`grn_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`po`.`grn_date` AS `transdate`,unix_timestamp(`po`.`grn_date`) AS `unixtransdate`,0 AS `purchase_wt`,0 AS `issue_wt`,'GRN Entry' AS `accType`,0 AS `met_credit`,0 AS `met_debit`,`po`.`grn_purchase_amt` AS `amt_credit`,0 AS `amt_debit` from (`ret_grn_entry` `po` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `po`.`grn_karigar_id`)))
WHERE ((`po`.`grn_type` <> 2) and (`po`.`grn_bill_status` = 1) and (`po`.`grn_purchase_amt` > 0))

UNION

select `po`.`po_ref_no` AS `ref_no`,`po`.`po_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`po`.`po_date` AS `transdate`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`,0 AS `purchase_wt`,0 AS `issue_wt`,'Supplier Bill Entry' AS `accType`,`po`.`tot_purchase_wt` AS `met_credit`,0 AS `met_debit`,0 AS `amt_credit`,0 AS `amt_debit` from (`ret_purchase_order` `po` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`po`.`is_suspense_stock` = 1) and (`po`.`isratefixed` = 0) and (`po`.`tot_purchase_amt` > 0))

UNION

select `po`.`po_ref_no` AS `ref_no`,`po`.`po_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`po`.`po_date` AS `transdate`,unix_timestamp(`po`.`po_date`) AS `unixtransdate`,0 AS `purchase_wt`,0 AS `issue_wt`,'Supplier Bill Entry' AS `accType`,0 AS `met_credit`,0 AS `met_debit`,`po`.`tot_purchase_amt` AS `amt_credit`,0 AS `amt_debit` from (`ret_purchase_order` `po` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`po`.`is_suspense_stock` = 1) and (`po`.`isratefixed` = 0) and (`po`.`tot_purchase_amt` > 0))

UNION

select `pay`.`pay_refno` AS `ref_no`,`pay`.`pay_sup_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`pay`.`pay_create_on` AS `transdate`,unix_timestamp(`pay`.`pay_create_on`) AS `unixtransdate`,0 AS `purchase_wt`,0 AS `issue_wt`,'PO Payment' AS `accType`,0 AS `met_credit`,0 AS `met_debit`,0 AS `amt_credit`,`pay`.`pay_amt` AS `amt_debit` from (`ret_po_payment` `pay` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `pay`.`pay_sup_id`)))
WHERE (`pay`.`pay_status` = 1)

UNION

select `bill`.`bill_no` AS `ref_no`,`bill`.`bill_cus_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`bill`.`bill_date` AS `transdate`,unix_timestamp(`bill`.`bill_date`) AS `unixtransdate`,0 AS `purchase_wt`,0 AS `issue_wt`,'Billing Payment' AS `accType`,0 AS `met_credit`,0 AS `met_debit`,0 AS `amt_credit`,`bill`.`tot_bill_amount` AS `amt_debit` from (`ret_billing` `bill` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `bill`.`bill_cus_id`)))
WHERE ((`bill`.`bill_status` = 1) and (`bill`.`billing_for` = 3) and (`bill`.`tot_bill_amount` > 0))
GROUP BY `bill`.`bill_id`

UNION

select `ret`.`pur_ret_ref_no` AS `ref_no`,`ret`.`pur_ret_supplier_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,ifnull(`ret`.`bill_date`,'') AS `transdate`,ifnull(unix_timestamp(`ret`.`bill_date`),'') AS `unixtransdate`,0 AS `purchase_wt`,0 AS `issue_wt`,'Purchase Return' AS `accType`,0 AS `met_credit`,0 AS `met_debit`,0 AS `amt_credit`,`ret`.`return_total_cost` AS `amt_debit` from (`ret_purchase_return` `ret` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `ret`.`pur_ret_supplier_id`)))
WHERE ((`ret`.`bill_status` = 1) and (`ret`.`return_total_cost` > 0))
ORDER BY `unixtransdate`;
