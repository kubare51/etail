/**
 * Targeted coverage for master-engine capabilities that don't fit the
 * generic per-field loop in master-crud.spec.js:
 *  - child-table masters (Tax Group, Attribute, Diamond Rate, Selling
 *    Diamond Rate): full add-with-children, diff-merge on edit
 *  - cross-field rules (date_range / range_order)
 *  - the custom `pattern` escape hatch layered on top of a named `rule`
 *  - exclusive_active (Selling Diamond Rate)
 *  - in_use_check dependency guards, chained through real records created
 *    via other masters' own Add endpoints (no direct DB access) — covers
 *    every in_use_check relationship where BOTH sides are migrated masters:
 *    Branch Floor/Floor Counter, Material/Material Rate, Tax/Tax Group,
 *    Metal/Tax Group, and Color+Cut+Clarity+Shape/Diamond. The remaining
 *    in_use_check entries point at non-migrated legacy screens (billing,
 *    tagging, design, orders, schemes) and aren't reachable this way —
 *    they share the same _master_check_in_use() code path already proven
 *    correct by the cases below.
 */
const { test, expect } = require('@playwright/test');
const api = require('./helpers/api');

test.describe('Child-table masters: add with children, diff-merge on edit', () => {
  test('Tax Group: add with one component, edit replaces it with a different tax (delete+insert)', async ({ page }) => {
    const tax1 = await api.createRecord(page, 'tax');
    const tax2 = await api.createRecord(page, 'tax');
    const suffix = api.uniqueSuffix();
    const name = `Test TGRP ${suffix}`;

    const addRes = await api.addRecord(page, 'tgrp', {
      tgrp_name: name,
      tgi: [{ tgi_taxcode: tax1.tax_id, tgi_calculation: 1, tgi_type: 1 }],
    });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBeTruthy();

    const rows = await api.listRows(page, 'tgrp');
    const tgrpRow = api.findRow(rows, 'tgrp_name', name);
    expect(tgrpRow, 'created tax group not found').toBeTruthy();
    const tgrpId = tgrpRow.tgrp_id;

    let children = await api.childRows(page, 'tgrp', tgrpId);
    expect(children.length).toBe(1);
    expect(String(children[0].tgi_taxcode)).toBe(String(tax1.tax_id));
    const firstChildPk = children[0].tgi_sno;

    // Worst-case diff-merge: resubmit with zero matching _pk — the old row
    // must be hard-deleted and the new one inserted, not silently duplicated.
    const editRes = await api.editRecord(page, 'tgrp', tgrpId, {
      tgrp_name: name,
      tgi: [{ tgi_taxcode: tax2.tax_id, tgi_calculation: 2, tgi_type: 2 }],
    });
    expect(editRes.ok, JSON.stringify(editRes.body)).toBeTruthy();

    children = await api.childRows(page, 'tgrp', tgrpId);
    expect(children.length).toBe(1);
    expect(String(children[0].tgi_taxcode)).toBe(String(tax2.tax_id));
    expect(children[0].tgi_sno).not.toBe(firstChildPk);
  });

  test('Attribute: edit keeps a matched _pk row in place and appends a new one', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const attrName = `TESTATTR${suffix}`;

    const addRes = await api.addRecord(page, 'attribute', {
      attr_name: attrName,
      attr_val: [{ attr_val: `Red${suffix}` }],
    });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBeTruthy();

    const rows = await api.listRows(page, 'attribute');
    // attr_name is uppercased server-side by this master's transform.
    const row = api.findRow(rows, 'attr_name', attrName.toUpperCase());
    expect(row, 'created attribute not found').toBeTruthy();
    const attrId = row.attr_id;

    let children = await api.childRows(page, 'attribute', attrId);
    expect(children.length).toBe(1);
    const existingPk = children[0].attr_val_id;

    const editRes = await api.editRecord(page, 'attribute', attrId, {
      attr_name: attrName,
      attr_val: [
        { _pk: existingPk, attr_val: `Blue${suffix}` },
        { attr_val: `Green${suffix}` },
      ],
    });
    expect(editRes.ok, JSON.stringify(editRes.body)).toBeTruthy();

    children = await api.childRows(page, 'attribute', attrId);
    expect(children.length).toBe(2);
    const editedRow = children.find((c) => String(c.attr_val_id) === String(existingPk));
    expect(editedRow, 'row matched by _pk should be updated in place, not replaced').toBeTruthy();
    expect(editedRow.attr_val).toBe(`Blue${suffix}`);
  });

  test('Attribute: rejects a duplicate attr_name (excluded from the generic loop as a child-table master)', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const attrName = `TESTATTRDUP${suffix}`;

    const firstRes = await api.addRecord(page, 'attribute', {
      attr_name: attrName,
      attr_val: [{ attr_val: `Val${suffix}` }],
    });
    expect(firstRes.ok, JSON.stringify(firstRes.body)).toBeTruthy();

    // attr_name is uppercased server-side, but the duplicate check is
    // case-insensitive anyway — submit the exact same casing back.
    const dupRes = await api.addRecord(page, 'attribute', {
      attr_name: attrName,
      attr_val: [{ attr_val: `Val2${suffix}` }],
    });
    expect(dupRes.ok, 'duplicate attr_name should be rejected').toBeFalsy();
  });

  test('Attribute: rejects two child rows with the same value in one submission', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const attrName = `TESTATTRROWDUP${suffix}`;

    const res = await api.addRecord(page, 'attribute', {
      attr_name: attrName,
      attr_val: [{ attr_val: `SameVal${suffix}` }, { attr_val: `SameVal${suffix}` }],
    });
    expect(res.ok, 'two identical attr_val rows in one submission should be rejected').toBeFalsy();
  });

  test('Tax Group: rejects two child rows using the same tax in one submission', async ({ page }) => {
    const tax = await api.createRecord(page, 'tax');
    const suffix = api.uniqueSuffix();

    const res = await api.addRecord(page, 'tgrp', {
      tgrp_name: `Test TGRP RowDup ${suffix}`,
      tgi: [
        { tgi_taxcode: tax.tax_id, tgi_calculation: 1, tgi_type: 1 },
        { tgi_taxcode: tax.tax_id, tgi_calculation: 2, tgi_type: 2 },
      ],
    });
    expect(res.ok, 'the same tax listed twice in one tax group should be rejected').toBeFalsy();
  });

  test('Diamond Rate: requires >=1 cent-rate row, rejects from_cent > to_cent, one rate per quality code', async ({ page }) => {
    const diamond = await api.createRecord(page, 'diamond'); // fresh, never-rated quality code

    const emptyRes = await api.addRecord(page, 'diamond_rate', { quality_code_id: diamond.quality_id, cent_rates: [] });
    expect(emptyRes.ok, 'empty cent_rates should be rejected (min_rows: 1)').toBeFalsy();

    const badRangeRes = await api.addRecord(page, 'diamond_rate', {
      quality_code_id: diamond.quality_id,
      cent_rates: [{ from_cent: 50, to_cent: 10, rate: 100 }],
    });
    expect(badRangeRes.ok, 'from_cent > to_cent should be rejected').toBeFalsy();

    const okRes = await api.addRecord(page, 'diamond_rate', {
      quality_code_id: diamond.quality_id,
      cent_rates: [{ from_cent: 0, to_cent: 50, rate: 100 }],
    });
    expect(okRes.ok, JSON.stringify(okRes.body)).toBeTruthy();

    const dupRes = await api.addRecord(page, 'diamond_rate', {
      quality_code_id: diamond.quality_id,
      cent_rates: [{ from_cent: 0, to_cent: 50, rate: 200 }],
    });
    expect(dupRes.ok, 'a second rate for the same quality code should be rejected (DB unique constraint)').toBeFalsy();
  });

  test('Diamond Rate: rejects two overlapping cent-rate ranges in one submission', async ({ page }) => {
    const diamond = await api.createRecord(page, 'diamond'); // fresh, never-rated quality code

    const res = await api.addRecord(page, 'diamond_rate', {
      quality_code_id: diamond.quality_id,
      cent_rates: [
        { from_cent: 0, to_cent: 50, rate: 100 },
        { from_cent: 30, to_cent: 80, rate: 150 },
      ],
    });
    expect(res.ok, 'overlapping cent-rate ranges (0-50 and 30-80) should be rejected').toBeFalsy();
  });

  test('Selling Diamond Rate: exclusive_active deactivates the prior rate for the same quality code', async ({ page }) => {
    const diamond = await api.createRecord(page, 'diamond');

    const first = await api.addRecord(page, 'selling_diamond_rate', {
      quality_code_id: diamond.quality_id,
      cent_rates: [{ from_cent: 0, to_cent: 50, rate: 100 }],
    });
    expect(first.ok, JSON.stringify(first.body)).toBeTruthy();

    let rows = await api.listRows(page, 'selling_diamond_rate');
    let matches = rows.filter((r) => String(r.quality_code_id) === String(diamond.quality_id));
    expect(matches.length).toBe(1);
    expect(Number(matches[0].rate_status)).toBe(1);
    const firstId = matches[0].selling_rate_id;

    const second = await api.addRecord(page, 'selling_diamond_rate', {
      quality_code_id: diamond.quality_id,
      cent_rates: [{ from_cent: 0, to_cent: 50, rate: 150 }],
    });
    expect(second.ok, JSON.stringify(second.body)).toBeTruthy();

    rows = await api.listRows(page, 'selling_diamond_rate');
    matches = rows.filter((r) => String(r.quality_code_id) === String(diamond.quality_id));
    expect(matches.length).toBe(2);
    const firstRow = matches.find((r) => String(r.selling_rate_id) === String(firstId));
    const secondRow = matches.find((r) => String(r.selling_rate_id) !== String(firstId));
    expect(Number(firstRow.rate_status), 'prior rate should be auto-deactivated').toBe(0);
    expect(Number(secondRow.rate_status), 'new rate should be active').toBe(1);
  });
});

