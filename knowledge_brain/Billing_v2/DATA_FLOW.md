# Billing Module — Data Flow (Phase 2 Deep Analysis)

> **Brain v2.0 Phase 2** | Traced from source code via WIRING.md targeting
> Source: `admin_ret_billing.php` (13,898 lines), `ret_billing_model.php` (12,184 lines)

---

## Flow 1: CREATE — Save New Bill (Normal Sale)

### Step-by-step trace

1. **JS Init** (`ret_billing.js`): On `case "add"` — calls `get_employee()`, `get_ActiveUOM()`, `get_ActiveProduct()`, `getStoneRateSettings()`, `getOtherChargesDetails()`, `get_branches()`
2. **User selects branch** → JS fetches metal rates via AJAX to controller
3. **User searches estimation/tag** → JS calls `getEstimationDetails()` controller (L5511) → model `getEstimationDetails()` (L2928-4629, **1,701 lines**)
4. **Estimation populates sale row** → JS populates item fields (product, design, purity, weight, MC, VA, etc.)
5. **JS calculates row total** → `calculateSaleBillRowTotal()`:
   - `rate_per_gram × net_weight`
   - `+ wastage (% or flat)`
   - `+ MC (making charges — per gram or flat)`
   - `+ stone_value`
   - `= item_cost (before tax)`
   - `+ CGST + SGST (or IGST) based on tax_group`
   - `= item_total`
6. **User adds payment** → Cash, Card, Cheque, Net Banking, Chit Utilization, Gift Voucher, Advance Adjustment
7. **JS form submit** → POST to `billing('save')` inside the mega-method (L229-5405)

### Controller `billing('save')` path (L229+)

1. **Validates `form_secret`** (L368-370): `strcasecmp($form_secret, $this->session->userdata('FORM_SECRET'))`
2. **Checks tag availability**: `get_tag_status($tag_id)` → must be `tag_status == 0` (L358-364)
3. **Gets day closing data**: `getBranchDayClosingData($id_branch)` — hard gate (L381)
4. **Gets metal rate**: `get_branchwise_rate($id_branch)` (L344)
5. **Bill date calculation**: `bill_date = (dCData['entry_date'] == date("Y-m-d") ? date("Y-m-d H:i:s") : dCData['entry_date'])` (L340)
6. **Generates bill number**: `code_number_generator($id_branch, $metal_type, $is_eda)` (L385)
7. **DB Transaction starts**: `$this->db->trans_begin()` (L350)
8. **INSERT `ret_billing`** — header record with bill_no, customer, amounts, metal rates, form_secret (L387-485)
9. **For each sale item**:
   - INSERT `ret_bill_details` — line item with product, tag, weights, tax, discount (L554-640)
   - `generateRefNo()` → UPDATE `ret_billing.sales_ref_no` (L644-646)
   - INSERT `ret_billing_item_stones` — for each stone in JSON (L648-687)
   - INSERT `ret_bill_other_metals` — for each other metal in JSON (L689-723)
   - UPDATE `ret_estimation_items.purchase_status = 1, bil_detail_id = tagInsert` (L731)
   - UPDATE `ret_estimation.estbillid = insId` (L739)
   - UPDATE `ret_taging.tag_status = 1` (sold) (L751)
   - UPDATE `ret_taging.is_partial` if `itemtype == 0` (L755-758)
   - INSERT `ret_taging_status_log` with `status=1`, form_secret composite (L764-788)
   - INSERT `ret_section_tag_status_log` if tag has section (L796+)
10. **For each payment**:
    - INSERT `ret_billing_payment` — Cash/CC/DC/CHQ/NB with device, card_no, ref_number (L503-550)
11. **Credit status**: `credit_status = (is_credit == 1) ? 2 : 1` (L427)
12. **Advance handling**: `make_as_advance`, `advance_deposit` fields (L451-453)
13. **TCS/TDS**: `tcs_tax_amt`, `tcs_tax_per`, `tds_percent`, `tds_tax_amt` (L455-461)
14. **Transaction complete**: `$this->db->trans_complete()` → commit or rollback
15. **Response**: `echo json_encode(['status' => true/false, 'bill_id' => N])`

### Tables written (in order)

