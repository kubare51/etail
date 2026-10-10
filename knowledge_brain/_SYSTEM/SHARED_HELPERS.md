# Shared Helpers — System Brain

> Last updated: 2026-07-02 (Initial Build)
> Scope: All files in `admin/application/helpers/` — globally loaded, available in every controller, model, and view

---

## Overview

Helpers are **CI3 global function libraries** — once loaded (via autoload or explicit `$this->load->helper()`), their functions are available everywhere. This makes them system-wide shared dependencies at the same risk tier as shared models.

### File Structure

```
admin/application/helpers/
├── lmx/
│   ├── functions/
│   │   ├── common_helper.php        ← 1,410 LOC — IRN e-Invoice + email + DB errors
│   │   └── export_helper.php        ← Export utilities
│   └── classes/
│       └── excel_helper.php         ← Excel generation
├── formsecret_helper.php            ← 59 LOC — CSRF-like form token security
├── feature_flag_helper.php          ← 47 LOC — Feature flag system (chit_settings JSON)
├── client_config_helper.php         ← 183 LOC — Three-layer client override system
├── customer_validation_helper.php   ← 225 LOC — Server-side customer validation
├── metal_wgt_digit_helper.php       ← 54 LOC — Metal weight decimal precision
├── receipt_helper.php               ← 2,121 LOC — Core receipt/print formatting
├── konva_receipt_helper.php         ← 2,193 LOC — Konva-based receipt rendering
├── template_receipt_helper.php      ← 3,161 LOC — Template-based receipt rendering
├── ret_billing_helper.php           ← 12 LOC — Billing utility stub
└── test_mapping.php                 ← 14 LOC — Test mapping
```

---

## P0 — Security & Cross-Module Impact

### `formsecret_helper.php` — Form Token Security

| Function | Lines | Purpose | Callers |
|---|---|---|---|
| `get_form_secret_key()` | 4-20 | Generate MD5-based CSRF token, store in session (pool of 10) | All forms with duplicate-submit protection |
| `verify_form_secret($token, $consume)` | 29-57 | Verify + consume token from pool or legacy single-secret | Controller form handlers |

**Security Notes:**
- Uses `md5(uniqid(rand(), true))` — weaker than CI3's native CSRF, but functional
- Pool of 10 tokens prevents multi-tab form breakage (common UX issue)
- Backward-compatible: checks both `FORM_SECRETS` array AND legacy `FORM_SECRET` single value
- `$consume = true` by default — token is single-use (prevents duplicate submission)

> ⚠️ **Risk**: If session storage is file-based and concurrent requests hit, array_shift + session write could race. Low probability but worth noting.

---

### `client_config_helper.php` — Three-Layer Override System

| Function | Lines | Purpose | Impact |
|---|---|---|---|
| `get_client_id()` | 21-24 | Return `CLIENT_ID` constant or `'default'` | System-wide client identification |
| `get_client_config()` | 35-64 | Load + cache `config/clients/{CLIENT_ID}.json` | Static cached per-request |
| `get_flag($key, $default)` | 77-86 | Read specific flag from client config | Feature toggling |
| `has_feature($name)` | 98-102 | Check if feature pack is enabled | Feature toggling |
| `get_client_path($type)` | 114-118 | Get client override directory path | Override resolution |
| `get_feature_path($feature, $type)` | 127-130 | Get feature pack directory path | Override resolution |
| `get_asset_url($path)` | 146-179 | Resolve asset through 3-layer override (client → feature → core) | JS/CSS loading |

**Three-Layer Override Resolution Order:**
1. `clients/{CLIENT_ID}/assets/{path}` — Client-specific override
2. `features/{feature}/assets/{path}` — Feature pack override
3. `admin/assets/{path}` — Core (default)

> ⚠️ **ENABLE_OVERRIDES kill switch**: If `ENABLE_OVERRIDES` constant is not defined or false, all overrides are bypassed.

---

### `feature_flag_helper.php` — Feature Flags

| Function | Lines | Purpose | Source Table |
|---|---|---|---|
| `feature_flag($code, $default)` | 20-45 | Check if feature flag is enabled | `chit_settings.feature_flags` (JSON column) |

**Mechanism:**
- Reads `feature_flags` JSON column from `chit_settings` (single row, LIMIT 1)
- Static cached per-request (no DB hit on repeated calls)
- Fail-closed: returns `$default` (default: `false`) if flag not found
- JSON structure: `[{"code": "SHOW_BRANCH_ZONE", "status": 1}, ...]`

