-- =============================================================================
-- VIEW: vw_nontag_stock_compat
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `vw_nontag_stock_compat` AS select min(`sp`.`id_stock_packet`) AS `id_nontag_item`,`sp`.`branch` AS `branch`,`sp`.`id_section` AS `id_section`,`sp`.`product` AS `product`,`sp`.`design` AS `design`,`sp`.`id_sub_design` AS `id_sub_design`,cast(sum(`sp`.`bal_no_of_piece`) as signed) AS `no_of_piece`,cast(sum(`sp`.`bal_gross_wt`) as decimal(14,3)) AS `gross_wt`,cast(sum(`sp`.`bal_net_wt`) as decimal(14,3)) AS `net_wt`,cast(sum(`sp`.`bal_less_wt`) as decimal(14,4)) AS `less_wt`,min(`sp`.`created_by`) AS `created_by`,min(`sp`.`created_on`) AS `created_on`,max(`sp`.`updated_by`) AS `updated_by`,max(`sp`.`updated_on`) AS `updated_on` from `ret_stock_packet` `sp` where ((`sp`.`status` = 1) and ((`sp`.`bal_gross_wt` > 0) or (`sp`.`bal_no_of_piece` > 0)))
GROUP BY `sp`.`branch`,`sp`.`product`,`sp`.`design`,`sp`.`id_sub_design`,`sp`.`id_section`;
