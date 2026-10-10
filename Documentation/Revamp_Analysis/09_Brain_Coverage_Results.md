# Brain Coverage Validation — Results

> **Run date**: 2026-06-09
> **Codebase**: etail_v3 (source)
> **Brain date**: 2026-03-26 (Round 13)

---

## Executive Summary

| Metric | Total | Covered by Brain | Uncovered | Coverage % |
|---|---|---|---|---|
| **Admin Controllers** | 56 | 31 | **25** | **55%** |
| **Non-Admin Controllers** | 29 | ~0 | **29** | **0%** |
| **Models** | 68 | 26 | **42** | **38%** |
| **Helpers** | 9 | 0 | **9** | **0%** |
| **Libraries** | 8 | 0 | **8** | **0%** |
| **Core Overrides** | 4 | 0 | **4** | **0%** |
| **View Files** | 848 | partial | unknown | unknown |
| **JS Files** | 70 (22.4 MB) | partial | unknown | unknown |

### Function Count

| Category | Covered Functions | Uncovered Functions | Total |
|---|---|---|---|
| Admin Controllers (covered) | ~1,800+ (est.) | — | — |
| Admin Controllers (uncovered) | — | **358** | — |
| Non-Admin Controllers | — | **324** | — |
| Models (uncovered) | — | **1,157** | — |
| Helpers | — | **46** | — |
| Libraries | — | **77** | — |
| Core Overrides | — | **12** | — |
| **Total Uncovered Functions** | — | **1,974** | — |

> **1,974 functions in the codebase have ZERO brain documentation.**

---

## Uncovered Admin Controllers (25 files, 358 functions, 574 KB)

| Controller | Functions | Size | Purpose |
|---|---|---|---|
| `admin_ret_estimation_konika.php` | **64** | 110 KB | Variant estimation (Konika client) — largest uncovered |
| `admin_import_export.php` | **44** | 39 KB | Data import/export |
| `admin_print_template.php` | **41** | 109 KB | Print template management |
| `admin_ret_services.php` | **22** | 38 KB | Retail services |
| `admin_apm.php` | **21** | 34 KB | Purchase approval module |
| `admin_emp_incentive.php` | **17** | 27 KB | Employee incentive calculation |
| `admin_ret_supp_catalog.php` | **16** | 21 KB | Supplier catalog |
| `admin_agent.php` | **15** | 29 KB | Agent management |
| `admin_gift_vocuher.php` | **14** | 31 KB | Gift voucher management |
| `admin_ret_tag_parts.php` | **13** | 26 KB | Tag parts management |
| `admin_invoice_builder.php` | **13** | 8 KB | Invoice builder |
| `Admin_knowledge_base.php` | **13** | 15 KB | Knowledge base |
| `admin_ret_task.php` | **13** | 23 KB | Task management |
| `admin_wallet.php` | **11** | 35 KB | Digital wallet |
| `admin_stock_age_master.php` | **8** | 10 KB | Stock aging |
| `admin_adv_booking.php` | **7** | 2 KB | Advanced booking |
| `admin_ret_eda.php` | **6** | 9 KB | Estimation discount approval |
| `admin_irn_settings.php` | **6** | 4 KB | e-Invoice/IRN |
| `admin_ret_wishlist.php` | **4** | 6 KB | Customer wishlist |
| `admin_mails.php` | **5** | 2 KB | Email management |
| `admin_opening_master.php` | **3** | 10 KB | Opening balance master |
| `admin_pan_verification.php` | **2** | 1 KB | PAN verification |
| `admin_app_api_backup.php` | — | — | Backup file (skip) |
| `admin_ret_dashboard_api_backup.php` | — | — | Backup file (skip) |
| `admin_services_27_10_25.php` | — | — | Backup file (skip) |

**Excluding 3 backup files**: 22 active controllers with 358 functions are undocumented.

### Risk Assessment of Uncovered Controllers