test.describe('Cross-field validation', () => {
  test('Old Metal Category: rejects touch_from greater than touch_to', async ({ page }) => {
    const metalType = await api.createRecord(page, 'metal_type');
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'old_metal_cat', {
      old_metal_cat: `Test OMC ${suffix}`,
      id_old_metal_type: metalType.id_metal_type,
      hsn_code: '7113',
      old_metal_perc: 92,
      old_metal_discount: 2,
      touch_from: 90,
      touch_to: 10,
    });
    expect(res.ok, 'touch_from > touch_to should be rejected').toBeFalsy();
  });

  test('Old Metal Category: hsn_code pattern rejects a 5-digit code that still passes the wholeNumber rule', async ({ page }) => {
    const metalType = await api.createRecord(page, 'metal_type');
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'old_metal_cat', {
      old_metal_cat: `Test OMC HSN ${suffix}`,
      id_old_metal_type: metalType.id_metal_type,
      hsn_code: '12345', // a valid whole number, but not a 4/6/8-digit HSN code
      old_metal_perc: 92,
      old_metal_discount: 2,
      touch_from: 10,
      touch_to: 90,
    });
    expect(res.ok, '5-digit HSN code should be rejected by the pattern escape hatch').toBeFalsy();
  });

  test('Financial Year: rejects a start date after the end date', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'financial_year', {
      fin_year_name: `Test FY ${suffix}`,
      fin_year_code: `Z${suffix}`.slice(0, 4),
      fin_year_from: '2027-03-31',
      fin_year_to: '2026-04-01',
    });
    expect(res.ok, 'start date after end date should be rejected').toBeFalsy();
  });
});