> ⚠️ **Uses raw SQL**: `$CI->db->query("SELECT IFNULL(feature_flags,'[]')...")` — violates Rule 1 (no raw SQL). Should use active record.

---

## P0 — Financial Impact

### `lmx/functions/common_helper.php` — IRN e-Invoice + Email + DB Errors

**3 functional sections, 1,410 LOC:**

#### Section 1: Email + DB Errors (lines 1-572)

| Function | Lines | Purpose |
|---|---|---|
| `mail_htmlContent_helper($content)` | 19-34 | Wrap email body in basic HTML template |
| `send_email_helper($to, $subject, $msg, $cc)` | 45-74 | Send email via SMTP (Gmail hardcoded) |
| `db_errormessage($code)` | 82-571 | Map MySQL error codes (1000-1480) to human-readable messages |

> ⚠️ `send_email_helper()` has **hardcoded Gmail SMTP** at line 56-57 but `$mail_server` and `$mail_password` are empty strings — **this function is broken/dead code**. The actual email is sent via `php_mail_smtp()` (line 1369).

#### Section 2: IRN e-Invoice Integration (lines 573-1363)

**30+ functions** handling GSP API integration for e-Invoicing (IRN/CRN):

| Function Group | Functions | Purpose | Models Used |
|---|---|---|---|
| **Environment** | `isProductionEnv()` | Dual check: `is_production` flag + base_url match | `admin_settings_model` |
| **Config Validation** | `validateIrnConfig($branchData)` | Pre-flight check for all IRN settings + branch credentials | `admin_settings_model` |
| **URL Building** | `buildGspAuthUrl()`, `buildGspInvoiceUrl()` | Dynamic GSP API URL construction (prod vs sandbox) | `admin_settings_model` |
| **Invoice Generation** | `generateEinvoice($billId)` | Entry point — dispatches to sale or return flow | `ret_billing_model` |
| **Sale Invoice** | `createGSPEInvoice()` | Build + POST sales/transfer invoice to GSP | `ret_billing_model`, `admin_settings_model` |
| **Sales Return** | `createGSPEInvoiceSalesReturn()` | Build + POST CRN (credit note) to GSP | `ret_billing_model`, `admin_settings_model` |
| **Data Builders** | `getTranDtls_irn()`, `getDocDtls_irn()`, `getSellerDtls_irn()`, `getBuyerDtls_irn()`, `getValDtls_irn()`, `getItemList_irn()` | Build JSON payload sections for GSP API | `ret_billing_model`, `admin_settings_model` |
| **Old Metal Variants** | `getValDtls_old_metal_irn()`, `getItemList_old_metal_irn()` | Old metal transfer-specific item/value builders | `ret_billing_model` |
| **Sales Return Variants** | `getDocDtls_str_irn()`, `getSellerDtls_str_irn()`, `getValDtls_str_irn()`, `getItemList_str_irn()`, `getInvoiceDetails_irn()` | Sales return-specific builders | `ret_billing_model` |
| **Auth** | `createAuthToken_irn()` | GET auth token from GSP, store in `branch.authtoken` | `ret_billing_model` |
| **API Call** | `generateGSPEInvoice_api()` | POST invoice JSON to GSP API | `ret_billing_model`, `admin_settings_model` |
| **Response** | `decodeeInvoice_irn()`, `updateIRNDetails_irn()`, `base64_to_jpeg_irn()` | Decode JWT, save IRN+QR to DB | `ret_billing_model` |
| **Utility** | `formatnumber_irn($num)` | `floatval(number_format($num, 2, '.', ''))` | - |

**IRN Generation Mode (`is_auto_gen_irn`):**
- `1` = Preview only (log, no API call)
- `2` = Auto-generate (API call)
- `3` = Auto-generate (API call) — same behavior as 2

**Tables Written:**
- `ret_billing` — `cusdel_irn`, `cusdel_signature`, `qrcodeimage`, `irn_error` columns
- `branch` — `authtoken` column (via `updateBranchAuthToken()`)

**Error Handling:**
- GSP752/752 = Auth token expired → clears token, retries recursively
- Error 2150 = Duplicate IRN → saves existing IRN, returns info status
- Other errors → saves error to `ret_billing.irn_error`, sends email alert

#### Section 3: SMTP Email (lines 1365-1409)

| Function | Lines | Purpose |
|---|---|---|
| `php_mail_smtp($to, $subject, $msg, $cc, $bcc, $attach)` | 1369-1408 | Send email via Gmail SMTP for IRN error alerts |

