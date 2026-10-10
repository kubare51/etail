# Limitations & Known Issues

All items below are confirmed by direct code inspection, not assumption. Where a limitation is implicit (inferred from code behavior) rather than explicitly commented, that's noted.

## Security / access control

1. **No permission or role checks anywhere in this module.** `admin_print_template.php` extends `CI_Controller` directly (not any shared auth base) and its constructor only checks `session->userdata('is_logged')`. Grepping the controller, the model, and every `print_templates/*.php` view for permission-related keywords (`get_access`, `role`, `acl`, `permission`, etc.) returns zero matches. Compare to a peer controller like `admin_ret_billing.php`, which additionally checks a time-window access restriction — `admin_print_template.php` has even less than that baseline.
2. **No menu/sidebar entry exists for this module.** None of the print-template migration files insert a `menu`/`access` row (other modules routinely do this). The admin sidebar is generated from those tables, so this module has no navigational entry point and, as a direct consequence, cannot be gated by the role/permission system that *does* operate on menu-linked routes elsewhere in the app. **Net effect: any authenticated user who knows or guesses the `/print-templates` URL has full create/edit/delete/export/import access, with no role restriction.**
3. **`delete($id)` has no server-side guard** against deleting an active/default template — the protection is a UI button-hiding rule only (see [07_Delete_Rules.md](07_Delete_Rules.md)).
4. **`preview($id)` has no null-check** on the loaded template before dereferencing its category — every comparable id-based loader in the same file (`designer()`, `load_design()`, `save_design()`, `designer_v2()`, `save_design_v2()`, `preview_live_v2()`) does check; this one doesn't, so a bad/deleted id would trigger a PHP notice/fatal rather than a clean redirect.

## Data integrity

5. **`save_design()` and `save_design_v2()` are not wrapped in a DB transaction**, unlike `save()`/`import()` which correctly use `trans_begin()`/`trans_commit()`. A failure between the "deactivate old versions" step and the "insert new active version" step could leave a template group with zero active versions, silently breaking print for that bill type/branch.
6. **No dependency/usage check before delete**, single or bulk — see [07_Delete_Rules.md](07_Delete_Rules.md) for full detail. Deleting an active/default template is not blocked and produces a silent change in what prints.
7. **`print_template_computed_vars` rows are orphaned on template delete** — no cascade, no explicit cleanup.
8. **Confirmed schema drift** between `database/migrations/*.sql` and actual dev-DB dumps (`sqls/*.sql`) for `print_template_placeholders` (different column names/sets: `field_key` vs `placeholder_key` lineages) and `print_template_custom_blocks` (a rich migration-defined schema vs. the much smaller schema the controller actually creates at runtime and that matches the real dev-DB dump). `print_template_mc_va_config` has a suspected but unconfirmed drift between migration columns (`mc_type`/`va_type`/`mc_visible`/`va_visible`) and the columns the controller actually reads/writes (`mc_mode`/`va_mode`).
9. **`_get_dynamic_fields_v2()` in `print_template_model.php` has duplicated logic** — the category-16 (Estimation) and category-17 (Issue Receipt) field-registration blocks each appear twice in sequence, pushing duplicate entries into the field list (harmless for a picker UI, but a real duplication bug worth cleaning up).

## Architecture / regression risk (V2 specifically)

