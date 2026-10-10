# Ret_Reports — Business Rules

> **Module**: Ret_Reports
> **Last Updated**: 2026-06-10 — LCA + Antigravity deep extraction (all 4 phases)
> **Method**: LCA structural index (1,225 functions) + Antigravity source deep-dive
> **Total Rules**: 50

---

## Overview

Ret_Reports is primarily a read-only module. Business rules cover **data aggregation formulas**, **weight/amount calculation logic**, **payment mode classification**, **stone type routing**, **display formatting**, and **access control**. The module has 641 JS functions, 353 model functions, and 231 controller functions.

---

## Phase 0 — Core Rules (Enhanced from RPT-001 to RPT-013)

---

### RULE-RPT-001: Access Control per Report
**Formula**: Every report checks user permissions before returning data.
**Implementation**: Controller AJAX handlers → `$this->admin_settings_model->get_access('admin_ret_reports/{method}/list')`
**Source**: Every `case 'ajax':` block in [admin_ret_reports.php](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php)
**Validation**: Server-side only. Access object passed in JSON response — JS decides button visibility.
**Edge cases**:
- If `get_access` fails silently, all buttons become visible (no deny-by-default)
- Access strings are hardcoded per method (e.g., `admin_ret_reports/branch_vault_report/list`)
- No client-side enforcement — a user could forge AJAX calls to bypass

---

### RULE-RPT-002: Cash Deposit Opening/Closing Calculation
**Formula**:
```
opening = previous_closing (first record = 0)
previous_deposited = dep_cur_balance - dep_amount
cash_inward = total_cash_payments - previous_deposited - closing
closing = opening + cash_inward - dep_amount
```
**Implementation**: Controller [deposit_report() L7113-L7229](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L7113-L7229)
**Data sources**:
- `type == 1` → billing_payment + iss_rcp_payment (billing + issue/receipt cash)
- `type == 2` → chit_payment only
- `total_cash_pay = billing_payment + iss_rcp_payment + chit_pay`
**Validation**: Server-side sequential loop. Each deposit builds on previous closing.
**Edge cases**:
- First deposit always has `opening = 0`
- Negative `closing` is possible if deposit > available cash
- `cash_inward` can go negative if `previous_deposited + closing > total_cash_pay`
- Output formatted with `moneyFormatIndia()` + `number_format()` — double formatting layer

---

### RULE-RPT-003: Day Transaction Payment Mode Aggregation
**Formula**:
```
total_receipts = cash + card + paytm + cheque + net_banking + cashfree
total_payments = cash + card + paytm + cheque + net_banking + cashfree
```
**Implementation**: Controller [daytransactions() L6949-L6968](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L6949-L6968)
**Dispatch**: `reporttype == 1` → Receipts bucket, `reporttype == 2` → Payments bucket
**Validation**: Server-side only. Model returns rows tagged with `reporttype`.
**Edge cases**:
- **6 fixed payment modes** — any new mode NOT in this list gets silently dropped from totals
- Both receipt and payment totals use identical mode names but separate accumulators
- Output formatted with `moneyFormatIndia()` which casts to `(float)` first

---

### RULE-RPT-004: Indian Money Format
**Formula**: INR formatting with commas: `XX,XX,XXX.XX`
**Implementation**: 
- PHP: Controller `moneyFormatIndia()` method
- JS: Global `money_format_india()` function used in all DataTable renderers
- JS: `toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})` for source summary
**Validation**: Display-only formatting
**Edge cases**:
- Negative numbers NOT handled explicitly in PHP version
- JS version wraps in `parseFloat()` first — safe from NaN for valid inputs
- Weight values use `.toFixed(3)`, amounts use `.toFixed(2)`

---

