-- =============================================================================
-- VEDA-087 — Backfill Sales Transfer Non-Tag OUTWARD stock logs
-- =============================================================================
--
-- REQUIRED, not optional. This bridges the deployment window.
--
-- The accompanying code change moves the status=11 (ST Outward) log row from
-- download-time to dispatch-time. For a transfer that is already dispatched but
-- not yet downloaded when this deploys:
--
--   old dispatch (already happened) -> wrote no outward row
--   new download (happens later)    -> no longer writes one either
--
-- so its outward would be lost permanently, and the sending branch's Stock In &
-- Out and Section-Wise reports would show an inward at the destination with no
-- matching outward at the source. This inserts the missing rows.
--
-- SCOPE — deliberately narrow:
--   bill_type IN (13,14)      ST and SRT only
--   bill_status = 1           cancelled bills excluded
--   download_date IS NULL     downloaded bills already have their row, written
--                             by the old code at download time
--   salesTransType = 2        non-tag lines only
--   bill_date >= 2026-04-01   current financial year only — see below
--   NOT EXISTS status=11      idempotent; re-running inserts nothing
--
-- INSERT-only. No updates, no deletes. On a client with no in-flight non-tag
-- transfers it affects 0 rows.
--
-- WHY THE DATE CUTOFF: rows are stamped with the SENDING branch's bill_date, so
-- backfilling a transfer that has been stuck in-flight since a prior year would
-- change that year's outward figure on a report the client may already have
-- closed their books on. The figure was missing rather than wrong, so it is a
-- correction — but a visible one, landing on every client at once. Deliberate
-- decision: repair the current financial year only. Transfers dispatched before
-- 2026-04-01 and never downloaded keep their existing (missing) outward and are
-- left exactly as they are.
--
-- SAFE TO RE-RUN, twice over:
--   1. run-migrations.sh records applied files in schema_migrations and skips
--      them on later deploys, so this normally executes once per database.
--   2. If it fails partway it is NOT recorded and the whole file is retried on
--      the next deploy — the NOT EXISTS guard is what makes that retry safe.
--      The guard is per-bill, so a partially-logged bill is skipped entirely:
--      it can under-insert, never duplicate.
--
-- Ordering: runs after 20260513_000001 (which adds salesTransType and
-- st_bill_id), guaranteed by the lexicographic filename sort.
-- =============================================================================

-- UP

-- Branch-level outward rows (feeds Stock In & Out v1 / v2)
INSERT INTO ret_nontag_item_log
    (bill_id, product, design, id_sub_design, no_of_piece, gross_wt, net_wt,
     status, from_branch, to_branch, created_by, created_on, `date`)
SELECT
    b.bill_id, bd.product_id, nt.design, nt.id_sub_design,
    bd.piece, bd.gross_wt, bd.net_wt,
    11,                       -- Sales Transfer Outward
    b.id_branch, b.to_branch,
    NULL,                     -- created_by NULL marks a backfilled row
    NOW(),
    DATE(b.bill_date)         -- SENDING branch's business date
FROM ret_bill_details bd
INNER JOIN ret_billing b ON b.bill_id = bd.bill_id
LEFT  JOIN ret_nontag_item nt
       ON nt.st_bill_id = b.bill_id
      AND nt.product    = bd.product_id
WHERE b.bill_type IN (13, 14)
  AND b.bill_status = 1
  AND b.download_date IS NULL
  AND bd.salesTransType = 2
  AND bd.product_id > 0                -- skip lines with no resolvable product (broken SRT non-tag dispatches)
  AND b.bill_date >= '2026-04-01'   -- current financial year only; older stuck transfers are left untouched
  AND NOT EXISTS (
        SELECT 1 FROM ret_nontag_item_log l
        WHERE l.bill_id = b.bill_id AND l.status = 11
      );

-- Section-level outward rows (feeds Section-Wise Stock).
-- from_section comes from the source stock row; if it can no longer be
-- resolved it stays NULL, and that line shows in the branch report but not the
-- section report — the same behaviour as a non-tag item with no section.
INSERT INTO ret_section_nontag_item_log
    (bill_id, product, design, id_sub_design, no_of_piece, gross_wt, net_wt,
     status, from_branch, to_branch, from_section, to_section,
     created_by, created_on, `date`)
SELECT
    b.bill_id, bd.product_id, nt.design, nt.id_sub_design,
    bd.piece, bd.gross_wt, bd.net_wt,
    11,
    b.id_branch, b.to_branch,
    nt.id_section, nt.id_section,
    NULL, NOW(),
    DATE(b.bill_date)
FROM ret_bill_details bd
INNER JOIN ret_billing b ON b.bill_id = bd.bill_id
LEFT  JOIN ret_nontag_item nt
       ON nt.st_bill_id = b.bill_id
      AND nt.product    = bd.product_id
WHERE b.bill_type IN (13, 14)
  AND b.bill_status = 1
  AND b.download_date IS NULL
  AND bd.salesTransType = 2
  AND bd.product_id > 0                -- skip lines with no resolvable product (broken SRT non-tag dispatches)
  AND b.bill_date >= '2026-04-01'   -- current financial year only; older stuck transfers are left untouched
  AND NOT EXISTS (
        SELECT 1 FROM ret_section_nontag_item_log l
        WHERE l.bill_id = b.bill_id AND l.status = 11
      );

-- DOWN

-- Backfilled rows are identified by created_by IS NULL; every row written at
-- runtime carries the operator's uid.
DELETE l FROM ret_nontag_item_log l
INNER JOIN ret_billing b ON b.bill_id = l.bill_id
WHERE l.status = 11 AND l.created_by IS NULL
  AND b.bill_type IN (13, 14) AND b.download_date IS NULL;

DELETE l FROM ret_section_nontag_item_log l
INNER JOIN ret_billing b ON b.bill_id = l.bill_id
WHERE l.status = 11 AND l.created_by IS NULL
  AND b.bill_type IN (13, 14) AND b.download_date IS NULL;
