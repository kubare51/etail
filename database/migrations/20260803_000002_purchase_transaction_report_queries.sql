-- ============================================================
-- Reference: Purchase Transaction Report — SQL queries
-- Date: 2026-08-03
-- Description: This is NOT a schema migration (nothing to run
--              as UP/DOWN). It is a copy of every SQL query
--              used by the new Purchase Transaction Report so
--              they exist in this folder alongside the menu
--              migration (20260803_000001_...).
--
--              The live, authoritative copy of these queries is
--              in PHP at:
--                admin/application/models/ret_reports_model.php
--                  - getPurchaseTransactionReport($filter)
--                  - get_purchaseTransDetailed($bill_id)
--                  - getFilteredStates()
--                  - get_branch_ids_by_state($state_id)
--
--              Each query below is written in its base form
--              (equivalent to every filter left on "All"). The
--              optional filter fragments the PHP appends are
--              included underneath as commented blocks, exactly
--              as they appear in the model, so nothing from the
--              implementation is missing from this file.
-- ============================================================


-- ------------------------------------------------------------
-- Query 1: Main report list (getPurchaseTransactionReport)
-- Reads Old Gold / Sales Return / Partly Sold transfer bills
-- (ret_billing.bill_type IN (13,14), ret_bill_details.salesTransType
-- IN (3,4,5)) with per-bill aggregated pieces/weights/category
-- and Received vs In-Transit status (b.download_date).
-- ------------------------------------------------------------
SELECT
	b.bill_id, b.bill_no,
	date_format(b.bill_date,'%d-%m-%Y') as created_date,
	date_format(b.download_date,'%d-%m-%Y') as dwnload_date,
	b.download_date,
	fb.name as from_branch, tb.name as to_branch,
	IFNULL(item.pieces,0) as pieces, IFNULL(item.grs_wt,0) as grs_wt, IFNULL(item.net_wt,0) as net_wt,
	IFNULL(item.cat_short_code,'') as cat_short_code, IFNULL(item.dia_wt,0) as dia_wt,
	emp.firstname as dwld_emp
FROM ret_billing b
LEFT JOIN branch fb ON fb.id_branch = b.from_branch
LEFT JOIN branch tb ON tb.id_branch = b.to_branch
LEFT JOIN employee emp ON emp.id_employee = b.download_by
LEFT JOIN (
	SELECT dt.bill_id,
		SUM(IFNULL(dt.piece,1)) as pieces,
		SUM(IFNULL(dt.gross_wt,0)) as grs_wt,
		SUM(IFNULL(dt.net_wt,0)) as net_wt,
		SUM(IFNULL(stn.dia_wt,0)) as dia_wt,
		GROUP_CONCAT(DISTINCT CASE WHEN dt.salesTransType = 3 THEN 'OG' ELSE cat.cat_code END) as cat_short_code
	FROM ret_bill_details dt
	LEFT JOIN ret_taging tag ON tag.tag_id = dt.tag_id
	LEFT JOIN ret_product_master pro ON pro.pro_id = dt.product_id
	LEFT JOIN ret_category cat ON cat.id_ret_category = pro.cat_id
	LEFT JOIN (
		SELECT st.tag_id, SUM(st.wt) as dia_wt
		FROM ret_taging_stone st
		LEFT JOIN ret_stone s ON s.stone_id = st.stone_id
		WHERE s.stone_type = 1
		GROUP BY st.tag_id
	) stn ON stn.tag_id = dt.tag_id
	WHERE dt.salesTransType IN (3,4,5)
	GROUP BY dt.bill_id
) item ON item.bill_id = b.bill_id
WHERE b.bill_type IN (13,14) AND b.bill_status = 1
AND EXISTS (SELECT 1 FROM ret_bill_details dtf2 WHERE dtf2.bill_id = b.bill_id AND dtf2.salesTransType IN (3,4,5))
-- Optional filter fragments appended by getPurchaseTransactionReport() below.
-- Only added when the corresponding UI filter is set to something other than "All":

-- Branch filter (Select Branch, or resolved from State Filter via get_branch_ids_by_state):
--   Select Type = Inward  (st_type = 1): AND b.to_branch   IN ($branch)
--   Select Type = Outward (st_type = 2, default): AND b.from_branch IN ($branch)