| Risk Level | Controllers | Why |
|---|---|---|
| **HIGH** | `admin_print_template.php` (41 funcs, 109 KB) | Print templates affect invoicing — business logic in templates |
| **HIGH** | `admin_ret_estimation_konika.php` (64 funcs, 110 KB) | Client-specific estimation — may have divergent business rules |
| **HIGH** | `admin_import_export.php` (44 funcs, 39 KB) | Data import — SQL injection, data integrity risks |
| **HIGH** | `admin_wallet.php` (11 funcs, 35 KB) | Digital wallet — financial operations |
| **HIGH** | `admin_gift_vocuher.php` (14 funcs, 31 KB) | Gift vouchers — financial value, redemption logic |
| **MEDIUM** | `admin_emp_incentive.php` (17 funcs, 27 KB) | Incentive calculations — payroll impact |
| **MEDIUM** | `admin_apm.php` (21 funcs, 34 KB) | Purchase approval — workflow logic |
| **MEDIUM** | `admin_agent.php` (15 funcs, 29 KB) | Agent commission logic |
| **LOW** | Remaining 14 controllers | Smaller, lower-risk utilities |

---

## Uncovered Non-Admin Controllers (29 files, 324 functions, 542 KB)

| Controller | Functions | Size | Risk |
|---|---|---|---|
| `chit_transaction.php` | **41** | 46 KB | HIGH — financial transactions |
| `chitapi.php` | **29** | 47 KB | HIGH — external API, auth concerns |
| `sktm_syncapi.php` | **29** | 39 KB | HIGH — data sync between systems |
| `sync_wallet_api.php` | **22** | 67 KB | HIGH — wallet sync, financial data |
| `print_designer.php` | **22** | 40 KB | MEDIUM — print output |
| `sync_api.php` | **19** | 45 KB | HIGH — multi-branch sync |
| `chit_admin.php` | **17** | 26 KB | MEDIUM — admin panel |
| `videoshopping_appt.php` | **17** | 13 KB | LOW — appointment feature |
| `sync_erp_api.php` | **14** | 28 KB | HIGH — ERP integration |
| `khimji_services.php` | **10** | 28 KB | MEDIUM — client-specific services |
| `refer_customer.php` | **10** | 7 KB | LOW — referral |
| `test_data_gen.php` | **10** | 16 KB | LOW — test utility |
| `sync_erp_services.php` | **9** | 26 KB | HIGH — ERP sync |
| `kyc.php` | **9** | 5 KB | MEDIUM — KYC data |
| `error_monitor.php` | **9** | 8 KB | LOW — monitoring |
| `regression_test.php` | **6** | 18 KB | LOW — test utility |
| `Override_status.php` | **6** | 13 KB | HIGH — status override logic |
| `tally_app_api.php` | **6** | 2 KB | MEDIUM — accounting integration |
| `pos_reconcile.php` | **6** | 10 KB | HIGH — POS reconciliation |
| `crm_mas_scheme.php` | **5** | 3 KB | LOW — CRM |
| `reconstruct.php` | **5** | 16 KB | HIGH — data reconstruction |
| `intra_sync_services.php` | **5** | 10 KB | HIGH — intra-sync |
| `OrderAccept.php` | **5** | 4 KB | MEDIUM — vendor acceptance |
| `ImportController.php` | **4** | 9 KB | MEDIUM — data import |
| `Test_cash_flow.php` | **4** | 12 KB | LOW — test |
| `healthcheck.php` | **2** | 6 KB | LOW — health endpoint |
| `Report_tester.php` | **2** | 4 KB | LOW — test |
| `collection_report.php` | **0** | 6 KB | LOW — report helper |
| `welcome.php` | **1** | 1 KB | LOW — default page |

---

## Uncovered Models (42 files, 1,157 functions)

### Critical Uncovered Models (> 30 functions)