| # | Table | Action | Purpose |
|---|---|---|---|
| 1 | `ret_billing` | INSERT | Bill header — customer, amounts, rates, dates |
| 2 | `customer` | UPDATE | PAN/Aadhaar/DL/Passport update if provided |
| 3 | `ret_billing_payment` | INSERT (×N) | One row per payment mode (Cash, CC, DC, CHQ, NB) |
| 4 | `ret_bill_details` | INSERT (×N) | One row per sale item — weights, costs, tax |
| 5 | `ret_billing_item_stones` | INSERT (×N) | Stone entries per item |
| 6 | `ret_bill_other_metals` | INSERT (×N) | Other metal entries per item |
| 7 | `ret_estimation_items` | UPDATE | `purchase_status = 1`, link `bil_detail_id` |
| 8 | `ret_estimation` | UPDATE | Link `estbillid` to this bill |
| 9 | `ret_taging` | UPDATE | `tag_status = 1` (sold), `is_partial` flag |
| 10 | `ret_taging_status_log` | INSERT | Movement audit with `status=1` (sale) |
| 11 | `ret_section_tag_status_log` | INSERT | Section-level movement (if tag has section) |

### ⚠️ Risk Notes

- **form_secret per split**: Each split appends `tag_id + key` to form_secret for uniqueness — prevents cross-split collisions but makes duplicate detection per-item
- **No pre-save validation on amounts**: Tax amounts, discount, MC/VA come from JS and are stored as-is — server does NOT re-calculate
- **Race condition on bill_no**: `code_number_generator()` uses `MAX(bill_no)` — concurrent users on same branch/metal can collide

---

## Flow 2: CREATE — Bill Split Save

### Step-by-step trace

1. **Trigger**: JS collects split data into `$_POST['billing']` + `$_POST['split_sale']` arrays
2. **Controller `billing('split_save')` path** (L320-900):
   - ⚠️ Uses raw `$_POST['billing']` (L324) — not `$this->input->post()`
   - Gets day closing data (called **twice** — L330 and L338, redundant)
   - `bill_split_ref_id = time()` — shared across all splits (L346)
3. **Transaction**: `trans_begin()` (L350)
4. **For each split** (`foreach $billSale['is_est_details']`):
   - Tag status check: `get_tag_status()` (L358)
   - Form secret check: `strcasecmp()` (L370)
   - Day closing gate: `sizeof($dCData) > 0` (L381)
   - Generates separate bill_no per split (L385)
   - INSERT `ret_billing` with `is_bill_split = 1`, `bill_split_ref_id` (L479-485)
   - INSERT payment, details, stones, other metals — same as Flow 1
   - UPDATE estimation items/header, tag status — same as Flow 1

### Key difference from Flow 1

- Each split creates its own `ret_billing` header with its own `bill_no`
- `bill_split_ref_id` links all splits together
- `credit_status` calculated per split (each split can be credit or non-credit independently)
- `form_secret` has extra uniqueness: `form_secret + '_' + tag_id + '_' + key` (L407, L762)

### ⚠️ Risk Notes

- **Raw `$_POST` usage**: L324 `$addData = $_POST['billing']` bypasses CI3's XSS protection
- **Redundant day closing call**: L330 and L338 both call `getBranchDayClosingData()` — wasted query
- **No atomic split validation**: If split 2 fails but split 1 succeeds, transaction covers both — all-or-nothing is correct, but error reporting doesn't say *which* split failed

---

## Flow 3: DELETE — Cancel Bill

### Step-by-step trace

1. **Trigger**: JS calls `cancel_bill()` controller (L8218-8664, **448 lines**)
2. **Method**: POST — `bill_id` + `remarks` via `$this->input->post()`
3. **OTP check**: Handled by separate `send_bill_cancel_otp()` (L9300) and `verify_otp_for_billcancel()` (L9378) — called BEFORE this method
4. **Transaction**: `$this->db->trans_begin()` (L8244)

### Reversal sequence

