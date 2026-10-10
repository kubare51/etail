# Billing Module — Business Rules (Brain v2.3)

> Auto-inferred by `lca brain-build` on 2026-06-13
> **31 rules** extracted from code analysis
> Each rule is tagged `[CONFIRM]` — review and correct as needed

---

## Summary

| Category | Count | Description |
|---|---|---|
| AUTH | 8 | OTP/approval authorization requirements |
| DATA_INTEGRITY | 14 | Transaction and data protection rules |
| STATUS | 6 | Status prerequisite checks (state machine) |
| CONDITIONAL | 2 | Type-based behavioral branching |
| VALIDATION | 1 | Input validation and file upload rules |

---

## AUTH Rules

### RULE-001: Billing Operations Require OTP Authorization

- **Category:** AUTH | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `billing()`, `service_bill()`, `bill_split()`
- **Tables:** `customer_delivery_address`, `customerorder`, `customerorderdetails`, `gift_card`, `payment`, `payment_mode_details`
- **Evidence:** has_otp=True (OTP send/verify pattern detected)

> 3 method(s) in this group use OTP: billing(), service_bill(), bill_split(). OTP is typically sent via SMS and verified before the DB write is committed.

**Your review:** ___

### RULE-002: Bill Cancellation Requires OTP Authorization

- **Category:** AUTH | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `send_bill_cancel_otp()`, `verify_otp_for_billcancel()`
- **Tables:** `otp`
- **Evidence:** has_otp=True (OTP send/verify pattern detected)

> 2 method(s) in this group use OTP: send_bill_cancel_otp(), verify_otp_for_billcancel(). OTP is typically sent via SMS and verified before the DB write is committed.

**Your review:** ___

### RULE-003: Credit Bill Operations Require OTP Authorization

- **Category:** AUTH | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `send_credit_bill_otp()`, `verify_credit_otp()`, `credit_coll_disc_admin_approval()`, `verify_credit_coll_disc_otp()`
- **Tables:** `otp`
- **Evidence:** has_otp=True (OTP send/verify pattern detected)

> 4 method(s) in this group use OTP: send_credit_bill_otp(), verify_credit_otp(), credit_coll_disc_admin_approval(), verify_credit_coll_disc_otp(). OTP is typically sent via SMS and verified before the DB write is committed.

**Your review:** ___

### RULE-004: Advance Transfer Requires OTP Authorization

- **Category:** AUTH | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `advance_transfer()`, `adtrnssendotp()`, `verify_advance_transfer_otp()`, `order_adtrnssendotp()`
- **Tables:** `otp`, `ret_advance_transfer`, `ret_issue_receipt`
- **Evidence:** has_otp=True (OTP send/verify pattern detected)

> 4 method(s) in this group use OTP: advance_transfer(), adtrnssendotp(), verify_advance_transfer_otp(), order_adtrnssendotp(). OTP is typically sent via SMS and verified before the DB write is committed.

**Your review:** ___

### RULE-005: Order Operations Require OTP Authorization

- **Category:** AUTH | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `order_verify_otp()`, `order_delievery_sendotp()`, `order_delievery_verify_otp()`
- **Tables:** `otp`
- **Evidence:** has_otp=True (OTP send/verify pattern detected)

> 3 method(s) in this group use OTP: order_verify_otp(), order_delievery_sendotp(), order_delievery_verify_otp(). OTP is typically sent via SMS and verified before the DB write is committed.

**Your review:** ___

### RULE-006: Admin Approval Requires OTP Verification

- **Category:** AUTH | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `admin_approval()`, `mobile_approval_request()`, `otp_app_approval()`
- **Tables:** `otp`, `ret_admin_approval_status`
- **Evidence:** has_otp=True (OTP send/verify pattern detected)

> 3 method(s) in this group use OTP: admin_approval(), mobile_approval_request(), otp_app_approval(). OTP is typically sent via SMS and verified before the DB write is committed.

**Your review:** ___

### RULE-007: `get_empty_record()` Requires OTP/Authorization

- **Category:** AUTH | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `get_empty_record()`
- **Evidence:** has_otp=True

> Method contains OTP-related code patterns. May be a send/verify helper rather than a standalone business rule.

**Your review:** ___

### RULE-008: `get_retSettings()` Requires OTP/Authorization

- **Category:** AUTH | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `get_retSettings()`
- **Evidence:** has_otp=True