-- Date filter (Date Group By):
--   Date Group By = Download Date: AND b.download_date >= '$FromDt 00:00:00' AND b.download_date <= '$ToDt 23:59:59'
--   Date Group By = Created Date (default): AND b.bill_date     >= '$FromDt 00:00:00' AND b.bill_date     <= '$ToDt 23:59:59'

-- Status filter (new — In-Transit / Received):
--   status_filter = 2 (In-Transit): AND b.download_date IS NULL
--   status_filter = 4 (Received):   AND b.download_date IS NOT NULL

-- Report Type filter (Purchase=1 / Purchase Return=2 / Sales Return=3 / Old metal=4):
--   report_type = 1 (Purchase):        AND b.bill_type = 13
--   report_type = 2 (Purchase Return): AND b.bill_type = 14
--   report_type = 3 (Sales Return):    AND EXISTS (SELECT 1 FROM ret_bill_details dtf WHERE dtf.bill_id = b.bill_id AND dtf.salesTransType = 4)
--   report_type = 4 (Old metal):       AND EXISTS (SELECT 1 FROM ret_bill_details dtf WHERE dtf.bill_id = b.bill_id AND dtf.salesTransType = 3)

-- Select Metal filter (single or multi-select id_metal, applies to Sales Return/Partly Sold lines):
--   AND EXISTS (
--       SELECT 1 FROM ret_bill_details dt_m
--       LEFT JOIN ret_product_master pro_m ON pro_m.pro_id = dt_m.product_id
--       LEFT JOIN ret_category cat_m ON cat_m.id_ret_category = pro_m.cat_id
--       WHERE dt_m.bill_id = b.bill_id AND dt_m.salesTransType IN (4,5)
--       AND cat_m.id_metal IN ($metal_ids)
--   )
ORDER BY b.bill_date DESC;


-- ------------------------------------------------------------
-- Query 2: Row drill-down (get_purchaseTransDetailed)
-- Per-bill item breakdown shown when a report row is expanded.
-- Replace 0 below with the bill_id being drilled into.
-- ------------------------------------------------------------
SELECT dt.bill_det_id, dt.salesTransType,
	CASE dt.salesTransType WHEN 3 THEN 'Old Gold' WHEN 4 THEN 'Sales Return' WHEN 5 THEN 'Partly Sold' ELSE 'Other' END as item_type_name,
	IFNULL(tag.tag_code,'-') as tag_code,
	IFNULL(pro.product_name, 'OLD GOLD') as product_name,
	IFNULL(dt.piece,1) as piece,
	IFNULL(dt.gross_wt,0) as gross_wt,
	IFNULL(dt.net_wt,0) as net_wt,
	IFNULL(stn.dia_wt,0) as dia_wt
FROM ret_bill_details dt
LEFT JOIN ret_taging tag ON tag.tag_id = dt.tag_id
LEFT JOIN ret_product_master pro ON pro.pro_id = dt.product_id
LEFT JOIN (
	SELECT st.tag_id, SUM(st.wt) as dia_wt
	FROM ret_taging_stone st
	LEFT JOIN ret_stone s ON s.stone_id = st.stone_id
	WHERE s.stone_type = 1
	GROUP BY st.tag_id
) stn ON stn.tag_id = dt.tag_id
WHERE dt.bill_id = 0 /* :bill_id */ AND dt.salesTransType IN (3,4,5);


-- ------------------------------------------------------------
-- Query 3: State Filter dropdown options (getFilteredStates)
-- Only states that have at least one branch assigned.
-- ------------------------------------------------------------
SELECT DISTINCT s.id_state, s.name
FROM state s
INNER JOIN branch b ON b.id_state = s.id_state
ORDER BY s.name;


-- ------------------------------------------------------------
-- Query 4: Resolve a selected State Filter to its branch ids
-- (get_branch_ids_by_state) — used to build the branch filter
-- when the user picks a State instead of a specific Branch.
-- Replace 0 below with the selected id_state.
-- ------------------------------------------------------------
SELECT id_branch
FROM branch
WHERE id_state = 0 /* :state_id */;
