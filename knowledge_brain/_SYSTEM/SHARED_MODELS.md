# Shared Models — Cross-Module Function Map

> Models loaded by 2+ modules. Changing a method here affects ALL loading modules.
> Last updated: 2026-07-02 (Full Refresh — 28 modules scanned)

---

## admin_settings_model (Universal — ALL modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `get_access($url)` | ALL modules | `access`, `menu`, `profile` | 🔴 Universal — breaks ALL permission checks. ⚠️ Known SQLi (XMOD-108) |
| `settingsDB('get', $id, '')` | ALL chit modules | `chit_settings` | 🔴 Universal — drives all chit config |
| `getBranchDayClosingData($id_branch)` | Billing, Estimation, Tagging, LOT, Stock Issue, BT, ST, Section Transfer, Customer Order, OI, Old Metal | `ret_day_closing` | 🔴 High — wrong date stamps everything wrong |
| `getCompanyDetails($branch)` | ALL modules (PDF, OTP) | `company` | 🟡 Medium — blank PDFs, wrong OTP text |
| `profileDB($action, $id, $data)` | Settings, Section Transfer | `profile` | 🟡 Medium — role/feature flag changes |
| `get_service_by_code($code)` | Customer Order, Section Transfer, Estimation | `services` | 🟡 Medium — OTP/SMS service gating |
| `limitDB('get', $type)` | Customer, Scheme, Chit Settings | `chit_limit` | 🟡 Medium — registration limits |
| `discount_db($action, $id, $data)` | Scheme, Billing | `chit_discount` | 🟡 Medium — discount rules |
| `get_gstsettings()` | Scheme, Billing | `chit_settings` | 🟡 Medium — GST config |
| `menu_generation()` | Login controller | `menu`, `access` | 🔴 High — navigation for ALL users |
| `getAllBranchDCData()` | Sales Transfer, Branch Transfer | `ret_day_closing` | 🟡 Medium — returns array-of-arrays (not flat!) |
| `get_company_settings()` | Employee, Masters | `company`, `chit_settings` | 🟡 Medium — multi-company toggle |

---

## log_model (Universal — ALL modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `log_detail('insert', '', $log_data)` | ALL modules | `log`, `log_detail` | 🟡 Medium — audit trail. No error handling on failure in most callers |

---

## sms_model (11 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `sendSMS_MSG91($mobile, $msg, '', $dlt)` | Billing, Tagging, SI, BT, Customer, CusOrder, Payment, Account, CollApp, CustApp, ChitReports | External API (MSG91) | 🟡 Medium — OTP delivery. Gateway ID=1 |
| `sendSMS_Nettyfish($mobile, $msg, $type)` | Same as above | External API (Nettyfish) | 🟡 Medium — Gateway ID=2 |
| `sendSMS_SpearUC(...)` | Same as above | External API (SpearUC) | 🟡 Medium — Gateway ID=3 |
| `sendSMS_Asterixt(...)` | Same as above | External API | 🟡 Medium — Gateway ID=4 |
| `sendSMS_Qikberry(...)` | Same as above | External API | 🟡 Medium — Gateway ID=5 |
| `send_whatsApp_message(...)` | Customer App, Chit Reports, Customer | External API (WhatsApp Business) | 🟢 Low — supplementary |

---

## admin_usersms_model (7 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `get_SMS_data($service_id, $id)` | Billing, Customer, Account, Employee, Collection App, Customer App, Chit Reports | SMS templates table | 🟡 Medium — template changes affect SMS content |
| `send_whatsApp_message(...)` | Customer, Customer App | WhatsApp API | 🟢 Low |
| `check_noti_settings(...)` | Account | notification settings | 🟢 Low |
| `insert_sent_notification(...)` | Account | sent_notification | 🟢 Low |

---

## email_model (6 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `send_email($to, $subject, $body, ...)` | Account, Payment, Customer, Customer Order, Collection App, Customer App | External SMTP | 🟡 Medium — email failure non-blocking but user expects confirmation |

---

## ret_billing_model (5 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `code_number_generator($type, $branch, ...)` | Billing, Sales Transfer | `ret_billing`, `bill_no_format` | 🔴 High — bill number generation. Duplicate bill numbers if broken |
| `generateRefNo($type, $branch)` | Sales Transfer | `ret_billing` | 🟡 Medium — reference number |
| `get_branchwise_rate($branch)` | Sales Transfer, Customer Order (AJAX) | `ret_branchwise_rate`, `metal_rates` | 🔴 High — pricing basis |
| `get_FinancialYear()` | Sales Transfer, Purchase, Estimation | `ret_financial_year` | 🔴 High — FY code for numbering |
| `getAllTaxgroupItems()` (AJAX) | Estimation JS, Tagging JS | `ret_taxgroupitems` | 🟡 Medium — tax group data |
| `insertData($table, $data)` | ALL modules via common pattern | Any table | 🔴 Universal — generic insert |
| `updateData($table, $data, $where)` | ALL modules via common pattern | Any table | 🔴 Universal — generic update |

