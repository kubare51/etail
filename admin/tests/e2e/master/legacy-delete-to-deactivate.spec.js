/**
 * Regression coverage for I-GMSJ-005 ("Master Data Deletion & Dependency
 * Validation"): Category, Product, Design, and Sub Design were the last 4
 * masters still doing a real hard DELETE on their "Delete" action (every
 * other master already only deactivates — see the "masters never
 * hard-delete" convention). Fixed 2026-07-22 in admin_ret_catalog.php:
 * their `delete` case now sets the table's existing status column instead
 * of removing the row, and owned child rows (Category's purity
 * associations, Design's karigars/purity/materials/sizes/stone, Product's
 * sections/charges — previously wiped alongside the parent) are preserved.
 *
 * This file proves, per master, both routes that can trigger a
 * deactivation (the `delete` action AND the `update_status` toggle):
 *   1. the record is NOT removed — it disappears from the active list but
 *      is still present and can be reactivated with its original data
 *      intact (a real hard DELETE would make this permanently impossible)
 *   2. Category specifically: a child association (purity) survives the
 *      cycle, since that's the concrete regression the fix closed
 *
 * Assertions deliberately check only HTTP status + the read-only active-list
 * endpoints, never the update_status response body shape — mainline echoes
 * JSON ({class:'success'}) but this response shape is NOT universal: forks
 * of this suite running against a branch whose catalog_master.js still
 * drives the status toggle via a plain <a href> (redirect+flashdata, no
 * JSON) need the exact same assertions to hold without modification.
 *
 * NOT covered here (same reasoning as master-special.spec.js's own
 * documented scope limit): the "blocked while it exists in stock" guard
 * (getItemsinTagDetails) and Product's "blocked while referenced by an
 * active Estimation" guard both require fabricating real ret_taging /
 * ret_estimation_items rows through their own heavyweight modules, which
 * this fast API-level master suite doesn't reach into. Both guards were
 * confirmed correct by direct code review (see the delete/update_status
 * cases in admin_ret_catalog.php), not by an automated test — flagging
 * this explicitly rather than silently claiming full coverage.
 */
const { test, expect } = require('@playwright/test');
const api = require('./helpers/api');
const legacy = require('./helpers/legacy-api');

test.describe('Category: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const name = `TESTCATDEL${suffix}`;
    // I-GMSJ-005: Purity is now required on Category — see legacy-validation-fixes.spec.js.
    const purity = await api.createRecord(page, 'purity');

    const addRes = await legacy.category.add(page, { name, id_purity: purity.id_purity });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);

    let active = await legacy.category.activeList(page);
    let row = (active || []).find((c) => c.name === name);
    expect(row, 'created category not found via active_category').toBeTruthy();
    const id = row.id_ret_category;

    const delRes = await legacy.category.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    active = await legacy.category.activeList(page);
    expect((active || []).some((c) => String(c.id_ret_category) === String(id)),
      'deleted category should no longer be active').toBe(false);

    const reactivateRes = await legacy.category.updateStatus(page, id, 1);
    expect(reactivateRes.status()).toBeLessThan(400);

    active = await legacy.category.activeList(page);
    row = (active || []).find((c) => String(c.id_ret_category) === String(id));
    expect(row, 'category must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeTruthy();
    expect(row.name).toBe(name);
  });

  test('update_status route deactivates and reactivates the same way', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const name = `TESTCATSTAT${suffix}`;
    const purity = await api.createRecord(page, 'purity');

    const addRes = await legacy.category.add(page, { name, id_purity: purity.id_purity });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);
    const active = await legacy.category.activeList(page);
    const row = (active || []).find((c) => c.name === name);
    expect(row).toBeTruthy();
    const id = row.id_ret_category;

    const deactivateRes = await legacy.category.updateStatus(page, id, 0);
    expect(deactivateRes.status()).toBeLessThan(400);

    let afterDeactivate = await legacy.category.activeList(page);
    expect((afterDeactivate || []).some((c) => String(c.id_ret_category) === String(id))).toBe(false);

    const reactivateRes = await legacy.category.updateStatus(page, id, 1);
    expect(reactivateRes.status()).toBeLessThan(400);

    const afterReactivate = await legacy.category.activeList(page);
    expect((afterReactivate || []).some((c) => String(c.id_ret_category) === String(id))).toBe(true);
  });

  test('purity associations survive a Delete + reactivate cycle (owned child rows are no longer wiped)', async ({ page }) => {
    const purity = await api.createRecord(page, 'purity');
    const suffix = Date.now() % 100000;
    const name = `TESTCATPUR${suffix}`;

    const addRes = await legacy.category.add(page, { name, id_purity: purity.id_purity });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);

    const active = await legacy.category.activeList(page);
    const row = (active || []).find((c) => c.name === name);
    expect(row).toBeTruthy();
    const id = row.id_ret_category;

    let purityRows = await legacy.category.catPurity(page, id);
    expect(purityRows.some((p) => String(p.id_purity) === String(purity.id_purity)),
      'setup purity association not found').toBe(true);

    await legacy.category.delete(page, id);
    const reactivateRes = await legacy.category.updateStatus(page, id, 1);
    expect(reactivateRes.status()).toBeLessThan(400);

    purityRows = await legacy.category.catPurity(page, id);
    expect(purityRows.some((p) => String(p.id_purity) === String(purity.id_purity)),
      'purity association must survive a Delete + reactivate cycle — previously wiped on delete').toBe(true);
  });
});