| Step | Table | Action | Purpose |
|---|---|---|---|
| 1 | `ret_billing` | UPDATE `bill_status = 2` | Mark bill as cancelled (⚠️ status=2, NOT 0 as v1 documented) |
| 2 | `ret_issue_receipt` | UPDATE `bill_status = 2` | Cancel linked receipts |
| 3 | `ret_estimation` | UPDATE `estbillid = NULL` | Unlink estimation from bill |
| 4 | `ret_estimation_items` | UPDATE `purchase_status = 0, bil_detail_id = NULL` | Mark items as unsold |
| 5 | `ret_taging` | UPDATE `tag_status = 0` | Tag available again (only if `item_type == 0`) |
| 6 | `ret_taging_status_log` | INSERT `status = 6` | Log cancel movement |
| 7 | `ret_section_tag_status_log` | INSERT `status = 6` | Section-level cancel log |
| 8 | `ret_nontag_item_log` | INSERT | Non-tag stock reversal log |
| 9 | `ret_estimation_old_metal_sale_details` | UPDATE `purchase_status = 0, bill_id = NULL` | Revert old metal sales |
| 10 | `ret_bill_details` | UPDATE `status = 1` | Revert return item status |
| 11 | `gift_card` | via `get_gift_issue_details()`, `get_redeem_details()` | Revert gift voucher issue/redeem |
| 12 | `scheme_account` | UPDATE `is_utilized = 0` | Revert chit utilization |
| 13 | `wallet_transaction` | INSERT (type=1) | Credit back wallet debit |
| 14 | `payment` | UPDATE `payment_status = 4` | Cancel chit deposit payment (if `make_as_advance == 2`) |
| 15 | `customerorderdetails` | UPDATE `orderstatus = 4` | Revert repair order (if `bill_type == 11`) |
| 16 | `ret_billing_advance` | UPDATE `is_adavnce_adjusted = 0, adjusted_bill_id = NULL` | Restore order advance (if `bill_type == 9`) |
| 17 | `ret_billing` (ref bill) | UPDATE `credit_status` | Recalculate credit status of original bill (if `bill_type == 7 or 8`) |
| 18 | `ret_other_inventory_purchase_items_details` | UPDATE | Revert other inventory items |
| 19 | `ret_other_invnetory_issue` | DELETE | Remove inventory issue records |
| 20 | `ret_other_inventory_purchase_items_log` | DELETE | Remove inventory purchase log |

### ⚠️ GAPs — Tables NOT restored on cancel

| Created During Save | Restored? | Gap |
|---|---|---|
| `ret_billing_payment` | ❌ NO | Payment records remain — kept for audit but balance isn't zeroed |
| `ret_billing_item_stones` | ❌ NO | Stone records remain attached to cancelled bill |
| `ret_bill_other_metals` | ❌ NO | Other metal records remain |
| `ret_billing_advance` (non-order) | ⚠️ PARTIAL | Only order advances (bill_type=9) are reverted |
| POS transaction (`ret_pos_requests`) | ❌ NO | POS transaction NOT auto-reversed on cancel |
| `customer` (PAN/Aadhaar update) | ❌ NO | KYC updates are permanent — not reverted on cancel |

### Sales Return Cancel special logic (L8560-8583)

When cancelling a **sales return** (bill_type=7) that references a credit bill:
- Recalculates `credit_status` of the original bill
- Formula: `balance = tot_bill_amount - tot_amt_received - total_collections - total_returned - round_off_amt`
- If `balance <= 0` → `credit_status = 1` (paid), else `credit_status = 2` (pending)

---

## Flow 4: ISSUE Voucher

### Step-by-step trace

1. **URL**: `/admin_ret_billing/issue/{type}`
2. **Controller `issue()`** (L6024-6921, **897 lines**)
3. **Switch cases**: `add`, `list`, `save`, `edit`, `update`, `ajax`, `cancel`
4. **Purpose**: Cash/asset going OUT — expense payments, loans, purchase payments

### `issue('save')` path (L6055+)

1. **Generates bill_no**: `bill_no_generate($id_branch)` — separate sequence from billing
2. **Gets day closing date**: `getBranchDayClosingData()`
3. **Transaction**: `trans_begin()`
4. **INSERT `ret_issue_receipt`**: type=1 (issue), with borrower, amount, account head, narration
5. **Payment processing**: INSERT `ret_issue_rcpt_payment` — Cash/CC/DC/CHQ/NB
6. **Expense details**: INSERT `ret_issue_expense_details` (if expense items)
7. **Advance refund**: INSERT `ret_advance_refund` (if refunding advance)
8. **Wallet**: INSERT `ret_wallet_transcation` (if wallet debit)
9. **Journal entries**: Creates journal via `account_model`
10. **Transaction**: `trans_complete()`

### Tables written

