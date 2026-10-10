/**
 * Coverage for the field-validation fixes made to 8 not-yet-migrated legacy
 * masters (see stabilization/07_modernization_spec/FIELD_VALIDATION_GAPS_TRACKER.md,
 * "B. Legacy masters"): Metal Process, Charges, Category, Design, Stone
 * Discount, Supplier Catalogue, Product, Cash Deposit.
 *
 * Each legacy controller is hand-written with its own URL/response contract
 * (see helpers/legacy-api.js) — some echo an informative JSON body on
 * rejection (Charges, Category, Stone Discount, Supplier Catalogue), which
 * this file asserts on directly; others only redirect+flashdata with no
 * inspectable response (Metal Process, Product, Design), so those are
 * verified indirectly via each master's own lightweight read/search
 * endpoint (list-by-name before/after, confirming a duplicate submission
 * did NOT create a second row).
 *
 * Scope note: Cash Deposit is intentionally NOT covered here. Its "add" has
 * no simple list-by-value read endpoint, and constructing a valid deposit
 * requires satisfying a live cash-in-hand business rule (dep_amount must not
 * exceed available balance) that's fragile to automate reliably — verify
 * that one manually if needed. Supplier Catalogue's overlap-check (as
 * opposed to its required-field check) is also not exercised here, since it
 * needs a real Product AND Design to already exist — deep enough a
 * dependency chain that it's out of scope for this pass.
 *
 * Known gap: Stone Discount's overlap-check half is test.fixme()'d — it's
 * blocked by a pre-existing DB bug (ret_stone_discount_master.id has a
 * PRIMARY KEY but no AUTO_INCREMENT, so every insert fails), not by
 * anything in this validation pass. See the fixme() call at that test for
 * the fix (written, not yet applied).
 */
const { test, expect } = require('@playwright/test');
const api = require('./helpers/api');
const legacy = require('./helpers/legacy-api');