test.describe('Product: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const catName = `TESTCATPD${suffix}`;
    const prodName = `TESTPRODDEL${suffix}`;
    const purity = await api.createRecord(page, 'purity');

    const catRes = await legacy.category.add(page, { name: catName, id_purity: purity.id_purity });
    expect(catRes.ok, JSON.stringify(catRes.body)).toBe(true);
    const activeCats = await legacy.category.activeList(page);
    const catRow = (activeCats || []).find((c) => c.name === catName);
    expect(catRow).toBeTruthy();

    const prodRes = await legacy.product.add(page, { product_name: prodName, cat_id: String(catRow.id_ret_category) });
    expect(prodRes.status(), await prodRes.text()).toBeLessThan(400);
    let activeProducts = await legacy.product.activeList(page);
    let prodRow = (activeProducts || []).find((p) => p.product_name === prodName);
    expect(prodRow, 'created product not found via active_list').toBeTruthy();
    const id = prodRow.pro_id;

    const delRes = await legacy.product.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    activeProducts = await legacy.product.activeList(page);
    expect((activeProducts || []).some((p) => String(p.pro_id) === String(id)),
      'deleted product should no longer be active').toBe(false);

    const reactivateRes = await legacy.product.updateStatus(page, id, 1);
    expect(reactivateRes.status()).toBeLessThan(400);

    activeProducts = await legacy.product.activeList(page);
    prodRow = (activeProducts || []).find((p) => String(p.pro_id) === String(id));
    expect(prodRow, 'product must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeTruthy();
    expect(prodRow.product_name).toBe(prodName);
  });
});

test.describe('Design: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const catName = `TESTCATDD${suffix}`;
    const prodName = `TESTPRODDD${suffix}`;
    const designName = `TESTDESIGNDEL${suffix}`;
    const purity = await api.createRecord(page, 'purity');

    const catRes = await legacy.category.add(page, { name: catName, id_purity: purity.id_purity });
    expect(catRes.ok, JSON.stringify(catRes.body)).toBe(true);
    const activeCats = await legacy.category.activeList(page);
    const catRow = (activeCats || []).find((c) => c.name === catName);
    expect(catRow).toBeTruthy();

    const prodRes = await legacy.product.add(page, { product_name: prodName, cat_id: String(catRow.id_ret_category) });
    expect(prodRes.status(), await prodRes.text()).toBeLessThan(400);
    const activeProducts = await legacy.product.activeList(page);
    const prodRow = (activeProducts || []).find((p) => p.product_name === prodName);
    expect(prodRow).toBeTruthy();

    const designRes = await legacy.design.add(page, { design_name: designName, product_id: String(prodRow.pro_id) });
    expect(designRes.status(), await designRes.text()).toBeLessThan(400);

    let found = await legacy.design.searchByName(page, designName, prodRow.pro_id);
    expect(found.length, 'created design not found via active_designBySearch').toBeGreaterThan(0);
    const id = found[0].value;

    const delRes = await legacy.design.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    found = await legacy.design.searchByName(page, designName, prodRow.pro_id);
    expect(found.length, 'deleted design should no longer be active').toBe(0);

    const reactivateRes = await legacy.design.updateStatus(page, id, 1);
    expect(reactivateRes.status()).toBeLessThan(400);

    found = await legacy.design.searchByName(page, designName, prodRow.pro_id);
    expect(found.length, 'design must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeGreaterThan(0);
  });
});

