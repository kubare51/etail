# Implementation Plan — 100% Knowledge Base Coverage

## Goal
Build a 100% complete, queryable, and AI-ready knowledge base from the `etail_v3` (source) codebase. This will serve as the single source of truth for all modernization, feature parity mapping, and AI-assisted rebuilding.

---

## The Coverage Gap (Today vs. Target)

Our current knowledge base contains **28 modules**, but is missing crucial foundational layers and client variants. The automated audit reveals:
*   **Actual PHP Codebase**: ~66 MB of PHP code.
*   **Current Coverage**: Only **~56%** of functions are documented (2,500 of ~4,474 total functions).
*   **The Undocumented Gap**: **1,974 functions** across 22 admin controllers, 29 non-admin controllers, 42 models, 9 helpers, 8 libraries, and 4 core overrides.
*   **High-Risk Blind Spots**:
    *   `MY_Controller.php` (Auth, session, client configs loaded by *every* request).
    *   `ret_app_api_model.php` (176 functions — entire mobile app backend).
    *   `ret_purchase_approval_model.php` (147 functions — financial approval workflow).
    *   Sync APIs (5 controllers, ~200 functions — multi-branch sync).
    *   `REST_Controller.php` & `Datatables.php` (Foundational libraries).

---

## Proposed Changes

We will systematically close the documentation gap by introducing **new module brains** for undocumented areas, script-automating map generation, and using AI-assisted extraction to write plain-English business rules.

### 1. New Module Brain Definitions

We will create **6 new dedicated module brains** in `knowledge_brain/` to cover the undocumented infrastructure and models:

*   #### [Core Overrides](file:///C:/xampp/htdocs/etail_v3/knowledge_brain/_CORE_OVERRIDES/)
    *   **Files**: `MY_Controller.php`, `MY_Loader.php`, `MY_Router.php`, `MY_DB_mysqli_driver.php`, `client_config_helper.php`, `feature_flag_helper.php`
    *   **Goal**: Document the triple-layer client override system, feature flags, and multi-client database router.
*   #### [Mobile API](file:///C:/xampp/htdocs/etail_v3/knowledge_brain/Mobile_API/)
    *   **Files**: `ret_app_api_model.php`, `chitapi.php`, `chitapi_model.php`
    *   **Goal**: Document the entire mobile app backend, endpoint parameters, and data flows.
*   #### [Purchase Approval](file:///C:/xampp/htdocs/etail_v3/knowledge_brain/Purchase_Approval/)
    *   **Files**: `ret_purchase_approval_model.php`, `admin_apm.php`
    *   **Goal**: Document the purchase approval workflow and validation constraints.
*   #### [Sync APIs](file:///C:/xampp/htdocs/etail_v3/knowledge_brain/Sync_APIs/)
    *   **Files**: `sync_api.php`, `sktm_syncapi.php`, `sync_wallet_api.php`, `sync_erp_api.php`, `syncapi_model.php`
    *   **Goal**: Document branch syncing, wallet syncing, and ERP syncing logic.
*   #### [POS Integration](file:///C:/xampp/htdocs/etail_v3/knowledge_brain/POS_Integration/)
    *   **Files**: `pos_model.php`, `POS_Provider_Loader.php`, `pos_reconcile.php`
    *   **Goal**: Document physical POS payment device integrations and reconciliation.
*   #### [Shared Libs](file:///C:/xampp/htdocs/etail_v3/knowledge_brain/_SHARED_LIBS/)
    *   **Files**: `Datatables.php`, `REST_Controller.php`, `receipt_helper.php`, `konva_receipt_helper.php`
    *   **Goal**: Document helper functions, server-side table pagination, and REST abstractions.

---

### 2. Implementation Script: Automated Map Generation

We will create a powershell script to execute `auto_brain.py` across all modules (including the new ones) to generate the AST maps (`CONTROLLER_MAP.md`, `MODEL_MAP.md`, `AJAX_MAP.md`, `VIEW_MAP.md`, `SHARED_DEPENDENCIES.md`).

#### [build_all_brains.ps1](file:///C:/xampp/htdocs/etail_v3/Documentation/Revamp_Analysis/scratch/build_all_brains.ps1)
A PowerShell script that:
1.  Defines the mapping of PHP files to their designated module folders in `knowledge_brain/`.
2.  Iterates through all 28 existing modules + the 6 new infrastructure modules.
3.  Runs `python auto_brain.py --project-root C:\xampp\htdocs\etail_v3 --module <name>` to build structural maps.
4.  Consolidates generated output into the local `knowledge_brain` directory.

---

### 3. Business Logic Enrichment (AI-Assisted)

Once the structural maps and method indexes are generated, we will execute a systematic AI-assisted review of the models to extract the plain-English business rules and constraints.
*   We will focus on the **11 critical financial models** (e.g. `ret_billing_model`, `chit_transaction_model`, `wallet_model`).
*   Rules will be compiled in `BUSINESS_RULES.md` and `FLOW_RISK_MATRIX.md` for each module.

---

## Verification Plan

To verify that our knowledge base is 100% complete and matches the source code:

### 1. Verification Scripts
We will write and execute an audit comparison script `check_coverage_gaps.ps1`:
*   Scan all PHP files in `admin/application/controllers`, `models`, `helpers`, `libraries`, and `core`.
*   Search for their names inside `knowledge_brain/**/CONTROLLER_MAP.md`, `MODEL_MAP.md`, or `MODULE_BRAIN.md`.
*   Output any remaining files that are completely unreferenced.

### 2. Method Index Verification
Verify that the sum of method counts across all generated `METHOD_INDEX.md` files equals the total method count in the LCA sqlite master index (`lca_index.json`).

### 3. Verification Commands
```powershell
# Run the gap checker script
powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\etail_v3\Documentation\Revamp_Analysis\scratch\check_coverage_gaps.ps1
```
