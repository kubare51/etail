# Business Intelligence Extraction: Current State & Improvements

## What LCA Already Extracts

The LCA indexer runs static analysis across all source code and produces:

| BI Layer | Full Index | Reports Module | What It Captures |
|---|---|---|---|
| **Formulas** | 22,042 | 3,111 | SQL aggregates, PHP arithmetic, JS calculations |
| **Conditionals** | 57,704 | 729 | if/switch branches with variable/value/action |
| **Magic Numbers** | 82,457 | 687 | Hardcoded values in comparisons |
| **Accumulators** | 18,084 | 118 | Loop-based running totals (`$x += $y`) |
| **Call Graph** | 226,430 | 65 | Function-to-function and function-to-table edges |
| **Functions** | 44,634 | 13 | Function signatures with complexity metrics |

### Sample Data Quality

**Formulas** — Captures the math, not the meaning:
```
Cash_flow_model::get_billing_cash:
  total = IFNULL(SUM(p.payment_amount), 0) AS total    ← SQL aggregate
  
Ret_reports_model::getcreditBill:
  $old_metal_amount += $old_items['amount']             ← PHP accumulator
```

**Conditionals** — Captures the branch, not the business rule:
```
$profile_settings['allow_bill_type'] == 2               ← What does 2 mean?
$data['report_type'] == 3                               ← What does 3 mean?
$data['branchtranstype'] == 5                           ← What does 5 mean?
$row->bill_type == 11                                   ← What does 11 mean?
```

**Magic Numbers** — Knows where, not what:
```
42x value=2  → allow_bill_type==2 (means what?)
23x value=3  → report_type==3     (means what?)  
18x value=5  → branchtranstype==5 (means what?)
```

---

## The Gap: Raw Data → Business Rules

LCA extracts **syntax**. Business intelligence requires **semantics**.

```
What LCA sees:    $row->bill_type == 11
What we need:     "bill_type 11 = Branch Transfer Out (excludes from sales reports)"
```

```
What LCA sees:    $total = IFNULL(SUM(p.payment_amount), 0)
What we need:     "Total cash collection = sum of all cash-mode payments for confirmed bills"
```

### Three levels of business intelligence:

| Level | What | LCA Has | We Need |
|---|---|---|---|
| **L1: Code patterns** | Formulas, conditionals, values | ✅ 180K+ items | Already extracted |
| **L2: Business rules** | What these patterns MEAN | ❌ Not interpreted | Rule interpreter |
| **L3: Business flows** | How rules compose into processes | ❌ Not connected | Flow extractor |

---

## Problem #1: LCA BI Data Isn't Queryable via MCP

The MCP `lca_investigate` tool has actions for formulas, conditionals, magic_numbers — but they return **"Re-index with deep_analyze=True"** even though the data exists in the JSON files.

The MCP server loads functions and calls into memory but **skips the BI layers** (formulas, conditionals, magic_numbers, accumulators). This means subagents can't access 180K+ extracted patterns.

> [!CAUTION]
> **Fix**: The MCP server's `load_index` function needs to also load formulas, conditionals, magic_numbers, and accumulators from the JSON. Without this, the entire BI layer is dead.

---

## Proposed Improvements

### Improvement 1: Fix MCP BI Layer (unlock existing data)

Make the 180K extracted patterns queryable:

```
lca_investigate(action="formulas", function_name="get_billing_cash")
→ Returns: [{variable: "total", expression: "SUM(payment_amount)", language: "sql"}]

lca_investigate(action="magic_numbers", file_path="ret_billing_model.php")
→ Returns: [{value: "2", context: "allow_bill_type==2", function: "getBillDetails"}]

lca_investigate(action="conditionals", function_name="save_billing")
→ Returns: [{variable: "$status", value: "1", action: "mark as confirmed"}]
```

**Effort**: Modify `lca_manage(load_index)` to also parse formulas/conditionals/magic_numbers/accumulators from JSON.  
**Impact**: Subagents instantly know all calculations and conditions in any function.

---

### Improvement 2: Magic Number → Business Meaning Map

Build a registry that translates magic numbers to business meanings:

```json
// knowledge_brain/_SYSTEM/BUSINESS_CONSTANTS.json
{
  "bill_type": {
    "1": "Regular Sale",
    "2": "Exchange/Return",
    "3": "Approval Sale",
    "4": "Credit Sale", 
    "5": "Repair Bill",
    "8": "Old Metal Purchase",
    "9": "Cash Receipt",
    "11": "Branch Transfer Out",
    "12": "Branch Transfer In"
  },
  "allow_bill_type": {
    "1": "Billing only",
    "2": "Billing + Estimation",
    "3": "All bill types"
  },
  "report_type": {
    "1": "Summary",
    "2": "Detailed",
    "3": "Old Metal",
    "4": "Exchange"
  },
  "payment_mode": {
    "1": "Cash",
    "2": "Card",
    "3": "UPI",
    "4": "NEFT/RTGS",
    "5": "Cheque"
  }
}
```