> Method contains OTP-related code patterns. May be a send/verify helper rather than a standalone business rule.

**Your review:** ___

---

## DATA_INTEGRITY Rules

### RULE-009: `billing()` is an Atomic Multi-Table Operation (49 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `billing()`
- **Tables:** `customer_delivery_address`, `customerorder`, `customerorderdetails`, `gift_card`, `payment`, `payment_mode_details`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 49 tables inside a DB transaction. Includes DELETE operations — rollback on failure is critical. Tables: customer_delivery_address, customerorder, customerorderdetails, gift_card, payment, payment_mode_details, payment_old_metal, ret_advance_refund....

**Your review:** ___

### RULE-010: `issue()` is an Atomic Multi-Table Operation (5 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `issue()`
- **Tables:** `ret_advance_refund`, `ret_issue_expense_details`, `ret_issue_rcpt_payment`, `ret_issue_receipt`, `ret_wallet_transcation`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 5 tables inside a DB transaction. Tables: ret_advance_refund, ret_issue_expense_details, ret_issue_rcpt_payment, ret_issue_receipt, ret_wallet_transcation.

**Your review:** ___

### RULE-011: `receipt()` is an Atomic Multi-Table Operation (13 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `receipt()`
- **Tables:** `ret_adv_receipt_tags`, `ret_adv_receipt_weight`, `ret_estimation_old_metal_sale_details`, `ret_issue_credit_collection_details`, `ret_issue_rcpt_payment`, `ret_issue_receipt`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 13 tables inside a DB transaction. Tables: ret_adv_receipt_tags, ret_adv_receipt_weight, ret_estimation_old_metal_sale_details, ret_issue_credit_collection_details, ret_issue_rcpt_payment, ret_issue_receipt, ret_issue_receipt_advance_adj, ret_purchase_items_log....

**Your review:** ___

### RULE-012: `cancel_bill()` is an Atomic Multi-Table Operation (13 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `cancel_bill()`
- **Tables:** `ret_bill_details`, `ret_billing`, `ret_estimation_items`, `ret_estimation_old_metal_sale_details`, `ret_issue_receipt`, `ret_nontag_item_log`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 13 tables inside a DB transaction. Includes DELETE operations — rollback on failure is critical. Tables: ret_bill_details, ret_billing, ret_estimation_items, ret_estimation_old_metal_sale_details, ret_issue_receipt, ret_nontag_item_log, ret_other_inventory_purchase_items_details, ret_other_inventory_purchase_items_log....

**Your review:** ___

### RULE-013: `paymentmode_edit()` is an Atomic Multi-Table Operation (2 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `paymentmode_edit()`
- **Tables:** `ret_billing`, `ret_billing_payment`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 2 tables inside a DB transaction. Includes DELETE operations — rollback on failure is critical. Tables: ret_billing, ret_billing_payment.

**Your review:** ___

### RULE-014: `service_bill()` is an Atomic Multi-Table Operation (3 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `service_bill()`
- **Tables:** `ret_service_bill`, `ret_service_bill_details`, `ret_service_bill_payment`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 3 tables inside a DB transaction. Tables: ret_service_bill, ret_service_bill_details, ret_service_bill_payment.

**Your review:** ___

### RULE-015: `order_place()` is an Atomic Multi-Table Operation (2 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `order_place()`
- **Tables:** `customerorder`, `customerorderdetails`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 2 tables inside a DB transaction. Tables: customerorder, customerorderdetails.

**Your review:** ___

### RULE-016: `advance_transfer()` is an Atomic Multi-Table Operation (2 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `advance_transfer()`
- **Tables:** `ret_advance_transfer`, `ret_issue_receipt`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 2 tables inside a DB transaction. Tables: ret_advance_transfer, ret_issue_receipt.

**Your review:** ___

### RULE-017: `cash_collection()` is an Atomic Multi-Table Operation (2 tables)

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `cash_collection()`
- **Tables:** `ret_cash_collection`, `ret_cash_collection_details`
- **Evidence:** has_transaction=True + multi-table writes

> Method writes to 2 tables inside a DB transaction. Tables: ret_cash_collection, ret_cash_collection_details.

**Your review:** ___

