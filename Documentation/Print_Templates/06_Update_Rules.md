# Feature Documentation — Update Rules

## The core rule: saves never mutate the live row — they insert a new version

This is the single most important fact about editing a template. Both save-design endpoints —

- `POST print-templates/save-design/{id}` → `admin_print_template.php::save_design()` (V1)
- `POST print-templates/save-design-v2/{id}` → `admin_print_template.php::save_design_v2()` (V2)

— follow the same pattern:

1. Load the current row by `$id`.
2. Compute `$group_code` = the row's `code` column (falling back to `template_code` if `code` is empty).
3. Build a **new** `template_code = "{group_code}_" . time()`.
4. Insert a **new row**: `code = $group_code`, `is_active = 1`, `is_default = 0`, `version_number = old_version + 1`, `parent_template_id = old_id`.
5. Call `print_template_model->deactivate_by_group_code($group_code)` to set `is_active = 0` on every other row sharing that `code`.
6. Copy `print_template_computed_vars` rows from the old template id to the new one.

**Consequence for "what can be edited after creation":** you never actually edit the original row — every save produces a full new historical version. The metadata fields set at creation (`template_name`, `template_category`, `code`) generally persist forward through this copy-and-insert process, but the design content (`gjs_data`/`template_html`/`template_css`) and paper/margin settings are what actually change per save.

## Paper size / orientation / margins

- **V1** (`save_design()`): persists print margins if `data['margins']` is present (top/right/bottom/left cast to float) as part of the new row.
- **V2** (`save_design_v2()`): additionally persists `paper_settings` (paper size, orientation, all four margins) with an explicit fallback chain — POSTed value → current row's existing value → hardcoded default (10mm margins, portrait).
- There is also a narrower, dedicated method for paper/margin-only updates: `print_template_model->update_paper_settings($id, $settings)` — the **only** model write method that whitelists its incoming keys (`paper_size`, `paper_width`, `paper_height`, `page_orientation`, `margin_top/right/bottom/left`) before writing, and the only one that does an in-place `UPDATE` rather than an insert-new-version.

## Versioning behavior

Confirmed, real versioning system — not aspirational:

- `code` groups all versions of one logical template.
- `version_number` / `parent_template_id` record lineage (each save increments version, references its predecessor).
- Only one row per `code` group is meant to have `is_active = 1` at a time — but this is enforced **procedurally**, not by a database constraint. There is no unique index or trigger preventing two active rows in the same group; it relies entirely on the application always calling `deactivate_by_group_code()` immediately before/around the activating insert, within the same request.
- **`save_design()` and `save_design_v2()` are not wrapped in a database transaction** (unlike `save()` and `import()`, which do use `trans_begin()`/`trans_commit()`). A failure between the deactivate step and the insert step — e.g. a crash or DB error mid-request — could leave a `code` group with **zero active versions**, meaning that bill type/branch would stop printing (V1 falls back to legacy view; V2 shows the hard "not configured" page) until someone manually reactivates a version.

## Reverting to a previous / default version

- **`toggle_active($id)`** → `activate_and_deactivate_others($id)`: promotes an arbitrary existing version within its `code` group to active, deactivating its siblings. Available from the template list UI.
- **`revert_default()`** → `print_template_model->revert_to_default($code)`: a specific "restore the system default" flow. It does **not** restore the original default row in place — it **copies** the row marked `is_default = 1` for that `code` group into a brand-new row (new `template_code`, `is_active = 1`, `version_number = default_version + 1`, `parent_template_id = default_id`), and deactivates whatever was previously active. If the currently-active row already *is* the default, it returns a "nothing to do" failure rather than creating a no-op version. Reachable only via a raw CodeIgniter segment URL (`index.php/admin_print_template/revert_default`) — there is no pretty route for it, though it works from `list.php` regardless.
- **`set_default($id)`** — marks a specific version as the category/branch default (used by `get_default()`'s tie-breaking and by `revert_to_default()`'s target). **No check that the target row is `is_active`** — you can mark an inactive/archived version as the default, which would only take effect the next time someone reverts to default or the active row is otherwise cleared.

## Validation and permission rules on update

- **No permission/role checks of any kind** gate any update action — see [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md) for the full finding. Any logged-in admin user can save a design, toggle activation, or revert to default on any template, regardless of role.
- **No required-field validation** on `save_design()`/`save_design_v2()` beyond checking the POST body decoded to something non-empty (`if (!$data)`) and that the target template id exists. An empty/near-empty design can be saved as a new active version with no warning.
- **Computed-variable formulas** (edited via the designer's formula editor, persisted through `save_computed_var()`) do have name sanitization — `var_name` is lowercased and stripped to `[a-z0-9_]` — but the `formula` field itself is stored as free text with no content validation. It is safe regardless: the formula is only ever evaluated later by `_tpl_eval_expr()`, a closed arithmetic grammar that aborts (returns `null`, leaving the placeholder blank) on anything it can't parse — it cannot execute arbitrary code even if the formula text is nonsensical or malicious-looking.

## What can't be changed after creation

- The `id_template` primary key and the `code` group identity are effectively permanent for a lineage — there's no "move this template to a different group" operation; doing so would require manually editing the DB or exporting/re-importing under a new code.
- There's no field-level "locked after publish" concept — everything (category, branch scope, name) can be changed on the next save, since a save just writes a whole new row with whatever the request supplies.
