# Feature Documentation — Settings

## Where settings live

There is **no dedicated settings screen** for the Print Templates module specifically. All behavior toggles are rows in the generic `ret_settings` key/value table, managed through one shared generic screen:

- Controller: `admin/application/controllers/admin_usersms.php` (route `settings/retail_setting/`)
- Model: `admin/application/models/admin_settings_model.php` — `retail_settingsDB($type, $name, $set_array)`
- Views: `admin/application/views/settings/retail_setting/list.php` and `form.php`

The form is completely generic — free-text `name`, `value`, `description` inputs. There is **no dropdown, no validation, and no in-UI explanation** of what values like `0`/`1`/`3` mean for any of these keys; an admin must know the exact key name and the meaning of each numeric value (which is why they're documented here). Unlike the print-template module itself, this settings screen **is** permission-gated by the standard `get_access('settings/retail_setting/list')` check.

Read via the shared helper present on ~17 models, e.g. `ret_billing_model.php`:
```php
function get_ret_settings($settings)
{
    $data = $this->db->query("SELECT value FROM ret_settings where name='" . $settings . "'");
    return $data->row()->value;
}
```

## Confirmed settings

### `template_based`

Read at `admin_ret_billing.php::billing_invoice()`. Controls which rendering path the **bill invoice** print uses.

| Value | Behavior |
|---|---|
| `3` | V2 Konva canvas template. Resolves via `find_konva_template($bill_type_id, $branch_id, 'billing')` — **strict**: only the `code = 'billing'` group is considered, no fallback to global/category templates. If none found or render fails, shows a hard "template not configured" page and stops — does **not** fall through to V1 or the legacy view. |
| `1` | V1 designer-based template (`print_template_model->render()`). If it returns empty/false, falls through to the legacy static PHP view. |
| anything else (`0`, unset, `2`, …) | Legacy static PHP view only (`receipt_billing.php` or `bill_format_2.php`, chosen by the separate `bill_format` setting below). |

Note: the code path checks `if ($template_based == 3) { ... exit; }` first, then `if ($template_based == 1 || $template_based == 3) { ... }` — the second condition's `== 3` branch is unreachable in practice since the `== 3` case always exits above it. Functionally, `== 3` and `== 1` are mutually exclusive outcomes.

### `bill_format`

Read at `admin_ret_billing.php::billing_invoice()` (and duplicated in `admin_app_api_backup.php`). Only relevant when `template_based` doesn't resolve to a successful V1/V2 render — chooses **which legacy static view** is the final fallback.

| Value | Behavior |
|---|---|
| `0` | `billing/print/receipt_billing.php` |
| anything else | `billing/print/bill_format_2.php` |

### `estimation_template_based`

Read via raw inline SQL (not `get_ret_settings()`) in `admin_ret_estimation.php`. Truthy only when exactly `1` (loose `== 1` check). Gates whether estimation print uses `print_template_model->render(16, ...)` before falling back to the legacy `estimation/print/est_print_2` view. **V2 Konva is not wired up for estimation** — this setting only ever reaches the V1 renderer.

### `issue_receipt_template_based`

Read via raw inline SQL at two call sites in `admin_ret_billing.php`. Same `== 1` truthy pattern, gating `print_template_model->render(17, ...)` for the "Issue to Supplier" receipt print, falling back to `billing/issueReceipt/print/issue`. Also V1-only, not wired to V2.

## Not a setting: `split_bill`

If you've seen `split_bill` referenced as a `ret_settings` key elsewhere (including in an earlier commit's PR description on this codebase), that's inaccurate — **`get_ret_settings('split_bill')` does not exist anywhere in the code.** What exists instead is a **computed, per-render boolean** (`$is_split_bill`), derived at render time in `template_receipt_helper.php` from whether any sales item on the bill is tag-split, and mirrored in the legacy static views. It only controls whether an "approximate benefit" split line is shown on the printed bill — it is not a configurable toggle, and there is nothing to set in the settings screen for it.

## Where each setting's effect lives in code

| Setting | Consuming file:function |
|---|---|
| `template_based` | `admin_ret_billing.php::billing_invoice()` |
| `bill_format` | `admin_ret_billing.php::billing_invoice()`, `admin_app_api_backup.php` |
| `estimation_template_based` | `admin_ret_estimation.php` (estimation print action) |
| `issue_receipt_template_based` | `admin_ret_billing.php` (two issue-receipt print call sites) |

## Per-document configuration (not global settings, but configuration nonetheless)

These aren't `ret_settings` rows, but they are the closest things to a "settings" surface within the print-template module itself:

- **MC/VA label config** (`print_template_mc_va_config` table) — per-branch, per-document-type configuration of Making Charge / Value Addition labels and calculation mode, read/written via `get_mc_va_config()` / `save_mc_va_config()` in `admin_print_template.php`, exposed to the V2 designer through `print-templates/mc-va-config/{id}`.
- **Paper size / orientation / margins** — stored per-template-row (`paper_size`, `page_orientation`, `margin_top/right/bottom/left` columns), not global; set per template via the designer, not a settings screen.

## `config.php`

`admin/application/config/config.php` has **no entries** related to print templates, Konva, GrapesJS, or PDF generation — grepped for all of those terms with zero matches. Nothing about this module is config-file-driven; everything is DB-driven (`ret_settings` rows + `print_templates` and related tables).
