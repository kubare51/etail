// @ts-check
const { test, expect } = require('@playwright/test');
const { getBaseUrl } = require('../helpers/navigation');

/**
 * Supplier Reassignment E2E Tests
 * 
 * Task: 9ed390a1a031
 * Tests the guard/block logic and cascade behavior when changing
 * the supplier (karigar) on GRN, Bill Entry, and Lot edit pages.
 * 
 * Test Data (from dev DB):
 *   TC-05: GRN #1578 (DCNM ID:814 → past month + has payment, should BLOCK)
 *   TC-07: GRN #1585 (RUTHRAMOORTHI ID:899 → past month + has rate cut, should BLOCK)
 *   TC-01: GRN #674 (RINKESH BHAI ID:581 → past month, should BLOCK)
 *   TC-04: Lot #9665 (Manual, no po_id/grn_id → should ALLOW)
 *   TC-13: Lot #9660 (Purchase lot, lot_from=2 → blocked by RULE-LOT-004)
 */

// Selectors
const SEL = {
  karigarHidden:    '#grn_entry_form #id_karigar',
  submitGrn:        '#submit_grn_entry',
  contentWrapper:   '.content-wrapper',
};

/**
 * Helper: Invoke GRN update AJAX directly via page.evaluate
 */
async function invokeGrnUpdate(page, grnId, newKarigarId) {
  return await page.evaluate(async ({ grnId, newKarigarId }) => {
    return new Promise((resolve) => {
      const formData = new FormData(document.querySelector('#grn_entry_form'));
      formData.set('order[id_karigar]', newKarigarId.toString());
      
      const params = new URLSearchParams();
      for (const [key, value] of formData.entries()) {
        params.append(key, value.toString());
      }
      
      $.ajax({
        url: base_url + 'index.php/admin_ret_purchase/grnentry/update/' + grnId,
        data: params.toString(),
        type: 'POST',
        dataType: 'json',
        success: function(data) {
          resolve({ status: data.status === true || data.status === 'true', message: data.message || data.msg || '' });
        },
        error: function(xhr, status, error) {
          try {
            const data = JSON.parse(xhr.responseText);
            resolve({ status: data.status === true || data.status === 'true', message: data.message || data.msg || '' });
          } catch(e) {
            resolve({ status: false, message: 'AJAX error: ' + error });
          }
        }
      });
    });
  }, { grnId, newKarigarId });
}

// ================================================================
// TEST GROUP 1: BLOCK — Past Month Entries
// ================================================================

