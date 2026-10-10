# `Billing_v2` Module Brain

> **Brain Version:** 1.0 (First build — 2026-07-02)
> **Controller:** `admin/application/controllers/admin_ret_billing.php`
> **Secondary:** `admin/application/controllers/admin_invoice_builder.php`
> **Model:** `admin/application/models/ret_billing_model.php`
> **Brain Source Files:** WIRING.md, RISKS.md, CROSS_MODULE.md, DATA_FLOW.md, STATUS_CODES.md, REVIEW_TRACKER.md
> **Status:** ⭐⭐⭐⭐ FULL BRAIN

---

## 1. Module Scale

| Metric | Value |
|---|---|
| Primary controller | `admin_ret_billing.php` |
| Primary controller lines | **13,599** |
| Secondary controller | `admin_invoice_builder.php` (170 lines, 13 methods) |
| Model | `ret_billing_model.php` |
| Public methods | **141** (primary) + **13** (secondary) |
| Largest single method | `billing()` — **5,177 lines** ⚠️ MEGA |
| Tables written (billing() alone) | **49 tables** |
| Tables owned | **71** |
| Shared tables from other modules | **57** |
| External AJAX controllers called | **9** |
| External AJAX endpoints called | **66** |
| External model dependencies | **219** |
| Duplicate method definitions | **6 pairs** (PHP uses last definition — first is dead code) |
| JS files | 3 (billing.js, billing_new.js, billing_return.js) |
| Risk level | 🔴 **CRITICAL** |

---

## 2. What This Module Does (Business View)

Billing_v2 is the **core transaction engine** of the eTail retail system. It handles every money-in and money-out event at the jewelry counter:

| Operation | Method | LOC |
|---|---|---|
| Create a sale bill (all types) | `billing()` | 5,177 |
| Issue a receipt (advance/balance) | `issue()` | 897 |
| Collect receipt / advance | `receipt()` | 1,050 |
| Cancel a bill | `cancel_bill()` | 448 |
| Service bill (repair/service) | `service_bill()` | 371 |
| Edit payment mode | `paymentmode_edit()` | 313 |
| Bill number formatting | `bill_number_format()` | 578 |
| Custom invoice template design | `designer()` (invoice_builder) | — |

### Bill Types Supported (inside `billing()`)

| Type | Description |
|---|---|
| `sale` | Standard retail sale — tagged items |
| `return` | Return of previously sold items |
| `exchange` | Return + re-purchase in one transaction |
| `credit` | Credit bill — payment deferred |
| `advance` | Advance payment against future order |
| `service` | Service/repair bill |
| `supplier_sale` | Supplier-side consignment sale |
| `home_section` | Home section (catalogue) sale |
| `pos` | POS terminal bill |
| `nontag` | Non-tagged/loose items sale |

---

## 3. Controller Method Index (Key Methods)

| Method | Line | LOC | Key Tables | Traits |
|---|---|---|---|---|
| `__construct()` | 17 | 48 | — | — |
| `billing()` | 229 | **5,177** | 49 tables (see below) | TXN, JSON, OTP, ⚠️MEGA |
| `issue()` | 6024 | 897 | ret_issue_receipt, ret_advance_refund, ret_wallet_transcation | TXN, JSON, ⚠️MEGA |
| `receipt()` | 7096 | **1,050** | 14 tables | TXN, JSON, ⚠️MEGA |
| `cancel_bill()` | 8217 | 448 | 13 tables | TXN, JSON, ⚠️LARGE |
| `service_bill()` | — | 371 | 3 tables | TXN, JSON, OTP |
| `bill_number_format()` | — | 578 | 1 table | TXN |
| `paymentmode_edit()` | — | 313 | 2 tables | TXN |
| `sendotp()` | 5760 | 66 | otp | TXN, OTP |
| `billing_invoice()` | 5876 | 94 | — | PRINT |
| `getEstimationDetails()` | 5511 | 29 | — | JSON |
| `get_scheme_accounts()` | 5625 | 19 | — | JSON |
| `get_advance_details()` | 5648 | 13 | — | JSON |
| `getBillDetails()` | 5663 | 23 | — | JSON |
| `createNewCustomer()` | 5406 | 34 | — | JSON |

### Tables Written by `billing()` (49 total)

