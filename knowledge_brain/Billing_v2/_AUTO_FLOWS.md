# Billing Module — Business Flows (Brain v2.3)

> Auto-inferred by `lca brain-build` on 2026-06-13
> **18 flows** extracted from transaction blocks, CRUD chains, and status lifecycles

---

## Summary

| Category | Count |
|---|---|
| LIFECYCLE | 9 |
| TRANSACTION | 9 |

---

## TRANSACTION Flows

### FLOW-001: Billing

- **Entry:** `billing()` (admin_ret_billing.php)
- **Size:** 5,177 lines
- **Traits:** 🔒 Transaction | 🔑 OTP | 📡 JSON
- **Writes:** `customer_delivery_address` → `customerorder` → `customerorderdetails` → `gift_card` → `payment` → `payment_mode_details` → `payment_old_metal` → `ret_advance_refund`
- **Status values:**
  - bill_type: 8 (Credit Collection)
  - purchase_status=0
  - is_credit: 1 (&nbsp;Yes)
  - is_receipt_select=1
  - bill_type: 15 (Approval Sales Bill)
  - is_partial: 0 (No)
  - bill_type: 4 (Purchase)
  - is_partial=1
- **Calls:** `$sms_model->get_SMS_data`, `$model->get_gift_issue_details`, `admin_settings_model->profileDB`, `$inc_model->get_category_for_product`, `$model->updatesecNTData`, `$ordermodel->generateOrderNo`

> Atomic operation: 49 tables written inside transaction. OTP authorization required. Returns JSON response. Method is 5177 lines — ⚠️ extremely complex, high bug risk.

### FLOW-002: Receipt

- **Entry:** `receipt()` (admin_ret_billing.php)
- **Size:** 1,050 lines
- **Traits:** 🔒 Transaction | 📡 JSON
- **Writes:** `ret_adv_receipt_tags` → `ret_adv_receipt_weight` → `ret_estimation_old_metal_sale_details` → `ret_issue_credit_collection_details` → `ret_issue_rcpt_payment` → `ret_issue_receipt` → `ret_issue_receipt_advance_adj` → `ret_purchase_items_log`
- **Status values:**
  - receipt_type: 8 (Petty Cash Receipt  &nbsp;&nbsp;&nbsp;)
  - bill_type: 15 (Approval Sales Bill)
  - receipt_type: 1 (Credit Collection Receipt)
  - receipt_type: 2 (Advance Receipt)
  - rct_type=2
- **Calls:** `$SETT_MOD->get_access`, `$model->getCreditBill`, `print_template_model->render`, `$model->updateWalletData`, `$model->insertData`, `admin_settings_model->get_access`

> Atomic operation: 13 tables written inside transaction. Returns JSON response. Method is 1050 lines — ⚠️ extremely complex, high bug risk.

### FLOW-003: Issue

- **Entry:** `issue()` (admin_ret_billing.php)
- **Size:** 897 lines
- **Traits:** 🔒 Transaction | 📡 JSON
- **Writes:** `ret_advance_refund` → `ret_issue_expense_details` → `ret_issue_rcpt_payment` → `ret_issue_receipt` → `ret_wallet_transcation`
- **Status values:**
  - issue_type: 3 (Advance Refund)
  - issue_type: 6 (Non Jewellery Expense)
- **Calls:** `$SETT_MOD->get_access`, `print_template_model->render`, `$model->updateWalletData`, `$model->insertData`, `admin_settings_model->get_access`, `$model->updateData`

> Atomic operation: 5 tables written inside transaction. Returns JSON response. Method is 897 lines — moderately complex.

### FLOW-004: Cancel Bill

