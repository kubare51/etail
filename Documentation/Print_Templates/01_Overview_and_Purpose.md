# Print Templates Module — Overview & Purpose

> Route: `/print-templates` · Controller: `admin/application/controllers/admin_print_template.php` · Model: `admin/application/models/print_template_model.php`

## What it is

The Print Templates module is eTail's system for designing and rendering the HTML documents that get printed for a business transaction — most importantly the sales bill/invoice, but also estimation printouts and issue-receipts. It replaces (partially — see [02_Old_vs_New_Comparison.md](02_Old_vs_New_Comparison.md)) a set of hardcoded PHP view files with database-driven templates that a non-developer can edit visually in the browser.

A `print_templates` DB table row holds one version of one template: its layout data, paper size/orientation, margins, and metadata (category, active/default flags, version lineage). At print time, the billing/estimation/issue-receipt controllers look up the active template for the relevant bill type and branch, and hand it to a rendering function that produces a full HTML document, which the browser then prints via `window.print()`.

## Why it was built

Prior to this module, every print layout was a literal `.php` view file under `admin/application/views/billing/print/` (e.g. `bill_format_2.php`, `receipt_billing.php`) containing raw HTML mixed with PHP business logic. Changing what a bill looked like — moving a field, adding a company logo, adjusting column widths — required a developer to edit and deploy PHP code, per client (this codebase serves 70+ client deployments, each with its own customizations layered on top of these files; see the client-specific copies `bill_format_konika.php`, `anb_bill_format.php`, `sparqle_bill_format.php`, `navratna_bill_format.php` still sitting in the tree).

The Print Templates module exists to move that layout authority out of code and into data, so that:
- A store admin (not a developer) can redesign a receipt layout without a deployment.
- Layout changes are per-tenant configuration (a `print_templates` row scoped by `template_category` and optionally `id_branch`), not a forked PHP file.
- Multiple bill types/document kinds (sales, estimation, issue receipt, and more — see the `template_category` map in `print_template_model.php`) can share one designer and one rendering engine instead of one hand-written view per kind per client.

## What problem it solves (and what it doesn't, yet)

**Solves:**
- Visual, no-deploy editing of print layouts (two different editor generations — see [08_Canva_Like_Editor.md](08_Canva_Like_Editor.md)).
- A shared, DB-backed placeholder/variable system (`print_template_placeholders`, plus a computed-variable formula engine in `print_template_computed_vars`) so templates can reference `{{invoice_no}}`, `{{customer_name}}`, computed values like `{{sub_total}} - {{discount}}`, etc., without a developer wiring each one by hand per template.
- Versioning: every save creates a new template row rather than mutating the live one, so there's a lineage (`version_number`, `parent_template_id`) and a one-click "revert to default" path.

**Does not (yet) solve** — flagged here so this isn't oversold, detailed further in [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md):
- It has not fully replaced the legacy PHP print views — those are still the final fallback (and still the *only* path for several other print flows: cheque printing, insurance-certificate bills, thermal repair receipts). See [02_Old_vs_New_Comparison.md](02_Old_vs_New_Comparison.md).
- There is no role/permission gating on the module — any logged-in admin user can create, edit, or delete any template (confirmed by direct code inspection of `admin_print_template.php`'s constructor and every action method — see [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md)).

## The two generations living side by side

This module actually contains **two designer generations** sharing one controller, one model, and one `print_templates` table:

| | V1 ("Designer") | V2 ("Designer V2", Konva Canvas) |
|---|---|---|
| Entry route | `print-templates/designer/{id}` | `print-templates/designer-v2/{id}` |
| Editing model | Block/section editor (stack of configurable HTML blocks: company header, customer info, items table, etc.), with an embedded GrapesJS-powered sub-modal for authoring custom block HTML | Freeform absolute-position canvas (drag text/image/table/shape anywhere), closer to a "Canva-style" design tool |
| Stored as | `template_html` / `template_css` (compiled from blocks) | `gjs_data.konva_json` (serialized Konva.js stage) |
| Renderer | `print_template_model->render()` (Mustache-style `{{}}` substitution over stored HTML) | `render_konva_template()` in `konva_receipt_helper.php` (reconstructs HTML from the JSON node graph, with pagination logic) |
| Selected by `ret_settings.template_based` | `1` | `3` |

Every new template created via `add()`/`save()` is redirected straight into the **V2** designer (`admin_print_template.php`'s `save()` method always redirects to `print-templates/designer-v2/<id>` on success) — V1 is effectively legacy-maintained-but-not-the-default-path-for-new-templates, even though its controller actions are all still live and reachable.

## Who uses this today

Confirmed live consumer: **bill invoice printing**, `billing_invoice()` in `admin/application/controllers/admin_ret_billing.php`, gated by the `ret_settings.template_based` value (see [04_Feature_Settings.md](04_Settings.md)). Also used by **estimation print** (`admin_ret_estimation.php`, gated by `estimation_template_based`) and **issue-receipt print** (`admin_ret_billing.php`, gated by `issue_receipt_template_based`) — both of those, however, only reach the older V1 `print_template_model->render()` path, not the V2 Konva renderer; the V2 Konva path (`find_konva_template()` / `render_konva_template()`) is currently wired up for the bill invoice flow only.