**How it helps**: When LCA finds `bill_type == 11`, the agent can look up the business meaning instead of guessing.

**How to build it**: 
1. Extract all unique magic number variables from LCA: `bill_type`, `status`, `payment_mode`, etc.
2. Cross-reference with `TAG_STATUS_MAP.md` and `HARDCODED_VALUES.md` (System Brain)
3. Fill gaps by reading the code's switch/case blocks
4. Store as JSON for instant lookup

---

### Improvement 3: Formula → Business Rule Interpreter

Transform raw formulas into documented business rules:

```
RAW (from LCA):
  function: Ret_billing_model::calculate_bill_total
  formulas:
    - grand_total = sub_total - discount + tax_amount + round_off
    - tax_amount = taxable_value * gst_rate / 100
    - taxable_value = sub_total - discount

INTERPRETED (what we need):
  Rule: BILLING-CALC-001
  Name: "Bill Grand Total Calculation"
  Formula: grand_total = (sub_total - discount) × (1 + gst_rate/100) + round_off
  Where:
    - sub_total = sum of line item amounts
    - discount = customer/scheme discount
    - gst_rate = from product category tax config
    - round_off = nearest rupee adjustment (±0.99)
  Constraints:
    - grand_total >= 0
    - discount <= sub_total
    - round_off between -0.99 and +0.99
  Used by: billing, estimation, credit note, exchange
  Code: ret_billing_model.php:L890-920
```

**How to build**: 
1. Group LCA formulas by function
2. Chain related formulas (output of one = input of another)
3. Resolve variable names against table schemas
4. Add business context from Module Brain
5. Store in structured format

---

### Improvement 4: Business Flow Extractor

Connect individual rules into end-to-end business processes:

```
FLOW: "Billing → Payment → Cashbook"

Step 1: Create Bill (admin_billing/save)
  Rule: BILLING-CALC-001 (calculate total)
  Tables: ret_billing, ret_bill_details
  Status: 0 (draft)

Step 2: Confirm Bill (admin_billing/confirm)  
  Rule: BILLING-STATUS-001 (status 0 → 1)
  Tables: ret_billing.status = 1
  Triggers: stock deduction, GST entry

Step 3: Record Payment (admin_billing/payment)
  Rule: PAYMENT-001 (payment_amount <= grand_total - paid_amount)
  Tables: ret_billing_payment
  Modes: cash(1), card(2), UPI(3)

Step 4: Cashbook Entry (auto)
  Rule: CASHBOOK-001 (cash payments appear in cashbook)
  Tables: (derived from payment query)
  Filter: payment_mode = 1 (cash only)
```

**How to build**:
1. Use LCA call chains to trace controller → model → table sequences
2. Identify state transitions (status changes)
3. Map triggers (what one action causes in another module)
4. Document in `FLOW_CHECKLISTS/` format

---

## Priority and Effort

| # | Improvement | Effort | Impact | Dependencies |
|---|---|---|---|---|
| 1 | **Fix MCP BI layer** | 2-3 hours | 🔴 HIGH — unlocks 180K patterns for all subagents | MCP server code change |
| 2 | **Magic number registry** | 3-4 hours | 🔴 HIGH — instant business meaning for 82K values | TAG_STATUS_MAP.md + code reading |
| 3 | **Formula interpreter** | 6-8 hours | 🟡 MED — chained formulas with business context | Fix #1 done first |
| 4 | **Business flow extractor** | 8-12 hours | 🟡 MED — end-to-end process documentation | Fix #1 + #2 done first |

### Recommended order:
```
Fix #1 (MCP BI) → Fix #2 (Magic Numbers) → Fix #3 (Formulas) → Fix #4 (Flows)
```

Each builds on the previous. Fix #1 alone makes the LCA data accessible. Fix #2 makes the data meaningful.

---

## Open Questions

1. **Where does the MCP server code live?** I need to modify the `load_index` function to also load BI layers. Is it in `logimax-devtools/backend/`?
2. **How complete is `TAG_STATUS_MAP.md`?** If it already maps most status codes, Fix #2 is mostly a reformatting exercise. If not, it's a research project.
3. **Do you want Fix #1 (MCP BI layer) now?** It's the foundation for everything else and unblocks 180K data points for zero ongoing effort.