`ret_billing`, `ret_bill_details`, `ret_billing_item_stones`, `ret_billing_payment`,
`ret_billing_chit_utilization`, `ret_billing_advance`, `ret_bill_return_details`,
`ret_billing_gift_voucher_details`, `ret_bill_delivery`, `ret_bill_other_metals`,
`ret_bill_old_metal_sale_details`, `ret_bill_other_charges`, `ret_bill_supplier_sales_details`,
`ret_nontag_item_log`, `ret_section_nontag_item_log`, `ret_home_section_item_log`,
`ret_home_section_item`, `ret_taging_status_log`, `ret_section_tag_status_log`,
`ret_partlysold`, `ret_advance_utilized`, `ret_advance_refund`, `ret_estimation`,
`ret_estimation_items`, `ret_estimation_item_stones`, `ret_estimation_item_other_materials`,
`ret_estimation_old_metal_sale_details`, `ret_est_chit_utilization`, `ret_est_gift_voucher_details`,
`ret_other_inventory_purchase_items_log`, `ret_other_inventory_purchase_items_details`,
`ret_other_invnetory_issue`, `ret_purchase_items_log`, `payment`, `payment_mode_details`,
`payment_old_metal`, `wallet_account`, `ret_wallet`, `ret_wallet_transcation`,
`wallet_transaction`, `ret_insurance`, `ret_issue_receipt`, `ret_issue_rcpt_payment`,
`customerorder`, `customerorderdetails`, `scheme_account`, `gift_card`,
`ret_pos_requests`, `customer_delivery_address`

---

## 4. Cross-Module Dependencies

### Outbound AJAX (JS calls other controllers)

| Controller | Endpoint Count | Key Endpoints |
|---|---|---|
| `admin_ret_estimation` | 21 | `estimation()`, `get_customer()`, `get_employee()`, `get_metal_purity_rate()` |
| `admin_ret_tagging` | 15 | `getAvailableMaterials()`, `getAvailableTaxGroups()`, `getTagStoneByTagId()` |
| `admin_ret_catalog` | 15 | `active_metals()`, `get_ActiveProducts()`, `get_NonTagProducts()` |
| `admin_app_api` | 4 | `bill_disc_app_approval()`, `credit_bill_app_approval()`, `get_approval_status()` |
| `admin_pos` | 5 | `checkSessionLock()`, `getTransactionStatus()`, `UploadBilledTransaction()` |
| `admin_ret_purchase` | 1 | `karigarmetalissue()` |
| `admin_ret_reports` | 3 | `get_ActiveProduct()`, `order_status()`, `tag_history()` |
| `admin_ret_order` | 1 | `get_repair_damage_master()` |
| `admin_ret_other_inventory` | 1 | `get_invnetory_item()` |

### Inbound Dependencies (modules that depend on Billing_v2)

| Module | Dependency Type |
|---|---|
| Estimation | Reads `ret_estimation*` tables, pipeline into billing |
| Tagging | Tag status updated by billing on sale/cancel |
| Payment | `payment` table written on every bill |
| Customer | Customer records referenced and wallet updated |
| Scheme | `scheme_account` debited on chit-utilization bills |
| Old Metal | `ret_bill_old_metal_sale_details` written on exchange |

---

## 5. Risk Assessment

### 🔴 CRITICAL (3)

| ID | Risk | File | Evidence |
|---|---|---|---|
| RISK-001 | `billing()` — **5,177-line MEGA method** | `admin_ret_billing.php:229` | 49 table writes, OTP, impossible to unit test |
| RISK-011 | `updateNewCustomer()` — **DELETE without transaction** | `ret_billing_model.php` | Hard DELETE on address table — no rollback possible |
| RISK-012 | `update_issue_rcpt_payment()` — **DELETE without transaction** | `ret_billing_model.php` | Hard DELETE on payment table — no rollback possible |

### 🟠 HIGH (11)

| ID | Risk | Evidence |
|---|---|---|
| RISK-002 | `getEstimationDetails()` — 1,701 lines, 14 tables | Model complexity |
| RISK-003 | `receipt()` — 1,050 lines, 14 tables, TXN | Controller complexity |
| RISK-004 | `issue()` — 897 lines, 6 tables, TXN | Controller complexity |
| RISK-005 | `getOtherEstimateItemsDetails()` — 821 lines, 19 tables | Model complexity |
| RISK-006 | `bill_number_format()` — 578 lines, TXN | Controller complexity |
| RISK-013 | **Duplicate method** `get_borrower()` at L8158 & L8178 | Dead code — L8158 is never called |
| RISK-014 | **Duplicate method** in model at L6481 & L6664 | Dead code |
| RISK-015 | **Duplicate method** in model at L6584 & L6799 | Dead code |
| RISK-016 | **Duplicate method** in model at L6949 & L6989 | Dead code |
| RISK-017 | **Duplicate method** in model at L8634 & L8650 | Dead code |
| RISK-018 | **Duplicate method** in model at L8886 & L8909 | Dead code |

