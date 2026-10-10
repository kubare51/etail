# Root Application — System Brain Registry

> Last updated: 2026-07-02 (Initial Build)
> Scope: Customer-facing frontend controllers (5 BUILD + 12 LATER) + all 22 root models
> Path: `/application/controllers/` and `/application/models/`

---

## Architecture

The root `/application/` is the **customer-facing layer** — website, mobile app APIs, payment gateway callbacks. It runs on a separate CI3 instance from the admin panel (`/admin/application/`).

```
Root Application
├── Controllers (17 files, ~26K LOC)
│   ├── mobile_api.php      — Customer mobile app REST API
│   ├── paymt.php            — Payment gateway callbacks
│   ├── adminapp_api.php     — Collection agent app REST API
│   ├── tally_app_api.php    — Tally ERP integration API
│   ├── digigold.php         — Digital gold feature
│   ├── chitscheme.php       — Chit scheme frontend (LATER)
│   └── ... 11 more (LATER)
└── Models (22 files, ~42K LOC)
    ├── ret_tally_api_model.php  — 19,665 LOC (!)
    ├── mobileapi_model.php      — 5,342 LOC
    ├── payment_modal.php        — 3,196 LOC
    └── ... 19 more
```

---

## BUILD Controllers (5)

### `mobile_api.php` (7,957 LOC, 195 methods)

| Category | Details |
|---|---|
| **Purpose** | Customer mobile app REST API — authentication, scheme browsing, payment, wallet, KYC, gifts, video shopping |
| **Models** | `email_model`, `services_modal`, `login_model`, `registration_model`, `scheme_modal`, `payment_modal`, `sms_model`, `digigold_modal`, `vs_model` |
| **Key Method Groups** | |
| — Auth | `authenticate_post()`, `generateOTP_get()`, `verifyOTP_post()`, `resetPassword_post()`, `createCustomer_post()` |
| — Schemes | `getSchemes_get()`, `getScheme_get()`, `createAccount_post()`, `deleteScheme_get()` |
| — Payments | `chitPayment_post()`, `mobile_payment_post()`, `cashfreeResponse_post()`, `easebuzzResponse_post()`, `razorResponse_post()` |
| — Wallet | `get_customerWallets_get()`, `insertWalletTrans()` |
| — KYC | `uploadAadhar_post()`, `insertkyc_post()`, `readKyc_get()` |
| — Catalog | `ajax_getproducts_post()`, `productenquriy_post()`, `newArrDetail_get()` |
| **Risk** | 🔴 Critical — customer-facing, handles money, auth, personal data |

### `paymt.php` (4,666 LOC, 71 methods)

| Category | Details |
|---|---|
| **Purpose** | Payment gateway callbacks and processing — Atom, TechProcess, HDFC, Cashfree, Razorpay, Easebuzz |
| **Models** | `login_model`, `registration_model`, `payment_modal`, `scheme_modal`, `email_model`, `services_modal`, `syncapi_model`, `chitapi_model`, `sms_model`, `adminappapi_model` |
| **Key Method Groups** | |
| — Callbacks | `payment_success()`, `payment_failure()`, `payment_cancel()`, `atomReturnURL()`, `techProcessResponseURL()`, `hdfcRedirect()`, `cashfreeresponseURL()`, `razorresponseURL()` |
| — Payments | `paySubmit()`, `split_payment()`, `wallet_transactionDB()`, `insert_common_data()` |
| — Reports | `metal_report()`, `payment_history()`, `pdc_report()`, `chit_detail_report()` |
| **Risk** | 🔴 Critical — payment gateway integration, money flow |

### `adminapp_api.php` (4,471 LOC, 75 methods)

