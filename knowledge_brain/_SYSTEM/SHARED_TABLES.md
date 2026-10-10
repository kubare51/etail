# Shared Tables — Cross-Module Risk Map

> Tables used by 2+ modules. Changes to these tables affect multiple modules.
> Last updated: 2026-07-02 (Full Refresh — 28 modules scanned)
> Modules scanned: 28

---

## Risk Levels

- 🔴 **HIGH**: Table written by 2+ modules (data corruption risk)
- 🟡 **MEDIUM**: Table read by 2+ modules but written by only 1 (stale read risk)
- 🟢 **LOW**: Lookup/config table — read-only by all modules

---

## Shared Table Matrix — Retail Core

| Table | Owner Module | Read By | Written By | Risk | Key Columns |
|---|---|---|---|---|---|
| `ret_taging` | Tagging | Billing, Estimation, Reports, Stock Issue, Branch Transfer, Sales Transfer, Section Transfer, Old Metal, LOT, Purchase, Customer Order, Retail Dashboard, Catalog_Inventory | Tagging, Billing, Branch Transfer, Sales Transfer, Section Transfer, Stock Issue, Customer Order, Old Metal | 🔴 **CRITICAL** | `id_tagging`, `tag_status`, `current_branch`, `id_section`, `tag_process`, `id_orderdetails` |
| `ret_taging_status_log` | Tagging | Reports, Branch Transfer | Tagging, Billing, Branch Transfer, Sales Transfer, Section Transfer, Stock Issue, Customer Order | 🔴 HIGH | `id_tagging`, `status`, `entry_date` |
| `ret_billing` | Billing | Reports, Retail Dashboard, Sales Transfer, Old Metal, Other Inventory, Customer (delete guard), Chit Reports | Billing, Sales Transfer | 🔴 HIGH | `id_billing`, `bill_type`, `bill_no`, `bill_status` |
| `ret_bill_details` | Billing | Reports, Branch Transfer, Sales Transfer, Old Metal, Retail Dashboard | Billing, Branch Transfer, Sales Transfer | 🔴 HIGH | `id_bill_detail`, `current_branch`, `transferred_to_acc_stock` |
| `ret_estimation` | Estimation | Billing, Reports, Customer (delete guard), Section Transfer, Retail Dashboard | Estimation | 🟡 MED | `id_estimation`, `estbillid`, `esti_no`, `est_status` |
| `ret_estimation_items` | Estimation | Billing, Reports, Section Transfer | Estimation | 🟡 MED | `id_estimation_item`, `purchase_status` |
| `ret_nontag_item` | Non-Tag Inventory | Branch Transfer, Section Transfer, Old Metal, LOT, Reports | Billing, Branch Transfer, Section Transfer, Old Metal, LOT | 🔴 HIGH | `id_nontag_item`, `gross_wt`, `net_wt`, `no_of_piece` |
| `ret_nontag_item_log` | Non-Tag Inventory | Reports | Branch Transfer, Section Transfer, Old Metal, LOT | 🔴 HIGH | Movement log entries |
| `ret_section_nontag_item_log` | Non-Tag Inventory | Reports | Branch Transfer, Section Transfer, Old Metal | 🔴 HIGH | Section-level log |
| `ret_lot_inwards` | LOT | Tagging, Estimation, Stock Issue, Sales Transfer, Reports | LOT, Old Metal | 🔴 HIGH | `id_lot_inwards`, `lot_no`, `lot_from` |
| `ret_lot_inwards_detail` | LOT | Tagging, Estimation, Stock Issue, Reports | LOT, Old Metal | 🔴 HIGH | Lot line items |
| `ret_branch_transfer` | Branch Transfer | Stock Issue, Reports, Other Inventory, Retail Dashboard | Branch Transfer | 🟡 MED | `id_branch_transfer`, `transfer_status` |
| `ret_bill_old_metal_sale_details` | Billing | Old Metal, Reports, Branch Transfer | Billing, Branch Transfer, Old Metal | 🔴 HIGH | `is_pocketed`, `current_branch`, `is_transferred` |
| `ret_section_tag_status_log` | Tagging/Section | Reports | Branch Transfer, Section Transfer | 🔴 HIGH | Section movement log |
| `ret_purchase_order` | Purchase | LOT, Reports, Purchase, Retail Dashboard | Purchase | 🟡 MED | PO header |
| `ret_purchase_order_items` | Purchase | Reports, LOT, Retail Dashboard | Purchase | 🟡 MED | PO line items |
| `ret_purchase_items_log` | Purchase | Reports | Branch Transfer, Purchase | 🔴 HIGH | Transit/inward log |
| `ret_purchase_item_stock_summary` | Purchase | Reports | Old Metal, Purchase | 🔴 HIGH | Stock summary |
| `ret_journal` | Accounts | Reports | Billing | 🟡 MED | Journal entries |
| `ret_home_section_item` | Section | Reports | Section Transfer | 🟡 MED | Home counter stock |
| `ret_day_closing` | Settings | Billing, Estimation, Tagging, LOT, Stock Issue, Branch Transfer, Sales Transfer, Section Transfer, Customer Order, Other Inventory, Old Metal | Settings | 🟡 MED | `entry_date`, `is_day_closed` |
| `customerorder` | Customer Order | LOT, Reports, Customer (delete guard), Stock Issue, Retail Dashboard | Customer Order | 🟡 MED | Order header |
| `customerorderdetails` | Customer Order | LOT, Reports, Branch Transfer, Section Transfer | Customer Order, Branch Transfer | 🔴 HIGH | `current_branch`, `id_orderdetails` |
| `ret_issue_receipt` | Finance | Estimation, Reports, Retail Dashboard | Payment/Finance | 🟡 MED | Advance receipts |

