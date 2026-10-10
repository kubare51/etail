# Feature Documentation — Delete Rules

## Soft delete vs hard delete: it's hard delete

`print_template_model::delete($id)` (`print_template_model.php`):
```php
public function delete($id) {
    return $this->db->where('id_template', $id)->delete('print_templates');
}
```
This is an unconditional `DELETE FROM print_templates WHERE id_template = $id`. **There is no soft-delete column** — no `deleted_at`, no `is_deleted` flag — anywhere in the `print_templates` schema. Once deleted, a template row is gone; there is no in-app "trash"/restore for it.

The bulk path, `print_template_model::delete_by_ids($ids)`, is the same hard delete via `WHERE_IN`, used by the controller's `bulk_delete()`.

## Dependency / "in use" checks before deletion: not implemented

Neither the model nor the controller checks whether a template is:
- The currently active template for its bill type/branch (`is_active = 1`)
- The category/branch default (`is_default = 1`)
- Referenced by any other data

...before deleting it. This is true for both the single-delete route and the bulk-delete route. Other modules in this codebase do implement this kind of check elsewhere (e.g. a stock-age master model explicitly blocks deletion of a config that's in use) — the print-template module does not follow that pattern.

**What actually happens if you delete an active/default template:** the row is removed. The next print request for that bill type/branch falls through to whatever `find_konva_template()` (V2) or `get_default()`/`render()` (V1) resolves to next — which, per the resolution logic in [03_Architecture_and_Developer_Guide.md](03_Architecture_and_Developer_Guide.md), may be a different category's template, the "Common" (category 0) template, or nothing at all (triggering the legacy PHP fallback for V1, or the hard "not configured" page for V2's strict `'billing'`-group lookup). This is a silent change in what prints for that bill type — there is no warning at delete time.

**Orphaned data**: `print_template_computed_vars` rows are not cleaned up when their owning template is deleted — there's no `ON DELETE CASCADE` in the schema and no explicit cleanup call in `delete()`/`delete_by_ids()`. Deleting a template leaves its computed-variable rows behind, referencing a now-nonexistent `id_template`.

## The only real guard is in the UI, not the server

`ajax_list()` in `admin_print_template.php` only *renders* the delete button (and the bulk-delete row checkbox) when a row is neither active nor default:
```php
if (!$t['is_active'] && !$t['is_default']) {
    // ...delete button rendered...
}
```
The controller action behind that button, `delete($id)`, performs **no equivalent check itself**:
```php
public function delete($id) {
    $this->print_template_model->delete($id);
    redirect('print-templates');
}
```
So this protection is entirely client-side/cosmetic. Any authenticated user (there is no role restriction on this module — see [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md)) who calls `print-templates/delete/{id}` directly (browser URL, curl, a crafted request) can delete an active or default template with no server-side objection.

## Bulk delete specifics

`bulk_delete()` (POST, reachable only via a raw CI segment URL — no pretty route):
- Validates `ids` is a non-empty array, casts to int, filters ≤0 values, and **caps at 100 records per request** (returns an error if you exceed that).
- **Backs up each deleted row's `gjs_data` to disk** before deleting: `FCPATH/template/{template_code}/{template_name}/{created_datetime}_{id}.txt`, with folder-name sanitization (`_sanitize_folder_name()`) to prevent path traversal from a crafted `template_code`/`template_name`. This is the closest thing to an undo mechanism that exists — a manual file-based backup, not a database restore path.
- **Not wrapped in a DB transaction**, despite doing file I/O plus a DB delete in the same request — a partial failure (e.g. disk write succeeds for some rows, then the DB delete fails) is possible, though the DB delete itself is a single bulk statement so DB-side atomicity for that part is fine.
- Same lack of active/default guard as single delete.

## Permission rules on delete

**Not implemented.** There is no role/permission check on `delete()` or `bulk_delete()` beyond the module-wide "must be logged in" check in the controller's constructor. See [09_Limitations_and_Known_Issues.md](09_Limitations_and_Known_Issues.md) for the full permission-model finding — this is not unique to delete, it's true of every action in this controller.

## Practical guidance (for anyone operating this feature, not just developing it)

- Treat "delete" in this module as genuinely permanent — there is no trash/undo through the UI, only the best-effort `.txt` backup file that `bulk_delete()` writes (and only for bulk delete, not single delete via the standard `delete($id)` route).
- Before deleting a template that might be active/default, manually verify its status in the list view first — the system will not stop you, and will not warn you, if you delete a template that's currently printing.
