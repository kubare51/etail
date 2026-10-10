/**
 * Test-data config for all 38 config-driven masters, mirroring
 * admin/application/config/master_entities.php. One generic spec
 * (master-crud.spec.js) drives every master from this data — same
 * "one engine, many configs" shape as the app itself, so adding a
 * 39th master later means adding one entry here, not a new spec file.
 *
 * field.kind:
 *  - 'text'          free text, value derived from field.rule (see valueFor)
 *  - 'fk'             <select> populated from another master/table — first
 *                     available option is auto-selected (dev-data independent)
 *  - 'fixed-select'   <select> with inline options declared on the field itself
 *  - 'date'           filled with a safe fixed date
 *
 * field.required / field.unique / field.rule mirror the same keys in
 * master_entities.php's own `rules` array for that field. `pk` is each
 * master's primary key column, needed to find the row created by an
 * add so later steps (edit/status-toggle) can target it by id.
 *
 * field.minVal/field.maxVal mirror master_entities.php's `min_val`/`max_val` —
 * several numeric fields (purity, old_metal_cat's percentages, tax_percentage,
 * mat_rate, rate_field) are range-checked ONLY, with no character-set `rule`
 * at all; don't assume every numeric field has a named rule.
 */

const TODAY = '2026-06-15';

// Masters below are excluded from the generic CRUD loop (master-crud.spec.js)
// because they carry a child_table with min_rows >= 1 — a parent-only Add
// always fails validation for these, so their full Add (with child rows),
// edit, and status-toggle coverage lives in master-special.spec.js instead,
// alongside their child-table diff-merge behavior.
const CHILD_TABLE_MASTERS = ['tgrp', 'attribute', 'diamond_rate', 'selling_diamond_rate'];