### RULE-RPT-005: Stock Book Closing Balance (Tag Section)
**Formula**:
```
closing_gwt  = op_blc_gwt + inw_gwt - sold_gwt - br_out_gwt - sect_out_gwt - ret_gwt - iss_gwt
closing_nwt  = op_blc_nwt + inw_nwt - sold_nwt - br_out_nwt - sect_out_nwt - ret_nwt - iss_nwt
closing_diawt = op_blc_diawt + inw_diawt - sold_diawt - br_out_diawt - sect_out_diawt - ret_diawt - iss_diawt
closing_grmwt = op_grm_blc_wt + grm_inw_wt - sold_grm_wt - grm_br_out_wt - grm_sect_out_wt - grm_ret_wt - grm_iss_wt
closing_ctwt  = op_ct_blc_wt + ct_inw_wt - sold_ct_wt - ct_br_out_wt - ct_sect_out_wt - ct_ret_wt - ct_iss_wt
```
**Implementation**: Controller [ho_daily_stock_book() L9144-L9148](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9144-L9148)
**Data sources**: 10+ model methods called sequentially (L9012-L9022):
- `get_headoffice_valut_report()` — HO vault (from PO model)
- `get_retagging_details()` — BT vault (from PO model)
- `get_section_wise_stock_inout_details()` — tag items
- `get_SectionTag_InwardOutward_Details()` — inward/outward (param: 1=inward, 2=outward)
- `get_nontag_section_details()` — non-tag items
- `get_SectionNonTag_InwardOutward_Details()` — non-tag inward/outward
- `getLotwiseTaggedVault()` — profit/loss
- `get_PurchaseReturnItems()` — purchase returns
- `get_MetalIssueItems()` — karigar metal issue
**Validation**: Server-side. 4-level nested `foreach` (section → product → design → item).
**Edge cases**:
- **5 independent weight dimensions** tracked separately
- Missing any movement type → zeros it → inaccurate closing
- 7 deduction types per weight — any new deduction type requires formula update

---

### RULE-RPT-006: Stock Book Stone Type Classification
**Formula**:
```
stone_type == 0                    → Ornaments (accumulate gross_wt, net_wt, diawt)
stone_type == 1 AND uom != 6      → Stone in Grams (accumulate grm_wt)
stone_type == 1 AND uom == 6      → Stone in Carats (accumulate ct_wt)
else (default)                     → Diamond (accumulate diawt only)
```
**Implementation**: Controller [ho_daily_stock_book() L9155-L9169](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9155-L9169) (inward), [L9173-L9188](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9173-L9188) (outward)
**Validation**: Server-side if/else chain
**Edge cases**:
- Same classification applied to both inward and outward sections
- `stone_type == 2` explicitly falls to Diamond
- **New stone_type values (3, 4, etc.) silently fall to Diamond bucket** — silent misclassification
- `uom == 6` is a magic number for "Carats" — not documented as a constant

---

### RULE-RPT-007: Green Tag Marking/Unmarking
**Formula**:
```
Marking   (req_status == 1): tag_mark=1, green_tag_date=now(), green_tag_marked_by=user_id
Unmarking (req_status == 0): tag_mark=0, green_tag_date=NULL, green_tag_marked_by=NULL,
                              unmark_by=user_id, unmark_date=now()
```
**Implementation**: Controller [update_green_tag() L183-L240](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L183-L240)
**Validation**: Server-side with transaction wrapping
**Edge cases**: Uses `$tag['req_status']` outside the loop at L225 — references last iteration's value. Bug if processing mixed mark/unmark batch.

---

### RULE-RPT-008: Lot Profit/Loss Calculation
**Formula**:
```
balance = lot_weight - tag_weight - receipt_weight - lot_merge_weight

if balance < 0 → Profit (negate and accumulate — more consumed than received)
if balance > 0 → Loss (accumulate directly — metal unaccounted for)
```
Applied independently to: gross_wt, net_wt, dia_wt
**Implementation**: Controller [ho_daily_stock_book() L9270-L9280](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9270-L9280)
**Validation**: Server-side loop over lot data from `getLotwiseTaggedVault()`
**Edge cases**:
- **Counter-intuitive sign convention**: negative balance = profit, positive = loss
- `profit -= (balance < 0 ? balance : 0)` → double negation makes positive
- Three weight types calculated independently — P&L can differ across dimensions

---

