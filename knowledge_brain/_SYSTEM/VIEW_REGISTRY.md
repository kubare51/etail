# Admin Views — System Brain Registry

> Last updated: 2026-07-02 (Initial Build)
> Scope: All 53 view directories, 851+ files in `admin/application/views/`
> Purpose: File registry, directory mapping, paired controller/model references

---

## Overview

Views in CI3 are PHP template files rendered by controllers. They contain:
- HTML structure with embedded PHP for dynamic data
- Form layouts (input fields, dropdowns, dataTables)
- Print/report templates (PDF, receipt, invoice)
- AJAX-loaded partial views
- Modal dialogs

**Views are passive** — they don't contain business logic or database queries. Their brain value is in **mapping which controller loads which view**, enabling faster navigation during bug fixes.

---

## View Directory Registry

### Directories with Module Brains (covered by module context)

| # | Directory | Files | Paired Module Brain | Controller(s) |
|---|---|---|---|---|
| 1 | `ret_reports/` | 194 | ✅ Ret_Reports | `admin_ret_reports.php` |
| 2 | `master/` | 159 | ✅ Masters | `admin_settings.php` (chit + retail) |
| 3 | `reports/` | 69 | ✅ Chit Reports | `admin_reports.php` |
| 4 | `ret_purchase/` | 64 | ✅ Purchase | `admin_ret_purchase.php`, `admin_ret_purchase_approval.php` |
| 5 | `billing/` | 39 | ✅ Billing | `admin_ret_billing.php` |
| 6 | `settings/` | 31 | ✅ Retail Settings | `admin_settings.php` |
| 7 | `scheme/` | 29 | ✅ Scheme | `admin_scheme.php` |
| 8 | `tagging/` | 22 | ✅ Tagging | `admin_ret_tagging.php` |
| 9 | `order/` | 19 | ✅ Customer Order | `admin_ret_order.php` |
| 10 | `other_inventory/` | 18 | ✅ Other Inventory | `admin_ret_other_inventory.php` |
| 11 | `payment/` | 15 | ✅ Payment | `admin_payment.php` |
| 12 | `dashboard/` | 15 | ✅ Chit Dashboard | `admin_dashboard.php` |
| 13 | `estimation/` | 14 | ✅ Estimation | `admin_ret_estimation.php` |
| 14 | `ret_metal_process/` | 11 | ✅ Old Metal Process | `admin_ret_old_metal_process.php` |
| 15 | `lot/` | 9 | ✅ LOT | `admin_ret_lot.php` |
| 16 | `branch_transfer/` | 8 | ✅ Branch Transfer | `admin_ret_brntransfer.php` |
| 17 | `sales_transfer/` | 6 | ✅ Sales Transfer | `admin_ret_sales_transfer.php` |
| 18 | `ret_stock_issue/` | 4 | ✅ Stock Issue | `admin_ret_stock_issue.php` |
| 19 | `ret_dashboard/` | 1 | ✅ Retail Dashboard | `admin_ret_dashboard.php` |
| 20 | `section_transfer/` | 1 | ✅ Section Transfer | `admin_ret_section_transfer.php` |

**Subtotal**: 538 files covered by existing module brains

---

### Directories WITHOUT Module Brains (newly covered by UNCOVERED_CONTROLLERS.md)