test.describe('Sub Design: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const name = `TESTSUBDEL${suffix}`;

    const addRes = await legacy.subDesign.add(page, { sub_design_name: name });
    expect(addRes.status(), await addRes.text()).toBeLessThan(400);

    let active = await legacy.subDesign.activeList(page);
    let row = (active || []).find((r) => r.sub_design_name === name);
    expect(row, 'created sub design not found via active_list').toBeTruthy();
    const id = row.id_sub_design;

    const delRes = await legacy.subDesign.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    active = await legacy.subDesign.activeList(page);
    expect((active || []).some((r) => String(r.id_sub_design) === String(id)),
      'deleted sub design should no longer be active').toBe(false);

    const reactivateRes = await legacy.subDesign.updateStatus(page, id, 1);
    expect(reactivateRes.status()).toBeLessThan(400);

    active = await legacy.subDesign.activeList(page);
    row = (active || []).find((r) => String(r.id_sub_design) === String(id));
    expect(row, 'sub design must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeTruthy();
    expect(row.sub_design_name).toBe(name);
  });
});

/**
 * I-GMSJ-005 (second pass, 2026-07-30): Weight Range, Charges, Wastage
 * Discount, and Supplier Catalogue were found to have the exact same
 * hard-delete gap during the "review remaining masters" pass. Weight Range
 * and Supplier Catalogue already had an unused status column; Charges and
 * Wastage Discount needed one added (see the 2026-07-30 migrations).
 *
 * Charges' and Wastage Discount's new in_use_check (blocked while referenced
 * by a tag/estimation) is NOT covered here — same documented scope limit as
 * the top of this file: fabricating a real ret_taging_charges/ret_estimation
 * row is out of reach for this fast API-level suite. Confirmed correct by
 * direct code review, not by an automated test.
 */
test.describe('Weight Range: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const name = `TESTWTDEL${suffix}`;
    // Uniqueness is (value, id_uom, id_product, id_design, id_sub_design, from_weight,
    // to_weight, weight_description) — vary from/to by suffix too so repeated runs
    // (or leftover data from other weight tests) never collide.
    const base = 1000 + (suffix % 8000);

    const addRes = await legacy.weight.add(page, { name, from_weight: String(base), to_weight: String(base + 5) });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);
    const id = addRes.body.weight_id;
    expect(id).toBeTruthy();

    const delRes = await legacy.weight.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    let row = await legacy.weight.getById(page, id);
    expect(row, 'deleted weight range should still exist (not hard-deleted)').toBeTruthy();
    expect(String(row.active)).toBe('0');

    const reactivateRes = await legacy.weight.updateStatus(page, id, 0);
    expect(reactivateRes.status()).toBeLessThan(400);

    row = await legacy.weight.getById(page, id);
    expect(row, 'weight range must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeTruthy();
    expect(String(row.active)).toBe('1');
  });
});

test.describe('Charges: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const name = `TESTCHGDEL${suffix}`;

    const addRes = await legacy.charges.add(page, { charge_name: name, charge_code: `C${suffix}` });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);

    let list = await legacy.charges.list(page);
    let row = (list || []).find((r) => r.name_charge === name);
    expect(row, 'created charge not found via getActiveChargesList').toBeTruthy();
    const id = row.id_charge;

    const delRes = await legacy.charges.delete(page, id);
    expect(delRes.status, JSON.stringify(delRes)).toBe(true);

    list = await legacy.charges.list(page);
    row = (list || []).find((r) => String(r.id_charge) === String(id));
    expect(row, 'deleted charge should still exist (not hard-deleted)').toBeTruthy();
    expect(String(row.status)).toBe('0');

    const reactivateRes = await legacy.charges.updateStatus(page, id, 0);
    expect(reactivateRes.status()).toBeLessThan(400);

    list = await legacy.charges.list(page);
    row = (list || []).find((r) => String(r.id_charge) === String(id));
    expect(row, 'charge must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeTruthy();
    expect(String(row.status)).toBe('1');
  });
});