| Category | Details |
|---|---|
| **Purpose** | Collection agent mobile app API — customer registration, payment collection, offline sync |
| **Models** | `services_modal`, `email_model`, `adminappapi_model`, `mobileapi_model`, `payment_modal`, `scheme_modal`, `integration_model`, `sms_model` |
| **Key Method Groups** | |
| — Auth | `authenticate_post()`, `createCustomer_post()` |
| — Collection | `pay_collection_post()`, `mobile_payment_post()` |
| — Offline Sync | `syncOfflineCustomers_post()`, `syncOfflineAccPayments_post()`, `insertOfflinepayments()` |
| — Reports | `customer_ledger_post()`, `agentWise_monthly_reports_post()` |
| **Risk** | 🔴 Critical — agent payment collection, offline money sync |

### `tally_app_api.php` (595 LOC, 57 methods)

| Category | Details |
|---|---|
| **Purpose** | Tally ERP integration REST API — export sales/purchase/BT/receipts/payments as voucher lists |
| **Models** | `ret_tally_api_model` |
| **Key Method Groups** | |
| — Sales | `importsales_get()`, `importsalesreturn_get()`, `importsalesvouchersList_post()` |
| — Purchase | `importpurchase_get()`, `importsupplierpurchase_get()` |
| — Branch Transfer | `branchtransferdetails_get()`, `importbt_get()` |
| — Receipts/Payments | `importreceipts_get()`, `imporissuepayments_get()` |
| — GUID Tracking | `updateSalesguid_post()`, `updatePurchaseguid_post()` (prevents duplicate sync) |
| — P&L | `repprofitloss_get()`, `repprofitlosspcs_get()` |
| **Risk** | 🔴 Critical — ERP financial data export, P&L reporting |

### `digigold.php` (96 LOC, 5 methods)

| Category | Details |
|---|---|
| **Purpose** | Digital gold feature — data display, target setting, content management |
| **Models** | `digigold_modal` |
| **Key Methods** | `digidata_post()`, `set_digiTarget_post()`, `content_get()` |
| **Risk** | 🟡 Financial — digital gold configuration |

---

## Root Models — Full Registry (22 files)

### MEGA Model: `ret_tally_api_model.php` (19,665 LOC, 70 methods)

> **Largest single file in the entire codebase**

| Category | Details |
|---|---|
| **Purpose** | Tally ERP data extraction — every financial transaction type formatted for Tally import |
| **Tables** | `ret_tally_import_chitpayments`, `ret_cat_wise_avg_rates`, plus 20+ tables via complex JOINs |
| **Method Groups** | |
| — Sales | `getSalesNewVoucherList()`, `getrepairsalesList()` |
| — Purchase | `getPurchasaeNewVoucherList()`, `getpurchaseList()`, `getsupplierpurchaseList()` |
| — Returns | `getSalesReturnNewVoucherList()`, `getSupplierPurchaseReturnVoucherList()` |
| — Branch Transfer | `getbranchtransferInitiatedList()`, `getbranchtrasferDetails()` |
| — Material I/O | `getMaterialReceiptVoucherList()`, `getMaterialIssueVoucherList()` |
| — Payments | `getPaymentsList()`, `getsupplierpaymentList()`, `getChitUtlizationPayments()` |
| — Receipts | `getAllreceiptsList()`, `getAllimporissuepaymentsList()` |
| — P&L | `getProfitLossList()`, `getProfitLossPcsList()`, `getDiamondProfitLossList()` |
| — GUID Updates | `updateSalesVoucherGUID()`, `updatePurchaseVoucherGUID()` (16 update methods) |
| **Risk** | 🔴 Critical — entire financial reporting pipeline to Tally |

### High-Impact Models