### RULE-024: `billing()` Performs Hard Delete on ret_estimation_item_stones, ret_estimation_item_other_materials, ret_estimation_items

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `billing()`
- **Tables:** `ret_estimation_item_stones`, `ret_estimation_item_other_materials`, `ret_estimation_items`, `ret_estimation_old_metal_sale_details`, `ret_estimation`, `ret_est_gift_voucher_details`
- **Evidence:** tables_delete: ['ret_estimation_item_stones', 'ret_estimation_item_other_materials', 'ret_estimation_items', 'ret_estimation_old_metal_sale_details', 'ret_estimation', 'ret_est_gift_voucher_details', 'ret_est_chit_utilization']

> Method permanently deletes data from 7 table(s): ret_estimation_item_stones, ret_estimation_item_other_materials, ret_estimation_items, ret_estimation_old_metal_sale_details, ret_estimation, ret_est_gift_voucher_details, ret_est_chit_utilization. Protected by transaction. Consider if soft-delete (is_deleted flag) would be safer.

**Your review:** ___

### RULE-025: `cancel_bill()` Performs Hard Delete on ret_other_invnetory_issue, ret_other_inventory_purchase_items_log

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `cancel_bill()`
- **Tables:** `ret_other_invnetory_issue`, `ret_other_inventory_purchase_items_log`
- **Evidence:** tables_delete: ['ret_other_invnetory_issue', 'ret_other_inventory_purchase_items_log']

> Method permanently deletes data from 2 table(s): ret_other_invnetory_issue, ret_other_inventory_purchase_items_log. Protected by transaction. Consider if soft-delete (is_deleted flag) would be safer.

**Your review:** ___

### RULE-026: `paymentmode_edit()` Performs Hard Delete on ret_billing_payment

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `paymentmode_edit()`
- **Tables:** `ret_billing_payment`
- **Evidence:** tables_delete: ['ret_billing_payment']

> Method permanently deletes data from 1 table(s): ret_billing_payment. Protected by transaction. Consider if soft-delete (is_deleted flag) would be safer.

**Your review:** ___

### RULE-027: `updateNewCustomer()` Performs Hard Delete on address

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `updateNewCustomer()`
- **Tables:** `address`
- **Evidence:** tables_delete: ['address']

> Method permanently deletes data from 1 table(s): address. ⚠️ NOT in a transaction — partial failure causes data loss. Consider if soft-delete (is_deleted flag) would be safer.

**Your review:** ___

### RULE-028: `update_issue_rcpt_payment()` Performs Hard Delete on ret_issue_rcpt_payment

- **Category:** DATA_INTEGRITY | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `update_issue_rcpt_payment()`
- **Tables:** `ret_issue_rcpt_payment`
- **Evidence:** tables_delete: ['ret_issue_rcpt_payment']

> Method permanently deletes data from 1 table(s): ret_issue_rcpt_payment. ⚠️ NOT in a transaction — partial failure causes data loss. Consider if soft-delete (is_deleted flag) would be safer.

**Your review:** ___

---

## STATUS Rules

### RULE-018: `billing()` Enforces Status Prerequisites

- **Category:** STATUS | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `billing()`
- **Tables:** `customerorderdetails`, `gift_card`, `ret_bill_details`, `ret_bill_old_metal_sale_details`, `ret_billing`
- **Evidence:** Status checks: bill_type=8 (Credit Collection), purchase_status=0, is_credit=1 (&nbsp;Yes), is_receipt_select=1, bill_type=15 (Approval Sales Bill), is_partial=0 (No)

> Method checks status values before performing updates: bill_type=8 (Credit Collection), purchase_status=0, is_credit=1 (&nbsp;Yes), is_receipt_select=1, bill_type=15 (Approval Sales Bill), is_partial=0 (No). This implies a state machine — certain operations are only valid when the record is in a specific state.

**Your review:** ___

### RULE-019: `issue()` Enforces Status Prerequisites

- **Category:** STATUS | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `issue()`
- **Tables:** `ret_issue_receipt`
- **Evidence:** Status checks: issue_type=3 (Advance Refund), issue_type=6 (Non Jewellery Expense)

> Method checks status values before performing updates: issue_type=3 (Advance Refund), issue_type=6 (Non Jewellery Expense). This implies a state machine — certain operations are only valid when the record is in a specific state.

**Your review:** ___

### RULE-020: `receipt()` Enforces Status Prerequisites