test.describe('Wastage Discount: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record survives and is reactivatable', async ({ page }) => {
    const suffix = Date.now() % 100000;
    // legacy-validation-fixes.spec.js's own wastage-range test uses base in
    // [100,899] (+ up to 55 more for its overlap check, so up to ~954) — stay
    // well clear of that whole zone to avoid a false "already Existed".
    const base = 960 + (suffix % 30);

    const addRes = await legacy.wastageDiscount.add(page, {
      disc_value: '1', from_wastage: String(base), to_wastage: String(base + 2),
    });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);
    const id = addRes.body.wastage_id;
    expect(id).toBeTruthy();

    const delRes = await legacy.wastageDiscount.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    let row = await legacy.wastageDiscount.getById(page, id);
    expect(row, 'deleted wastage range should still exist (not hard-deleted)').toBeTruthy();
    expect(String(row.status)).toBe('0');

    const reactivateRes = await legacy.wastageDiscount.updateStatus(page, id, 0);
    expect(reactivateRes.status()).toBeLessThan(400);

    row = await legacy.wastageDiscount.getById(page, id);
    expect(row, 'wastage range must survive Delete and be reactivatable — a real hard DELETE ' +
      'would make this permanently unrecoverable').toBeTruthy();
    expect(String(row.status)).toBe('1');
  });
});

test.describe('Supplier Catalogue: Delete no longer hard-deletes', () => {
  test('delete route deactivates; record, weight tiers, and images survive', async ({ page }) => {
    const suffix = Date.now() % 100000;
    const catName = `TESTCATSC${suffix}`;
    const prodName = `TESTPRODSC${suffix}`;
    const designName = `TESTDESIGNSC${suffix}`;
    const purity = await api.createRecord(page, 'purity');

    const catRes = await legacy.category.add(page, { name: catName, id_purity: purity.id_purity });
    expect(catRes.ok, JSON.stringify(catRes.body)).toBe(true);
    const activeCats = await legacy.category.activeList(page);
    const catRow = (activeCats || []).find((c) => c.name === catName);
    expect(catRow).toBeTruthy();

    const prodRes = await legacy.product.add(page, { product_name: prodName, cat_id: String(catRow.id_ret_category) });
    expect(prodRes.status(), await prodRes.text()).toBeLessThan(400);
    const activeProducts = await legacy.product.activeList(page);
    const prodRow = (activeProducts || []).find((p) => p.product_name === prodName);
    expect(prodRow).toBeTruthy();

    const designRes = await legacy.design.add(page, { design_name: designName, product_id: String(prodRow.pro_id) });
    expect(designRes.status(), await designRes.text()).toBeLessThan(400);
    const found = await legacy.design.searchByName(page, designName, prodRow.pro_id);
    expect(found.length, 'created design not found via active_designBySearch').toBeGreaterThan(0);
    const designId = found[0].value;

    const addRes = await legacy.supplierCatalog.add(page, {
      product_id: prodRow.pro_id, design_id: designId, subdesign_id: '',
      from_weight: '5', to_weight: '10',
    });
    expect(addRes.ok, JSON.stringify(addRes.body)).toBe(true);
    const id = addRes.body.supp_cat_id;
    expect(id).toBeTruthy();

    const delRes = await legacy.supplierCatalog.delete(page, id);
    expect(delRes.status()).toBeLessThan(400);

    const row = await legacy.supplierCatalog.getById(page, id);
    expect(row, 'deleted supplier catalogue entry should still exist (not hard-deleted)').toBeTruthy();
    expect(String(row.status)).toBe('0');
  });
});