### RULE-RPT-009: Reorder Level Detection
**Formula**: `reorder_needed = (current_stock < reorder_min)`
**Implementation**: Model `get_reorder_details()` — based on `ret_reorder_settings` table
**Configuration**: Per product/design/branch, with `reorder_based_on` field determining the metric
**Validation**: Server-side query-based
**Edge cases**: If not configured for a product/branch combo, item never triggers reorder.

---

### RULE-RPT-010: Stock Age Calculation
**Formula**: `stock_age_days = DATEDIFF(current_date, tag_entry_date)`
**Buckets**: 0-30, 31-60, 61-90, 91-180, 181-365, 365+ days
**Implementation**: Model `getStockAgeDetails()`, `getDynamicStockAgeDetails()` L28568-L28920
**Validation**: Server-side SQL DATEDIFF
**Edge cases**: Uses tag entry date, NOT purchase date. Re-tagged items reset their age to 0.

---

### RULE-RPT-011: Inventory Turnover Ratio
**Formula**:
```
Average_Stock = (Opening_Weight + Closing_Weight) / 2
Turnover = Total_Sold_Weight / Average_Stock
```
**Implementation**: Model [get_inventory_turnover_details() L27825-L28525](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L27825-L28525) (700 LOC)
**LCA Risk**: HIGH — uses `date_create`, `date_format`, `diff`, `is_array`, raw SQL
**Validation**: Server-side. Division by zero handled with SQL IFNULL/COALESCE.
**Edge cases**: `diff()` call computes date range for period-based averaging.

---

### RULE-RPT-012: GST Abstract Calculation
**Formula**:
```
CGST = taxable_amount × cgst_rate / 100
SGST = taxable_amount × sgst_rate / 100
IGST = taxable_amount × igst_rate / 100
Total_Tax = CGST + SGST + IGST
```
**Implementation**: 
- Model `get_gst_abstract_details_v1()` L35323-L35930 (607 LOC, 50 downstream calls)
- Model `get_gst_abstract_details_bills()` L35934-L36353 (419 LOC)
**Params**: `$data, $billingType` — billingType drives different GST grouping
**Validation**: Server-side SQL aggregation
**Edge cases**: Inter-state vs intra-state determines IGST vs CGST+SGST split. Heavy date manipulation with 8+ `date()`/`strtotime()` calls.

---

### RULE-RPT-013: Section Stock Closing (Non-Tag)
**Formula**:
```
closing_gwt = op_blc_gwt + inw_gwt - sales_gwt - br_out_gwt - pur_ret_gwt - kar_iss_gwt
closing_nwt = op_blc_nwt + inw_nwt - sales_nwt - br_out_nwt - kar_iss_nwt   ← MISSING pur_ret_nwt!
```
**Implementation**: Controller [ho_daily_stock_book() L9214-L9215](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9214-L9215)
**⚠ KNOWN BUG**: `pur_ret_nwt` subtracted from gross closing but NOT from net closing. Creates discrepancy when purchase returns have different gross vs net weights (common for stone items).
**Edge cases**: 4-level nested foreach same as tag section. Non-tag items don't track diamond/stone weight separately.

---

## Phase 1 — Financial Report Calculations

---

### RULE-RPT-014: HO Vault Opening/Closing Aggregation
**Formula**:
```
HO Vault:   opening = SUM(blc_gwt, blc_nwt, blc_diawt)
            inward  = SUM(inw_gwt, inw_nwt, inw_diawt)
            closing = SUM(closing_gwt, closing_nwt, closing_diawt)

BT Vault:   opening = SUM(op_blc_gwt, op_blc_nwt, op_blc_diawt)
            inward  = SUM(inw_gwt, inw_nwt, inw_diawt)
            pocket  = SUM(pkt_gwt, pkt_nwt, pkt_diawt)     ← BT vault only
            closing = SUM(closing_gwt, closing_nwt, closing_diawt)
```
**Implementation**: Controller [ho_daily_stock_book() L9044-L9104](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9044-L9104)
**Cross-module**: Calls `ret_purchase_order_model::get_headoffice_valut_report()` and `get_retagging_details()`
**Edge cases**: BT vault has "pocket" metric that HO vault doesn't — asymmetric aggregation.