- **Entry:** `cancel_bill()` (admin_ret_billing.php)
- **Size:** 448 lines
- **Traits:** 🔒 Transaction | 📡 JSON
- **Writes:** `ret_bill_details` → `ret_billing` → `ret_estimation_items` → `ret_estimation_old_metal_sale_details` → `ret_issue_receipt` → `ret_nontag_item_log` → `ret_other_inventory_purchase_items_details` → `ret_other_inventory_purchase_items_log`
- **Status values:**
  - bill_type: 9 (Order Delivery)
  - bill_type: 8 (Credit Collection)
  - make_as_advance: 2 (&nbsp;Chit Deposit)
  - bill_type: 5 (Order Advance)
  - is_credit: 1 (&nbsp;Yes)
  - bill_type: 7 (Sales Return)
  - bill_type: 11 (Repair Order Delivery)
  - item_type=0
- **Calls:** `$model->get_BillAmount`, `$model->get_gift_issue_details`, `$model->getBillingDetails`, `$model->insertData`, `admin_settings_model->getPreviousDateStatuslog`, `$model->updateData`

> Atomic operation: 13 tables written inside transaction. Returns JSON response. Method is 448 lines — moderately complex.

### FLOW-005: Service Bill

- **Entry:** `service_bill()` (admin_ret_billing.php)
- **Size:** 371 lines
- **Traits:** 🔒 Transaction | 🔑 OTP | 📡 JSON
- **Writes:** `ret_service_bill` → `ret_service_bill_details` → `ret_service_bill_payment`
- **Status values:**
  - bill_type: 6 (Advance)
- **Calls:** `$model->insertBatchData`, `$model->service_bill_number_generator`, `$model->ajax_getServiceBillList`, `admin_settings_model->profileDB`, `$model->get_empty_record`, `log_model->log_detail`

> Atomic operation: 3 tables written inside transaction. OTP authorization required. Returns JSON response. Method is 371 lines — moderately complex.

### FLOW-006: Paymentmode Edit

- **Entry:** `paymentmode_edit()` (admin_ret_billing.php)
- **Size:** 313 lines
- **Traits:** 🔒 Transaction | 📡 JSON
- **Writes:** `ret_billing` → `ret_billing_payment`
- **Calls:** `$model->updateData`, `$model->deleteData`, `$model->get_empty_record`, `$model->get_active_bill`, `$model->get_active_bill_list`, `$model->insertData`

> Atomic operation: 2 tables written inside transaction. Returns JSON response. Method is 313 lines — moderately complex.

### FLOW-007: Cash Collectionection

- **Entry:** `cash_collection()` (admin_ret_billing.php)
- **Size:** 175 lines
- **Traits:** 🔒 Transaction | 📡 JSON
- **Writes:** `ret_cash_collection` → `ret_cash_collection_details`
- **Calls:** `$model->get_cashCollectionDetails`, `$SETT_MOD->get_access`, `$model->ajax_getCashCollection`, `admin_settings_model->profileDB`, `$model->getDenomination`, `$model->get_denomination`

> Atomic operation: 2 tables written inside transaction. Returns JSON response. Method is 175 lines — manageable size.

### FLOW-008: Order Place

- **Entry:** `order_place()` (admin_ret_billing.php)
- **Size:** 127 lines
- **Traits:** 🔒 Transaction | 📡 JSON
- **Writes:** `customerorder` → `customerorderdetails`
- **Calls:** `$model->updateData`, `$billing_model->get_approval_tag_details`, `$model->generatePurNo`, `$model->insertData`, `$model->get_FinancialYear`

> Atomic operation: 2 tables written inside transaction. Returns JSON response. Method is 127 lines — manageable size.

### FLOW-009: Advance Transfer

- **Entry:** `advance_transfer()` (admin_ret_billing.php)
- **Size:** 117 lines
- **Traits:** 🔒 Transaction | 🔑 OTP | 📡 JSON
- **Writes:** `ret_advance_transfer` → `ret_issue_receipt`
- **Calls:** `$model->get_retSettings`, `$model->bill_no_generate`, `admin_settings_model->getBranchDayClosingData`, `$model->insertData`, `$model->get_FinancialYear`, `$model->get_ret_settings`

