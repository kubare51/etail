-- =============================================================================
-- VIEW: ret_view_grn_pay_ledger
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `ret_view_grn_pay_ledger` AS select `grn`.`grn_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,'Purchase' AS `accType`,0 AS `credit`,sum(`grn`.`grn_purchase_amt`) AS `debit` from (`ret_grn_entry` `grn` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `grn`.`grn_karigar_id`)))
WHERE ((`grn`.`grn_bill_status` = 1) and (`grn`.`grn_purchase_amt` > 0))
GROUP BY `grn`.`grn_karigar_id`

UNION

select `po`.`po_karigar_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,'Purchase' AS `accType`,0 AS `credit`,sum(`po`.`tot_purchase_amt`) AS `debit` from (`ret_purchase_order` `po` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `po`.`po_karigar_id`)))
WHERE ((`po`.`is_suspense_stock` = 1) and (`po`.`isratefixed` = 0) and (`po`.`tot_purchase_amt` > 0))
GROUP BY `po`.`po_karigar_id`

UNION

select `po`.`pay_sup_id` AS `sup_id`,`retkar`.`firstname` AS `sup_name`,'Payment' AS `accType`,sum(`po`.`pay_amt`) AS `credit`,0 AS `debit` from (`ret_po_payment` `po` left join `ret_karigar` `retkar` on((`retkar`.`id_karigar` = `po`.`pay_sup_id`)))
WHERE ((`po`.`pay_status` = 1) and (`po`.`pay_amt` > 0))
GROUP BY `po`.`pay_sup_id`;
