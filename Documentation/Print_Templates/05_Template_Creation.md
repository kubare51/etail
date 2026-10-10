# Feature Documentation — Template Creation

## Step-by-step flow

1. **`GET print-templates/add`** → `admin_print_template.php::add()` renders `print_templates/form.php` with the category list (`print_template_model::CATEGORIES`) and a hardcoded paper-size list: `['A4','A5','Letter','Thermal-58mm','Thermal-80mm','Custom']`.
2. User fills the metadata form and submits **`POST print-templates/save`** → `admin_print_template.php::save()`.
3. Two paths inside `save()`:
   - **Blank template**: no file uploaded → seeds `gjs_data = json_encode([])` and `template_html = '<div style="padding: 20px;">New Template</div>'`.
   - **Import-on-create**: a `template_file` is uploaded → validated via `_read_template_upload()`; if the decoded payload contains no `gjs_data`/`template_html`, the user is redirected back to `add` with a flash error ("That file contains no template design.").
4. Row is inserted with `is_active = 0`, `is_default = 0` — **a newly created template is never automatically active or default**, regardless of path.
5. Insert + computed-var copy are wrapped in `$this->db->trans_begin()` / `trans_commit()` / `trans_rollback()` (correct transactional pattern per this repo's conventions).
6. On success, the user is **always redirected to `print-templates/designer-v2/{new_id}`** — i.e. every newly created template lands in the V2 Konva canvas designer next, never the V1 block editor, even though V1 remains fully reachable afterward via its own route.

## Fields captured on the "Add" form

| Field | POST key | Required server-side? |
|---|---|---|
| Template code (group key) | `template_code` | No — no empty check |
| Code (alt/legacy grouping key) | `code` | No, nullable |
| Template name | `template_name` | No — no empty check |
| Category | `template_category` | No — no empty check |
| Paper size | `paper_size` | No |
| Orientation | `page_orientation` | No |
| Branch scope | `id_branch` | No, nullable (omit = applies to all branches) |
| Optional design file | `template_file` | No — only validated *if present* |

**Confirmed gap**: `save()` performs **no required-field validation** on any of the metadata fields — `template_name`, `template_category`, etc. could all be submitted blank and would be inserted as empty/NULL. The only validation in this method concerns the *optional* file upload, and only checks that the decoded JSON contains recognizable design data, not that the metadata is sane.

## Defaults applied at creation

- `is_active = 0`
- `is_default = 0`
- `gjs_data = '[]'` and `template_html = '<div style="padding: 20px;">New Template</div>'` when no file is uploaded
- `created_by` is **not** set by `save()` (unlike `import()`, which does stamp `created_by` from the session user id) — a template created via the "Add" form has no recorded creator.

## Creating via import instead

`POST print-templates/import` → `admin_print_template.php::import()` is the other creation path, used to bring in a template exported from another environment/template. Validation here is materially stronger than `save()`:
- File must be present, error-free, ≤10MB, and a genuinely uploaded file (`is_uploaded_file()`).
- Must decode as JSON with `format === 'etail-print-template'` and a non-empty `template` object.
- The payload's columns are whitelisted against the real `print_templates` table columns (`_template_row_from_payload()`, via `$this->db->list_fields('print_templates')`) before insert — protects against a hand-edited JSON file injecting unexpected columns.
- Forces a fresh identity regardless of what the import file says: new `template_code = {group_code}_{unix_time}`, `is_active = 0`, `is_default = 0`, fresh `created_at`/`updated_at`.
- **Does** stamp `created_by` from `$this->session->userdata('user_id')` if present.
- Wrapped in a DB transaction, same as `save()`.

## What happens immediately after creation

Because the new row starts with `is_active = 0`, the template exists but **prints nothing** until it's activated — either by saving a design in the designer (which, per the versioning model documented in [03_Architecture_and_Developer_Guide.md](03_Architecture_and_Developer_Guide.md), inserts a *new* active row and deactivates siblings sharing the same `code`), or explicitly via `toggle_active($id)` from the template list.

## Known gap worth flagging to anyone building on top of this flow

There is no uniqueness check on `template_name` or `code` at creation — you can create multiple templates with identical names/codes with no warning. The `code` column is how versioning/activation grouping works (see [03_Architecture_and_Developer_Guide.md](03_Architecture_and_Developer_Guide.md)); accidentally reusing an existing `code` value on a brand-new "Add" would place the new template into an existing version group's history rather than starting a fresh lineage. There is no explicit test/guard against this in `save()`.
