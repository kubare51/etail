# Stock In & Out Report (v1 & v2) — Complete Business Logic

> **Module:** Ret_Reports · **Controllers:** `stock_details_v1()` / `stock_details_v2()`
> **Companion to:** [Stock_InOut_Thermal_Print_Analysis.md](Stock_InOut_Thermal_Print_Analysis.md)
> **Traced:** 2026-07-21, against the current working tree
> **Supersedes:** [RULE-RPT-045](../../knowledge_brain/Ret_Reports/BUSINESS_RULES.md) (2 lines, stale `retail_v5` paths)

This document records what the two Stock In & Out reports actually compute — tables,
filters, status flags, arithmetic, grouping — and the defects found while tracing.
It exists because the thermal-print change request required knowing exactly where
"Number of Pieces" comes from, and no such record existed.

**Line numbers were verified against the working tree on 2026-07-21.** They drift
constantly (the controller is 10,842 lines, the model 40,829) — re-verify before relying on them.

---

## 1. Which is which

| | v1 | v2 |
|---|---|---|
| URL | `admin_ret_reports/stock_details_v1/list` | `admin_ret_reports/stock_details_v2/list` |
| Controller | [admin_ret_reports.php:1307](../../admin/application/controllers/admin_ret_reports.php#L1307) | [:9469](../../admin/application/controllers/admin_ret_reports.php#L9469) |
| Model (tag) | [ret_reports_model.php:16832](../../admin/application/models/ret_reports_model.php#L16832) | [:34754](../../admin/application/models/ret_reports_model.php#L34754) |
| Model (non-tag) | [:17586](../../admin/application/models/ret_reports_model.php#L17586) | [:35311](../../admin/application/models/ret_reports_model.php#L35311) |
| JS | [ret_reports.js:11465](../../admin/assets/js/ret_reports.js#L11465) | [:10675](../../admin/assets/js/ret_reports.js#L10675) |
| View | `stock_details_v1.php` (484 ln, 20 cols) | `stock_details_v2.php` (523 ln, 19 cols) |

### Provenance — v2 is the newer *creation*, v1 is the newer *code*

Commit `aa246da48` (2025-10-11, *"modified in Stock In Out report needed"*) renamed the
original `stock_details.php` → `stock_details_v1.php` and created `stock_details_v2.php`
fresh in the same commit. The release note confirms:

> *"added new stock_in & out report_v2 and renaming the older stock_in & out to
> stock_in & out_v1 version"* — Zoho task 124744000003305502

**But every subsequent fix landed on v1 only:**

| Commit | Date | Change | Applied to |
|---|---|---|---|
| `2bf948336` | 2025-11-11 | pure weight + purity filter | v1 only |
| `86046d63b` | 2026-02-25 | branch select single-choice | both |
| `d77b38d30` | 2026-04-08 | — | v1 only |
| `a2c339f42` | 2026-04-10 | — | v1 only |
| `6978cb41e` | 2026-06-18 | id_metal handling + DataTables fix | v1 only |

v2's last commit is 2026-02-25 — it has missed 8 months of maintenance.
Conversely v2 carries `is_eda` handling and correct `pro.cat_id` aliasing that v1's
purity refactor later broke.

> **⚠ Neither version is a superset of the other.** "Old" and "New" describe creation
> order, not code quality. A fix applied to one is not present in the other.

---

## 2. Request → Response

### 2.1 POST payload (from JS)

```
dt_range, id_branch, id_product, id_metal, id_category, group_by,
id_design, id_sub_design, list_by, prod_group_id
                                    + purity   ← v1 ONLY
```

`from_weight` / `to_weight` are read by the model but **never sent** by the JS —
the weight-range narrowing is permanently inert in both versions.

### 2.2 `list_by` dispatch — [controller:1340-1355](../../admin/application/controllers/admin_ret_reports.php#L1340-L1355)

| Value | Label | Tagged query | Non-tag query |
|---|---|---|---|
| 0 | ALL | ✔ | ✔ |
| 1 | Tag | ✔ | `[]` |
| 2 | Non-tag | `[]` | ✔ |

The four summary helpers (`other_ow`, `showroom_sales`, `branch_outward`,
`issued_stock`) run **unconditionally** at [1362-1368](../../admin/application/controllers/admin_ret_reports.php#L1362-L1368),
regardless of `list_by` — see Defect **C5**.

### 2.3 JSON response

```php
['list' => 3-level nested array (tagged),
 'non_tag_items' => 3-level nested array,
 'profile' => profile row incl. allow_stock_type,
 'stocksummery' => [openpcs, openwt, otherpcs, othergwt, showroomsalepcs,
                    showroomsalegwt, inwardpcs, inwardwt, brainw_pcs, brainw_gwt,
                    lotinw_pcs, lotinw_gwt, outwardpcs, outwardwt, issued_pcs,
                    issued_wt, soldpcs, soldwt, closepcs, closewt,
                    close_purewt   ← v1 only],
 'access' => …]
```

Weights pass through `number_format($x, 2)` server-side, then the JS re-formats to
3 dp — **precision is lost before the client asks for 3 decimals**, so every summary
weight ends in `0`.

---

## 3. Date windows — [model:16950-16960](../../admin/application/models/ret_reports_model.php#L16950-L16960)

```php
$FromDt         = 'Y-m-d' from dt_range[0]
$ToDt           = 'Y-m-d' from dt_range[1]
$op_blc_to_date = FromDt − 1 day
```

| Window | Predicate |
|---|---|
| Opening | `date(m1.date) <= $op_blc_to_date` |
| Period (inward/sold/outward) | `date(m1.date) BETWEEN '$FromDt' AND '$ToDt'` |
| Closing snapshot (`current`) | `date(m1.date) <= '$ToDt'` |

The `date()` cast on both sides means **same-day records are included** at both ends —
no time-of-day truncation problem.

If `dt_range` is empty, `$FromDt`/`$ToDt` are undefined → PHP notice and `BETWEEN '' AND ''`.

---

## 4. Tagged stock — the 15 derived subqueries

Base table `ret_taging t`, joined to `ret_product_master`, `ret_purity`,
`ret_design_master`, `ret_sub_design_master`, `branch`, `ret_category`, `metal`,
`ret_section`, `ret_weight`.

**The outer query has no branch filter** — branch is applied *inside* each subquery
via `m1.to_branch` / `l.from_branch` / `bill.id_branch`.

| Alias | Source | Status / flags | Window | Produces |
|---|---|---|---|---|
| `blc` | `ret_taging_status_log` self-anti-join | latest row, `status IN (0,6)`, approval gate | ≤ FromDt−1 | opening gwt/nwt/piece/purity |
| `blc_dwt` | + `ret_taging_stone`, `ret_stone` | `stone_type = 1` | ≤ FromDt−1 | opening dia wt |
| `INW` | status log | `status = 0`, approval gate | BETWEEN | inward |
| `INW_dwt` | + stone | `status = 0`, `stone_type = 1` | BETWEEN | inward dia wt |
| `s` (sold) | **`ret_bill_details`** + `ret_billing` | `bill_status=1`, `bill_type NOT IN (13,14)`, `item_type=0` | BETWEEN | **`SUM(b.piece)`**, tag gwt/nwt |
| `s_dwt` | + stone | same + `stone_type=1` | BETWEEN | sold dia wt |
| `br_out` | status log | `status IN (2,3,4,5,7,9,10,11,12,15,16,17)`, `from_branch` | BETWEEN | branch outward |
| `br_out_dwt` | + stone | same | BETWEEN | outward dia wt |
| `current` | status log | latest, `status = 0`, **no approval gate** | ≤ ToDt | closing snapshot — **discarded, see C24** |
| `in_trans` | `ret_taging` | **`t.tag_status = 4`** (master, not log) | BETWEEN | in-transit |
| `BRAINW` | status log | `status=0` AND `from_branch IS NOT NULL` | BETWEEN | branch-transfer inward |
| `LOTINW` | status log | `status=0` AND `from_branch IS NULL` | BETWEEN | lot (fresh tagging) inward |
| `pur_ret` | `ret_purchase_return_items` | `return_item_type=2`, `tag_issue_from=1` | BETWEEN | purchase return |
| `stn_pur_ret` | + `ret_stone` | same + `stone_type=1` | BETWEEN | pur-return dia wt |
| `kar_iss` | `ret_karigar_metal_issue_details` | `bill_status=1` | BETWEEN | karigar issue |

`BRAINW` + `LOTINW` partition `INW` by `from_branch IS NULL`.

### Approval-stock gate — [model:17022-17025](../../admin/application/models/ret_reports_model.php#L17022-L17025)

```php
get_ret_settings('appr_stock_incl_in_reports') == 1
    ? "and m1.issuspensestock = 0"     // ← EXCLUDES approval stock
    : ""
```

Two problems: the flag named *"include approval stock"* **excludes** it (**C15**), and
it is applied to only 5 of the 15 subqueries (**C14**). v2 hardcodes it on, ignoring the
setting entirely.

---

## 5. Grouping

| `group_by` | Label | SQL GROUP BY | Level 1 | Level 2 | Leaf label (grid col 0) |
|---|---|---|---|---|---|
| 1 | Product | `tag.product_id` | `metal_name` | `category_name` | `product_name` |
| 2 | Category | `p.cat_id` | `metal_name` | `category_name` | `category_name` |
| 3 | Section & WeightRange | `product_id, id_section, id_weight` | `section_name` | `product_name` | `weight_range` |
| 4 | Product/Design/SubDesign | `product_id, design_id, id_sub_design` | `product_name` | `design_name` | `sub_design_name` |

**The result is a 3-level nested array** — `$stock_detail[level1][level2][] = $row`
([model:17552-17573](../../admin/application/models/ret_reports_model.php#L17552-L17573)).
Any consumer must iterate three deep. This is the single most important structural
fact for anyone writing a new renderer (see Defect **T1**).

Zero-row suppression (`$hasData`) exists **only for group_by = 3**.

---

## 6. Non-tagged stock

Discriminator: `ret_product_master.stock_type = 2`. Movement ledger is
`ret_nontag_item_log`, not `ret_taging_status_log`. **Opening is derived in SQL,
not read from a snapshot** — [model:17768-17775](../../admin/application/models/ret_reports_model.php#L17768-L17775):

```sql
op_blc_pcs = blc_inw.no_of_piece − blc_sales.piece − blc_br_out.no_of_piece − op_blc_metal_issue.piece
br_out_pcs = br_out.no_of_piece + metal_issue.piece + pur_ret.pcs
```

### Tagged vs non-tagged divergence

| Aspect | Tagged | Non-tagged |
|---|---|---|
| Opening | snapshot from `blc` | derived: inward − sales − out − issue |
| Ledger | `ret_taging_status_log` | `ret_nontag_item_log` |
| Sold field name | `sold_pcs/gwt/nwt` | **`sales_pcs/gwt/nwt`** |
| Sold filter | `item_type = 0` | **`is_non_tag = 1`** |
| Outward statuses | 2,3,4,5,**7**,9,10,11,12,**15,16,17** | 2,3,4,5,9,10,11,12 |
| Karigar issue | separate `iss_*` columns | folded into `br_out_*` |
| Purchase return | `return_item_type = 2` | `return_item_type = 3`, folded into `br_out_*` |
| Dia weight | 4 `*_dwt` subqueries | **none** |
| Pure weight | `pure_wt` | **not selected** → `NaN` (**C26**) |
| In-transit | present | absent |
| Approval gate | applied | **never** |
| Purity filter | v1 only | **unsupported** |

---

## 7. ⭐ Where PCS comes from

**This is the answer to Q1 in the thermal-print analysis.**

| Grid column | idx | Expression | Ultimate source |
|---|---|---|---|
| Op Stock | 1 | `op_blc_pcs` | `SUM(ret_taging.piece)` — **stored column**, not `COUNT(tag_id)` |
| I/W Pcs | 5 | `inw_pcs` | `SUM(ret_taging.piece)` |
| O/W Pcs | 9 | `sold / other` (v1) or summed (v2) | `SUM(ret_bill_details.piece)` + `SUM(ret_taging.piece)` + `SUM(pur_ret_pcs)` + `SUM(issue_pcs)` — **four tables** |
| Closing Stock | 13 | **computed in JS** | see below |
| Closing Stock | 19 | `closing_pcs + in_trans_pcs` | + `SUM(tag.piece)` where `tag_status=4` |

### The closing formula lives in JavaScript

[ret_reports.js:11885-11889](../../admin/assets/js/ret_reports.js#L11885-L11889):

```js
closing_pcs = op_blc_pcs + inw_pcs − (sold_pcs + br_out_pcs + ret_pcs + iss_pcs)
closing_gwt = op_blc_gwt + inw_gwt − sold_gwt − br_out_gwt − ret_gwt − iss_gwt
closing_nwt = op_blc_nwt + inw_nwt − sold_nwt − br_out_nwt − ret_nwt − iss_nwt
closing_dwt = op_blc_dwt + inw_dwt − sold_dwt − br_out_dwt − ret_diawt − iss_diawt
```

The SQL **also** computes a `closing_pcs` (`IFNULL(current.piece,0)`, an independent
`≤ ToDt` snapshot) — and the grid **never reads it**. The two are different numbers.
Any server-side consumer that reads `closing_pcs` from the model gets the snapshot,
**not** what the user sees. See Defect **T2**.

### ⚠ The pcs source asymmetry

Opening and inward pcs come from **`ret_taging.piece`** (tag master).
Sold pcs comes from **`ret_bill_details.piece`** (bill line).

Any tag where these differ — partial sale, split, data-entry drift — causes closing
pcs to drift **permanently**. This is the most likely root cause of pcs mismatches
in this report. In v2, sold *pcs* comes from the bill line while sold *weights* come
from the tag master — same mismatch, within a single row.

---

## 8. Summary panel arithmetic — [controller:1434-1495](../../admin/application/controllers/admin_ret_reports.php#L1434-L1495)

```
Opening    = Σ op_blc_pcs / op_blc_gwt
Inward     = 0                                   ← DEAD (C1)
Lot I/W    = Σ lotinw_pcs / lotinw_gwt
Br.Tr I/W  = Σ brainw_pcs / brainw_gwt
Outward    = branch_outward(): log status 3,4,9  +  bill_type 14
Issued     = issued_stock():   log status 7
Showroom   = showroom_sales(): bill_type != 13  +  bill_type = 13
Other O/W  = other_ow()['partly_sold']: SUM(tag.gross_wt − d.gross_wt), is_partial_sale=1
Closing    = Σ (op + inw − sold − br_out)        ← NO ret/iss terms (C2)
Close Pure = Σ pure_wt                           ← actually OPENING pure weight (C3)
```

### `allow_stock_type` profile gate — [js:12386-12421](../../admin/assets/js/ret_reports.js#L12386-L12421)

| Value | Meaning | Visible columns |
|---|---|---|
| 1 | Pcs only | 1, 5, 9, 13, 18, 19 |
| 2 | Weight only | 2,3,4,6,7,8,10,11,12,14,15,16 |
| 3 | All | both sets |

Column 17 (Closing Pur Wt) is gated by **neither** — see **C35**.

---

## 9. Defects Register

Severity: 🔴 wrong numbers shown to users · 🟡 broken feature / silent failure · 🟢 hygiene

### Shared (both versions)

| ID | Sev | Defect |
|---|---|---|
| **C1** | 🔴 | `inwardpcs`/`inwardwt` incremented only inside a `/* */` comment block ([1440-1442](../../admin/application/controllers/admin_ret_reports.php#L1440-L1442)) yet still published and rendered — **the "Inward Stock" summary row is permanently 0** |
| **C2** | 🔴 | Summary closing (`op+inw−sold−br_out`) ≠ grid closing (`…−ret−iss`). The two disagree by the purchase-return + karigar-issue volume |
| **C3** | 🔴 | "Closing Pur Wt" is **opening** pure weight — `blc.purity` is the opening snapshot; no inward/outward purity is ever applied |
| **C4** | 🔴 | Summary counters loop `$list` only — with `list_by=2` the entire summary panel reads 0 against a populated table |
| **C5** | 🟡 | Summary helpers run regardless of `list_by` — non-tag view shows tagged Outward/Issued/Showroom figures |
| **C6** | 🟡 | Outward/Issued overlap: status 7 appears in both the grid's outward column and the summary's separate Issued row |
| **C11** | 🔴 | **No-branch query variant omits `pur_ret`/`stn_pur_ret`/`kar_iss` entirely** → `ret_pcs`/`iss_pcs` undefined → `parseFloat(undefined)` = `NaN` → **closing is NaN when "All branches" is selected** |
| **C12** | 🟡 | group_by=3 without a branch: `weight_range` not selected → col 0 renders `undefined`; GROUP BY references a column not in the projection |
| **C13** | 🔴 | Opening requires `status IN (0,6)`, closing requires `status = 0` — **a period's closing ≠ the next period's opening** wherever status-6 tags exist |
| **C14** | 🟡 | Approval gate applied to 5 of 15 subqueries — turning it on removes approval stock from opening/inward but leaves it in closing/sold |
| **C15** | 🟢 | `appr_stock_incl_in_reports == 1` produces `issuspensestock = 0` — the flag named "include" **excludes** |
| **C16** | 🟡 | `INW_dwt` ON-clause references alias `INW` instead of `INW_dwt` for group_by 3/4 — inward dia wt joined to the wrong derived table |
| **C17** | 🟡 | `iss_diawt` hardcoded to `'0.000'` — karigar-issued diamond weight never computed |
| **C21** | 🟡 | `in_trans` uses `tag_status = 4` from the **master**, not point-in-time — long-standing in-transit tags counted only if they have log activity in range |
| **C22** | 🟡 | Every subquery selects unaggregated columns not in its GROUP BY — **the report errors out entirely under `ONLY_FULL_GROUP_BY`** |
| **C23** | 🔴 | Raw `$_POST` interpolation throughout the SQL — **SQL injection**, violates the CLAUDE.md "never trust `$_POST`" rule |
| **C24** | 🟢 | SQL `closing_pcs`/`gwt`/`nwt` and the whole `current` subquery computed then discarded |
| **C25** | 🟢 | `from_weight`/`to_weight` read by the model, never sent by the JS |
| **C26** | 🟡 | Non-tag `pure_wt` never selected but read by the JS → `NaN` |
| **C28** | 🔴 | Non-tag `op_blc_metal_issue`/`metal_issue`/`pur_ret` use `= $id_branch` not `IN (…)` → **multi-branch non-tag figures are wrong** |
| **C29** | 🟡 | Non-tag `blc_inw` emits an **empty GROUP BY** for group_by=3 → SQL syntax error. Non-tag + Section grouping is broken |
| **C31** | 🟡 | Non-tag outward status set omits 7, 15, 16, 17 — those movements never leave non-tag stock |
| **C32** | 🔴 | Grand-total "Op dia Wt" accumulates `metal_closing_dia_wt` instead of `metal_op_blc_dwt` — **shows closing dia wt in the opening column** |
| **C33** | 🟡 | `nt_metal_outward_pureWt += parseFloat(x).toFixed(3)` — `.toFixed()` returns a **string**, so `+=` concatenates |
| **C35** | 🟡 | Column 17 never gated by `allow_stock_type` — a "Pcs only" profile still sees pure weight |
| **C38** | 🔴 | Every cell wrapped in `x > 0 ? … : ''` — **negative openings and closings render as blank**, hiding exactly the anomalies C11/C13/§7 produce |
| **C40** | 🟢 | Missing `+` before `'</tr>'` in ~9 places — no `</tr>` ever appended; rows close only by browser parser recovery |
| **C41** | 🟢 | Columns 13 and 19 both headed "Closing Stock" with different meanings |
| **C43** | 🟢 | ~1,000 lines of commented-out predecessor code, plus a full parallel copy in `ret_reports_14_07_2025.js` |

### v2-specific

| ID | Sev | Defect |
|---|---|---|
| **V1** | 🔴 | **Closing Net Wt is always `NaN`** — JS reads `item.issue_nwt`; the model emits `iss_nwt`. Poisons every sub-total, metal-total and grand-total. v1 has this fixed; **v2's Closing Net Wt column is silently blank** |
| **V2** | 🔴 | **Opening balance subtracts in-period EDA sales.** `op_blc_pcs = blc.piece − eda_s.piece`, but `eda_s` is filtered on the *reporting period* while `blc` is the *pre-period* snapshot. v2's Opening is lower than v1's by the period's `is_eda=2` volume |
| **V3** | 🔴 | **Sold excludes `is_eda=2` bills** (`s` filtered `is_eda=1`). For a client on `allow_bill_type=2` (EDA-only) v2's Sold is **permanently 0**. Worse, the opening-side compensation exists only in the branch-selected path — with "All branches", EDA sales leave neither opening nor sold, so **closing is overstated by the entire EDA volume** |
| **V4** | 🟡 | Multi-select filters degraded to scalar `=` (category, product, design, sub-design, prod_group) — selecting two categories filters by neither correctly |
| **V5** | 🟡 | `implode(' , ', $data['id_metal'])` without `is_array()` guard — PHP warning + wrong value on scalar POST |
| **V6** | 🟡 | Approval gate hardcoded on — v2 ignores `appr_stock_incl_in_reports` |
| **V7** | 🟡 | DataTable init wrapped in `if (!isDataTable(...))` — **v2 never re-initialises after the first search** |
| **V8** | 🟢 | No zero-row suppression — prints every product row even when all-zero |
| **V9** | 🟢 | No purity filter, no Closing Pur Wt column (19 cols vs v1's 20) |

### v1-specific

| ID | Sev | Defect |
|---|---|---|
| **N1** | 🔴 | The purity refactor rewrote `pur_ret`/`stn_pur_ret`/`kar_iss` to use `p.cat_id` and dropped `wt.id_weight` — but those subqueries alias `ret_product_master` as **`pro`**, not `p`. **group_by=2 (Category) and group_by=3 (Weight range) raise `Unknown column 'p.cat_id'`** and fail outright. v2 is correct here |
| **N2** | 🟡 | Non-tag `blc_inw` GROUP BY is an empty string for group_by=3 (v2 has `"m1.product,p.id_section"`) |
| **N3** | 🟡 | `b.item_type = 0` missing from the no-branch `s` subquery — all-branch sold weights include non-sale line types |
| **N4** | 🟡 | Purity filter dropped from the no-branch outer WHERE — the dropdown silently does nothing when no branch is selected |

---

## 10. Consequences for any new consumer

Anything that renders this report — a thermal slip, an export, an API — must handle:

1. **The 3-level nested array** (§5). A single `foreach` yields arrays, not rows.
2. **Closing is computed in JS, not SQL** (§7). Reading `closing_pcs` from the model
   gives the `current` snapshot, a *different number* from the screen.
3. **`NaN` when no branch is selected** (C11) and **`NaN` for v2 net weight** (V1).
4. **Negative values render blank** (C38) — don't replicate that in a stock-count slip.
5. **Non-tag rows are a separate array** with different field names (`sales_pcs`
   not `sold_pcs`) and no dia/pure weight.
6. **v1 and v2 produce different numbers for the same filters** (V2, V3).

---

## 11. Recommended remediation order

| Priority | Items | Rationale |
|---|---|---|
| P0 | C23 | SQL injection |
| P0 | V1, V2, V3 | v2 shows wrong numbers today |
| P0 | N1 | v1 group_by 2 & 3 fail outright |
| P1 | C11, C13, C28, C32 | wrong numbers under common filter combinations |
| P1 | C1, C2, C3, C4 | summary panel contradicts the grid |
| P2 | C14–C17, C21, C26, C29, C31, C38 | silent under-reporting |
| P3 | C24, C25, C40, C41, C43, V8 | hygiene |
| — | **Consolidate v1 + v2** | ~2,100 duplicated lines with ~40 divergences is the root cause of most of the above |