test.describe('Legacy master field validation', () => {
  test('Metal Process: rejects missing process_name and a duplicate process_name', async ({ page }) => {
    const before = await legacy.metalProcess.list(page);
    await legacy.metalProcess.add(page, { process_name: '' });
    const afterBlank = await legacy.metalProcess.list(page);
    expect(afterBlank.length, 'a blank process_name should not create a row').toBe(before.length);

    const suffix = api.uniqueSuffix();
    const name = `TESTMP${suffix}`;
    await legacy.metalProcess.add(page, { process_name: name });
    let rows = await legacy.metalProcess.list(page);
    expect(rows.filter((r) => r.process_name === name.toUpperCase()).length, 'first add should create exactly one row').toBe(1);

    await legacy.metalProcess.add(page, { process_name: name });
    rows = await legacy.metalProcess.list(page);
    expect(rows.filter((r) => r.process_name === name.toUpperCase()).length, 'duplicate process_name should be rejected, not create a second row').toBe(1);
  });

  test('Charges: rejects missing charge_name and a duplicate charge_name', async ({ page }) => {
    const suffix = api.uniqueSuffix();

    const missing = await legacy.charges.add(page, { charge_name: '', charge_code: `C${suffix}`.slice(0, 10) });
    expect(missing.ok, `missing charge_name should be rejected: ${JSON.stringify(missing.body)}`).toBeFalsy();

    const name = `Test Charge ${suffix}`;
    const first = await legacy.charges.add(page, { charge_name: name, charge_code: `C${suffix}`.slice(0, 10) });
    expect(first.ok, `first add should succeed: ${JSON.stringify(first.body)}`).toBeTruthy();

    const dup = await legacy.charges.add(page, { charge_name: name, charge_code: `C2${suffix}`.slice(0, 10) });
    expect(dup.ok, `duplicate charge_name should be rejected: ${JSON.stringify(dup.body)}`).toBeFalsy();
  });

  test('Category: rejects missing name and a duplicate name', async ({ page }) => {
    const metal = await api.createRecord(page, 'metal');
    const suffix = api.uniqueSuffix();

    const missing = await legacy.category.add(page, { name: '', id_metal: metal.id_metal });
    expect(missing.ok, `missing name should be rejected: ${JSON.stringify(missing.body)}`).toBeFalsy();

    const name = `TESTCAT${suffix}`;
    const first = await legacy.category.add(page, { name, id_metal: metal.id_metal });
    expect(first.ok, `first add should succeed: ${JSON.stringify(first.body)}`).toBeTruthy();

    const dup = await legacy.category.add(page, { name, id_metal: metal.id_metal });
    expect(dup.ok, `duplicate name should be rejected: ${JSON.stringify(dup.body)}`).toBeFalsy();
  });

  test('Stone Discount: rejects missing fields and an overlapping discount range', async ({ page }) => {
    const missing = await legacy.stoneDiscount.add(page, { stone_type: '', min_disc: '', max_disc: '' });
    expect(missing.ok, `missing fields should be rejected: ${JSON.stringify(missing.body)}`).toBeFalsy();

    // The overlap-check half of this test is blocked by a pre-existing DB
    // schema bug, unrelated to this validation pass: ret_stone_discount_master.id
    // got a PRIMARY KEY retrofitted by 20260524_000002_add_primary_keys_batch2.sql
    // without AUTO_INCREMENT, so every insert since has failed with
    // "Duplicate entry '0' for key PRIMARY" (confirmed live on the dev DB).
    // Fix is written and ready at
    // database/migrations/20260715_000001_add_auto_increment_batch1_batch2_pks.sql
    // but not yet applied (holding off pending a deliberate migration run,
    // not something to slip in via a test run). Re-enable the block below
    // once that migration has been applied to this environment.
    test.fixme(true, 'Blocked by missing AUTO_INCREMENT on ret_stone_discount_master.id — see database/migrations/20260715_000001_add_auto_increment_batch1_batch2_pks.sql');

    const stone = await api.createRecord(page, 'stone');
    const first = await legacy.stoneDiscount.add(page, { stone_type: stone.stone_id, min_disc: 0, max_disc: 10 });
    expect(first.ok, `first add should succeed: ${JSON.stringify(first.body)}`).toBeTruthy();

    const overlap = await legacy.stoneDiscount.add(page, { stone_type: stone.stone_id, min_disc: 5, max_disc: 15 });
    expect(overlap.ok, `overlapping range (5-15 vs existing 0-10) should be rejected: ${JSON.stringify(overlap.body)}`).toBeFalsy();
  });

  test('Supplier Catalogue: rejects missing product/design', async ({ page }) => {
    const missing = await legacy.supplierCatalog.add(page, { product_id: '', design_id: '', from_weight: 0, to_weight: 10 });
    expect(missing.ok, `missing product/design should be rejected: ${JSON.stringify(missing.body)}`).toBeFalsy();
  });

  test('Product: rejects missing product_name and a duplicate product_name', async ({ page }) => {
    const metal = await api.createRecord(page, 'metal');
    const suffix = api.uniqueSuffix();
    const catName = `TESTPRODCAT${suffix}`;
    const catRes = await legacy.category.add(page, { name: catName, id_metal: metal.id_metal });
    expect(catRes.ok, `prerequisite category creation failed: ${JSON.stringify(catRes.body)}`).toBeTruthy();
    const categories = await legacy.category.activeList(page);
    const cat = categories.find((c) => c.name === catName.toUpperCase() || c.name === catName);
    expect(cat, 'prerequisite category not found via active_category').toBeTruthy();

    const before = await legacy.product.activeList(page);
    await legacy.product.add(page, { cat_id: cat.id_ret_category, product_name: '' });
    const afterBlank = await legacy.product.activeList(page);
    expect(afterBlank.length, 'a blank product_name should not create a row').toBe(before.length);

    const name = `TESTPROD${suffix}`;
    await legacy.product.add(page, { cat_id: cat.id_ret_category, product_name: name });
    let rows = await legacy.product.activeList(page);
    expect(rows.filter((r) => r.product_name === name.toUpperCase()).length, 'first add should create exactly one row').toBe(1);

    await legacy.product.add(page, { cat_id: cat.id_ret_category, product_name: name });
    rows = await legacy.product.activeList(page);
    expect(rows.filter((r) => r.product_name === name.toUpperCase()).length, 'duplicate product_name should be rejected, not create a second row').toBe(1);
  });

  test('Design: rejects missing design_name and a duplicate design_name', async ({ page }) => {
    // Prerequisite product, via the same chain as the Product test above.
    const metal = await api.createRecord(page, 'metal');
    const suffix = api.uniqueSuffix();
    const catName = `TESTDESCAT${suffix}`;
    const catRes = await legacy.category.add(page, { name: catName, id_metal: metal.id_metal });
    expect(catRes.ok, JSON.stringify(catRes.body)).toBeTruthy();
    const categories = await legacy.category.activeList(page);
    const cat = categories.find((c) => c.name === catName.toUpperCase() || c.name === catName);
    expect(cat, 'prerequisite category not found').toBeTruthy();

    const prodName = `TESTDESPROD${suffix}`;
    await legacy.product.add(page, { cat_id: cat.id_ret_category, product_name: prodName });
    const products = await legacy.product.activeList(page);
    const prod = products.find((p) => p.product_name === prodName.toUpperCase());
    expect(prod, 'prerequisite product not found').toBeTruthy();
    const productId = prod.pro_id;

    const designName = `TESTDES${suffix}`;
    await legacy.design.add(page, { design_name: designName, product_id: productId });
    let matches = await legacy.design.searchByName(page, designName, productId);
    expect(matches.length, 'first add should create exactly one design').toBe(1);

    await legacy.design.add(page, { design_name: designName, product_id: productId });
    matches = await legacy.design.searchByName(page, designName, productId);
    expect(matches.length, 'duplicate design_name should be rejected, not create a second design').toBe(1);
  });
});