10. **Dual-engine drift** — the single largest structural risk. The browser Konva canvas and the server-side `render_konva_template()` reconstruction are independent implementations of "what this layout looks like"; the file is dense with comments documenting specific past incidents from this (table-height/page-boundary calculations that drifted between the pre-calc pass and render pass and put trailing content inside a table; a JS "canvas-delta correction" pass that double-counted PHP's own layout math and pushed content off the page). See [02_Old_vs_New_Comparison.md](02_Old_vs_New_Comparison.md) and [03_Architecture_and_Developer_Guide.md](03_Architecture_and_Developer_Guide.md).
11. **Pagination complexity** — table chunking, text-wrap deltas, hidden-conditional-zone collapse, and repeat/footer-zone pinning all interact through a single shared "cursor" in the PHP renderer. Small changes are explicitly called out in code comments as regression-prone.
12. **Large, multi-concern files** — `konva-designer.js` (~4,900 lines) and `konva_receipt_helper.php` (~2,800 lines) each handle many distinct concerns (selection, history, snap guides, rulers, save/load, table layout, pagination, conditional evaluation) in one file, making isolated testing and change-impact reasoning harder.
13. **Dead/vestigial "Smart Reflow" code** in `konva-designer.js` — a full row-grouping/auto-layout system is still defined but disabled at every call site, annotated as having destroyed manual multi-column layouts when it was active. Left in place as dead code; could confuse a future maintainer into thinking it's live.
14. **No client-side save validation** — an empty canvas, off-page elements, or overlapping elements are all saved without warning (see [08_Canva_Like_Editor.md](08_Canva_Like_Editor.md)).

## Coverage gaps — what the template system does not reach

15. **Not wired to estimation or issue-receipt V2 rendering.** `estimation_template_based` and `issue_receipt_template_based` only ever reach the V1 `print_template_model->render()` path — there is no Konva V2 rendering for those document types, even though the setting-naming convention suggests parity with billing.
16. **Several print flows never touch the template system at all** and always use hardcoded legacy views: cheque printing, insurance-certificate bills, thermal repair receipts, and a raw debug data dump. These would require new engineering work to migrate, not a settings change.
17. **Six legacy print view files are dead/orphaned** in the tree (`bill_format_konika.php`, `bill_format_2_arc.php`, `anb_bill_format.php`, `sparqle_bill_format.php`, `navratna_bill_format.php`, `billing_soft_copy.php`) — not called from anywhere, kept only as historical client-fork reference. Not itself a bug, but a housekeeping note for anyone auditing this directory.
18. **Two different data-mapper naming generations coexist**: the legacy `receipt_helper.php` mappers (`map_estimation_data_to_template()`, `map_issue_receipt_data_to_template()` — still actively called) alongside the newer `template_receipt_helper.php` mappers (`tpl_map_billing_data_to_template()`, `tpl_map_estimation_data_to_template()`, `tpl_map_issue_receipt_data_to_template()`). Notably, `tpl_map_issue_receipt_data_to_template()` exists but is **not actually called from any live controller path found** — the live issue-receipt flow still uses the older `map_issue_receipt_data_to_template()`. This suggests an incomplete migration between the two mapper generations; a developer should confirm which one is authoritative before modifying issue-receipt fields.
19. `receipt_helper.php`'s original `map_billing_data_to_template()` (no `tpl_` prefix) is confirmed **dead code** — fully superseded by `tpl_map_billing_data_to_template()` for the billing flow, referenced only in comments elsewhere, never called.

## Performance / operational

20. No performance issue was directly measured as part of this documentation pass (no profiling was performed) — this section intentionally does not assert a performance problem that wasn't observed. The pagination pipeline in `render_konva_template()` does multiple full passes over the node list per render (hidden-zone computation, text-wrap pre-calc, table delta pre-calc, final render, page assembly) — worth keeping in mind for very large/complex templates, but not confirmed as a measured bottleneck.

## Explicitly "Not implemented" (stated plainly per documentation convention, not left ambiguous)

- Soft delete / trash-and-restore for templates.
- Role/permission-based access control for any print-template action.
- Server-side required-field validation on template metadata creation (`save()`).
- Dependency/usage checks before template deletion.
- Database-level (constraint/trigger) enforcement of the single-active-version-per-group invariant — it's application-level discipline only.
- V2 Konva rendering for estimation or issue-receipt document types.
- Real, scannable QR code preview inside the V2 editor (the on-canvas QR is a decorative placeholder; the real code is generated only at print time).