- **Category:** STATUS | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `receipt()`
- **Tables:** `ret_estimation_old_metal_sale_details`, `ret_issue_receipt`
- **Evidence:** Status checks: receipt_type=8 (Petty Cash Receipt  &nbsp;&nbsp;&nbsp;), bill_type=15 (Approval Sales Bill), receipt_type=1 (Credit Collection Receipt), receipt_type=2 (Advance Receipt), rct_type=2

> Method checks status values before performing updates: receipt_type=8 (Petty Cash Receipt  &nbsp;&nbsp;&nbsp;), bill_type=15 (Approval Sales Bill), receipt_type=1 (Credit Collection Receipt), receipt_type=2 (Advance Receipt), rct_type=2. This implies a state machine — certain operations are only valid when the record is in a specific state.

**Your review:** ___

### RULE-021: `cancel_bill()` Enforces Status Prerequisites

- **Category:** STATUS | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `cancel_bill()`
- **Tables:** `ret_bill_details`, `ret_billing`, `ret_estimation_items`, `ret_estimation_old_metal_sale_details`, `ret_issue_receipt`
- **Evidence:** Status checks: bill_type=9 (Order Delivery), bill_type=8 (Credit Collection), make_as_advance=2 (&nbsp;Chit Deposit), bill_type=5 (Order Advance), is_credit=1 (&nbsp;Yes), bill_type=7 (Sales Return)

> Method checks status values before performing updates: bill_type=9 (Order Delivery), bill_type=8 (Credit Collection), make_as_advance=2 (&nbsp;Chit Deposit), bill_type=5 (Order Advance), is_credit=1 (&nbsp;Yes), bill_type=7 (Sales Return). This implies a state machine — certain operations are only valid when the record is in a specific state.

**Your review:** ___

### RULE-022: `cancelTransactionRequest()` Enforces Status Prerequisites

- **Category:** STATUS | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `cancelTransactionRequest()`
- **Tables:** `ret_pos_requests`
- **Evidence:** Status checks: pos_req_status=1

> Method checks status values before performing updates: pos_req_status=1. This implies a state machine — certain operations are only valid when the record is in a specific state.

**Your review:** ___

### RULE-023: `cash_adjustment_cancel()` Enforces Status Prerequisites

- **Category:** STATUS | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `cash_adjustment_cancel()`
- **Tables:** `ret_cash_adjustment`
- **Evidence:** Status checks: status=2

> Method checks status values before performing updates: status=2. This implies a state machine — certain operations are only valid when the record is in a specific state.

**Your review:** ___

---

## CONDITIONAL Rules

### RULE-029: Bill Type Determines Business Logic (12 types used)

- **Category:** CONDITIONAL | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `billing()`, `cancel_bill()`, `getBillingDetails()`
- **Evidence:** bill_type checks across 3 methods

> The `bill_type` column drives major behavioral branching in 3 methods: billing(), cancel_bill(), getBillingDetails(). Types in use: 1=Sales, 3=Sales&Return, 4=Purchase, 5=Order Advance, 6=Advance, 7=Sales Return, 8=Credit Collection, 9=Order Delivery, 10=Chit Pre Close, 11=Repair Order Delivery, 13=Sales Transfer, 15=Approval Sales Bill. Each type may have different validation, calculation, and status rules.

**Your review:** ___

### RULE-030: Credit vs Cash Billing Has Separate Business Rules

- **Category:** CONDITIONAL | **Confidence:** 🟢 HIGH | **Status:** `[CONFIRM]`
- **Methods:** `billing()`, `receipt()`, `cancel_bill()`
- **Evidence:** is_credit/credit_status checks in 3 methods

> Credit billing is handled differently from cash billing in 3 methods: billing(), receipt(), cancel_bill(). Credit bills likely involve: deferred payment, credit limits, collection tracking, and approval workflows.

**Your review:** ___

---

## VALIDATION Rules

### RULE-031: File Upload Operations (9 methods)

- **Category:** VALIDATION | **Confidence:** 🟡 MEDIUM | **Status:** `[CONFIRM]`
- **Methods:** `upload_image()`, `base64ToFile()`, `set_image()`, `upload_img()`, `billing()`, `createNewCustomer()`
- **Evidence:** has_file_upload=True

> Methods handling file uploads: upload_image(), base64ToFile(), set_image(), upload_img(), billing(), createNewCustomer(), updateNewCustomer(), receipt(), UploadBilledTransaction(). Must validate: file type, size, extension on server-side. Check if uploaded files are stored with original names (security risk) or renamed.

**Your review:** ___
