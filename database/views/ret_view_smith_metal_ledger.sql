-- =============================================================================
-- VIEW: ret_view_smith_metal_ledger
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `ret_view_smith_metal_ledger` AS select `ms`.`met_issue_ref_id` AS `ref_no`,`ms`.`met_issue_karid` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`ms`.`met_issue_date` AS `transdate`,unix_timestamp(`ms`.`met_issue_date`) AS `unixtransdate`,0 AS `purchase_wt`,sum(`msdet`.`issue_metal_wt`) AS `issue_wt`,`retcat`.`name` AS `catname`,`msdet`.`issue_cat_id` AS `cat_id`,`met`.`metal` AS `metal`,`retcat`.`id_metal` AS `metid`,'Material Issue' AS `accType`,0 AS `credit`,sum(`msdet`.`issue_metal_wt`) AS `debit` from ((((`ret_karigar_metal_issue` `ms` left join `ret_karigar_metal_issue_details` `msdet` on((`msdet`.`issue_met_parent_id` = `ms`.`met_issue_id`))) left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `ms`.`met_issue_karid`))) left join `ret_category` `retcat` on((`retcat`.`id_ret_category` = `msdet`.`issue_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `retcat`.`id_metal`)))
WHERE ((`ms`.`bill_status` = 1) and (`msdet`.`issue_metal_wt` > 0))
GROUP BY `ms`.`met_issue_ref_id`,`msdet`.`issue_cat_id`

UNION

select `grn`.`grn_ref_no` AS `ref_no`,`grn`.`grn_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`grn`.`grn_date` AS `transdate`,unix_timestamp(`grn`.`grn_date`) AS `unixtransdate`,sum(`grni`.`grn_net_wt`) AS `purchase_wt`,0 AS `issue_wt`,`retcat`.`name` AS `catname`,`grni`.`grn_item_cat_id` AS `cat_id`,`met`.`metal` AS `metal`,`retcat`.`id_metal` AS `metid`,'Material Receipt' AS `accType`,sum(`grni`.`grn_net_wt`) AS `credit`,0 AS `debit` from ((((`ret_grn_entry` `grn` left join `ret_grn_items` `grni` on((`grni`.`grn_item_grn_id` = `grn`.`grn_id`))) left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `grn`.`grn_karigar_id`))) left join `ret_category` `retcat` on((`retcat`.`id_ret_category` = `grni`.`grn_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `retcat`.`id_metal`)))
WHERE ((`grn`.`grn_type` = 2) and (`grn`.`grn_bill_status` = 1) and (`grni`.`grn_net_wt` > 0))
GROUP BY `grni`.`grn_item_grn_id`,`grni`.`grn_item_cat_id`

UNION

select `grn`.`grn_ref_no` AS `ref_no`,`grn`.`grn_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,`grn`.`grn_date` AS `transdate`,unix_timestamp(`grn`.`grn_date`) AS `unixtransdate`,sum(`grni`.`grn_wastage`) AS `purchase_wt`,0 AS `issue_wt`,'Wastage' AS `catname`,`grni`.`grn_item_cat_id` AS `cat_id`,`met`.`metal` AS `metal`,`retcat`.`id_metal` AS `metid`,'Material Receipt' AS `accType`,sum(`grni`.`grn_wastage`) AS `credit`,0 AS `debit` from ((((`ret_grn_entry` `grn` left join `ret_grn_items` `grni` on((`grni`.`grn_item_grn_id` = `grn`.`grn_id`))) left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `grn`.`grn_karigar_id`))) left join `ret_category` `retcat` on((`retcat`.`id_ret_category` = `grni`.`grn_item_cat_id`))) left join `metal` `met` on((`met`.`id_metal` = `retcat`.`id_metal`)))
WHERE ((`grn`.`grn_type` = 2) and (`grn`.`grn_bill_status` = 1) and (`grni`.`grn_wastage` > 0))
GROUP BY `grni`.`grn_item_grn_id`,`grni`.`grn_item_cat_id`
ORDER BY `unixtransdate`;