---

## Shared Table Matrix — Chit/Scheme System

| Table | Owner Module | Read By | Written By | Risk | Key Columns |
|---|---|---|---|---|---|
| `customer` | Customer | Billing, Estimation, Payment, Account, Reports, Chit Dashboard, Collection App, Customer App, Other Inventory | Customer, Billing (PAN/Aadhaar), Estimation, Chit Dashboard, Customer App | 🔴 **CRITICAL** | `id_customer`, `mobile`, `pan`, `aadharid` |
| `scheme_account` | Account | Payment, Scheme, Reports, Chit Dashboard, Collection App, Customer App, Customer | Account, Collection App, Customer App, Chit Reports | 🔴 HIGH | `id_schemeacc`, `acc_no`, `status` |
| `payment` | Payment | Account, Scheme, Reports, Chit Dashboard, Collection App, Customer App, Chit Reports | Payment, Collection App, Customer App, Chit Reports (cancel) | 🔴 **CRITICAL** | `id_payment`, `payment_status`, `added_by` |
| `scheme` | Scheme | Payment, Account, Reports, Chit Dashboard, Collection App, Customer App | Scheme | 🟡 MED | `id_scheme`, `scheme_type` |
| `wallet_account` | Wallet | Account, Payment, Customer App, Collection App | Customer, Employee, Customer App, Collection App | 🔴 HIGH | Wallet balance |
| `wallet_transaction` | Wallet | Reports, Customer App | Payment, Account, Customer App, Collection App | 🔴 HIGH | Wallet tx log |
| `employee` | Employee | ALL modules (read) | Employee | 🟡 MED | `id_employee`, `emp_code`, `login_branches` |
| `employee_devices` | Employee | Collection App, Customer App | Collection App | 🔴 HIGH | Device registration |
| `otp` | Settings | Account, Payment | Account, Payment, Customer, Customer App, Collection App, Stock Issue | 🔴 HIGH | OTP records |
| `gift_card` | Gift | Customer (delete guard), Reports | Billing, Account | 🔴 HIGH | Gift voucher |
| `gift_mapping` | Other Inventory | Scheme/Chit | Other Inventory, Scheme | 🔴 HIGH | OI→scheme link |
| `kyc` / `customer_kyc` | Customer | Customer App | Customer, Chit Reports, Customer App | 🔴 HIGH | KYC documents |

---

## Shared Table Matrix — Configuration & Master Data

| Table | Owner Module | Read By | Written By | Risk | Key Columns |
|---|---|---|---|---|---|
| `chit_settings` | Settings/Masters | ALL modules | Settings only | 🟡 MED | Single-row global config (60+ flags) |
| `ret_settings` | Settings | ALL retail modules | Retail Settings only | 🟡 MED | Name-value config |
| `branch` | Settings | ALL modules | Settings only | 🟡 MED | `id_branch`, `is_ho` |
| `profile` | Settings | ALL modules, Employee | Settings only | 🟡 MED | Feature flags per role |
| `metal_rates` | Settings | Payment, Account, Reports, Customer App, Retail Dashboard | Settings only | 🟡 MED | Rate history |
| `ret_financial_year` | Settings | Billing, Estimation, Account, Payment, LOT | Settings only | 🟡 MED | `fin_year_code`, `fin_status` |
| `ret_product_master` | Catalog | ALL retail modules (read) | Catalog only | 🟢 LOW | Product hierarchy |
| `ret_category` | Catalog | ALL retail modules (read) | Catalog only | 🟢 LOW | Category hierarchy |
| `ret_design_master` | Catalog | ALL retail modules (read) | Catalog only | 🟢 LOW | Design names |
| `ret_purity` | Catalog | ALL retail modules (read) | Catalog only | 🟢 LOW | Purity definitions |
| `ret_stone` / `ret_stone_type` | Catalog | Tagging, Purchase, Reports | Catalog only | 🟢 LOW | Stone master |
| `ret_section` | Catalog | ALL retail modules | Catalog only | 🟢 LOW | Section/counter |
| `ret_karigar` | Catalog | Purchase, Reports, LOT, Old Metal, Other Inventory, Branch Transfer | Catalog only | 🟢 LOW | Vendor/goldsmith |
| `ret_uom` | Catalog | LOT, Other Inventory, Reports | Catalog only | 🟢 LOW | Unit of measurement |
| `country` / `state` / `city` | Settings | Customer, Employee, Old Metal, Other Inventory | Settings only | 🟢 LOW | Geography |
| `access` + `menu` | Masters | ALL modules via `get_access()` | Masters only | 🟡 MED | RBAC permissions |
| `company` | Masters | ALL modules | Masters only | 🟢 LOW | Company profile |