> Atomic operation: 2 tables written inside transaction. OTP authorization required. Returns JSON response. Method is 117 lines — manageable size.

---

## LIFECYCLE Flows

### FLOW-010: `ret_billing` Lifecycle

- **Entry:** `billing()` ()
- **Traits:** 🔒 Transaction
- **Writes:** `ret_billing`

> CREATE: billing. UPDATE: billing, cancel_bill, paymentmode_edit, updatebilleinvoicedetails. DELETE: none (soft-delete?).

### FLOW-011: `ret_bill_details` Lifecycle

- **Entry:** `billing()` ()
- **Traits:** 🔒 Transaction
- **Writes:** `ret_bill_details`

> CREATE: billing. UPDATE: billing, cancel_bill. DELETE: none (soft-delete?).

### FLOW-012: `ret_issue_receipt` Lifecycle

- **Entry:** `advance_transfer, billing, issue()` ()
- **Traits:** 🔒 Transaction
- **Writes:** `ret_issue_receipt`

> CREATE: billing, issue, receipt, advance_transfer. UPDATE: issue, receipt, cancel_bill, close_issue_receipt. DELETE: none (soft-delete?).

### FLOW-013: `ret_service_bill` Lifecycle

- **Entry:** `service_bill()` ()
- **Traits:** 🔒 Transaction
- **Writes:** `ret_service_bill`

> CREATE: service_bill. UPDATE: cancel_service_bill. DELETE: none (soft-delete?).

### FLOW-014: `ret_cash_collection` Lifecycle

- **Entry:** `cash_collection()` ()
- **Traits:** 🔒 Transaction
- **Writes:** `ret_cash_collection`

> CREATE: cash_collection. UPDATE: none. DELETE: none (soft-delete?).

### FLOW-015: `ret_advance_transfer` Lifecycle

- **Entry:** `advance_transfer()` ()
- **Traits:** 🔒 Transaction
- **Writes:** `ret_advance_transfer`

> CREATE: advance_transfer. UPDATE: none. DELETE: none (soft-delete?).

### FLOW-016: Bill Status State Machine

- **Entry:** `billing()()` ()
- **Writes:** `ret_billing`
- **Status values:**
  - 1 (Success): checked by ajax_getReceiptlist, ajax_getIssuetist, get_credit_history_for_print
  - 2 (Cancelled): checked by unknown

> bill_status has 2 known states: 1=Success, 2=Cancelled. Transitions are guarded by status checks in controller methods.

### FLOW-017: Receipt Type Classification

- **Entry:** `receipt()()` ()
- **Writes:** `ret_issue_receipt`
- **Status values:**
  - 1=Credit Collection Receipt
  - 2=Advance Receipt
  - 3=Advance Deposit
  - 4=Opening Blc
  - 5=Order Advance to General Advance
  - 6=Advance Deposit
  - 7=Advance Transfer
  - 8=Petty Cash Receipt  &nbsp;&nbsp;&nbsp;
  - 9=Amount Receipt  &nbsp;&nbsp;

> Receipt operations are classified into 9 types: 1=Credit Collection Receipt, 2=Advance Receipt, 3=Advance Deposit, 4=Opening Blc, 5=Order Advance to General Advance, 6=Advance Deposit, 7=Advance Transfer, 8=Petty Cash Receipt  &nbsp;&nbsp;&nbsp;, 9=Amount Receipt  &nbsp;&nbsp;.

### FLOW-018: Issue Type Classification

- **Entry:** `issue()()` ()
- **Writes:** `ret_issue_receipt`
- **Status values:**
  - 1=PAYMENT ISSUE VOUCHER
  - 2=Credit Issue
  - 3=Advance Refund
  - 4=Existing Out Standing
  - 5=Expenses
  - 6=Non Jewellery Expense

> Issue operations are classified into 6 types: 1=PAYMENT ISSUE VOUCHER, 2=Credit Issue, 3=Advance Refund, 4=Existing Out Standing, 5=Expenses, 6=Non Jewellery Expense.