| Model | Functions | Size | Risk |
|---|---|---|---|
| `ret_app_api_model.php` | **176** | 260 KB | **CRITICAL** — entire mobile app backend |
| `ret_purchase_approval_model.php` | **147** | 128 KB | **HIGH** — purchase approval workflow |
| `syncapi_model.php` | **85** | 51 KB | **HIGH** — data sync across branches |
| `admin_usersms_model.php` | **78** | 73 KB | **HIGH** — SMS gateway (8+ copies mentioned in brain!) |
| `print_template_model.php` | **49** | 79 KB | **HIGH** — all print output generation |
| `pos_model.php` | **46** | 38 KB | **HIGH** — POS device integration |
| `chitapi_model.php` | **45** | 18 KB | **MEDIUM** — chit API backend |
| `chit_transaction_model.php` | **34** | 58 KB | **HIGH** — chit financial transactions |
| `ret_emp_incentive_model.php` | **32** | 40 KB | **MEDIUM** — payroll calculations |
| `agent_model.php` | **30** | 30 KB | **MEDIUM** — agent commissions |
| `services_model.php` | **30** | 44 KB | **MEDIUM** — services layer |

### Remaining Uncovered Models (< 30 functions)

| Model | Functions | Size |
|---|---|---|
| `gift_voucher_model.php` | 29 | 12 KB |
| `sktm_syncapi_model.php` | 28 | 13 KB |
| `ret_tag_parts_model.php` | 24 | 44 KB |
| `sync_walletapi_model.php` | 22 | 14 KB |
| `chitadmin_model.php` | 21 | 8 KB |
| `Advance_booking_model.php` | 21 | 30 KB |
| `ret_services_model.php` | 20 | 43 KB |
| `sync_erpapi_model.php` | 20 | 10 KB |
| `kyc_model.php` | 15 | 25 KB |
| `ret_eda_model.php` | 15 | 16 KB |
| `ret_supp_catalog_model.php` | 15 | 8 KB |
| `ret_task_model.php` | 15 | 8 KB |
| `cash_flow_model.php` | 13 | 24 KB |
| `print_designer_model.php` | 13 | 7 KB |
| `vs_appt_model.php` | 13 | 9 KB |
| `wallet_model.php` | 13 | 25 KB |
| `ret_stock_age_master_model.php` | 12 | 5 KB |
| `chit_series_model.php` | 11 | 9 KB |
| `sms_model.php` | 10 | 22 KB |
| `error_monitor_model.php` | 10 | 12 KB |
| `opening_master_model.php` | 10 | 7 KB |
| `digigold_modal.php` | 9 | 22 KB |
| `integration_model.php` | 8 | 7 KB |
| `ret_wishlist_model.php` | 7 | 7 KB |
| `irn_settings_model.php` | 6 | 4 KB |
| `log_model.php` | 6 | 5 KB |
| `email_model.php` | 5 | 4 KB |
| `catalog_model.php` | 5 | 8 KB |
| `curd_model.php` | 5 | 1 KB |
| `ret_tally_api_model.php` | 4 | 6 KB |
| `crm_mas_scheme_model.php` | 4 | 3 KB |

---

## Uncovered Helpers (9 files, 46 functions)

| Helper | Functions | Purpose |
|---|---|---|
| `konva_receipt_helper.php` | 14 | Canvas-based receipt generation |
| `receipt_helper.php` | 9 | Receipt formatting and output |
| `template_receipt_helper.php` | 8 | Template-based receipts |
| `client_config_helper.php` | 7 | Client-specific configuration |
| `customer_validation_helper.php` | 2 | Customer input validation |
| `metal_wgt_digit_helper.php` | 2 | Metal weight precision |
| `formsecret_helper.php` | 2 | Form CSRF/security |
| `feature_flag_helper.php` | 1 | Feature toggles |
| `test_mapping.php` | 1 | Test data mapping |

> **All 46 helper functions are shared across ALL controllers.** None are documented in any brain.

---

## Uncovered Libraries (8 files, 77 functions)