| # | Directory | Files | Paired Controller | Purpose |
|---|---|---|---|---|
| 21 | `include/` | 20 | Shared (all controllers) | Header, footer, sidebar, common includes |
| 22 | `layout/` | 11 | Shared (all controllers) | Page layout templates (admin panel frame) |
| 23 | `agent/` | 9 | `admin_agent.php` | Agent CRUD forms, settlement views |
| 24 | `sms/` | 8 | `admin_usersms.php` | SMS compose, template, notification views |
| 25 | `gift_voucher/` | 6 | `admin_gift_vocuher.php` | Gift voucher settings, issue, receipt |
| 26 | `advance_booking/` | 6 | `admin_adv_booking.php` | Advance booking plans, list |
| 27 | `print_templates/` | 5 | `admin_print_template.php` | Print template designer views |
| 28 | `wishlist/` | 4 | `admin_ret_wishlist.php` | Customer wishlist views |
| 29 | `catalog/` | 4 | `admin_catalog.php` | Chit catalog category/product views |
| 30 | `tag_parts/` | 3 | `admin_ret_tag_parts.php` | Tag parts splitting views |
| 31 | `log/` | 3 | Shared | Activity log views |
| 32 | `employee_product_pcs/` | 3 | Employee-related | Employee product piece tracking |
| 33 | `authenticate/` | 3 | `chit_admin.php` | Login, OTP, forgot password views |
| 34 | `vs_appointment/` | 3 | `videoshopping_appt.php` | Video shopping appointment views |
| 35 | `wallet_transaction/` | 3 | `admin_wallet.php` | Wallet transaction views |
| 36 | `notification/` | 3 | `admin_usersms.php` | Push notification views |
| 37 | `metal_rates/` | 3 | `admin_rateapi.php` | Metal rate display/update views |
| 38 | `pp/` | 3 | Unknown | Likely purchase payment or print preview |
| 39 | `opening_master/` | 2 | `admin_opening_master.php` | Cash opening balance views |
| 40 | `wallet_account/` | 2 | `admin_wallet.php` | Wallet account management views |
| 41 | `invoice_builder/` | 2 | `admin_invoice_builder.php` | Invoice builder views |
| 42 | `registration/` | 2 | Customer registration | Registration form views |
| 43 | `qrcode/` | 2 | QR code display | QR code generation views |
| 44 | `mails/` | 2 | `admin_mails.php` | Email template views |
| 45 | `print_designer/` | 2 | `print_designer.php` | Print designer canvas views |
| 46 | `error_monitor/` | 1 | `error_monitor.php` | Error dashboard view |
| 47 | `apm/` | 1 | `admin_apm.php` | APM dashboard view |
| 48 | `notice_board/` | 1 | `admin_ret_task.php` | Notice board view |
| 49 | `ret_task/` | 1 | `admin_ret_task.php` | Task management view |
| 50 | `irn_settings/` | 1 | `admin_irn_settings.php` | IRN settings view |
| 51 | `Denomination/` | 1 | Payment/Day close | Cash denomination view |
| 52 | `knowledge_base/` | 1 | `Admin_knowledge_base.php` | Knowledge base view |
| 53 | `day_close/` | 1 | `admin_ret_services.php` | Day close procedure view |

**Subtotal**: 131 files in directories without dedicated module brains

---

## View Naming Conventions

| Pattern | Meaning | Example |
|---|---|---|
| `*_form.php` | Data entry form | `purchase_form.php` |
| `*_list.php` | DataTable listing page | `agent_list.php` |
| `*_view.php` | Read-only detail view | `order_view.php` |
| `*_print.php` | Print template (PDF/receipt) | `billing_print.php` |
| `*_modal.php` | Modal dialog content | `customer_modal.php` |
| `*_ajax.php` | AJAX partial response | `dashboard_ajax.php` |
| `*_setting*.php` | Settings/config page | `pos_settings.php` |

---

## High-Risk Views (Print/Financial)

These views directly affect printed output and financial documents:

| Directory | Key Print Views | Risk |
|---|---|---|
| `billing/` | Invoice prints, receipt formats | 🔴 Customer-facing financial documents |
| `estimation/` | Estimation print templates | 🟡 Pre-billing documents |
| `ret_reports/` | Report templates (194 files!) | 🟡 Management reports |
| `payment/` | Payment receipts, settlement views | 🔴 Financial receipts |
| `lot/` | LOT cards, barcode labels | 🟡 Stock tracking |
| `branch_transfer/` | Transfer slips | 🟡 Stock movement |

---

## Shared Infrastructure Views

| Directory | Files | Purpose |
|---|---|---|
| `include/` | 20 | Headers, footers, sidebars, CSS/JS includes — loaded by ALL pages |
| `layout/` | 11 | Page wrapper templates — AdminLTE frame |

> ⚠️ Changes to `include/` or `layout/` affect EVERY page in the admin panel. Treat as system-wide impact.