---

## High-Risk Tables (Written by 2+ Modules)

> [!CAUTION]
> These tables receive writes from multiple modules. A bug fix in one module
> can corrupt data for another. Always check ALL writing modules before changing
> INSERT/UPDATE logic.

| Table | Written By | What Each Writes |
|---|---|---|
| `ret_taging` | **8 modules**: Tagging (CRUD, retag), Billing (tag_status=1 sold), Branch Transfer (tag_status=4 transit, current_branch), Sales Transfer (tag_status, current_branch), Section Transfer (id_section, tag_status=14), Stock Issue (tag_status=7 issue/0 receipt), Customer Order (id_orderdetails), Old Metal (tag_process=1) |
| `customer` | Customer (full CRUD), Billing (PAN/Aadhaar update), Estimation (inline create/update), Chit Dashboard (customer_edit), Customer App (registration, profile update) |
| `payment` | Payment (full CRUD), Collection App (addPayment), Customer App (payment init + gateway update), Chit Reports (cancel_payment, updatePaymentDetails) |
| `scheme_account` | Account (full CRUD), Collection App (insert_schemeAcc), Customer App (createAccount_post), Chit Reports (updateAccountDetails) |
| `ret_nontag_item` | Billing (stock deduction), Branch Transfer (transit/download), Section Transfer (section move), Old Metal (receipt), LOT (non-tag insert) |
| `ret_bill_details` | Billing (create), Branch Transfer (current_branch update), Sales Transfer (insert for bill_type 13/14) |
| `ret_lot_inwards` | LOT (create), Old Metal (create for melting/testing/polishing receipt) |
| `wallet_account` | Customer (create on add), Employee (create on add), Customer App (create on registration), Collection App (create) |
| `wallet_transaction` | Payment (create), Account (create), Customer App (wallet debit), Collection App (wallet debit) |
| `otp` | Account, Payment, Customer, Customer App, Collection App, Stock Issue |

---

## Impact Lookup

When changing a table, check this list:

| If you change... | Check these modules... |
|---|---|
| `ret_taging` columns | Tagging, Billing, Estimation, Reports, Branch Transfer, Sales Transfer, Section Transfer, Stock Issue, Customer Order, Old Metal, LOT, Purchase, Catalog_Inventory, Retail Dashboard |
| `ret_billing` columns | Billing, Reports, Sales Transfer, Old Metal, Other Inventory, Retail Dashboard, Customer (delete guard) |
| `ret_estimation` columns | Estimation, Billing, Reports, Section Transfer, Retail Dashboard, Customer (delete guard) |
| `customer` columns | Customer, Billing, Estimation, Payment, Account, Reports, Chit Dashboard, Collection App, Customer App |
| `payment` columns | Payment, Account, Reports, Chit Dashboard, Collection App, Customer App, Chit Reports |
| `scheme_account` columns | Account, Payment, Scheme, Reports, Chit Dashboard, Collection App, Customer App, Customer |
| `chit_settings` columns | ALL modules — single-row config drives everything |
| `ret_settings` columns | ALL retail modules |
| `ret_nontag_item` columns | Billing, Branch Transfer, Section Transfer, Old Metal, LOT, Reports |
| `ret_lot_inwards` columns | LOT, Tagging, Estimation, Stock Issue, Sales Transfer, Reports, Old Metal |
| `ret_purchase_order` columns | Purchase, LOT, Reports, Retail Dashboard |
| `ret_day_closing` columns | Billing, Estimation, Tagging, LOT, Stock Issue, Branch Transfer, Sales Transfer, Section Transfer, Customer Order, Other Inventory, Old Metal |
| `ret_karigar` columns | Purchase, Reports, LOT, Old Metal, Other Inventory, Branch Transfer (10+ queries) |
| `profile` columns | ALL modules (RBAC), Employee, Login |
| `access` / `menu` columns | ALL modules (navigation + permissions) |