| Model | LOC | Methods | Key Tables | Purpose | Risk |
|---|---|---|---|---|---|
| `mobileapi_model.php` | 5,342 | 199 | `customer`, `scheme_account`, `payment`, `wallet_transaction`, `metal_rates` | Mobile app data layer — ALL customer-facing data | 🔴 Critical |
| `payment_modal.php` | 3,196 | 116 | `payment`, `scheme_account`, `inter_wallet_*`, `customer_reg`, `transaction` | Payment processing, gateway response, receipt generation | 🔴 Critical |
| `adminappapi_model.php` | 2,326 | 56 | `employee`, `customer`, `scheme_account`, `wallet_transaction`, `payment_collection_remarks` | Agent app data layer | 🔴 Critical |
| `scheme_modal.php` | 2,028 | 104 | `scheme`, `scheme_account`, `payment`, `customer`, `customer_reg` | Scheme frontend — joining, closing, payment | 🔴 Financial |
| `commonapi_model.php` | 1,745 | 30 | `chit_settings`, `scheme_account` | Shared API utilities — rate calc, due dates, plan templates | 🔴 Financial |
| `registration_model.php` | 1,024 | 42 | `customer`, `address`, `scheme_account`, `payment`, `customer_reg` | Customer registration + account creation | 🟡 Data |
| `syncapi_model.php` (root) | 1,001 | 75 | `payment`, `transaction`, `scheme_account`, `customer_reg` | Sync data layer — offline upload/download | 🔴 Financial |

### Medium-Impact Models

| Model | LOC | Methods | Key Tables | Purpose | Risk |
|---|---|---|---|---|---|
| `scheme_modal_old.php` | 1,136 | 52 | Same as `scheme_modal.php` | **OLD** version — likely deprecated | 🟡 Legacy |
| `services_modal.php` | 685 | 25 | `wallet_account`, `product_enquiry` | Services frontend — offers, products, wallet | 🟡 |
| `digigold_modal.php` (root) | 668 | 18 | Digital gold tables | Digital gold data layer | 🟡 Financial |
| `chitapi_model.php` | 552 | 45 | `jil_new_customer`, `jil_customer_reg`, `jil_transaction`, `offline_chit_closing` | Chit API data layer — JIL tables | 🟡 Sync |
| `sms_model.php` | 479 | 8 | SMS gateway tables | SMS sending — Nettyfish, MSG91, SpearUC, WhatsApp | 🟡 Comms |
| `login_model.php` | 313 | 23 | `metal_rates` | Auth + login, metal rate lookups | 🟡 Security |

### Low-Impact Models

| Model | LOC | Methods | Purpose | Risk |
|---|---|---|---|---|
| `kyc_model.php` (root) | 236 | 7 | KYC frontend data | 🟡 Compliance |
| `dashboard_modal.php` | 231 | 15 | Customer dashboard data | 🟢 Reporting |
| `purchase_model.php` | 227 | 16 | Purchase frontend (advance booking) | 🟡 Financial |
| `email_model.php` (root) | 186 | 5 | Email sending (SMTP) | 🟡 Comms |
| `prestaapi_model.php` | 174 | 6 | PrestaShop product sync | 🟢 Integration |
| `vs_model.php` | 158 | 10 | Video shopping appointments | 🟢 Operational |
| `jwl_adv_pay_model.php` | 154 | 15 | Jewellery advance payment | 🟡 Financial |
| `integration_model.php` (root) | 102 | 7 | Integration data (Khimji) | 🟡 Integration |

---

## LATER Controllers (12)

| File | LOC | Purpose |
|---|---|---|
| `chitscheme.php` | 2,053 | Chit scheme frontend pages |
| `user.php` | 1,752 | User registration/login frontend |
| `jwl_adv_pay.php` | 1,112 | Jewellery advance payment |
| `purchase.php` | 1,056 | Purchase frontend |
| `services.php` | 831 | Customer services frontend |
| `cf_autodebit.php` | 761 | Cashfree auto-debit integration |
| `vs_appt_book.php` | 287 | Video shopping appointments |
| `presta_api.php` | 113 | PrestaShop integration |
| `dashboard.php` | 110 | Customer dashboard |
| `kyc.php` | 67 | Customer KYC frontend |
| `welcome.php` | 24 | Default page |
| `errors.php` | 20 | Error handler |