---

## payment_model / payment_modal (7 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `paymentDB($action, $id, $data)` | Payment, Account, Chit Reports | `payment` | 🔴 Critical — all payment CRUD |
| `addPayment($data)` | Collection App, Customer App | `payment` | 🔴 Critical — mobile payment creation |
| `generate_receipt_no($branch)` | Payment, Collection App | `payment` | 🔴 High — receipt numbering |
| `account_number_generator(...)` | Payment, Account, Collection App | `scheme_account` | 🔴 High — account numbering |
| `get_metalrate_by_branch($branch)` | Payment, Scheme, Account | `metal_rates`, `branch_rate` | 🟡 Medium — rate lookup |
| `getMetalRate()` | Collection App, Customer App | `metal_rates` | 🟡 Medium |
| `wallet_balance($acc_id)` | Collection App | `wallet_account` | 🟡 Medium |
| `get_due_date($acc_id)` | Payment, Collection App, Customer App | `payment`, `scheme` | 🟡 Medium |

---

## account_model (5 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `get_all_closed_accdetails($cust_id)` | Chit Dashboard | `scheme_account`, `payment` | 🟡 Medium — N+1 loop in dashboard |
| `get_all_closed_acccount($cust_id)` | Chit Dashboard | `scheme_account` | 🟢 Low |
| `scheme_summary_data(...)` | Chit Reports | `scheme_account`, `payment` | 🟡 Medium |
| `sch_acc_count()` | Chit Settings | `scheme_account` | 🟢 Low |
| `getSchemeAccountByCustomer($cust)` | Masters | `scheme_account` | 🟢 Low |

---

## scheme_model / scheme_modal (5 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `get_scheme($id)` | Account, Payment, Collection App, Customer App | `scheme` | 🟡 Medium |
| `join_existing($data)` | Collection App, Customer App | `scheme_account` | 🔴 High — account creation |
| `checkreferral_code($code)` | Collection App, Customer App | `agent` | 🟡 Medium |
| `allowMultipleChits($cust)` | Collection App, Customer App | `scheme_account`, `chit_settings` | 🟡 Medium |
| `verify_existing($data)` | Collection App, Customer App | `scheme_account` | 🟡 Medium |
| `scheme_count()` | Chit Settings, Masters | `scheme` | 🟢 Low |

---

## wallet_model / Wallet_model (6 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `get_wallet_acc_number($type)` | Customer, Employee, Collection App, Customer App | `wallet_account` | 🔴 High — next wallet number (race condition) |
| `wallet_accountDB('insert', $data)` | Customer, Employee, Customer App, Collection App | `wallet_account` | 🔴 High — wallet creation |
| `wallet_transactionDB($action, $data)` | Account, Payment, Customer App, Collection App | `wallet_transaction` | 🔴 High — wallet debit/credit |

---

## ret_catalog_model (4 modules)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `getActiveUOM()` | LOT, Catalog, Other Inventory (AJAX) | `ret_uom` | 🟢 Low |
| `getActiveProducts()` | LOT, Purchase (AJAX) | `ret_product_master` | 🟡 Medium |
| `get_deposit()` | Reports | Deposit tables | 🟡 Medium |

---

## mobileapi_model (2 modules — but critical)

| Method | Called By Modules | Tables Touched | Risk if Changed |
|---|---|---|---|
| `get_payment_details($acc_id)` | Collection App, Customer App | `scheme_account`, `payment`, `scheme` | 🔴 Critical — primary eligibility engine for mobile |
| `insert_schemeAcc($data)` | Collection App, Customer App | `scheme_account` | 🔴 Critical — mobile account creation |
| `branchesData()` | Collection App, Customer App | `branch` | 🟢 Low |

---

> [!WARNING]
> Before modifying ANY method in a shared model, grep ALL controllers to find callers:
> ```powershell
> Select-String -Pattern "method_name" -Path "c:\xampp\htdocs\etail_development_src\admin\application\controllers\*.php" -Recurse
> Select-String -Pattern "method_name" -Path "c:\xampp\htdocs\etail_development_src\admin\application\models\*.php" -Recurse
> ```
