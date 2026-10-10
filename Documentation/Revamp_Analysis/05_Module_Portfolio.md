# Module Portfolio

> **Purpose**: Module-by-module breakdown — size, complexity, dependencies, risk, and modernization priority.
> **Data source**: Knowledge brain analysis, CLAUDE.md module registry, LCA devtools infrastructure.

---

## Module Classification

### Retail Core Modules

| Module | Controller | Key Model | Tables | Dependencies | Risk | Priority |
|---|---|---|---|---|---|---|
| **Billing** | `admin_ret_billing.php` (435 KB) | `ret_billing_model.php` | ret_billing, ret_billing_items, payment, ret_taging, journal | Tagging, Estimation, Payment, Account, Customer, Scheme | CRITICAL | Wave 3 |
| **Estimation** | `admin_ret_estimation.php` (111 KB) | `ret_estimation_model.php` | ret_estimation, ret_estimation_items, ret_taging | Tagging, Billing, Catalog | HIGH | Wave 3 |
| **Tagging** | `admin_ret_tagging.php` (219 KB) | `ret_tagging_model.php` | ret_taging, ret_tag_detail | LOT, Catalog, Billing, Estimation, Branch Transfer, Stock Issue | CRITICAL | Wave 2 |
| **LOT Management** | `admin_ret_lot.php` (65 KB) | `ret_lot_model.php` | ret_lot, ret_lot_detail | Tagging, Catalog | HIGH | Wave 2 |
| **Stock Issue** | `admin_ret_stock_issue.php` (33 KB) | `ret_stock_issue_model.php` | ret_stock_issue, ret_stock_issue_items | Tagging, Catalog | MEDIUM | Wave 4 |
| **Catalog/Inventory** | `admin_ret_catalog.php` (570 KB) | `ret_catalog_model.php` | ret_catalog, item_master, category | All modules | CRITICAL | Wave 1 |
| **Branch Transfer** | `admin_ret_brntransfer.php` (67 KB) | `ret_brntransfer_model.php` | ret_branch_transfer, ret_branch_transfer_items | Tagging, Catalog | HIGH | Wave 4 |
| **Sales Transfer** | `admin_ret_sales_transfer.php` (55 KB) | `ret_sales_transfer_model.php` | ret_sales_transfer | Tagging, Billing | MEDIUM | Wave 4 |
| **Section Transfer** | `admin_ret_section_transfer.php` (21 KB) | `ret_section_transfer_model.php` | ret_section_transfer | Tagging, Catalog | LOW | Wave 4 |
| **Customer Order** | `admin_ret_order.php` (101 KB) | `ret_order_model.php` | ret_order, ret_order_items | Customer, Billing, Tagging | MEDIUM | Wave 7 |
| **Old Metal Process** | `admin_ret_metal_process.php` (117 KB) | `ret_metal_process_model.php` | ret_old_metal, ret_old_metal_items | Billing, Customer | MEDIUM | Wave 7 |
| **Purchase** | `admin_ret_purchase.php` (365 KB) | `ret_purchase_model.php` | ret_purchase, ret_purchase_items, ret_grn | Catalog, Vendor, Account | HIGH | Wave 7 |
| **Purchase Approval** | `admin_ret_purchase_approval.php` (133 KB) | `ret_purchase_approval_model.php` | ret_purchase_approval | Purchase, Account | MEDIUM | Wave 7 |
| **Reports** | `admin_ret_reports.php` (218 KB) | `ret_reports_model.php` | All tables (read-only) | All modules | LOW | Wave 5 |
| **Retail Dashboard** | `admin_ret_dashboard.php` (47 KB) | `ret_dashboard_model.php` | Various (read-only) | All modules | LOW | Wave 5 |
| **Other Inventory** | `admin_ret_other_inventory.php` (59 KB) | `ret_other_inventory_model.php` | ret_other_inventory | Catalog | LOW | Wave 7 |
| **Tag Parts** | `admin_ret_tag_parts.php` (26 KB) | `ret_tag_parts_model.php` | ret_tag_parts | Tagging | LOW | Wave 2 |

### Chit/Scheme Modules

| Module | Controller | Key Model | Tables | Dependencies | Risk | Priority |
|---|---|---|---|---|---|---|
| **Scheme Management** | `admin_manage.php` (265 KB) | `manage_model.php` | scheme, scheme_account, scheme_installment | Payment, Account, Customer | HIGH | Wave 6 |
| **Payment Collection** | `admin_payment.php` (418 KB) | `payment_model.php` | payment, scheme_account | Scheme, Account, Billing | CRITICAL | Wave 6 |
| **Chit Dashboard** | — | — | Various (read-only) | Scheme, Payment | LOW | Wave 6 |
| **Chit Reports** | — | — | Various (read-only) | All Chit modules | LOW | Wave 6 |
| **Chit Settings** | — | — | chit_settings | All modules | HIGH | Wave 1 |

### Supporting Modules