---

### RULE-RPT-015: Metal Issue Classification
**Formula**:
```
stone_type != 2 → Metal issue: gross_wt AND net_wt = issue_wt (same value for both!)
stone_type == 2 → Diamond issue: dia_wt = issue_wt only
```
**Implementation**: Controller [ho_daily_stock_book() L9246-L9252](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9246-L9252)
**Edge cases**: For non-diamond metal issues, gross = net (identical value). Breaks if items have stone deductions where gross ≠ net.

---

### RULE-RPT-016: Incentive Balance Calculation
**Formula**:
```
balance = total_incentive - total_given
total_given = SUM(payment_log entries for employee + period + branch)
```
**Implementation**: Controller [employee_sales_referal_and_sales_return() L8748-L8768](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L8748-L8768)
**Dispatch**:
- `select_view == 2` → switches to `ret_emp_incentive_model`
- `id_report_type == 1` → Detailed report (grouped by employee name)
- `id_report_type != 1` → Summary report (with balance calculation)
**Edge cases**: `total_given` fetched per employee per period — partial payments tracked via `save_payment_log`.

---

### RULE-RPT-017: Receipts vs Payments Ledger Dispatch
**Formula**:
```
reqledger_type == 1  → getReceiptsAllLedger()   (money coming in)
reqledger_type != 1  → getAllPaymentLedger()     (money going out)
```
**Implementation**: Controller [receipts_ledger_statement() L8899-L8902](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L8899-L8902)
**Edge cases**: Any value other than `1` routes to payments — no explicit validation. `reqledger_type = 0`, `3`, `'abc'` all map to payments.

---

### RULE-RPT-018: Cash Abstract — Sales Taxable Amount
**Formula**:
```
taxable_amount = item_cost - item_total_tax
sales_rate = sales_rate / piece
avg_rate = taxable_amount / net_wt
rate_diff = avg_rate - sales_rate
```
**Implementation**: JS [set_cashabstract_table() L4700-L4704](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L4700-L4704)
**Grouping**: 
- `report_type == 2` → Category-wise with sub-totals per category
- `report_type == 1` → Grouped by `group_type` (1=category_name, else=metal_name)
**Edge cases**: 
- `sales_mode == 2` → shows avg_rate column
- `sales_mode != 2` → shows `taxable_amount - bill_discount` instead

---

### RULE-RPT-019: Cash Abstract — Grand Total Formula
**Formula**:
```
TOTAL = sales_amt + credit_amount + adv_amt + gen_adv_amt + advance_deposit_amt 
        + handling_charges + repair_order_delivered.amount
        - purchase_amt - sales_return_amt - due_amount - adv_refund_amt - other_expense_amount
```
**Implementation**: JS [set_cashabstract_table() L5389](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L5389)
**Components** (20+ data buckets):
- item_details, old_matel_details, return_details, payment_details
- advance_adjusted, due_details, chit_details, credit_details
- wallet_adjusted, advance_detals, general_adv_details, general_pay
- order_adj, home_bill, voucher_details, partly_sale
- repair_order_delivered, general_credit_collection
- other_expense, advance_deposit, chit_credit_collection
- eda_sales_abstract, eda_purchase_abstract
**Edge cases**: `round_off_amt` parsed but NOT included in grand total formula.

---

### RULE-RPT-020: Cash Abstract — Source-wise Summary
**Formula**:
```
summary_total = offline_total + online_total + admin_app_total
```
Mode-wise breakdown per source (offline/online/admin_app).
**Implementation**: JS [getCashAbstractSourceSummary() L4383-L4480](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L4383-L4480)
**API**: Calls `admin_reports/payment_summary_modewise` (different controller!)
**Edge cases**:
- Null/undefined totals defaulted to 0 via ternary
- `parseFloat()` applied to all values before arithmetic
- `toLocaleString('en-IN')` for display — different from `money_format_india()`

---

