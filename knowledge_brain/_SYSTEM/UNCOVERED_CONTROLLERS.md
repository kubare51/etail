# Uncovered Admin Controllers — System Brain Registry

> Last updated: 2026-07-02 (Initial Build)
> Scope: 55 admin controllers that do NOT have dedicated module brains
> Purpose: Method index, model dependencies, route mapping for searchable brain coverage

---

## Registry Format

Each entry: `File | LOC | Methods | Models Loaded | Risk Category`

---

## TIER 1 — High-Risk Financial/Sync Controllers (>1000 LOC)

### `admin_ret_purchase_approval.php` (2,353 LOC, 29 methods)

| Category | Details |
|---|---|
| **Purpose** | Purchase order approval workflow, QC, karigar management, GRN processing, MD dashboard |
| **Models** | `email_model` |
| **Key Methods** | `purchase()`, `approvalstock()`, `generate_lot()`, `generate_lot_from_halmarking()`, `update_qc_status()`, `grn_invoice()`, `md_dashboard()` |
| **Tables (via model)** | Purchase orders, purchase items, karigar, halmarking, GRN |
| **Risk** | 🔴 Financial — approval controls purchase costs + stock entry |

### `admin_usersms.php` (2,183 LOC, 50 methods)

| Category | Details |
|---|---|
| **Purpose** | SMS/Email/Push notification management, group messaging, scheme-based targeting |
| **Models** | `sms_model` |
| **Key Methods** | `send_sms()`, `sendsms_allcustomer()`, `sendsms_selectcustomer()`, `send_email()`, `create_pushnotification()`, `due_notification()`, `onesignalNotificationToAll()` |
| **Risk** | 🟡 Communication — bulk messaging, notification templates |

### `admin_print_template.php` (1,912 LOC, 41 methods)

| Category | Details |
|---|---|
| **Purpose** | Print template designer (GrapesJS v2), block compilation, live preview, MC/VA config |
| **Models** | `print_template_model` |
| **Key Methods** | `designer()`, `designer_v2()`, `save_design()`, `save_design_v2()`, `preview_live()`, `preview_live_v2()`, `_compile_blocks()`, `save_computed_var()` |
| **Risk** | 🟡 Visual — receipt/invoice printing |

### `sync_wallet_api.php` (1,594 LOC, 22 methods)

| Category | Details |
|---|---|
| **Purpose** | REST API for wallet sync (mobile apps), offline transaction upload |
| **Models** | None explicit (likely uses REST library) |
| **Key Methods** | `walletTransByStatus_post()`, `newWalletTrans_post()`, `insertWalletTrans_post()`, `readSyncWallet_post()`, `updateSyncWallet_post()` |
| **Risk** | 🔴 Financial — wallet money sync between offline/online |

### `chitapi.php` (1,536 LOC, 29 methods)

| Category | Details |
|---|---|
| **Purpose** | External API for chit transactions, registrations, offline payment upload |
| **Models** | None explicit (REST controller) |
| **Key Methods** | `transactions_post()`, `registrations_post()`, `insertNewCustomer_post()`, `insertOfflinePayment_post()`, `insertClosedAccData_post()` |
| **Risk** | 🔴 Financial — chit payment sync API |

### `admin_ret_dashboard_api.php` (1,218 LOC, 53 methods)

| Category | Details |
|---|---|
| **Purpose** | REST API for retail dashboard — sales glance, top sellers, branch comparison, financial status |
| **Models** | `ret_purchase_approval_model`, `email_model`, `ret_catalog_model` |
| **Key Methods** | `get_Sales_glance_post()`, `get_top_selling_post()`, `get_monthly_sales_post()`, `get_branch_comparison_post()`, `get_FinancialStatus_post()`, `get_rate_cut_profit_loss_post()`, `md_approve_order_post()` |
| **Risk** | 🟡 Reporting — dashboard data, MD approval |

### `sync_api.php` (1,201 LOC, 19 methods)

| Category | Details |
|---|---|
| **Purpose** | General sync API for mobile/POS — transaction/customer/payment sync |
| **Models** | None explicit (REST controller) |
| **Key Methods** | `transactionsByStatus_post()`, `insertTransactions_post()`, `customersByStatus_post()`, `insertCustomers_post()`, `uploadOfflineData_post()` |
| **Risk** | 🔴 Financial — core data sync API |

### `admin_import_export.php` (1,164 LOC, 38 methods)

