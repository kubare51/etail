# Architecture & Developer Guide

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.x, CodeIgniter 2.x, `class admin_print_template extends CI_Controller` (directly — no shared admin base controller) |
| Storage | MySQL, table `print_templates` + supporting tables (below) |
| V1 editor | Custom block/section editor (`builder-v2.js`, `block-editor.js`, `block-definitions.js`, `designer-init.js`, `invoice-components.js`, `variable-plugin.js`), with GrapesJS `0.21.13` (from `unpkg`) loaded **only inside a "Block Designer" sub-modal** for authoring individual block HTML/CSS — not the primary canvas |
| V2 editor | Konva.js `9.x` (CDN, `unpkg.com/konva@9/konva.min.js`), vanilla ES5 JS, no jQuery/Bootstrap/Select2 on this page |
| Rendering | Server-side PHP HTML string generation (no PDF library in this path — DomPDF exists in the codebase but is used by unrelated features, e.g. cheque printing) |

## Folder structure

```
admin/application/
  controllers/admin_print_template.php      # all CRUD + render endpoints (both V1 and V2)
  models/print_template_model.php           # DB access, versioning, expression evaluator, rendering (V1)
  helpers/konva_receipt_helper.php          # V2 Konva rendering pipeline (render_konva_template, find_konva_template)
  helpers/receipt_helper.php                # get_receipt_data(), generate_qr_code(), legacy data mappers
  helpers/template_receipt_helper.php       # tpl_map_billing_data_to_template() — the live billing→template data mapper
  views/print_templates/
    list.php          # template listing / version management UI
    form.php           # "Add new template" metadata form
    designer.php        # V1 block editor
    designer_v2.php     # V2 Konva canvas editor
    preview.php          # static preview wrapper
admin/assets/
  js/print-designer/
    konva-designer.js         # V2 canvas runtime (~4900 lines)
    builder-v2.js, block-editor.js, block-definitions.js,
    designer-init.js, invoice-components.js, variable-plugin.js   # V1 runtime
  css/print-designer/konva-designer.css
database/migrations/2026*_*print_template*.sql   # schema (see drift notes below)
```

## Data flow: creation → storage → rendering → print

1. **Create** — `POST print-templates/save` (`admin_print_template.php::save()`) inserts a bare `print_templates` row (`is_active=0`, `is_default=0`) and redirects to `print-templates/designer-v2/{id}`.
2. **Design** — the browser editor (V1 or V2) loads the current design via `GET print-templates/load-design/{id}` (V1) — V2 has no separate load-design route reused; V2's `designer_v2.php` bootstraps via `load_design($id)` route too (same controller action serves both, branching on payload shape). The user edits nodes/blocks.
3. **Save design** — `POST print-templates/save-design/{id}` (V1, `save_design()`) or `POST print-templates/save-design-v2/{id}` (V2, `save_design_v2()`). **Neither of these mutates the existing row.** Both compute a `group_code` (from the `code` column), build a new `template_code = {group_code}_{unix_time}`, `INSERT` a brand-new row with `is_active=1`, `version_number = old+1`, `parent_template_id = old id`, then call `print_template_model->deactivate_by_group_code($group_code)` to turn off every sibling row. Computed variables (`print_template_computed_vars`) are copied from the old row's id to the new one.
4. **Activate** — a template only becomes the one that prints when it's `is_active=1` within its `code` group (enforced procedurally by step 3, or explicitly via `toggle_active($id)` → `activate_and_deactivate_others($id)`) **and**, for the `get_default()` lookup used by V1, when it's the top result of that scoped query (see below).
5. **Print** — the consuming controller (e.g. `admin_ret_billing.php::billing_invoice()`) reads `ret_settings.template_based`, resolves the active template via `find_konva_template()` (V2) or `print_template_model->get_default()` (V1, called internally by `render()`), and renders it to an HTML string, which is `echo`'d and printed via `window.print()`.

## Template resolution logic (which row prints)

This differs between V1 and V2 — a real, verified difference worth knowing before debugging "wrong template printed":