### RULE-RPT-021: Cash Abstract — Payment Mode Classification
**Formula**:
```
'Cash'       → cash_payment
'CC' or 'DC' → card_payment    ⚠ OPERATOR PRECEDENCE BUG
'CHQ'        → cheque_payment (type 1=credit, type 2=debit with sign flip)
'NB'         → online_payment
nb_type 1    → RTGS
nb_type 2    → IMPS
nb_type 3    → NEFT
nb_type 4    → UPI
```
**Implementation**: JS [set_cashabstract_table() L5402-L5419](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L5402-L5419)
**⚠ BUG**: `item.payment_mode == 'CC' || item.payment_mode == 'DC' && item.payment_amount != null` — JS operator precedence makes `&&` bind tighter than `||`, so CC payments bypass the null check. Should be wrapped in parentheses.
**Edge cases**:
- Cheque has credit/debit split: `type == 1` adds, `type == 2` subtracts
- `nb_type` provides further granularity within Net Banking

---

### RULE-RPT-022: Cash Abstract — Advance Receipt Aggregation
**Formula**:
```
advance_receipt = adv_amt + gen_adv_amt
where:
  adv_amt = SUM(advance_details.advance_amount)   (order-linked advances)
  gen_adv_amt = SUM(general_adv_details.amount)    (general advances)
```
**Implementation**: JS [set_cashabstract_table() L5153-L5164, L4657-L4664](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L5153-L5164)
**Edge cases**: Weight-based advance amount (`received_weight * rate_per_gram`) is commented out in both blocks. If re-enabled, would add to advance totals.

---

### RULE-RPT-023: Partly Sold Balance Calculation
**Formula**:
```
pending_pieces = actual_pieces - sold_pieces
pending_gwt = actual_gross_wt - sold_gross_wt    (.toFixed(3))
pending_nwt = actual_net_wt - sold_net_wt        (.toFixed(3))
```
**Implementation**: JS [set_partlysold_table() L4212-L4224](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L4212-L4224)
**DOM**: `#partlysold_list` DataTable with drill-down to bill-level partial details
**Edge cases**: Pieces use `parseFloat()` without `.toFixed()` — can show floating point artifacts. Weights correctly use `.toFixed(3)`.

---

## Phase 2 — Stock & Inventory Reports

---

### RULE-RPT-024: Branch Transfer Weight Routing
**Formula**:
```
if stone_type != 2 → grs_wt, net_wt accumulate normally; dia_wt from dia_wt field
if stone_type == 2 → grs_wt goes to dia_wt bucket; grs_wt and net_wt show as 0.000
```
**Implementation**: JS [set_btreport_table() L6011-L6025](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L6011-L6025)
**Direction logic**:
- `transtype == 1` → from_branch = selected branch
- `transtype == 2` → to_branch = selected branch
- `else` → from_branch = selected branch
**Edge cases**: `branch_transfer_type == 6` switches to "other inventory" view with completely different column structure (no weight columns).

---

### RULE-RPT-025: Old Metal Purchase — Report Type Dispatch
**Formula**:
```
oldmetal_report_type == 1 → Summary report (summary_report div)
oldmetal_report_type == 2 → Detailed report (detailed_report div)
oldmetal_report_type == 3 → Bill-wise report (bill_wise_report div)
oldmetal_report_type == 4 → Employee-wise report (emp_wise_report div)
```
**Implementation**: JS [set_old_metal_table() L6340-L6464](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L6340-L6464)
**Employee-wise sub-calculations**:
```
less_wt = dust_wt + allstotwt
stone_wt_display = allstotwt - sttotwt
diamond_wt = sttotwt
```
**Edge cases**: `refund_amt` displayed as negated value `(refund_amt * -1)`, only shown for first item per bill.

---

### RULE-RPT-026: Old Metal Purchase Weight Components
**Formula** (per item in detailed view):
```
gross_wt → total weight as received
stone_wt → non-diamond stone weight (allstotwt - sttotwt)
dia_wt → diamond weight (sttotwt)
dust_wt → impurity weight
pure_wt → weight after touch/purity applied
wast_wt → wastage weight (based on wastage_percent)
net_wt → final payable weight
```
**Implementation**: JS [set_old_metal_table() L6507-L6534](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L6507-L6534)
**Edge cases**: All fields defaulted to `0` via `|| 0` — prevents NaN but masks missing data silently.

