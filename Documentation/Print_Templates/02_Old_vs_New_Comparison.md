# Old PHP File-Based Print vs New Template System

## The old system

Static PHP view files under `admin/application/views/billing/print/`, loaded directly via `$this->load->view('billing/print/bill_format_2', $data, true)`. Confirmed still-live files and their call sites:

| File | Used for | Called from |
|---|---|---|
| `receipt_billing.php` | Legacy bill layout when `ret_settings.bill_format == 0` | `admin_ret_billing.php` (`billing_invoice()` final fallback), `admin_app_api_backup.php` |
| `bill_format_2.php` | Legacy bill layout when `ret_settings.bill_format != 0` (the common default) | `admin_ret_billing.php` (`billing_invoice()` final fallback), `admin_app_api.php`, `admin_app_api_backup.php` |
| `thermal_print.php` | Repair-order thermal receipt | `admin_ret_billing.php` (`repair_order_thermal_print()`) |
| `insured_bill.php` | Insurance-certificate print | `admin_ret_billing.php` (`billing_insurance()`) |
| `viewdb.php` | Raw debug data dump (not a real bill format) | `admin_ret_billing.php` (`viewdb()`) |

Confirmed **dead/orphaned** in this source tree — present on disk but not referenced by any `load->view()` call anywhere in the repo: `bill_format_konika.php`, `bill_format_2_arc.php`, `anb_bill_format.php`, `sparqle_bill_format.php`, `navratna_bill_format.php`, `billing_soft_copy.php`. These are historical client-specific forks kept for reference, not part of any live flow.

## The new system

The `print_templates` DB table plus two designer UIs (V1 block editor, V2 Konva canvas — see [08_Canva_Like_Editor.md](08_Canva_Like_Editor.md)) and two renderers (`print_template_model->render()`, `render_konva_template()`).

## Direct comparison

| | Old (PHP file views) | New (Print Templates) |
|---|---|---|
| Where layout lives | Hardcoded HTML/PHP in a `.php` view file, one per client fork | A row in `print_templates.gjs_data`/`template_html`, editable per category/branch |
| Who can change it | A developer, via code edit + deploy | Any logged-in admin user, via the browser designer, no deploy |
| Per-client customization | A forked copy of the view file (`anb_bill_format.php`, `sparqle_bill_format.php`, etc.) | A separate template row scoped by `id_branch`/`template_category` — no code fork needed |
| Versioning / rollback | Git history of the file; no in-app rollback | In-app version lineage (`version_number`, `parent_template_id`) + one-click "Revert to Default" (`revert_to_default()`) |
| Placeholder/variable system | Ad hoc — whatever PHP variables the view author wired in directly | Centralized registry: `print_template_placeholders` + dynamically-discovered fields from `tpl_map_billing_data_to_template()` + user-defined computed-variable formulas |
| Multi-page / pagination | Manual PHP loops per format file | Automatic page-break, table-chunking, and repeat-zone (header/footer) logic in `render_konva_template()` (V2 only) |
| PDF output | Some flows use DomPDF (`new DOMPDF(); ...; $dompdf->stream(...)`) — e.g. `admin_ret_billing.php`'s Receipt.pdf/Cheque.pdf streams | The template-driven paths (V1 and V2) are pure HTML echoed to the browser and printed via `window.print()` — DomPDF is **not** used in the Konva/V1-template render path (it's used elsewhere in the same controller for unrelated features) |

## Advantages of the new system

- **No-deploy layout changes.** The core reason it exists (see [01_Overview_and_Purpose.md](01_Overview_and_Purpose.md)).
- **One rendering engine per generation, not one file per client.** Reduces the "70+ client forks of a print view" sprawl going forward, for whichever bill types have been migrated onto it.
- **Structured variable/placeholder system**, including a genuinely safe formula evaluator for computed variables (`_tpl_eval_expr()` in `print_template_model.php` — a hand-written recursive-descent arithmetic parser, confirmed **not** using PHP `eval()`; unknown identifiers or unrecognized characters abort parsing rather than executing anything).
- **Automatic pagination** (V2): table row chunking across pages, repeat-every-page header/footer zones, conditional-block collapse — logic that in the old system would have to be hand-coded per format file.
- **Import/export** of a template as a portable JSON file (`export()`/`import()` controller actions), useful for moving a design between environments.