/**
 * Regression coverage for the 2026-07-20 lmx_validate() additions
 * (admin/application/helpers/lmx/core/validation_helper.php), added while
 * auditing Retail_1.1.1.0001's independent legacy validation (its
 * validate_<entity>_data()/validate_master_field() and
 * sanitize_and_validate_text() pathways enforce a minimum length and a
 * SQL/XSS substring blacklist that this engine didn't have). Both are
 * opt-in per rule field ('min_length' / 'security_blacklist' in
 * master_entities.php) — these tests use Hook, Color, and Financial Year,
 * the pilot masters that had those keys added during that audit
 * (stabilization/08_retail_1.1.1.0001_propagation.md).
 */
test.describe('min_length / security_blacklist opt-in validation (2026-07-20)', () => {
  test('Hook: a 1-character hook_name is rejected (min_length: 2)', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'hook', {
      hook_name: 'A',
      hook_short_code: `H${suffix}`.slice(0, 10),
    });
    expect(res.ok, JSON.stringify(res.body)).toBeFalsy();
    expect(res.body.message || JSON.stringify(res.body)).toMatch(/at least 2 characters/i);
  });

  test('Hook: a single-character hook_short_code succeeds (min_length: 1 is a no-op beyond required)', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'hook', {
      hook_name: `Test Hook ${suffix}`,
      hook_short_code: suffix.slice(0, 1),
    });
    expect(res.ok, JSON.stringify(res.body)).toBeTruthy();
  });

  test('Color: a value containing a SQL-injection-pattern substring is rejected (security_blacklist)', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'color', {
      color: `SELECT ${suffix}`,
    });
    expect(res.ok, JSON.stringify(res.body)).toBeFalsy();
    expect(res.body.message || JSON.stringify(res.body)).toMatch(/invalid content/i);
  });

  test('Color: a value containing an XSS-pattern substring is rejected (security_blacklist)', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'color', {
      color: `javascript:${suffix}`,
    });
    expect(res.ok, JSON.stringify(res.body)).toBeFalsy();
  });

  test('Color: an ordinary name that happens to contain no blacklisted substring still succeeds', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'color', {
      color: `Rose Gold ${suffix}`,
    });
    expect(res.ok, JSON.stringify(res.body)).toBeTruthy();
  });

  test('Financial Year: a fin_year_name containing a SQL-injection-pattern substring is rejected (security_blacklist, sanitize_and_validate_text pathway)', async ({ page }) => {
    const suffix = api.uniqueSuffix();
    const res = await api.addRecord(page, 'financial_year', {
      fin_year_name: `DROP ${suffix}`,
      fin_year_code: `Y${suffix}`.slice(0, 4),
      fin_year_from: '2026-04-01',
      fin_year_to: '2027-03-31',
    });
    expect(res.ok, JSON.stringify(res.body)).toBeFalsy();
  });
});

