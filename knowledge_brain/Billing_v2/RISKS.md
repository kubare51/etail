# Billing Module — Risk Assessment (Brain v2.3)

> Auto-inferred by `lca brain-build` on 2026-06-13
> **18 risks** detected from code analysis

---

## Risk Summary

| Severity | Count |
|---|---|
| 🔴 CRITICAL | 3 |
| 🟠 HIGH | 11 |
| 🟡 MEDIUM | 4 |

| Category | Count |
|---|---|
| COMPLEXITY | 10 |
| CONSISTENCY | 6 |
| DATA_LOSS | 2 |

---

## 🔴 CRITICAL Risks

### RISK-001: `billing()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 5177 lines, touches 49 tables, uses transaction, has OTP
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-011: `updateNewCustomer()` — DATA_LOSS

- **File:** `ret_billing_model.php`
- **Evidence:** DELETE from address without transaction
- **Impact:** If subsequent operations fail, deleted data from address is permanently lost with no rollback.
- **Fix:** Either: (a) wrap in transaction, or (b) use soft-delete (is_deleted flag) instead of hard DELETE.

### RISK-012: `update_issue_rcpt_payment()` — DATA_LOSS

- **File:** `ret_billing_model.php`
- **Evidence:** DELETE from ret_issue_rcpt_payment without transaction
- **Impact:** If subsequent operations fail, deleted data from ret_issue_rcpt_payment is permanently lost with no rollback.
- **Fix:** Either: (a) wrap in transaction, or (b) use soft-delete (is_deleted flag) instead of hard DELETE.

---

## 🟠 HIGH Risks

### RISK-002: `getEstimationDetails()` — COMPLEXITY

- **File:** `ret_billing_model.php`
- **Evidence:** 1701 lines, touches 14 tables
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-003: `receipt()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 1050 lines, touches 14 tables, uses transaction
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-004: `issue()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 897 lines, touches 6 tables, uses transaction
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-005: `getOtherEstimateItemsDetails()` — COMPLEXITY

- **File:** `ret_billing_model.php`
- **Evidence:** 821 lines, touches 19 tables
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-006: `bill_number_format()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 578 lines, touches 1 tables, uses transaction
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-013: `admin_ret_billing.php:8178()` — CONSISTENCY

- **File:** `admin_ret_billing.php`
- **Evidence:** Duplicate method: first at admin_ret_billing.php:8158, redefined at admin_ret_billing.php:8178. PHP uses the LAST definition.
- **Impact:** The first definition at admin_ret_billing.php:8158 is dead code. Anyone reading/fixing that version is wasting time — the runtime uses admin_ret_billing.php:8178 instead.
- **Fix:** Remove the dead definition at admin_ret_billing.php:8158 or merge them.

### RISK-014: `ret_billing_model.php:6664()` — CONSISTENCY

- **File:** `ret_billing_model.php`
- **Evidence:** Duplicate method: first at ret_billing_model.php:6481, redefined at ret_billing_model.php:6664. PHP uses the LAST definition.
- **Impact:** The first definition at ret_billing_model.php:6481 is dead code. Anyone reading/fixing that version is wasting time — the runtime uses ret_billing_model.php:6664 instead.
- **Fix:** Remove the dead definition at ret_billing_model.php:6481 or merge them.

### RISK-015: `ret_billing_model.php:6799()` — CONSISTENCY

- **File:** `ret_billing_model.php`
- **Evidence:** Duplicate method: first at ret_billing_model.php:6584, redefined at ret_billing_model.php:6799. PHP uses the LAST definition.
- **Impact:** The first definition at ret_billing_model.php:6584 is dead code. Anyone reading/fixing that version is wasting time — the runtime uses ret_billing_model.php:6799 instead.
- **Fix:** Remove the dead definition at ret_billing_model.php:6584 or merge them.

### RISK-016: `ret_billing_model.php:6989()` — CONSISTENCY

- **File:** `ret_billing_model.php`
- **Evidence:** Duplicate method: first at ret_billing_model.php:6949, redefined at ret_billing_model.php:6989. PHP uses the LAST definition.
- **Impact:** The first definition at ret_billing_model.php:6949 is dead code. Anyone reading/fixing that version is wasting time — the runtime uses ret_billing_model.php:6989 instead.
- **Fix:** Remove the dead definition at ret_billing_model.php:6949 or merge them.

### RISK-017: `ret_billing_model.php:8650()` — CONSISTENCY

- **File:** `ret_billing_model.php`
- **Evidence:** Duplicate method: first at ret_billing_model.php:8634, redefined at ret_billing_model.php:8650. PHP uses the LAST definition.
- **Impact:** The first definition at ret_billing_model.php:8634 is dead code. Anyone reading/fixing that version is wasting time — the runtime uses ret_billing_model.php:8650 instead.
- **Fix:** Remove the dead definition at ret_billing_model.php:8634 or merge them.

### RISK-018: `ret_billing_model.php:8909()` — CONSISTENCY

- **File:** `ret_billing_model.php`
- **Evidence:** Duplicate method: first at ret_billing_model.php:8886, redefined at ret_billing_model.php:8909. PHP uses the LAST definition.
- **Impact:** The first definition at ret_billing_model.php:8886 is dead code. Anyone reading/fixing that version is wasting time — the runtime uses ret_billing_model.php:8909 instead.
- **Fix:** Remove the dead definition at ret_billing_model.php:8886 or merge them.

---

## 🟡 MEDIUM Risks

### RISK-007: `cancel_bill()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 448 lines, touches 13 tables, uses transaction
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-008: `service_bill()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 371 lines, touches 3 tables, uses transaction, has OTP
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-009: `paymentmode_edit()` — COMPLEXITY

- **File:** `admin_ret_billing.php`
- **Evidence:** 313 lines, touches 2 tables, uses transaction
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.

### RISK-010: `getEstimationDetailsTags()` — COMPLEXITY

- **File:** `ret_billing_model.php`
- **Evidence:** 303 lines, touches 3 tables
- **Impact:** High bug density — methods this large are impossible to unit test, difficult to review, and prone to side effects. Any bug fix in this method risks breaking other flows.
- **Fix:** Refactor into smaller methods. Extract each bill_type branch or $type action into its own method.