const masters = [
  { key: 'theme', label: 'Theme', pk: 'id_theme', statusCol: 'theme_status', fields: [
    { name: 'theme_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'theme_code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
    { name: 'theme_desc', kind: 'text', rule: 'remarks' },
  ]},
  { key: 'making_type', label: 'Making Type', pk: 'mak_id', statusCol: 'mak_status', fields: [
    { name: 'mak_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'mak_short_code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
  ]},
  { key: 'hook', label: 'Hook', pk: 'hook_id', statusCol: 'hook_status', fields: [
    { name: 'hook_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'hook_short_code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
  ]},
  { key: 'color', label: 'Color', pk: 'id_color', statusCol: 'status', fields: [
    { name: 'color', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'description', kind: 'text', rule: 'remarks' },
  ]},
  { key: 'cut', label: 'Cut', pk: 'id_cut', statusCol: 'status', fields: [
    { name: 'cut', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'description', kind: 'text', rule: 'remarks' },
  ]},
  { key: 'clarity', label: 'Clarity', pk: 'id_clarity', statusCol: 'status', fields: [
    { name: 'clarity', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'description', kind: 'text', rule: 'remarks' },
  ]},
  { key: 'uom', label: 'UOM', pk: 'uom_id', statusCol: 'uom_status', fields: [
    { name: 'uom_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'uom_short_code', kind: 'text', required: true, rule: 'shortCode' },
  ]},
  { key: 'screw', label: 'Screw', pk: 'screw_id', statusCol: 'screw_status', fields: [
    { name: 'screw_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'screw_short_code', kind: 'text', required: true, rule: 'shortCode' },
  ]},
  { key: 'financial_year', label: 'Financial Year', pk: 'fin_id', statusCol: 'fin_status', fields: [
    { name: 'fin_year_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    // Real fin_year_code values are bare numeric strings (e.g. "26", "23") —
    // two dependent in_use_check tables (ret_karigar_metal_issue.fin_year_code,
    // ret_stock_issue.fin_year) store this SAME code as an int column. A
    // non-numeric code here (e.g. "FY45") gets coerced to 0 by MySQL when
    // compared against those int columns, coincidentally colliding with
    // legacy rows that predate fin-year tracking (531 rows sitting at 0) —
    // false-blocking deactivation. Stay in the 90-98 range, well outside any
    // real financial year currently in use, to avoid colliding with those too.
    { name: 'fin_year_code', kind: 'text', required: true, rule: 'shortCode', value: () => String(90 + Math.floor(Math.random() * 9)) },
    { name: 'fin_year_from', kind: 'date', required: true, value: () => '2026-04-01' },
    { name: 'fin_year_to', kind: 'date', required: true, value: () => '2027-03-31' },
  ]},
  // statusCol added 2026-07-23 (I-GMSJ-005) — see database/migrations/20260723_000001_*
  { key: 'metal_type', label: 'Metal Type', pk: 'id_metal_type', statusCol: 'status', fields: [
    { name: 'metal_type', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'id_metal', kind: 'fk', required: true },
  ]},
  { key: 'stock_issue_type', label: 'Stock Issue Type', pk: 'id_stock_issue_type', statusCol: 'status', fields: [
    { name: 'name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    // I-GMSJ-005: was free text (nameField), accepting any numeric value —
    // DB column is tinyint(1) (1=Remove From Stock, 0=No).
    { name: 'is_remove_from_stock', kind: 'fixed-select', required: true, options: [1, 0] },
  ]},
  { key: 'repair_master', label: 'Repair Master', pk: 'id_repair_master', statusCol: 'status', fields: [
    { name: 'name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'product_division', label: 'Product Division', pk: 'id_pro_division', statusCol: 'status', fields: [
    { name: 'div_value', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'shape', label: 'Shape', pk: 'shape_id', statusCol: 'status', fields: [
    { name: 'name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'description', kind: 'text', rule: 'remarks' },
  ]},
  { key: 'ret_collection', label: 'Collection', pk: 'id_collection', statusCol: 'status', fields: [
    { name: 'collection_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'ret_pay_device', label: 'Pay Device', pk: 'id_device', statusCol: 'status', fields: [
    { name: 'device_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'device_type', kind: 'fixed-select', required: true, options: [1, 0] },
  ]},
  { key: 'ret_account', label: 'Account', pk: 'id_acc_head', statusCol: 'status', fields: [
    { name: 'name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'ret_crdr_ledger', label: 'Cr/Dr Ledger', pk: 'id_crdr_ledger', statusCol: 'status', fields: [
    { name: 'ledger_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  // statusCol added 2026-07-23 (I-GMSJ-005) — see database/migrations/20260723_000002_*
  { key: 'ret_delivery', label: 'Delivery', pk: 'id_sale_delivery', statusCol: 'status', fields: [
    { name: 'name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'ret_section', label: 'Section', pk: 'id_section', statusCol: 'status', fields: [
    { name: 'section_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'section_short_code', kind: 'text', required: true, rule: 'shortCode' },
  ]},
  { key: 'material', label: 'Material', pk: 'material_id', statusCol: 'material_status', fields: [
    { name: 'material_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'material_code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
  ]},
  { key: 'purity', label: 'Purity', pk: 'id_purity', statusCol: 'status', fields: [
    { name: 'purity', kind: 'text', required: true, unique: true, minVal: 0.01, maxVal: 100, value: () => (Math.random() * 90 + 1).toFixed(2) },
    { name: 'description', kind: 'text', rule: 'remarks' },
  ]},
  { key: 'stone', label: 'Stone', pk: 'stone_id', statusCol: 'stone_status', fields: [
    { name: 'stone_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'stone_code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
  ]},
  { key: 'metal', label: 'Metal', pk: 'id_metal', statusCol: 'metal_status', fields: [
    { name: 'metal', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'metal_code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
  ]},
  // Controller method is ret_size() (dispatching master_crud with entity key
  // 'size') — the URL segment doesn't match the config key for this one
  // master, unlike every other one. urlKey overrides the URL only.
  { key: 'size', urlKey: 'ret_size', label: 'Size', pk: 'id_size', statusCol: 'active', fields: [
    // Scoped unique per id_product — this config never posts id_product, so
    // it resolves to 0 both times (lmx_input('int') defaults unset to 0),
    // which still correctly collides since both the create and duplicate
    // attempt land in the same "no product" (0) bucket.
    { name: 'value', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'name', kind: 'text', required: true, rule: 'nameField' },
  ]},
  { key: 'tag', label: 'Tag', pk: 'tag_id', statusCol: 'tag_status', fields: [
    { name: 'tag_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'diamond', label: 'Diamond (Quality Code)', pk: 'quality_id', statusCol: 'status', fields: [
    { name: 'code', kind: 'text', required: true, unique: true, rule: 'shortCode' },
    { name: 'clarity_id', kind: 'fk', required: true },
    { name: 'color_id', kind: 'fk', required: true },
    { name: 'cut_id', kind: 'fk', required: true },
    { name: 'shape_id', kind: 'fk', required: true },
  ]},
  { key: 'material_rate', label: 'Material Rate', pk: 'mat_rate_id', statusCol: 'status', fields: [
    // Composite uniqueness (material_id + effective_date) — mat_rate and
    // effective_date are both fixed constants below (never suffix-varying),
    // so reusing material_id's captured value alone reproduces the exact
    // same combination and correctly collides.
    { name: 'material_id', kind: 'fk', required: true, unique: true },
    { name: 'mat_rate', kind: 'text', required: true, minVal: 0.01, value: () => '123.45' },
    { name: 'effective_date', kind: 'date', required: true, value: () => TODAY },
  ]},
  { key: 'old_metal_cat', label: 'Old Metal Category', pk: 'id_old_metal_cat', statusCol: 'is_active', fields: [
    { name: 'old_metal_cat', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'id_old_metal_type', kind: 'fk', required: true },
    { name: 'hsn_code', kind: 'text', required: true, rule: 'wholeNumber', value: () => '7113' },
    { name: 'old_metal_perc', kind: 'text', required: true, minVal: 0, maxVal: 100, value: () => '92' },
    { name: 'old_metal_discount', kind: 'text', required: true, minVal: 0, maxVal: 100, value: () => '2' },
    { name: 'touch_from', kind: 'text', required: true, minVal: 0, maxVal: 100, value: () => '10' },
    { name: 'touch_to', kind: 'text', required: true, minVal: 0, maxVal: 100, value: () => '90' },
  ]},
  // statusCol added 2026-07-23 (I-GMSJ-005) — see database/migrations/20260723_000003_*
  { key: 'ret_metalpurity', label: 'Metal Purity', pk: 'id_metal_purity_rate', statusCol: 'status', fields: [
    // Composite uniqueness (id_metal + id_purity) — rate_field is a fixed
    // constant below, and id_metal is FK-cached (stable across this whole
    // describe.serial block), so reusing id_purity's captured value alone
    // reproduces the exact same combination and correctly collides.
    { name: 'id_metal', kind: 'fk', required: true },
    { name: 'id_purity', kind: 'fk', required: true, unique: true },
    // I-GMSJ-005: rate_field stores a metal_rates COLUMN NAME (which live
    // rate to look up for this metal+purity), not a numeric amount — was
    // free numeric text, which is what let this same suite seed 18+ rows on
    // dev with the literal string "5500" (not a valid column, so Estimation/
    // Billing's rate lookup for those rows was silently broken).
    { name: 'rate_field', kind: 'fixed-select', required: true, options: ['goldrate_22ct', 'goldrate_24ct'] },
  ]},
  { key: 'branch_floor', label: 'Branch Floor', pk: 'floor_id', statusCol: 'floor_status', fields: [
    // Scoped unique per branch_id, which is FK-cached and stable across this
    // whole describe.serial block, so the duplicate test correctly reuses
    // the same branch.
    { name: 'branch_id', kind: 'fk', required: true },
    { name: 'floor_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'floor_short_code', kind: 'text', required: true, rule: 'shortCode' },
  ]},
  { key: 'floor_counter', label: 'Floor Counter', pk: 'counter_id', statusCol: 'counter_status', fields: [
    // Scoped unique per floor_id (FK-cached/stable), same reasoning as
    // Branch Floor above.
    { name: 'floor_id', kind: 'fk', required: true },
    { name: 'counter_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'counter_short_code', kind: 'text', required: true, rule: 'shortCode' },
  ]},
  { key: 'ret_qc_cancel_reason', label: 'QC Cancel Reason', pk: 'id_cancel', statusCol: 'status', fields: [
    // Server-side transform uppercases this on save (matches legacy behavior)
    // — the stored value differs from what was submitted.
    { name: 'cancel_reason', kind: 'text', required: true, unique: true, rule: 'nameField', transform: 'uppercase' },
  ]},
  { key: 'tax', label: 'Tax', pk: 'tax_id', statusCol: 'tax_status', fields: [
    { name: 'tax_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
    { name: 'tax_code', kind: 'text', required: true, rule: 'shortCode' },
    { name: 'tax_percentage', kind: 'text', required: true, minVal: 0, maxVal: 100, value: () => '3' },
  ]},
  { key: 'tgrp', label: 'Tax Group', pk: 'tgrp_id', statusCol: 'tgrp_status', fields: [
    { name: 'tgrp_name', kind: 'text', required: true, unique: true, rule: 'nameField' },
  ]},
  { key: 'attribute', label: 'Attribute', pk: 'attr_id', statusCol: 'attr_status', fields: [
    { name: 'attr_name', kind: 'text', required: true, rule: 'nameField' },
  ]},
  { key: 'diamond_rate', label: 'Diamond Rate', pk: 'rate_id', statusCol: 'rate_status', fields: [
    { name: 'quality_code_id', kind: 'fk', required: true, unique: true },
  ]},
  { key: 'selling_diamond_rate', label: 'Selling Diamond Rate', pk: 'selling_rate_id', statusCol: 'rate_status', fields: [
    { name: 'quality_code_id', kind: 'fk', required: true },
  ]},
];

function valueFor(field, suffix) {
  if (field.value !== undefined) return field.value(suffix);
  // Field names (e.g. "theme_name") contain underscores, which nameField's
  // charset doesn't allow — never embed field.name raw into a rule-checked
  // value. Keep generated values SHORT: max_length is schema-clamped to the
  // real DB column width, which for some masters (e.g. Financial Year's
  // fin_year_name) is much tighter than the config's declared max_length —
  // a bare suffix, no descriptive prefix, is the only length safe everywhere.
  switch (field.rule) {
    case 'nameField': return suffix;
    case 'shortCode': return suffix.slice(0, 8);
    case 'remarks': return `Test remarks ${suffix}`;
    case 'numeric': return '10';
    case 'wholeNumber': return '10';
    default: return suffix;
  }
}

module.exports = { masters, valueFor, TODAY, CHILD_TABLE_MASTERS };
