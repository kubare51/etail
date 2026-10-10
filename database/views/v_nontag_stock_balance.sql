-- =============================================================================
-- VIEW: v_nontag_stock_balance
-- =============================================================================
-- This is the canonical definition. Edit THIS file, then create a migration
-- that references it. See database/views/README.md for workflow.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_nontag_stock_balance` AS select min(`sp`.`id_stock_packet`) AS `id_nontag_item`,`sp`.`branch` AS `branch`,`sp`.`id_section` AS `id_section`,`sp`.`product` AS `product`,`sp`.`design` AS `design`,`sp`.`id_sub_design` AS `id_sub_design`,sum(`sp`.`bal_no_of_piece`) AS `no_of_piece`,sum(`sp`.`bal_gross_wt`) AS `gross_wt`,sum(`sp`.`bal_net_wt`) AS `net_wt`,sum(`sp`.`bal_less_wt`) AS `less_wt`,min(`sp`.`created_by`) AS `created_by`,min(`sp`.`created_on`) AS `created_on`,max(`sp`.`updated_by`) AS `updated_by`,max(`sp`.`updated_on`) AS `updated_on`,(sum((`sp`.`bal_gross_wt` * `sp`.`buy_rate`)) / nullif(sum(`sp`.`bal_gross_wt`),0)) AS `avg_buy_rate`,sum((`sp`.`item_cost` * (`sp`.`bal_gross_wt` / nullif(`sp`.`orig_gross_wt`,0)))) AS `closing_cost`,count(`sp`.`id_stock_packet`) AS `packet_count` from `ret_stock_packet` `sp` where ((`sp`.`status` = 1) and (`sp`.`bal_gross_wt` > 0))
GROUP BY `sp`.`branch`,`sp`.`id_section`,`sp`.`product`,`sp`.`design`,`sp`.`id_sub_design`;
