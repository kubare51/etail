# Uncovered Admin Models — System Brain Registry

> Last updated: 2026-07-02 (Initial Build)
> Scope: 41 admin models that do NOT have dedicated module brains
> Purpose: Method index, table dependencies, caller mapping for searchable brain coverage

---

## TIER 1 — High-Impact Models (>1000 LOC)

### `ret_purchase_approval_model.php` (2,392 LOC, 146 methods)

| Category | Details |
|---|---|
| **Purpose** | Purchase order lifecycle — PO creation, approval, QC, halmarking, GRN, karigar metal issue, MD approval |
| **Callers** | `admin_ret_purchase_approval.php`, `admin_ret_dashboard_api.php`, `OrderAccept.php` |
| **Tables** | `customerorder`, purchase orders, purchase items, halmarking, GRN, karigar, wallet |
| **Key Method Groups** | |
| — PO Management | `generatePurNo()`, `get_purchase_order_Details()`, `get_pur_order_details()` |
| — QC/Halmarking | `get_purchase_qc_details()`, `generate_HalmarkingRefNo()`, `get_halmarking_items()` |
| — GRN | `generate_grn_refno()`, `ajax_getGrnentrydetails()`, `get_grn_details()` |
| — Karigar | `get_karigar_details()`, `get_karigar_pending_orders()`, `get_KarigarMetal_issue()` |
| — MD Approval | `get_md_pending_orders()`, `md_approve_order()`, `md_reject_order()` |
| — Payment | `update_po_paymentData()`, `get_supplier_pay_details()`, `purchase_payment_details()` |
| **Risk** | 🔴 Financial — controls purchase costs, stock intake, supplier payments |

### `ret_dashboard_api_model.php` (1,879 LOC, 45 methods)

| Category | Details |
|---|---|
| **Purpose** | Dashboard data aggregation — sales, stock, purchase, financial metrics |
| **Callers** | `admin_ret_dashboard_api.php` |
| **Tables** | `customerorder`, `ret_purchase_order`, plus billing/estimation/catalog via joins |
| **Key Methods** | `get_dashboard_sales_glance()`, `get_top_selling()`, `get_monthly_sales()`, `get_branch_sales()`, `get_product_stock()`, `get_rate_cut_profit_loss()` |
| **Risk** | 🟡 Reporting — read-only but critical for business decisions |

### `admin_usersms_model.php` (1,818 LOC, 72 methods)

| Category | Details |
|---|---|
| **Purpose** | SMS/notification data layer — templates, services, customer targeting, WhatsApp |
| **Callers** | `admin_usersms.php` (primary), 7+ other modules via SMS notifications |
| **Tables** | `chit_settings`, `sent_notifications`, services, customer, scheme |
| **Key Method Groups** | |
| — Service CRUD | `insert_service()`, `update_service()`, `delete_service()`, `get_sms_services()` |
| — Notification | `insert_notification()`, `update_notification()`, `notification_on_off()` |
| — Customer Targeting | `get_customers_by_scheme()`, `get_duecustomer()`, `get_allcustomersms_list()` |
| — WhatsApp | `send_whatsApp_message()` |
| **Risk** | 🟡 Communication — controls all outbound messaging |

### `print_template_model.php` (1,413 LOC, 45 methods)

| Category | Details |
|---|---|
| **Purpose** | Print template CRUD, rendering engine, formula evaluation, placeholder system |
| **Callers** | `admin_print_template.php`, `print_designer.php`, `admin_invoice_builder.php` |
| **Tables** | `print_templates`, `print_template_placeholders`, `print_template_mc_va_config`, `print_template_defaults`, `print_template_computed_vars` |
| **Key Methods** | `render()`, `render_string_template()`, `evaluate_formula()`, `_tpl_eval_expr()`, `get_placeholders_v2()` |
| **Risk** | 🟡 Visual — template rendering, formula engine could produce wrong values |

### `syncapi_model.php` (1,265 LOC, 85 methods)

| Category | Details |
|---|---|
| **Purpose** | Transaction/customer sync data layer — CRUD for offline data upload, revert handling |
| **Callers** | `sync_api.php` |
| **Tables** | `payment`, `transaction`, `scheme_account`, `customer_reg`, `revert_approve_log`, `new_customer`, `offline_payments` |
| **Key Methods** | `insertPayment()`, `updatePayment()`, `insert_transaction()`, `insert_CustomerReg()`, `revertPayment()`, `checkTransExists()` |
| **Risk** | 🔴 Financial — sync data integrity |

### `chit_transaction_model.php` (1,092 LOC, 34 methods)

| Category | Details |
|---|---|
| **Purpose** | Payment gateway response processing, receipt generation, incentive calculation |
| **Callers** | `chit_transaction.php` |
| **Tables** | `payment`, `scheme_account`, `customer`, `payment_mode` |
| **Key Methods** | `updateGatewayResponse()`, `generate_receipt_no()`, `generate_account_no()`, `calcIncentiveAmt()`, `onPayTranStream()` |
| **Risk** | 🔴 Financial — payment recording + receipt generation |

---

## TIER 2 — Medium-Impact Models (500-1000 LOC)