| Library | Functions | Purpose |
|---|---|---|
| `Datatables.php` | 28 | Server-side DataTables processing — used by ALL list views |
| `REST_Controller.php` | 24 | REST API framework — foundation for ALL API endpoints |
| `Format.php` | 14 | Data format conversion (JSON, XML, CSV) |
| `POS_Provider_Loader.php` | 4 | POS device provider abstraction |
| `hdfc.php` | 3 | HDFC payment gateway |
| `Zend.php` | 2 | Zend framework integration |
| `dompdf.php` | 1 | PDF generation |
| `Excel.php` | 1 | Excel export |

> **REST_Controller (24 functions)** and **Datatables (28 functions)** are foundational — every API and every list view depends on them.

---

## Uncovered Core Overrides (4 files, 12 functions)

| File | Functions | Purpose |
|---|---|---|
| `MY_Controller.php` | 3 | Base controller with auth, session, settings loading |
| `MY_Loader.php` | 4 | Custom view/model loading |
| `MY_DB_mysqli_driver.php` | 3 | Custom database driver (query modifications?) |
| `MY_Router.php` | 2 | Custom URL routing |

> **MY_Controller.php is loaded by EVERY request.** It likely contains auth middleware, session handling, and settings loading that every module depends on. This is completely undocumented.

---

## Coverage Gap Summary

### By Size (uncovered code volume)

| Category | Size | % of Total PHP |
|---|---|---|
| Uncovered admin controllers | 574 KB | — |
| Uncovered non-admin controllers | 542 KB | — |
| Uncovered models | ~1.3 MB (estimated) | — |
| Uncovered helpers | ~20 KB | — |
| Uncovered libraries | ~150 KB | — |
| Uncovered core | ~15 KB | — |
| **Total uncovered PHP** | **~2.6 MB** | **~4% of 66MB total** |

### By Function Count

```
Brain-covered functions:     ~2,500 (estimated from 28 modules)
Uncovered functions:          1,974 (verified count)
Total functions:             ~4,474
Actual coverage:             ~56%
```

### By Risk

| Risk Level | Uncovered Items | Key Concern |
|---|---|---|
| **CRITICAL** | `ret_app_api_model.php` (176 functions) | Entire mobile app backend — auth, payments, data |
| **CRITICAL** | `MY_Controller.php` (core override) | Auth/session loaded by every request |
| **CRITICAL** | `REST_Controller.php` (24 functions) | All API endpoints built on this |
| **HIGH** | Sync APIs (5 controllers, ~200 functions) | Multi-branch data sync |
| **HIGH** | Purchase Approval (147 functions) | Financial approval workflow |
| **HIGH** | SMS model (78 functions) | All communication logic |
| **HIGH** | POS model (46 functions) | Payment device integration |
| **HIGH** | Print Template (90 functions across ctrl+model) | All invoice/receipt output |
| **MEDIUM** | Wallet, Gift Voucher, Incentive | Financial features |
| **LOW** | Test utilities, health checks | Non-production code |

---

## Recommended Actions

### Immediate (must-do before revamp)

1. **Brain `MY_Controller.php`** — This is the foundation. Every bug diagnosis and feature understanding depends on knowing what happens before any controller method runs.

2. **Brain `ret_app_api_model.php`** (176 functions, 260 KB) — The entire mobile app backend. Likely has the same security patterns (no auth, IDOR, base64 passwords) seen in chit APIs.

3. **Brain `ret_purchase_approval_model.php`** (147 functions, 128 KB) — Purchase approval is a major workflow module completely undocumented.

4. **Brain all sync APIs** (sync_api, sync_erp_api, sync_wallet_api, sktm_syncapi) — 200+ functions handling cross-system data sync. Data integrity risks are extreme.

### Short-term (within 2 weeks)

5. **Brain `admin_usersms_model.php`** (78 functions) — The brain mentions "8+ copies of SMS gateway" but the actual SMS model is undocumented.

6. **Brain all POS-related files** (pos_model, POS_Provider_Loader, pos_reconcile) — POS integration affects billing.

7. **Brain `Datatables.php`** and `REST_Controller.php` — foundational libraries.

8. **Brain all helpers** — 46 shared functions used everywhere.

### During revamp (as each module is modernized)

9. Brain remaining controllers and models as you reach their wave in the modernization plan.