| # | Table | Action | Purpose |
|---|---|---|---|
| 1 | `ret_issue_receipt` | INSERT | Issue voucher header (type=1) |
| 2 | `ret_issue_rcpt_payment` | INSERT (×N) | Payment details |
| 3 | `ret_issue_expense_details` | INSERT | Expense line items |
| 4 | `ret_advance_refund` | INSERT | Advance refund record |
| 5 | `ret_wallet_transcation` | INSERT | Wallet debit transaction |
| 6 | `ret_journal` | INSERT (via account_model) | Accounting journal entries |

---

## Flow 5: RECEIPT Voucher

### Step-by-step trace

1. **URL**: `/admin_ret_billing/receipt/{type}`
2. **Controller `receipt()`** (L7096-8145, **1,050 lines**)
3. **Switch cases**: `add`, `list`, `paymentmode_edit`, `credit_bill`, `save`, `edit`, `update`, `cancel`, `advance_receipt`, `ajax`
4. **Purpose**: Cash/asset coming IN — advance payments, credit collections, loan repayments

### `receipt('save')` path (L7173+)

1. **Form secret check**: `strcasecmp($form_secret, $this->session->userdata('FORM_SECRET'))` (L7191-7196)
2. **Gets day closing date**: `getBranchDayClosingData()`
3. **Generates receipt_no**: `generate_receipt_no()`
4. **Transaction**: `trans_begin()`
5. **INSERT `ret_issue_receipt`**: type=2 (receipt), with receipt_type differentiating sub-flows:
   - `receipt_type=1`: Advance receipt
   - `receipt_type=2`: Credit collection
   - `receipt_type=3`: Loan repayment
   - `receipt_type=4`: Sale advance
   - `receipt_type=7`: Advance transfer
6. **Payment processing**: INSERT `ret_issue_rcpt_payment` — Cash/CC/DC/CHQ/NB
7. **Credit collection**: INSERT `ret_issue_credit_collection_details` + UPDATE `ret_billing.credit_status`
8. **Advance receipt tags**: INSERT `ret_adv_receipt_tags`, `ret_adv_receipt_weight`
9. **Old metal purchase**: INSERT `ret_estimation_old_metal_sale_details`, `ret_purchase_items_log`
10. **Wallet**: UPDATE `ret_wallet`, INSERT `ret_wallet_transcation`
11. **Advance adjustment**: INSERT `ret_issue_receipt_advance_adj`
12. **Journal entries**: Creates journal via `account_model`
13. **Transaction**: `trans_complete()`

### Tables written

| # | Table | Action | Purpose |
|---|---|---|---|
| 1 | `ret_issue_receipt` | INSERT | Receipt header (type=2) |
| 2 | `ret_issue_rcpt_payment` | INSERT (×N) | Payment details |
| 3 | `ret_issue_credit_collection_details` | INSERT | Credit collection breakdown |
| 4 | `ret_billing` | UPDATE `credit_status` | Mark original bill as paid/partial |
| 5 | `ret_adv_receipt_tags` | INSERT | Advance receipt tag details |
| 6 | `ret_adv_receipt_weight` | INSERT | Advance receipt weight details |
| 7 | `ret_estimation_old_metal_sale_details` | INSERT | Old metal purchase during receipt |
| 8 | `ret_purchase_items_log` | INSERT | Purchase item stock log |
| 9 | `ret_wallet` | UPDATE | Wallet balance update |
| 10 | `ret_wallet_transcation` | INSERT | Wallet transaction record |
| 11 | `ret_issue_receipt_advance_adj` | INSERT | Advance adjustment records |
| 12 | `ret_receipt_wgt_detail` | INSERT | Receipt weight details |
| 13 | `ret_section_tag_status_log` | INSERT | Section tag status (if applicable) |
| 14 | `ret_taging_status_log` | INSERT | Tag status log (if advance receipt with tags) |

---

## Flow 6: SERVICE Bill

### Step-by-step trace

1. **URL**: `/admin_ret_billing/service_bill/{type}`
2. **Controller `service_bill()`** (L9735-10105, **371 lines**)
3. **Purpose**: Repair/service work billing — separate tables from main billing

### `service_bill('save')` path