## Disadvantages / trade-offs of the new system

- **Dual-engine drift risk.** The V2 designer (browser Konva canvas) and the V2 renderer (server-side PHP HTML reconstruction) are two separate implementations of "what does this layout look like." Anything the browser can visually do that the PHP renderer doesn't account for produces a print output that doesn't match what the designer showed. This is the single largest structural risk flagged in the pre-existing `Documentation/PRINT_TEMPLATE_DESIGNER_V2_TECHNICAL_STUDY.md` technical study, and it's corroborated by the density of "this must match X exactly or Y silently breaks" comments inside `konva_receipt_helper.php`.
- **No permission/role gating**, unlike almost every other admin module in this codebase (see [04_Settings.md](04_Settings.md) and [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md) for the `get_access()` pattern this module doesn't use).
- **No dependency/usage check before delete** — deleting a template that's currently the active/default one for a bill type is not blocked server-side (see [07_Delete_Rules.md](07_Delete_Rules.md)).
- **Two schema-drifted supporting tables** (`print_template_placeholders`, `print_template_custom_blocks` — see [03_Architecture_and_Developer_Guide.md](03_Architecture_and_Developer_Guide.md)) where the migration-file schema and the actual dev-DB dump disagree, evidence the module's own schema management has had gaps.
- **Not a full replacement** — several print flows (cheque printing, insurance certificate, thermal repair receipt, debug view) never route through the template system at all; they call the legacy PHP views directly and always will unless someone migrates them.

## Advantages the old PHP approach had

- **Zero indirection.** What you see in the file is exactly what prints — no separate "design-time JSON" vs "print-time HTML reconstruction" gap to keep in sync.
- **Full power of PHP** available directly in the layout (arbitrary conditionals, loops, function calls) — the new system's Mustache-style `{{}}` substitution and closed arithmetic-expression grammar are deliberately more constrained (safer, but less expressive; e.g. the computed-variable evaluator supports only `+ - * /` and parentheses, no functions).
- **No moving parts at print time** beyond "render a PHP view" — no JSON parsing, no pagination heuristics, no client/server position-reconciliation.

## Disadvantages of the old PHP approach

- Every layout tweak needs a code change and deployment, multiplied across 70+ client forks.
- No visual editing — a developer has to reason about pixel/mm positioning in raw HTML/CSS.
- No structured placeholder registry — reusing a data field across formats means re-wiring it by hand in each file.
- No versioning/rollback beyond git; a bad change is live until re-deployed.

## Migration notes

- **Nothing changes automatically for existing PHP-based prints.** The old files are not deprecated or removed — they remain the default and final fallback. A given bill type only starts using the new system when `ret_settings.template_based` is explicitly set to `1` or `3` for that install (see [04_Settings.md](04_Settings.md)); with no value set (or any other value), the legacy view fires exactly as before.
- **The switch is per-setting, not per-template.** `template_based` is a single global switch read at print time (not scoped by category in the switch logic itself, though the templates it then looks up are scoped by category/branch) — turning it on affects every bill invoice print for that install at once, not one bill type at a time.
- **Failure falls back, except for the strict "billing" group.** If `template_based == 1` and the V1 render returns empty/false, execution falls through to the legacy static view — so a broken V1 template degrades gracefully. If `template_based == 3`, the V2 Konva path deliberately does **not** fall through to V1 or legacy on failure — it shows an explicit "template not configured" page instead (`konva_template_not_configured_page()`), by design, per an explicit comment in `konva_receipt_helper.php`: "Printing must never silently fall back to a different template — a bill printed from the wrong layout looks correct and gets handed to a customer."
- **Estimation and issue-receipt prints only ever reach V1**, never V2 — `estimation_template_based` and `issue_receipt_template_based` both gate `print_template_model->render()` only. A client wanting the Konva canvas experience for those document types would need new engineering work, not just a settings change.
