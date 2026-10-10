# Purchase Module — Supplier Bill Entry: Other Charges Added Twice in Taxable Amount — Test Case Report

> **Scope:** `admin_ret_purchase/purchase/purchase_add` (Supplier Bill Entry), item-row taxable amount calculation for `grn_type == 1`.
> **Related files:** `admin/assets/js/ret_purchase_order.js` (item cost / taxable amount calc), `admin/application/views/ret_purchase/pur_entry_form.php`
> **Related prior bug:** [[BUG_PUR-CLT02]] (Tax Calculation Mismatch Between GRN and Supplier Bill Entry — Hallmark Charges). This double-add regression was introduced by that fix: the "add other charges after GST" line was duplicated instead of moved, so `other_charges_amount` was added to `item_cost` twice.
> **Date:** 2026-07-23
> **Status:** Fix applied (removed duplicate addition at `ret_purchase_order.js` ~line 20682-20687), `node --check` clean. Manual UI verification pending — mark Pass/Fail per row.

---

## TC-01 — Other Charges added exactly once to Taxable Amount (reported scenario)

| Field | Detail |
|---|---|
| **Precondition** | Supplier Bill Entry (`purchase_add`), GRN type = 1 (Supplier Bill), an ornament product (e.g. Silver Ring) with no per-item GST slab that would otherwise complicate the math. |
| **Steps** | 1. Add an item: Gross/Pure Weight = 100.000 gm, Rate = ₹200/gm. 2. Set Other Charges = ₹515.00 via the Other Charges modal. 3. Observe `#item_total_taxable`. |
| **Expected Result** | Taxable Amount = (100 × 200) + 515 = **₹20,515.00**. |
| **Prior Behavior** | Taxable Amount displayed **₹21,030.00** — ₹515 more than expected, i.e. Other Charges added twice (20,515 + 515). |
| **Fix** | Removed the duplicated `item_cost = parseFloat(parseFloat(item_cost) + parseFloat(other_charges_amount)).toFixed(2);` line (the second copy, labeled `// PUR-CLT02 FIX`) in the `grn_type == 1` branch of the item cost calculation. |
| **Pass/Fail** | ☐ |

---

## TC-02 — Zero Other Charges unaffected

| Field | Detail |
|---|---|
| **Precondition** | Same as TC-01 but Other Charges left at ₹0 / not added. |
| **Steps** | Add item: 100.000 gm × ₹200/gm, no other charges. |
| **Expected Result** | Taxable Amount = ₹20,000.00 (unchanged by the fix, since `0 + 0 = 0` either way — confirms no regression for the no-other-charges case). |
| **Pass/Fail** | ☐ |

---

## TC-03 — Other Charges with their own GST (matches GRN — no cascading tax)

| Field | Detail |
|---|---|
| **Precondition** | Reproduces the original [[BUG_PUR-CLT02]] numeric example: 40g × ₹15,000/gm ornament, Hallmark/Other Charges = ₹300 with 18% tax (₹54). |
| **Steps** | 1. Create the matching GRN first; note GRN's Taxable Amount (₹600,000), product GST (₹18,000), Other Charges (₹300), Other Charges Tax (₹54), Final Price (₹618,354). 2. Open the corresponding Supplier Bill Entry for the same GRN. |
| **Expected Result** | Supplier Bill: Taxable Amount = ₹600,000 (matches GRN, other charges added once — not the ₹600,300/₹600,354-style inflated base from either the original cascading-tax bug or this double-add regression). Product GST = ₹18,000. Final Price = ₹618,354, matching GRN exactly. |
| **Prior Behavior (double-add regression)** | Other Charges portion of the final total would be inflated by an extra ₹300 (charges added twice), on top of whatever tax-ordering behavior TC-03 is otherwise checking. |
| **Pass/Fail** | ☐ |

---

## TC-04 — Multiple item rows, Other Charges only added once per row

| Field | Detail |
|---|---|
| **Precondition** | Supplier Bill Entry with 2+ item rows, each with different Other Charges values (e.g. Row 1 = ₹515, Row 2 = ₹200). |
| **Steps** | Add both items, inspect each row's individual `item_total_taxable` before adding to the item table, then check the summary `total_summary_taxable_amt`. |
| **Expected Result** | Each row's taxable amount reflects its own Other Charges added exactly once. Summary total = sum of correctly-computed row taxable amounts (no per-row doubling compounding into the summary). |
| **Pass/Fail** | ☐ |

---

## TC-05 — Job Work Receipt (GRN type 2/4) unaffected

| Field | Detail |
|---|---|
| **Precondition** | Purchase entry with GRN type = 2 (Job Work Receipt) or 4 (JB), with Other Charges set. |
| **Steps** | Add an item with Other Charges, observe taxable amount. |
| **Expected Result** | Other Charges added exactly once (this branch, ~line 21025-21027, was already single-add and was **not** modified by this fix — included here to confirm no regression was introduced by only touching the `grn_type == 1` branch). |
| **Pass/Fail** | ☐ |

---

## How to run

1. `node --check admin/assets/js/ret_purchase_order.js` — currently passes.
2. Execute TC-01 through TC-05 in the browser against Supplier Bill Entry (`admin_ret_purchase/purchase/purchase_add`) and, for TC-03/TC-05, the matching GRN/Job Work Receipt screens.
3. Cross-check `#item_total_taxable`, `.total_summary_taxable_amt`, and final payable amount against manual calculation: `(Weight × Rate) + Other Charges` for the taxable base.
4. Log Pass/Fail per row above; file any failures as new `BUG_PUR-*` entries in `bug_report_AI/purchase/` per the module's existing convention.