- **V2** (`find_konva_template($bill_type_id, $branch_id, $only_code)`, `konva_receipt_helper.php`): when called with an explicit group code (as `billing_invoice()` does, passing `'billing'`), it queries **only** `code = 'billing' AND is_active = 1` (branch-scoped), and returns `false` with **no fallback** if nothing matches — the caller then shows a hard "not configured" page rather than guessing. When called without a code, it falls through category-match → `code = 'global'` → category `0` ("Common").
- **V1** (`print_template_model->get_default($category, $branch_id)`, called internally by `render()`): queries `template_category = $category AND is_active = 1`, branch-scoped, `ORDER BY is_default DESC, id_branch DESC LIMIT 1`; `render()` itself additionally falls back to category `0` if nothing is found for the requested category.

## Key files/classes/functions a new developer must know

| Concern | File | Key symbol |
|---|---|---|
| All CRUD/render HTTP endpoints | `admin_print_template.php` | `save()`, `save_design()`/`save_design_v2()`, `delete()`, `toggle_active()`, `preview()`/`preview_live()`/`preview_live_v2()`, `export()`/`import()` |
| Versioning/activation state machine | `print_template_model.php` | `deactivate_by_group_code()`, `activate_and_deactivate_others()`, `set_default()`, `revert_to_default()` |
| Placeholder/variable registry | `print_template_model.php` | `get_placeholders()` / `get_placeholders_v2()`, `_get_dynamic_fields_v2()`, `get_sample_data()` |
| Computed-variable formula engine | `print_template_model.php` (module-level functions, not class methods) | `_tpl_tokenize()`, `_tpl_parse_additive/multiplicative/factor()`, `_tpl_eval_expr()` — hand-written recursive-descent parser, **not** `eval()` |
| V1 Mustache-style renderer | `print_template_model.php` | `render()`, `render_string_template()` |
| V2 Konva renderer | `konva_receipt_helper.php` | `render_konva_template()`, `find_konva_template()`, `_konva_render_table()`, `_konva_build_table_chunks()` |
| Billing→template data mapping (the live one) | `template_receipt_helper.php` | `tpl_map_billing_data_to_template()` — its own header comment calls this "the single source of truth" for the variable registry; new fields must be declared here first |
| V2 canvas runtime | `konva-designer.js` | `saveDesign()`, `loadDesign()`, `autoExtendCanvas()`, `drawPageBreaks()` |

## How to extend it

### Add a new placeholder/field
1. Add the key to `tpl_map_billing_data_to_template()` in `template_receipt_helper.php` (this is what actually populates the value at render time for both V1 and V2).
2. Optionally add a row to `print_template_placeholders` (`placeholder_key`/`field_key` depending on which schema variant your DB has — see the schema-drift note in this document) so it shows up in the designer's field picker with a friendly label/group, instead of relying on auto-discovery (`_auto_label()`/`_auto_group()` in `print_template_model.php`, which will still surface it automatically from `get_sample_data()` even without a DB row, just with a less curated label/group).

### Add a new bill/document type (template_category)
1. Add an entry to the `CATEGORIES` constant in `print_template_model.php`.
2. Wire the printing controller for that document type to call `find_konva_template()`/`render_konva_template()` (V2) and/or `print_template_model->render()` (V1) the same way `billing_invoice()` does, gated by whatever `ret_settings` flag you introduce for it (follow the `template_based` / `estimation_template_based` / `issue_receipt_template_based` naming pattern already in use).
3. Ensure `tpl_map_billing_data_to_template()` (or a new sibling mapper, following the `tpl_map_*_data_to_template()` naming pattern) produces the flat data array the renderer expects.