| Module | Controller | Key Model | Tables | Dependencies | Risk | Priority |
|---|---|---|---|---|---|---|
| **Customer** | `admin_customer.php` (80 KB) | `customer_model.php` | customer, customer_address | Billing, Scheme, Order | MEDIUM | Wave 1 |
| **Employee** | `admin_employee.php` (43 KB) | `employee_model.php` | employee, profile | All modules (auth) | LOW | Wave 1 |
| **Account/Journal** | — | `account_model.php` | journal, ledger | Billing, Payment, Scheme | HIGH | Wave 6 |
| **Settings** | `admin_settings.php` (303 KB) | `settings_model.php` | ret_settings, chit_settings, profile | All modules | CRITICAL | Wave 1 |
| **Masters** | — | `masters_model.php` | item_master, category, branch, metal_type | All modules | HIGH | Wave 1 |

### API & Integration Modules

| Module | Controller | Purpose |
|---|---|---|
| **App API** | `admin_app_api.php` (204 KB) | Customer-facing mobile app backend |
| **Dashboard API** | `admin_ret_dashboard_api.php` (50 KB) | Dashboard data API |
| **Sync API** | `sync_api.php` (45 KB) | Multi-branch data synchronization |
| **Sync ERP API** | `sync_erp_api.php` (28 KB) | ERP data sync |
| **Wallet API** | `sync_wallet_api.php` (67 KB) | Digital wallet management |
| **Tally API** | `tally_app_api.php` (2 KB) | Tally accounting integration |
| **POS** | `admin_pos.php` (46 KB) | Point of Sale integration |
| **Rate API** | `admin_rateapi.php` (29 KB) | Gold/silver rate management |

---

## Modernization Waves

### Wave 1: Foundation (Weeks 5-8)
**Modules**: Settings, Catalog, Customer, Employee, Masters

**Why first**: Every other module depends on these. Settings control feature flags. Catalog defines the product structure. Customer is referenced by billing, schemes, and orders.

**Key tasks**:
- Centralize settings access (currently scattered across controllers)
- Add input validation to all master CRUD operations
- Document all configuration options (150+ settings)

---

### Wave 2: Core Data (Weeks 9-12)
**Modules**: LOT, Tagging, Tag Parts

**Why second**: Tagging is the central data entity (tag_status written by 10+ modules). LOT creates tags. Both must have a centralized state machine before billing can be modernized.

**Key tasks**:
- Implement tag_status state machine (the #1 architectural fix)
- Centralize all tag_status writes through a single guard function
- Add transaction wrapping to LOT→Tag creation flow

---

### Wave 3: Business Operations (Weeks 13-20)
**Modules**: Estimation, Billing

**Why third**: These are the most complex and most used modules. Billing alone is 435 KB with multiple bill types. Estimation feeds into billing via conversion.

**Key tasks**:
- Extract billing formulas into testable service layer
- Fix print vs save formula mismatches
- Add reversal logic for all cancel operations
- Build REST API for bill creation/retrieval

---

### Wave 4: Inventory Movement (Weeks 21-26)
**Modules**: Branch Transfer, Stock Issue, Section Transfer, Sales Transfer

**Key tasks**:
- Fix cross-branch data consistency
- Add transaction wrapping to transfer flows
- Implement proper reversal for cancelled transfers

---

### Wave 5: Reporting & Dashboard (Weeks 27-30)
**Modules**: Reports, Retail Dashboard, Chit Dashboard

**Key tasks**:
- Performance optimization (N+1 queries, missing indexes)
- Add read replica support for heavy reports
- Standardize report output formats

---

### Wave 6: Scheme/Chit (Weeks 31-38)
**Modules**: Scheme, Account, Payment, Chit Reports, Chit Settings

**Key tasks**:
- Fix scheme maturity calculation bugs
- Add proper utilization tracking
- Implement journal entry validation

---

### Wave 7: Supporting Operations (Weeks 39-48)
**Modules**: Purchase, Customer Order, Old Metal, Other Inventory

**Key tasks**:
- Purchase approval workflow modernization
- GRN reconciliation improvements
- Old metal valuation standardization

---

## E2E Test Coverage by Module

| Module | Test File | Coverage |
|---|---|---|
| Billing | `test_billing.py` | ✅ |
| Estimation | `test_estimation.py` | ✅ |
| Tagging | `test_tagging.py` | ✅ |
| LOT | `test_lot_inward.py` | ✅ |
| Stock Issue | `test_stock_issue.py` | ✅ |
| Section Transfer | `test_section_transfer.py` | ✅ |
| Old Metal | `test_old_metal_process.py` | ✅ |
| Customer Order | `test_customer_order.py` | ✅ |
| Purchase PO | `test_purchase_po.py` | ✅ |
| Purchase Return | `test_purchase_return.py` | ✅ |
| GRN | `test_grn_entry.py` | ✅ |
| Masters | `test_masters.py` | ✅ |
| Login | `test_login.py` | ✅ |
| Vendor | `test_vendor.py` | ✅ |
| Approval | `test_approval_rate_fixing.py` | ✅ |
| Repair | `test_repair_order.py` | ✅ |
| QC | `test_qc_issue_receipt.py` | ✅ |
| Smith/Karigar | `test_karigar_allotment.py`, `test_smith_*` | ✅ |
| Scheme | — | ❌ Needed |
| Payment | — | ❌ Needed |
| Branch Transfer | — | ❌ Needed |
| Reports | — | ❌ Needed |
| Dashboard | — | ❌ Needed |
| Customer | — | ❌ Needed |
| Settings | — | ❌ Needed |