---

### RULE-RPT-027: Lot-wise Sold & Pending
**Formula**:
```
Columns: lot_no, karigar_name, no_of_pcs, total_gwt, sold_pcs, sold_gwt, pending_pcs, pending_gwt
```
All weight values formatted with `money_format_india()`.
**Implementation**: JS [set_lotwise_table() L3915-L4019](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L3915-L4019)
**Edge cases**: Endpoint → `admin_ret_reports/lot_wise/ajax`. Model calculates pending = total - sold.

---

### RULE-RPT-028: Cash Abstract — Purchase (Old Metal) Aggregation
**Formula**:
```
per item:
  sales_rate = rate_per_grm / tot_pur
  avg_rate = amount / net_wt
  rate_diff = avg_rate - sales_rate

totals:
  tot_pur_gwt += gross_wt
  tot_pur_nwt += net_wt
  tot_pur_dia_wt += dia_wt
  tot_pur_amt += amount
```
**Implementation**: JS [set_cashabstract_table() L4956-L4986](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L4956-L4986)
**Edge cases**: `rate_diff` can be negative (avg rate < sales rate = potential loss on purchase).

---

## Phase 3 — Transaction Reports & Model SQL

---

### RULE-RPT-029: Cash Abstract — Sales Return Aggregation
**Formula**:
```
per item:
  taxable_amount = item_cost - item_total_tax
  sales_rate = sales_rate / piece
  avg_rate = taxable_amount / net_wt
  rate_diff = avg_rate - sales_rate

totals accumulate: amt, gwt, wt, diawt, less_wt, tax, discount, pcs, taxable_amt, collected_mc
```
**Implementation**: JS [set_cashabstract_table() L4795-L4893](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L4795-L4893)
**Edge cases**: Inner accumulators reset to 0 after each category block (L4888-L4893). Display mirrors sales section structure.

---

### RULE-RPT-030: Branch Vault Report (Model — 1,514 LOC)
**Implementation**: Model [branch_vault_report() L29377-L30891](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L29377-L30891)
**LCA Risk**: HIGH — uses raw SQL `query()` calls, `date_create()` + `date_format()`
**Params**: `$data` array with date range and metal type filters
**Cross-module**: Called by controller `branch_vault_report()` which passes `$_POST` directly

---

### RULE-RPT-031: Day Transactions Report (Model — 1,107 LOC)
**Implementation**: Model [day_transactions_report() L18229-L19336](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L18229-L19336)
**LCA Risk**: HIGH — 4 raw `query()` calls
**Business rule**: Splits transactions by receipt/payment mode (cash, card, paytm, cheque, net_banking, cashfree)
**Cross-ref**: RPT-003 covers the controller aggregation; this is the model-side SQL.

---

### RULE-RPT-032: Section-wise Stock In/Out (3 versioned copies)
**Implementation**:
- `get_section_wise_stock_inout_details()` L22671-L23709 (1,038 LOC) — **CURRENT**
- `get_section_wise_stock_inout_details_15_07_24()` L21821-L22669 (848 LOC)
- `get_section_stock_inout_details_16_10_2023()` L20950-L21373 (423 LOC)
**⚠ ANTI-PATTERN**: 3 near-identical functions (2,309 LOC total). Bug fix in one must be manually replicated.

---

### RULE-RPT-033: Category-wise Day Transaction (Model — 704 LOC)
**Implementation**: Model [get_categorywise_day_transaction() L32445-L33149](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L32445-L33149)
**Dependencies**: `get_profile_settings()`, `userdata()` — branch-aware, user-context-aware
**Uses raw SQL** (`query()` + `result_array()`)

---

