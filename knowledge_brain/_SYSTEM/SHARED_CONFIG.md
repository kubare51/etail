# Shared Config — System Brain

> Last updated: 2026-07-02 (Initial Build)
> Scope: All files in `admin/application/config/` — CI3 configuration layer

---

## Autoload Registry (`autoload.php` — Line 109, 133, 224)

### Auto-loaded Libraries (every request)
```php
$autoload['libraries'] = array('database', 'session', 'form_validation', 'upload');
```

### Auto-loaded Helpers (every request — SYSTEM-WIDE)
```php
$autoload['helper'] = array(
    'url',                          // CI3 core — base_url(), site_url()
    'form',                         // CI3 core — form_open(), form_close()
    'file',                         // CI3 core — read/write files
    'cookie',                       // CI3 core — cookie handling
    'formsecret',                   // CUSTOM — CSRF token (get_form_secret_key, verify_form_secret)
    'lmx/functions/common_helper',  // CUSTOM — IRN e-Invoice + email + DB errors (1,410 LOC)
    'client_config',                // CUSTOM — Three-layer client override system
    'metal_wgt_digit',              // CUSTOM — Metal weight precision (trim_decimal, formatMetalWeight)
    'feature_flag',                 // CUSTOM — Feature flag system (chit_settings JSON)
    'ret_billing'                   // CUSTOM — Billing utility stub (12 LOC)
);
```

### Auto-loaded Models (every request)
```php
$autoload['model'] = array('admin_settings_model');
```

> ⚠️ `admin_settings_model` is the ONLY auto-loaded model. This means every single controller has `$this->admin_settings_model` available without explicit loading. This is the source of the "hidden dependency" pattern — many methods call `get_ret_settings()` or `get_chit_settings()` without loading the model.

---

## Config File Inventory

| File | Lines | Purpose | Risk if Modified |
|---|---|---|---|
| `bill.php` | 1,512 | Billing configuration — print formats, tax rules, bill types | 🔴 Financial — affects all billing |
| `routes.php` | 1,033 | URL routing — maps URLs to controller methods | 🔴 All endpoints — wrong route = 404 |
| `config.php` | 455 | Base URL, encryption key, session config, CSRF settings | 🔴 System-wide — breaks authentication/sessions |
| `rest.php` | 241 | REST API configuration (CodeIgniter REST Server) | 🟡 API layer |
| `user_agents.php` | 167 | Browser user agent strings | 🟢 Low risk |
| `apm.php` | 124 | APM (Application Performance Monitoring) config | 🟢 Monitoring |
| `mimes.php` | 104 | MIME type mappings | 🟢 File upload types |
| `database.php` | 99 | Database connection credentials | 🔴 CRITICAL — DB access |
| `autoload.php` | 97 | Auto-loaded libraries/helpers/models (documented above) | 🔴 System-wide — adding/removing breaks everything |
| `foreign_chars.php` | 63 | Character transliteration | 🟢 Low risk |
| `smileys.php` | 61 | Smiley image mappings | 🟢 Unused |
| `hooks.php` | 43 | CI3 hooks — pre/post controller execution | 🟡 Request lifecycle |
| `constants.php` | 36 | File permission constants | 🟢 Low risk |
| `migration.php` | 35 | Migration settings | 🟢 Dev only |
| `cashfree.php` | 17 | Cashfree payment gateway config | 🔴 Financial — payment integration |
| `profiler.php` | 14 | CI3 profiler settings | 🟢 Debug only |
| `doctypes.php` | 13 | HTML doctype strings | 🟢 Low risk |

---

## High-Risk Config Details

### `bill.php` (1,512 LOC)
The largest config file — likely contains:
- Print template IDs and format mappings
- Bill type definitions (sale, return, transfer, etc.)
- Tax calculation parameters
- Bill number format patterns

> ⬜ **Not yet analyzed in detail** — requires separate deep-dive due to its size and financial impact.

### `routes.php` (1,033 LOC)
Maps all URLs to controllers. Key routing patterns:
- Admin panel routes (`admin/*`)
- API routes (`api/*`)
- Custom routes for specific modules

### `config.php` (455 LOC)
Contains system-wide settings:
- `base_url` — Application URL
- `encryption_key` — Session/cookie encryption
- `sess_*` — Session configuration
- `csrf_*` — CSRF protection settings
- `global_xss_filtering` — XSS filter toggle

### `cashfree.php` (17 LOC)
Payment gateway credentials — **must never be hardcoded** in version control.

---

## Safety Rules

1. **Never modify config files without explicit approval** (Rule 4 from GEMINI.md)
2. **Never commit database.php or cashfree.php changes** — these contain credentials
3. **Adding to autoload.php** increases memory usage for ALL requests — justify before adding
4. **Removing from autoload.php** will break any controller that doesn't explicitly load the dependency
5. **Routes changes** must be tested against ALL modules — a wrong regex can shadow other routes