> 🔴 **CRITICAL SECURITY**: Line 1377-1378 has **hardcoded Gmail credentials** (`logimaxtechmail@gmail.com` / app password). This is a credential leak in source code. Should be in environment config.

---

## P1 — Precision & Calculation

### `metal_wgt_digit_helper.php` — Metal Weight Precision

| Function | Lines | Purpose | Session Dependencies |
|---|---|---|---|
| `trim_decimal($value, $precision)` | 5-36 | Truncate (not round) decimal to specified precision | None |
| `formatMetalWeight($value)` | 40-51 | Format metal weight using session config | `metal_wgt_roundoff`, `metal_wgt_decimal` |

**Logic:**
- `roundoff == 0` → **truncate** (use `trim_decimal`) — cuts extra decimals without rounding
- `roundoff != 0` → **round** (use `number_format`) — standard PHP rounding

> ⚠️ Precision risk: `trim_decimal` adds `1e-10` epsilon before truncating (line 10-11) to handle floating point representation. Handles scientific notation edge case.

---

### `customer_validation_helper.php` — Server-Side Validation

| Function | Purpose | Bug Fixes |
|---|---|---|
| `validate_customer_data(&$CI, $mode, $id_customer)` | Full server-side validation for customer CRUD | BUG-C02, C05, C06, H03-H06, M01, M03, M06, M07 |
| `sanitize_customer_field($value)` | XSS-safe string sanitization | Generic |

**Validation Rules:**
- Required: firstname, mobile (10 digits), address1, country, state, city, pincode (6 digits), gender
- Conditional: GST number required for company type (cus_type=2), regex-validated
- DOB: Must be ≥18 years, not future
- Wedding date: Must be after DOB, age ≥18 at wedding
- Password: Required on add, min 8 chars
- Sanitization: `htmlspecialchars()` with `ENT_QUOTES`

---

## P2 — Receipt/Print Helpers (NOT YET ANALYZED)

These 3 files total **7,475 LOC** and are the core print/receipt rendering engine:

| Helper | LOC | Purpose | Status |
|---|---|---|---|
| `receipt_helper.php` | 2,121 | Core receipt formatting | ⬜ Not analyzed |
| `konva_receipt_helper.php` | 2,193 | Konva canvas-based receipt rendering | ⬜ Not analyzed |
| `template_receipt_helper.php` | 3,161 | GrapesJS template-based receipt rendering | ⬜ Not analyzed |

> These require a separate deep-dive session due to their size and visual output complexity.

---

## Cross-Module Impact Matrix

| Helper | Modules That Use It | Risk if Changed |
|---|---|---|
| `common_helper.php` (IRN functions) | Billing, Admin Settings | 🔴 Financial — IRN generation breaks |
| `formsecret_helper.php` | ALL modules with form submission | 🔴 Security — duplicate submission protection |
| `feature_flag_helper.php` | ALL modules checking flags | 🟡 Feature toggling — wrong flag = hidden features |
| `client_config_helper.php` | ALL modules (asset override) | 🟡 Client override — wrong config = wrong JS/CSS |
| `metal_wgt_digit_helper.php` | Catalog, Billing, Estimation, Purchase, Reports | 🔴 Financial precision — wrong rounding = wrong prices |
| `customer_validation_helper.php` | Customer module | 🟡 Validation — only Customer controller |
| `receipt_helper.php` | Billing, Payment (print views) | 🟡 Visual — receipt layout |
| `template_receipt_helper.php` | Print Designer, Billing | 🟡 Visual — template receipts |

---

## Known Issues & Risks

| ID | Severity | Description | File | Line |
|---|---|---|---|---|
| HLP-001 | 🔴 CRITICAL | Hardcoded Gmail SMTP credentials in source code | `common_helper.php` | 1377-1378 |
| HLP-002 | 🟡 MEDIUM | `send_email_helper()` is dead code (empty credentials) | `common_helper.php` | 47-53 |
| HLP-003 | 🟡 MEDIUM | `feature_flag_helper` uses raw SQL instead of active record | `feature_flag_helper.php` | 26-28 |
| HLP-004 | 🟡 MEDIUM | IRN auth token retry is recursive — could infinite-loop if GSP keeps returning 752 | `common_helper.php` | 1196, 1265 |
| HLP-005 | 🟢 LOW | `db_errormessage()` has 480-line static array — could be externalized to config | `common_helper.php` | 84-566 |
| HLP-006 | 🟢 LOW | SSL verification disabled in cURL calls (`CURLOPT_SSL_VERIFYPEER => 0`) | `common_helper.php` | 1185-1186 |