### Add a new V2 canvas element type
1. Add a sidebar tile in `designer_v2.php` (`data-tool="..."`).
2. Add a creator function in `konva-designer.js` following the pattern of `addText()`/`addRect()`/`addSpacer()` — set a `customType` attribute if the element needs special print-time handling (plain Text/Rect/Line/Image don't need one; tables/spacer/QR do).
3. Handle the new `customType` in `_konva_render_node()` in `konva_receipt_helper.php` — this is the dispatch point that turns a Konva JSON node into an HTML block at print time. **This is the step most likely to be forgotten** — a new element type that only exists on the design-time side but isn't handled in `_konva_render_node()` will silently render as nothing (or fall through to a default), which is the "dual-engine drift" risk called out in [02_Old_vs_New_Comparison.md](02_Old_vs_New_Comparison.md).

### Add a new print format (paper size, etc.)
Add to the `$papers`/`PAPERS` lookup table in **both** `konva-designer.js` (client, mm dimensions for canvas sizing) and `konva_receipt_helper.php` (server, must match exactly or the print output will be mis-sized relative to what the designer showed).

## Database schema — confirmed tables

`print_templates` is the canonical table (consolidated definition in `database/migrations/20260429_000007a_safety_create_print_template_tables.sql`):

| Column | Type | Notes |
|---|---|---|
| `id_template` | INT AUTO_INCREMENT PK | |
| `template_code` | VARCHAR(100) | Unique per version (`{code}_{unix_time}`) |
| `code` | VARCHAR(100) | Groups all versions of one logical template together — this is the versioning key |
| `template_name` | VARCHAR(255) | |
| `template_category` | VARCHAR(50), default `'0'` | Maps to `print_template_model::CATEGORIES` |
| `gjs_data` | LONGTEXT | Konva JSON (V2) or GrapesJS-era data (V1 legacy sub-format) |
| `header_gjs_data` / `body_gjs_data` / `footer_gjs_data` | LONGTEXT | present in schema; not observed populated by current V1/V2 save paths |
| `template_html` / `template_css` | LONGTEXT / TEXT | V1 compiled output |
| `config_json` | LONGTEXT | |
| `paper_size`, `paper_width`, `paper_height`, `page_orientation` | | |
| `margin_top/right/bottom/left` | VARCHAR(20) | |
| `is_default`, `is_active` | TINYINT(1), default `0` | Both default to inactive/non-default on creation |
| `version_number`, `parent_template_id` | INT | Lineage |
| `id_branch` | INT, nullable | Branch scoping; `NULL` = applies to all branches |
| `created_by`, `created_at`, `updated_at` | | `created_by` is only actually populated by `import()`, **not** by `save()` — a gap worth knowing when auditing who created a manually-added template |

Supporting tables: `print_template_placeholders` (field registry — **see schema drift warning below**), `print_template_computed_vars` (`id_template`, `var_name`, `var_label`, `formula`, unique on `(id_template, var_name)`), `print_template_mc_va_config` (per-branch/document-type MC/VA label config), `print_template_defaults` (system-provided starter layouts), `print_template_custom_blocks` (V1 custom block library).

### ⚠️ Confirmed schema drift — read before trusting a DDL file

Two tables have **inconsistent column definitions** between the `database/migrations/*.sql` files and the actual dev-DB dumps under `sqls/*.sql`:

- **`print_template_placeholders`**: migrations define `field_key`/`field_label`/`is_loop_field`/`parent_loop`; the dev-DB dump defines `placeholder_key`/`placeholder_label`/`placeholder_group`/`sample_value`/`display_order`. The model code (`get_placeholders()`, `get_placeholders_v2()`) reads a **hybrid** of both column sets. Do not assume either single file is the ground truth — check the actual target database before writing a migration against this table.
- **`print_template_custom_blocks`**: the migration defines a rich 11-column table; the controller (`save_custom_block()` in `admin_print_template.php`) **creates its own smaller 5-column table at runtime** (`id, block_name, block_html, created_at, updated_at`) if the table doesn't already exist, and this smaller shape matches the dev-DB dump. This table is also the only one in the module **never touched via the model** — all reads/writes happen directly in the controller with raw `$this->db` calls.
- **`print_template_mc_va_config`**: possible drift between the migration's `mc_type`/`va_type`/`mc_visible`/`va_visible` columns and the controller's actual read/write keys `mc_mode`/`va_mode` — flagged as unconfirmed; verify against a live DB before relying on either.

## Known architectural risk: dual-engine drift (V2)

The single biggest structural fact to internalize before changing V2 code: **the Konva canvas (what the designer shows in the browser) and `render_konva_template()` (what the server reconstructs for print) are two independent implementations**, not a canvas export. `konva_receipt_helper.php` contains extensive comments documenting past bugs from this — e.g. the table-height calculation and the page-boundary value must be computed identically in the pre-calculation pass and the render pass, or "trailing content" ends up rendered inside a table; a prior JS "canvas-delta correction" pass double-counted PHP's own layout math and pushed content off the page. See [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md) for the full list.