test.describe('in_use_check dependency guards (chained through real UI-created records)', () => {
  test('Branch Floor: blocked from deactivation while a Floor Counter references it', async ({ page }) => {
    const floor = await api.createRecord(page, 'branch_floor');
    await api.createRecord(page, 'floor_counter', { floor_id: floor.floor_id });

    await api.updateStatus(page, 'branch_floor', floor.floor_id, 1);
    const rows = await api.listRows(page, 'branch_floor');
    const row = api.findRow(rows, 'floor_id', floor.floor_id);
    expect(Number(row.floor_status), 'floor should still be active — blocked by its counter').toBe(1);
  });

  test('Material: blocked from deactivation while a Material Rate references it', async ({ page }) => {
    const material = await api.createRecord(page, 'material');
    await api.createRecord(page, 'material_rate', { material_id: material.material_id });

    await api.updateStatus(page, 'material', material.material_id, 1);
    const rows = await api.listRows(page, 'material');
    const row = api.findRow(rows, 'material_id', material.material_id);
    expect(Number(row.material_status), 'material should still be active — blocked by its rate').toBe(1);
  });

  test('Tax: blocked from deactivation while a Tax Group component references it', async ({ page }) => {
    const tax = await api.createRecord(page, 'tax');
    const suffix = api.uniqueSuffix();
    const tgrpAdd = await api.addRecord(page, 'tgrp', {
      tgrp_name: `Test TGRP Guard ${suffix}`,
      tgi: [{ tgi_taxcode: tax.tax_id, tgi_calculation: 1, tgi_type: 1 }],
    });
    expect(tgrpAdd.ok, JSON.stringify(tgrpAdd.body)).toBeTruthy();

    await api.updateStatus(page, 'tax', tax.tax_id, 1);
    const rows = await api.listRows(page, 'tax');
    const row = api.findRow(rows, 'tax_id', tax.tax_id);
    expect(Number(row.tax_status), 'tax should still be active — blocked by the tax group using it').toBe(1);
  });

  test("Tax Group: blocked from deactivation while a Metal references its tgrp_id", async ({ page }) => {
    // Direction matters: tgrp's in_use_check protects ITSELF from
    // deactivation while a metal still points at it via tgrp_id — it's not
    // the metal side that's guarded.
    const tax = await api.createRecord(page, 'tax');
    const tgSuffix = api.uniqueSuffix();
    const tgrpName = `Test TGRP ForMetal ${tgSuffix}`;
    const tgrpAdd = await api.addRecord(page, 'tgrp', {
      tgrp_name: tgrpName,
      tgi: [{ tgi_taxcode: tax.tax_id, tgi_calculation: 1, tgi_type: 1 }],
    });
    expect(tgrpAdd.ok, JSON.stringify(tgrpAdd.body)).toBeTruthy();
    const tgrpRows = await api.listRows(page, 'tgrp');
    const tgrpRow = api.findRow(tgrpRows, 'tgrp_name', tgrpName);
    expect(tgrpRow).toBeTruthy();

    // metal's tgrp_id is optional and not part of master-config's minimal
    // field set, so build this payload directly rather than via createRecord.
    const metalSuffix = api.uniqueSuffix();
    const metalName = `Test Metal ${metalSuffix}`;
    const metalAdd = await api.addRecord(page, 'metal', {
      metal: metalName,
      metal_code: `TM${metalSuffix}`.slice(0, 10),
      tgrp_id: tgrpRow.tgrp_id,
    });
    expect(metalAdd.ok, JSON.stringify(metalAdd.body)).toBeTruthy();

    await api.updateStatus(page, 'tgrp', tgrpRow.tgrp_id, 1);
    const rows = await api.listRows(page, 'tgrp');
    const row = api.findRow(rows, 'tgrp_id', tgrpRow.tgrp_id);
    expect(Number(row.tgrp_status), 'tax group should still be active — blocked by the metal referencing it').toBe(1);
  });

  test('Color, Cut, Clarity, Shape: all four blocked from deactivation while a Diamond quality code uses them', async ({ page }) => {
    const color = await api.createRecord(page, 'color');
    const cut = await api.createRecord(page, 'cut');
    const clarity = await api.createRecord(page, 'clarity');
    const shape = await api.createRecord(page, 'shape');
    const suffix = api.uniqueSuffix();

    const diamondAdd = await api.addRecord(page, 'diamond', {
      code: `TDG${suffix}`.slice(0, 12),
      clarity_id: clarity.id_clarity,
      color_id: color.id_color,
      cut_id: cut.id_cut,
      shape_id: shape.shape_id,
    });
    expect(diamondAdd.ok, JSON.stringify(diamondAdd.body)).toBeTruthy();

    const targets = [
      { key: 'color', row: color, idField: 'id_color', statusField: 'status' },
      { key: 'cut', row: cut, idField: 'id_cut', statusField: 'status' },
      { key: 'clarity', row: clarity, idField: 'id_clarity', statusField: 'status' },
      { key: 'shape', row: shape, idField: 'shape_id', statusField: 'status' },
    ];
    for (const t of targets) {
      await api.updateStatus(page, t.key, t.row[t.idField], 1);
      const rows = await api.listRows(page, t.key);
      const updated = api.findRow(rows, t.idField, t.row[t.idField]);
      expect(Number(updated[t.statusField]), `${t.key} should still be active — blocked by the diamond using it`).toBe(1);
    }
  });
});