### RULE-RPT-034: GST Abstract v1 (Model — 607 LOC)
**Implementation**: Model [get_gst_abstract_details_v1() L35323-L35930](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L35323-L35930)
**LCA Analysis**: 50 downstream calls — heavy date manipulation + raw SQL
**Params**: `$data, $billingType` — billingType drives different grouping

---

### RULE-RPT-035: Wastage-wise P&L (Model — 294 LOC)
**Formula**: Calculates profit/loss per tag based on wastage (making charges vs actual weight used)
**Implementation**: Model [get_wastagewisepandlreport() L8823-L9117](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L8823-L9117)
**Dependencies**: `get_profile_settings()`, `userdata()`, raw SQL

---

### RULE-RPT-036: Cash Book Details (Model — 303 LOC)
**Implementation**: Model [get_cash_book_details() L32078-L32381](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L32078-L32381)
**Dependencies**: `get_profile_settings()`, `userdata()` × 2, date manipulation
**LCA Calls**: 28 downstream calls detected

---

### RULE-RPT-037: Metal Stock Details (Model — 431 LOC)
**Implementation**: Model [get_metal_stock_details() L7580-L8011](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L7580-L8011)
**Dependencies**: `getBranchDayClosingData()`, `date_create()`, `date_format()`, raw SQL
**LCA Calls**: 127 downstream calls — highest fan-out of any reports model function

---

### RULE-RPT-038: Bill Details (Model — 547 LOC)
**Implementation**: Model [getBillDetails() L593-L1140](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L593-L1140)
**LCA Calls**: 188 downstream calls (highest call count)
**Dependencies**: `get_profile_settings()`, `userdata()`, 6× `post()` calls — reads multiple form inputs

---

## Phase 4 — Controller Logic & Remaining

---

### RULE-RPT-039: HO Daily Stock Book — Aggregate Response Structure
**Formula**: The response assembles 15+ sub-objects into a single JSON:
```
hovault_open/inward/closing (3 weights each)
btvault_open/inward/pocket/closing (3 weights each + pocket)
sect_tag_open/inward/outward/closing (5 weights each)
sect_nontag_open/inward/outward/closing (2 weights each)
purchase_return (3 weights)
metal_issue (3 weights)
profit/loss (3 weights each)
```
All formatted with `number_format($value, 3, '.', '')`.
**Implementation**: Controller [ho_daily_stock_book() L8982-L9087](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L8982-L9087) (401 LOC!)
**⚠ ANTI-PATTERN**: Business logic in controller — entire 400-LOC calculation should be in model.

---

### RULE-RPT-040: Cash Abstract — Home Bill Display
**Formula**: Displayed with pieces, gross_wt, item_cost. No tax or net weight breakdown.
**Implementation**: JS [set_cashabstract_table() L5004-L5037](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L5004-L5037)
**Edge cases**: Only shown when `home_bill_details.length > 0`. Not included in grand total calculation.

---

### RULE-RPT-041: Cash Abstract — EDA (Electronic Document Authentication) Sales/Purchase
**Formula**: Separate sections for EDA sales and EDA purchases with metal, pieces, grosswt, netwt, collectedamt/purchaseamt.
**Implementation**: JS [set_cashabstract_table() L5075-L5144](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L5075-L5144)
**Edge cases**: EDA amounts NOT included in the grand total formula (RPT-019). Displayed as information only.

---

### RULE-RPT-042: Cash Abstract — Chit Payment
**Formula**: `chit_payment` aggregated from `payment_details` where `payment_mode == 'CHQ'` AND is a chit type.
**Implementation**: JS within payment_details loop
**Edge cases**: Chit payments use a separate accumulator but share the cheque mode code — potential confusion.

---

### RULE-RPT-043: Cash Abstract — General Payment Aggregation
**Formula**:
```
gen_cash_payment, gen_card_payment, gen_cheque_payment, gen_online_payment
gen_cheque_payment_pay (outgoing), gen_cheque_payment_rec (incoming)
gen_online_payment subtypes: RTGS, IMPS, NEFT, UPI
```
**Implementation**: JS L4615-L4631
**Edge cases**: Separate accumulators for general issue payments vs billing payments.