| Category | Details |
|---|---|
| **Purpose** | Bulk data import/export — Excel import of accounts, customers; export to Excel |
| **Models** | `admin_settings_model`, `customer_model` |
| **Key Methods** | `import_account()`, `import_customer()`, `export_to_excel()`, `parse_scheme_account()`, `excel_to_PHP()`, `upload_data()` |
| **Risk** | 🟡 Data integrity — bulk imports can corrupt data |

### `chit_transaction.php` (1,044 LOC, 40 methods)

| Category | Details |
|---|---|
| **Purpose** | Payment gateway webhook processing — Cashfree, Easebuzz, Razorpay verification |
| **Models** | None explicit |
| **Key Methods** | `easebuzz_webhook()`, `payment_verify()`, `webhook()`, `processCashfreeTxns()`, `processEasebuzzTxns()`, `processRazorpyTxns()`, `payment_update()` |
| **Risk** | 🔴 Financial — payment gateway integration |

---

## TIER 2 — Medium-Risk Controllers (500-1000 LOC)

### `admin_ret_services.php` (964 LOC, 22 methods)

| Category | Details |
|---|---|
| **Purpose** | Retail services — day close, stock balance reconciliation, OTP approval |
| **Models** | `email_model` |
| **Key Methods** | `dayClose()`, `resetDayClose()`, `autoDayClose()`, `stock_balance()`, `stock_balance_nontag()`, `required_otp_approval()` |
| **Risk** | 🔴 Financial — day close affects all reporting |

### `admin_pos.php` (886 LOC, 32 methods)

| Category | Details |
|---|---|
| **Purpose** | POS terminal integration — device management, PhonePe callback, settlement, reconciliation |
| **Models** | `pos_model`, `ret_billing_model`, `admin_settings_model` |
| **Key Methods** | `posSettings()`, `savePOSDevice()`, `phonepeCallback()`, `posSettlementSummary()`, `runReconciliation()`, `orphanDetection()` |
| **Risk** | 🔴 Financial — POS payment processing |

### `print_designer.php` (800 LOC, 22 methods)

| Category | Details |
|---|---|
| **Purpose** | Print designer UI — template CRUD, variable substitution, PDF export |
| **Models** | `print_designer_model`, `print_template_model`, `ret_estimation_model`, `ret_billing_model` |
| **Key Methods** | `preview()`, `preview_pdf()`, `export_pdf()`, `_render_to_mpdf()`, `_substitute_vars()` |
| **Risk** | 🟡 Visual — print output |

### `admin_wallet.php` (796 LOC, 11 methods)

| Category | Details |
|---|---|
| **Purpose** | Wallet account management — settings, transactions, categories |
| **Models** | None explicit |
| **Key Methods** | `wallet_setting()`, `wallet_account()`, `wallet_transaction()`, `wallet_category()` |
| **Risk** | 🟡 Financial — wallet management |

### `admin_gift_vocuher.php` (775 LOC, 14 methods)

| Category | Details |
|---|---|
| **Purpose** | Gift voucher CRUD — settings, issue, receipt |
| **Models** | None explicit |
| **Key Methods** | `gift_voucher_settings()`, `gift_master()`, `gift_issue()`, `get_issue_receipt()` |
| **Risk** | 🟡 Financial — gift voucher values |

### `sync_erp_api.php` (753 LOC, 14 methods)

| Category | Details |
|---|---|
| **Purpose** | ERP sync outbound API — push scheme/payment data to external ERP |
| **Models** | None explicit (REST controller) |
| **Key Methods** | `insertCusSchemes_post()`, `updateCusSchemes_post()`, `insertPayments_post()`, `cancelPayments_post()` |
| **Risk** | 🔴 Financial — ERP payment sync |

### `admin_apm.php` (711 LOC, 21 methods)

| Category | Details |
|---|---|
| **Purpose** | Application Performance Monitoring dashboard — slow queries, errors, memory hogs |
| **Models** | None explicit |
| **Key Methods** | `_getSlowEndpoints()`, `_getSlowQueries()`, `_getErrors()`, `_getMemoryHogs()`, `_getNPlusOne()`, `export_csv()`, `check_alerts()` |
| **Risk** | 🟢 Monitoring — no data modification |

### `admin_agent.php` (635 LOC, 15 methods)