### 🟡 MEDIUM (4)

| ID | Risk | Evidence |
|---|---|---|
| RISK-007 | `cancel_bill()` — 448 lines, 13 tables, TXN | Cancellation complexity |
| RISK-008 | `service_bill()` — 371 lines, TXN, OTP | Moderate complexity |
| RISK-009 | `paymentmode_edit()` — 313 lines, TXN | Edit complexity |
| RISK-010 | `getEstimationDetailsTags()` — 303 lines, 3 tables | Model complexity |

---

## 6. Data Flow

### Sale → Cancel Flow

```
User submits bill form
  → billing() [L229]
    → Validate: tag status, customer, payment mode
    → $type branch: (sale | return | exchange | credit | advance | ...)
    → Write: ret_billing + ret_bill_details
    → Write: ret_taging_status_log (tag sold)
    → Write: ret_billing_payment + payment
    → IF chit_utilized: write ret_billing_chit_utilization + scheme_account
    → IF wallet_used: write ret_wallet + ret_wallet_transcation
    → IF old_metal: write ret_bill_old_metal_sale_details
    → COMMIT

User cancels bill
  → cancel_bill() [L8217]
    → Read ret_billing by bill_no
    → REVERSE: ret_taging_status_log (tag back to available)
    → REVERSE: ret_nontag_item_log / ret_section_tag_status_log
    → REVERSE: wallet_transaction (credit back)
    → REVERSE: scheme_account (restore chit balance)
    → DELETE: ret_billing, ret_bill_details, ret_billing_payment
    → COMMIT
```

> ⚠️ **Cancel reversal is 65-80% complete** — some edge cases (insurance, POS, supplier bills) leave orphaned records.

### Estimation → Billing Pipeline

```
Estimation module saves draft
  → ret_estimation* tables written
  
Customer approves → billing() called with estimation_id
  → getEstimationDetails() [model, 1701 lines]
    → Pulls all estimation items, stones, old metals
  → billing() re-writes into ret_billing* tables
  → ret_estimation tables updated to "billed" status
```

---

## 7. Known Bugs & Incomplete Flows

| Bug | Severity | Description |
|---|---|---|
| Cancel reversal gaps | 🔴 HIGH | `cancel_bill()` doesn't reverse: insurance records, POS requests, supplier sale details in all paths |
| 6 duplicate method pairs | 🟠 HIGH | PHP silently uses the last definition — the first copies are dead code |
| DELETE without transaction | 🔴 CRITICAL | Two model methods delete data without transaction wrapping |
| No unit tests | 🟠 HIGH | 0% test coverage on the most complex module in the system |
| `billing()` — single method for 10 bill types | 🔴 CRITICAL | Makes targeted bug fixes extremely risky |

---

## 8. Invoice Builder Sub-Module

`admin_invoice_builder.php` is a **separate controller** (170 lines, 13 methods) for designing custom print templates.

| Method | Purpose |
|---|---|
| `designer()` | Open the invoice template visual editor |
| `save_design()` | Save template JSON to DB |
| `load_design()` | Load template for editing |
| `get_fields()` | Get available merge fields |
| `preview()` | Render a preview of the invoice |
| `upload_image()` | Upload logo/header image for template |
| `set_default()` | Mark a template as the default for a branch |

---

## 9. Danger Zones (Do Not Touch Without Full Context)

| Zone | Why Dangerous |
|---|---|
| `billing()` L229–5406 | 5,177 lines — any change risks breaking 10 different bill types simultaneously |
| `cancel_bill()` L8217 | 13 table writes reversed in sequence — order matters; breaking one step leaves corrupted state |
| `receipt()` L7096 | Handles advance adjustments, wallet credits, and tag status — high interdependency |
| Duplicate methods in model | Fixing the "wrong" copy (the first one) has zero effect at runtime |
| 49-table transaction in `billing()` | Any uncaught exception mid-transaction can leave partial state across many tables |

---

## 10. Recommended Next Actions

| Priority | Action |
|---|---|
| P0 | Remove duplicate method definitions (6 pairs) — low risk, high clarity gain |
| P0 | Wrap `updateNewCustomer()` DELETE in transaction |
| P0 | Wrap `update_issue_rcpt_payment()` DELETE in transaction |
| P1 | Fix `cancel_bill()` to cover insurance + POS + supplier reversal paths |
| P1 | Extract `billing()` bill-type branches into separate private methods |
| P2 | Add FLOW_RISK_MATRIX document |
| P2 | Add INVARIANT_MATRIX document |
| P3 | Write PHPUnit integration tests for `billing()` and `cancel_bill()` |