---

### RULE-RPT-044: Day Transaction — Controller Pre-processing
**Formula**:
```
Date range from dt_range: split('-'), str_replace('/', '-'), date_create(), date_format('Y-m-d')
```
**Implementation**: Controller [daytransactions() L6776-L6943](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L6776-L6943)
**Edge cases**: `dt_range != ''` check — empty string bypasses date parsing entirely.

---

### RULE-RPT-045: Stock Details v1/v2 — Controller Split
**Implementation**:
- [stock_details_v1() L1268-L1528](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L1268-L1528) (260 LOC)
- [stock_details_v2() L9161-L9415](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9161-L9415) (254 LOC)
**v1 vs v2**: Different model methods called, different view templates. v2 is the newer version but v1 still active.

---

### RULE-RPT-046: GST R1 Report
**Implementation**: Controller [gst_r1() L9504-L9638](file:///d:/XAMPP/htdocs/retail_v5/admin/application/controllers/admin_ret_reports.php#L9504-L9638) (134 LOC)
**Purpose**: Generates GST R1 return data for filing

---

### RULE-RPT-047: Category-wise BT Report (3 versioned copies)
**Implementation**:
- `get_categorywise_bt_report()` L14232-L14539 (307 LOC) — **CURRENT**
- `get_categorywise_bt_report_on01082022()` L14540-L14888 (348 LOC)
- `get_categorywise_bt_report_23072022()` L15168-L15903 (735 LOC)
**⚠ ANTI-PATTERN**: Same as RPT-032 — date-versioned code clones.

---

### RULE-RPT-048: Category-wise Book Stocks (Model — 764 LOC)
**Implementation**: Model [get_categorywise_bookstocks_report() L26867-L27631](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L26867-L27631)
**LCA Risk**: HIGH — one of the largest model functions

---

### RULE-RPT-049: Category-wise Stock Report (Model — 717 LOC)
**Implementation**: Model [get_categorywise_stock_report() L19337-L20054](file:///d:/XAMPP/htdocs/retail_v5/admin/application/models/ret_reports_model.php#L19337-L20054)

---

### RULE-RPT-050: Detailed Day Transaction Report (JS — 7,198 LOC)
**Implementation**: JS [get_Detailed_Daytransaction_report() L51703-L58901](file:///d:/XAMPP/htdocs/retail_v5/admin/assets/js/ret_reports.js#L51703-L58901)
**LCA Stats**: 38 AJAX calls, largest single function in the codebase
**⚠ CRITICAL ANTI-PATTERN**: 7,198-line single function. Impossible to maintain, test, or debug.

---

## Cross-Module Dependency Map

| Direction | Count | Key Connections |
|---|---|---|
| **Outbound** (Reports → Other) | 4,980 | `ret_purchase_order_model` (vault), `admin_settings_model` (access), `ret_emp_incentive_model` (incentive), `ret_catalog_model` (deposits, products) |
| **Inbound** (Other → Reports) | 703 | `admin_ret_dashboard_api` (widgets), JS cross-module AJAX (estimation, billing, tagging) |

---

## Known Bugs & Anti-Patterns

| ID | Type | Description | Rule Ref |
|---|---|---|---|
| BUG-1 | Calculation | Missing `pur_ret_nwt` in non-tag closing formula | RPT-013 |
| BUG-2 | Operator precedence | CC payment null check bypassed due to `\|\|` vs `&&` priority | RPT-021 |
| AP-1 | Code clone | 3 copies of `get_section_wise_stock_inout_details` (2,309 LOC) | RPT-032 |
| AP-2 | Code clone | 3 copies of `get_categorywise_bt_report` (1,390 LOC) | RPT-047 |
| AP-3 | Monster function | 7,198-line JS function | RPT-050 |
| AP-4 | Business logic in controller | 400-LOC stock calculation in controller | RPT-039 |
| AP-5 | Silent data exclusion | EDA amounts not in grand total | RPT-041 |
| AP-6 | Missing formatting | `round_off_amt` parsed but not included in total | RPT-019 |