| Category | Details |
|---|---|
| **Purpose** | Sales agent management — CRUD, settlement, referral accounts |
| **Models** | None explicit |
| **Key Methods** | `agent_form()`, `agent_post()`, `agent_settlement()`, `agent_settlement_list()`, `agent_referral_account()` |
| **Risk** | 🟡 Financial — agent settlements |

### `chit_admin.php` (633 LOC, 17 methods)

| Category | Details |
|---|---|
| **Purpose** | Chit administration — login, OTP, device check, forgot password |
| **Models** | `email_model` |
| **Key Methods** | `login()`, `authenticate()`, `forgetAdmin()`, `otpConformation()`, `checkDevice()` |
| **Risk** | 🟡 Security — authentication |

### `admin_ret_supp_catalog.php` (615 LOC, 16 methods)

| Category | Details |
|---|---|
| **Purpose** | Supplier catalog management — image upload, product linking |
| **Models** | `ret_supp_catalog_model` |
| **Key Methods** | `supplier_catalog()`, `save_image()`, `upload_img()`, `delete_image()` |
| **Risk** | 🟢 Catalog — no financial impact |

### `admin_ret_task.php` (613 LOC, 13 methods)

| Category | Details |
|---|---|
| **Purpose** | Task management system — assignment, attachments, notice board |
| **Models** | None explicit |
| **Key Methods** | `task()`, `task_attachement_upload()`, `update_status()`, `notice_board()` |
| **Risk** | 🟢 Operational — task tracking |

### `sync_erp_services.php` (575 LOC, 9 methods)

| Category | Details |
|---|---|
| **Purpose** | ERP sync services — push new scheme/payment data via cURL |
| **Models** | `account_model`, `email_model` |
| **Key Methods** | `sendNewSchAndPay()`, `sendNewPay()`, `erpCurl()`, `getBearerToken()` |
| **Risk** | 🔴 Financial — ERP data push |

### `admin_ret_tag_parts.php` (548 LOC, 13 methods)

| Category | Details |
|---|---|
| **Purpose** | Tag parts (weight splitting) — auto-part, manual revert, EOD queue |
| **Models** | `ret_tag_parts_model`, `ret_tag_model`, `admin_settings_model`, `log_model` |
| **Key Methods** | `calculate_parts()`, `save()`, `manual_revert()`, `get_eod_queue()`, `batchCheckTagEstimation()` |
| **Risk** | 🔴 Financial — tag weight affects billing |

### `khimji_services.php` (535 LOC, 10 methods)

| Category | Details |
|---|---|
| **Purpose** | Client-specific (Khimji) integration services — account/receipt generation, API sync |
| **Models** | `email_model` |
| **Key Methods** | `generateAcNoOrReceiptNo()`, `regCusInKhimji()`, `khimji_curl()` |
| **Risk** | 🟡 Client-specific — integration layer |

### `admin_emp_incentive.php` (532 LOC, 17 methods)

| Category | Details |
|---|---|
| **Purpose** | Employee incentive configuration — rules, ranges, reports, payment marking |
| **Models** | `ret_stock_age_master_model` |
| **Key Methods** | `config_form()`, `config_post()`, `config_batch_save()`, `incentive_report()`, `ajax_mark_paid()` |
| **Risk** | 🟡 Financial — employee incentive payouts |

### `admin_rateapi.php` (494 LOC, 6 methods)

| Category | Details |
|---|---|
| **Purpose** | Metal rate API — update gold/silver rates by branch |
| **Models** | `admin_settings_model` |
| **Key Methods** | `update_rateapi()`, `update_rateByBranch()`, `update_daily_collection()` |
| **Risk** | 🔴 Financial — metal rates affect all pricing |

---

## TIER 3 — Low-Risk / Utility Controllers (<500 LOC)