test.describe('Supplier Reassignment — Current Month Restriction', () => {

  test('TC-16: Past month GRN — should block supplier change', async ({ page }) => {
    const baseUrl = getBaseUrl();
    const grnId = 1578;       // DCNM (ID:814) — date: 2026-05-05 (past month)
    const newKarigarId = 899;

    await page.goto(`${baseUrl}/admin_ret_purchase/grnentry/edit/${grnId}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    await page.waitForSelector(SEL.contentWrapper, { timeout: 15000 });
    await page.waitForTimeout(3000);

    const currentKarigar = await page.locator(SEL.karigarHidden).inputValue();
    expect(currentKarigar).toBe('814');

    // Invoke update with different supplier — should be BLOCKED by month guard
    const result = await invokeGrnUpdate(page, grnId, newKarigarId);

    expect(result.status).toBe(false);
    expect(result.message).toContain('past month');
  });

  test('TC-17: Another past month GRN — month guard fires before financial guard', async ({ page }) => {
    const baseUrl = getBaseUrl();
    const grnId = 1585;       // RUTHRAMOORTHI (ID:899) — date: 2026-05-26 (past month)
    const newKarigarId = 581;

    await page.goto(`${baseUrl}/admin_ret_purchase/grnentry/edit/${grnId}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    await page.waitForSelector(SEL.contentWrapper, { timeout: 15000 });
    await page.waitForTimeout(3000);

    const currentKarigar = await page.locator(SEL.karigarHidden).inputValue();
    expect(currentKarigar).toBe('899');

    const result = await invokeGrnUpdate(page, grnId, newKarigarId);

    expect(result.status).toBe(false);
    // Month guard fires BEFORE financial guard
    expect(result.message).toContain('past month');
  });
});

// ================================================================
// TEST GROUP 2: ALLOW — Current Month Cascade (Changes Data!)
// ================================================================

test.describe('Supplier Reassignment — Cascade Tests (Current Month)', () => {

  test('TC-01: Current month GRN without financials — should allow supplier change and cascade', async ({ page }) => {
    const baseUrl = getBaseUrl();
    const grnId = 1586;        // RINKESH BHAI (ID:2) — today's GRN, no financials
    const newKarigarId = 899;  // RUTHRAMOORTHI M
    const originalKarigarId = 2;

    await page.goto(`${baseUrl}/admin_ret_purchase/grnentry/edit/${grnId}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    await page.waitForSelector(SEL.contentWrapper, { timeout: 15000 });
    await page.waitForTimeout(3000);

    // Verify correct GRN loaded
    const currentKarigar = await page.locator(SEL.karigarHidden).inputValue();
    expect(currentKarigar).toBe(String(originalKarigarId));

    // Invoke update — should SUCCEED (current month, no financials)
    const result = await invokeGrnUpdate(page, grnId, newKarigarId);
    expect(result.status).toBe(true);

    // Verify cascade: reload and check karigar changed
    await page.goto(`${baseUrl}/admin_ret_purchase/grnentry/edit/${grnId}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    await page.waitForSelector(SEL.contentWrapper, { timeout: 15000 });
    await page.waitForTimeout(3000);

    const updatedKarigar = await page.locator(SEL.karigarHidden).inputValue();
    expect(updatedKarigar).toBe(String(newKarigarId));

    // === REVERT for idempotency ===
    const revertResult = await invokeGrnUpdate(page, grnId, originalKarigarId);
    expect(revertResult.status).toBe(true);
  });
});

// ================================================================
// TEST GROUP 3: NO-OP Tests (No Supplier Change = No Guard)
// ================================================================

test.describe('Supplier Reassignment — No-Op Tests', () => {

  test('TC-15: GRN save without changing supplier — should succeed even on past month', async ({ page }) => {
    const baseUrl = getBaseUrl();
    const grnId = 1578;        // Past month, has payment
    const sameKarigarId = 814; // Same as current — NO change

    await page.goto(`${baseUrl}/admin_ret_purchase/grnentry/edit/${grnId}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    await page.waitForSelector(SEL.contentWrapper, { timeout: 15000 });
    await page.waitForTimeout(3000);

    // Submit with SAME supplier — guard should NOT trigger
    const result = await invokeGrnUpdate(page, grnId, sameKarigarId);

    // Should succeed — no supplier change = no guard (including month guard)
    expect(result.status).toBe(true);
  });
});

// ================================================================
// TEST GROUP 3: LOT — Guard Tests
// ================================================================

test.describe('Supplier Reassignment — Lot Guard Tests', () => {

  test('TC-04: Manual lot — should allow gold smith change', async ({ page }) => {
    const baseUrl = getBaseUrl();
    const lotNo = 9665;

    await page.goto(`${baseUrl}/admin_ret_lot/lot_inward/edit/${lotNo}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    await page.waitForSelector(SEL.contentWrapper, { timeout: 15000 });
    await page.waitForTimeout(3000);

    const url = page.url();
    expect(url).toContain('/edit/');
    expect(url).not.toContain('/list');
  });

  test('TC-13: Purchase lot — should block edit (RULE-LOT-004)', async ({ page }) => {
    const baseUrl = getBaseUrl();
    const lotNo = 9660;

    await page.goto(`${baseUrl}/admin_ret_lot/lot_inward/edit/${lotNo}`, {
      waitUntil: 'domcontentloaded',
      timeout: 30000,
    });
    
    await page.waitForTimeout(3000);

    const url = page.url();
    expect(url).toContain('/list');
  });
});