| Model | LOC | Methods | Tables | Caller(s) | Risk |
|---|---|---|---|---|---|
| `services_model.php` | 984 | 30 | `daily_collection`, `inter_wallet_trans*`, `wallet_transaction`, `scheme_account`, `payment` | `admin_services.php` | 🟡 Financial — daily collection, wallet |
| `ret_tag_parts_model.php` | 906 | 24 | `ret_taging`, `ret_tag_parts_detail`, `ret_tag_parts_master`, `ret_taging_status_log`, `ret_taging_stone` | `admin_ret_tag_parts.php` | 🔴 Financial — tag weight splitting |
| `ret_emp_incentive_model.php` | 870 | 32 | Incentive config tables | `admin_emp_incentive.php` | 🟡 Financial — incentive calculations |
| `pos_model.php` | 774 | 46 | `ret_pos_device_list`, `ret_pos_providers`, `ret_pos_requests`, `pos_payment_sessions` | `admin_pos.php`, `pos_reconcile.php` | 🔴 Financial — POS payments |
| `ret_services_model.php` | 765 | 20 | `services`, stock balance tables | `admin_ret_services.php` | 🟡 Stock — day close, stock balance |
| `agent_model.php` | 701 | 30 | `ly_settlement_request`, `agent`, `ly_influencer_settings` | `admin_agent.php` | 🟡 Financial — agent settlements |
| `Advance_booking_model.php` | 597 | 21 | `chit_settings`, `ct_advance_payment` | `admin_adv_booking.php` | 🟡 Financial — advance payments |
| `cash_flow_model.php` | 519 | 13 | `opening_master`, `branch` | `Test_cash_flow.php` | 🔴 Financial — cash flow calculations |
| `kyc_model.php` | 505 | 15 | `kyc_settings`, `kyc_rules`, `kyc`, `kyc_master`, `kyc_attribute` | `kyc.php` | 🟡 Compliance — KYC documents |

---

## TIER 3 — Low-Impact Models (<500 LOC)

| Model | LOC | Methods | Tables | Caller(s) | Risk |
|---|---|---|---|---|---|
| `admin_report_model.php` | 457 | 15 | `cust_enquiry`, `cust_enquiry_status` | `admin_reports.php` | 🟢 Reporting |
| `ret_eda_model.php` | 393 | 15 | Billing/purchase tables | `admin_ret_eda.php` | 🟡 Reporting |
| `sktm_syncapi_model.php` | 375 | 28 | `scheme_account`, `customer_reg`, `payment`, `transaction` | `sktm_syncapi.php` | 🟡 Sync (SKTM-specific) |
| `digigold_modal.php` (admin) | 371 | 9 | Digital gold tables | `admin_wallet.php` | 🟡 Financial |
| `sync_walletapi_model.php` | 325 | 22 | `inter_wallet_trans*`, `wallet_transaction`, `inter_sync_wallet` | `sync_wallet_api.php` | 🔴 Financial — wallet sync |
| `error_monitor_model.php` | 325 | 11 | Error log tables | `error_monitor.php` | 🟢 Monitoring |
| `gift_voucher_model.php` | 304 | 29 | `ret_bill_gift_voucher_settings` | `admin_gift_vocuher.php` | 🟡 Financial |
| `sync_erpapi_model.php` | 301 | 20 | `customer_reg`, `transaction`, `scheme_account`, `payment` | `sync_erp_api.php` | 🔴 Financial — ERP sync |
| `chitadmin_model.php` | 274 | 21 | Auth/login tables | `chit_admin.php` | 🟡 Security |
| `ret_supp_catalog_model.php` | 220 | 15 | `supp_catalogue_images` | `admin_ret_supp_catalog.php` | 🟢 Catalog |
| `ret_task_model.php` | 204 | 14 | Task/notice tables | `admin_ret_task.php` | 🟢 Operational |
| `catalog_model.php` | 198 | 5 | Category/product tables | `admin_catalog.php` | 🟢 Catalog |
| `vs_appt_model.php` | 191 | 13 | `appt_request`, `appt_emp_allot`, `appt_request_log` | `videoshopping_appt.php` | 🟢 Operational |
| `ret_wishlist_model.php` | 187 | 7 | Wishlist tables | `admin_ret_wishlist.php` | 🟢 CRM |
| `print_designer_model.php` | 179 | 13 | Template tables | `print_designer.php` | 🟢 Visual |
| `ret_section_transfer_model.php` | 176 | 14 | Section transfer tables | `admin_ret_section_transfer.php` | 🟡 Stock |
| `opening_master_model.php` | 174 | 10 | `branch` | `admin_opening_master.php` | 🟡 Financial |
| `chit_series_model.php` | 166 | 11 | Chit series tables | Chit modules | 🟡 Financial |
| `integration_model.php` | 155 | 8 | Integration tables | `khimji_services.php` | 🟡 Integration |
| `log_model.php` | 146 | 6 | Log tables | `admin_ret_tag_parts.php`, shared | 🟢 Logging |
| `ret_stock_age_master_model.php` | 139 | 12 | Stock age tables | `admin_stock_age_master.php` | 🟢 Master |
| `irn_settings_model.php` | 117 | 6 | `ret_settings`, `branch` | `admin_irn_settings.php` | 🟡 Tax |
| `email_model.php` | 108 | 5 | None (sends email) | 6+ controllers | 🟡 Communication |
| `ret_tally_api_model.php` (admin) | 102 | 4 | Branch transfer/sales tables | `tally_app_api.php` | 🟡 ERP |
| `crm_mas_scheme_model.php` | 82 | 4 | `scheme` | `crm_mas_scheme.php` | 🟢 Master |
| `curd_model.php` | 44 | 5 | Generic (any table) | Multiple | 🟢 Utility |

---

## SKIPPED (Backups)

| File | LOC | Reason |
|---|---|---|
| `ret_app_api_model_backup.php` | 4,694 | Backup of `ret_app_api_model.php` |
| `ret_dashboard_api_model_backup.php` | 1,550 | Backup of dashboard API model |