| File | LOC | Methods | Purpose | Models | Risk |
|---|---|---|---|---|---|
| `regression_test.php` | 424 | 6 | Automated test runner | - | 🟢 Dev |
| `admin_catalog.php` | 391 | 6 | Chit catalog management | - | 🟢 Catalog |
| `videoshopping_appt.php` | 371 | 17 | Video shopping appointment slots | `admin_settings_model` | 🟢 Operational |
| `admin_ret_section_transfer.php` | 370 | 5 | Section transfer (has brain) + OTP | `ret_section_transfer_model`, `admin_settings_model` | 🟡 Stock |
| `Admin_knowledge_base.php` | 331 | 6 | Internal knowledge base + change requests | - | 🟢 Internal |
| `reconstruct.php` | 268 | 5 | Data reconstruction (print blocks) | - | 🟢 Utility |
| `Override_status.php` | 254 | 6 | Client override status scanner | - | 🟢 Dev |
| `pos_reconcile.php` | 241 | 6 | POS payment reconciliation | `pos_model`, `ret_billing_model` | 🔴 Financial |
| `admin_ret_eda.php` | 236 | 6 | Electronic Delivery Advice reports | `ret_eda_model`, `admin_settings_model` | 🟡 Reporting |
| `admin_stock_age_master.php` | 226 | 8 | Stock aging master CRUD | - | 🟢 Master |
| `intra_sync_services.php` | 215 | 5 | Intra-branch sync services | - | 🟡 Sync |
| `admin_opening_master.php` | 207 | 3 | Cash opening balance master | - | 🟡 Financial |
| `error_monitor.php` | 199 | 9 | Error monitoring dashboard | `error_monitor_model` | 🟢 Monitoring |
| `ImportController.php` | 190 | 4 | Excel import utility | - | 🟡 Data |
| `Test_cash_flow.php` | 181 | 3 | Cash flow report testing | `ret_reports_model`, `ret_catalog_model`, `cash_flow_model` | 🟢 Dev |
| `admin_invoice_builder.php` | 178 | 13 | Invoice builder UI | `print_template_model` | 🟡 Visual |
| `refer_customer.php` | 169 | 10 | Customer referral system | - | 🟢 CRM |
| `admin_ret_wishlist.php` | 165 | 4 | Customer wishlist | `ret_wishlist_model`, `admin_settings_model` | 🟢 CRM |
| `healthcheck.php` | 161 | 2 | Server health check (ping) | - | 🟢 Ops |
| `collection_report.php` | 126 | 0 | Collection report (empty?) | - | 🟢 Report |
| `kyc.php` | 111 | 9 | KYC document management | `kyc_model` | 🟡 Compliance |
| `admin_irn_settings.php` | 103 | 6 | IRN/e-Invoice settings UI | - | 🟡 Tax |
| `crm_mas_scheme.php` | 97 | 5 | CRM scheme master | - | 🟢 Master |
| `Report_tester.php` | 92 | 2 | Report testing utility | `ret_reports_model` | 🟢 Dev |
| `tally_app_api.php` | 84 | 6 | Tally API (admin side) | `ret_tally_api_model`, `ret_estimation_model` | 🟡 ERP |
| `OrderAccept.php` | 84 | 5 | Order acceptance handler | `ret_order_model`, `ret_purchase_approval_model` | 🟡 Order |
| `admin_mails.php` | 62 | 5 | Email management — closing/joining requests | - | 🟢 Comms |
| `admin_adv_booking.php` | 60 | 7 | Advance booking management | `payment_model` | 🟡 Financial |
| `admin_pan_verification.php` | 47 | 2 | PAN card verification API | - | 🟡 Compliance |
| `welcome.php` | 24 | 1 | Default CI3 welcome page | - | 🟢 None |

---

## Cross-Reference: Model Dependencies

| Model | Used By Controllers |
|---|---|
| `email_model` | purchase_approval, usersms, ret_services, chit_admin, sync_erp_services, khimji_services |
| `admin_settings_model` | pos, import_export, rateapi, videoshopping, tag_parts, section_transfer, eda, wishlist |
| `ret_billing_model` | pos, print_designer, pos_reconcile |
| `pos_model` | pos, pos_reconcile |
| `print_template_model` | print_template, print_designer, invoice_builder |
| `ret_purchase_approval_model` | dashboard_api, OrderAccept |
| `ret_tally_api_model` | tally_app_api |
| `sms_model` | usersms |
| `customer_model` | import_export |
| `ret_tag_parts_model` | tag_parts |
| `log_model` | tag_parts |

---

## SKIPPED (Backups)

| File | LOC | Reason |
|---|---|---|
| `admin_ret_dashboard_api_backup.php` | 876 | Backup of `admin_ret_dashboard_api.php` |
| `admin_app_api_backup.php` | 4,513 | Backup of `admin_app_api.php` |
| `admin_services_27_10_25.php` | 4,320 | Dated backup of `admin_services.php` |
| `admin_ret_estimation_konika.php` | 1,945 | Client variant of estimation |