1. **Day closing + form secret gate**: Same pattern as billing
2. **Generates bill_no**: `service_bill_number_generator()` — separate sequence
3. **Transaction**: `trans_begin()`
4. **INSERT `ret_service_bill`**: Header — customer, amount, bill_date
5. **INSERT `ret_service_bill_details`**: Line items — repair items, quantities
6. **INSERT `ret_service_bill_payment`**: Payment rows — Cash/CC/DC/CHQ/NB
7. **Journal entries**: via `account_model`
8. **Transaction**: `trans_complete()`

### Cancellation: `cancel_service_bill()` (L10152-10198)

- UPDATE `ret_service_bill.bill_status = 2`, `cancelled_date`, `cancel_reason`
- ⚠️ **No payment reversal** — `ret_service_bill_payment` records remain
- ⚠️ **No journal reversal** — accounting entries not reversed

---

## Flow 7: The billing() MEGA-Method Map (L229-5405)

The `billing($type, $id)` method controls the entire invoice lifecycle via a switch on `$type`:

| Case | Lines | Description |
|---|---|---|
| `add` | L247-299 | Load master data, prepare UI for new bill |
| `list` | L301-307 | Render bill list view |
| `approvallist` | L309-318 | Render approval list view |
| `split_save` | L320-900 | **[CRITICAL]** Split bill save (Flow 2 above) |
| `ajax` | L900+ | DataTable list data |
| `ajaxapprovallist` | L900+ | Approval list data |
| `save` | L1100-3600 | **[CRITICAL]** Main save — 2,500 lines, 15+ bill types |
| `edit` | L3600-4475 | Load existing bill for editing |
| `update` | L4480-5200 | **[CRITICAL]** UPDATE — uses DELETE-then-INSERT for child records |
| `delete` / `cancell` | L5200-5400 | Cancellation via mega-method (delegates to cancel_bill) |
| `default` | L5400-5405 | Render standard list view |

### The `save` Action — Bill Type Routing

Inside `case 'save'`, `$b_type = $_POST['b_type']` determines the sub-flow:

| bill_type | Meaning | Key Difference |
|---|---|---|
| 1 | Standard Sale | Full item + payment + journal flow |
| 2 | Old Metal Purchase | Separate purchase return logic |
| 3 | Supplier Bill | Supplier payment flow |
| 4-5 | Chit/Scheme/Order Advance | Scheme account adjustment |
| 7 | Sales Return | Reverse of standard sale |
| 8 | Credit Collection | Payment against credit bill |
| 9 | Customer Order Sale | Order advance adjustment |
| 11 | Repair Order Bill | Repair order completion |
| 15 | Suspense Stock | Tag marked as suspense (`issuspensestock=1`) |

### ⚠️ Risk: Modifying the MEGA-method

Due to its 5,177-line size, **any modification to the save or update block is extremely high-risk**. A change to how tax_amount or discount is saved must be verified against:
1. The Sale Path (bill_type=1)
2. The Purchase Path (bill_type=2)
3. The Return Path (bill_type=7)
4. The edit/update roundtrip (to ensure modified field isn't lost on DELETE-then-INSERT)

---

## Key Observations

### Shared Patterns Across All Flows

1. **Day Closing Gate**: Every save operation checks `getBranchDayClosingData()` — bills cannot be created without day closing
2. **Form Secret**: Duplicate submission prevention — compared against session value
3. **Transaction Wrapping**: All multi-table operations use `trans_begin()/trans_complete()`
4. **Journal Entries**: All financial operations create accounting journal entries via `account_model`
5. **Bill Number Generation**: Each flow has its own number generator — `code_number_generator()` for billing, `bill_no_generate()` for issue/receipt, `service_bill_number_generator()` for service

### DELETE-then-INSERT Pattern

The `billing('update')` path uses DELETE-then-INSERT for child records:
- Deletes existing `ret_bill_details`, `ret_billing_payment`, `ret_billing_item_stones`
- Re-inserts all from current form state
- ⚠️ **Data loss risk**: If update fails mid-way, child records may be lost
- ⚠️ **Audit trail loss**: Original `created_by`/`created_time` on child records are overwritten

### Raw `$_POST` Usage

Multiple flows use raw `$_POST` instead of `$this->input->post()`:
- `billing('split_save')` L324: `$addData = $_POST['billing']`
- `receipt('save')` L7175: `$addData = $_POST['receipt']`
- `issue('save')` L6057: `$addData = $_POST['issue']`

⚠️ **XSS Risk**: Bypasses CI3's input sanitization
