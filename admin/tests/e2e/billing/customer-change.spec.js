// @ts-check
const { test, expect } = require('@playwright/test');
const { goToPaymentEdit } = require('../helpers/navigation');
const SEL = require('../helpers/selectors');

const PE = SEL.paymentEdit;

test.describe('Payment Edit Page — Customer Reassignment', () => {

  test.beforeEach(async ({ page }) => {
    await goToPaymentEdit(page);
  });

  // ---------------------------------------------------------------
  // ELIGIBILITY GUARDS & BLOCKS
  // ---------------------------------------------------------------

  test('TC-06: Past month bill is blocked', async ({ page }) => {
    const billId = '3318'; // May 2026

    // Search for the bill
    await page.locator(PE.billSearchInput).fill(billId);
    await page.locator(PE.billSearchBtn).click();
    await page.waitForTimeout(2000);

    // Click "Change Customer" button (injected dynamically next to details)
    const changeBtn = page.locator('#btnChangeCustomer');
    await expect(changeBtn).toBeVisible();
    await changeBtn.click();

    // Should show error toaster immediately because it's past month
    await expect(page.locator('#toaster .alert-danger')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#toaster .alert-danger')).toContainText('previous month');
  });

  test('TC-04: Gift voucher bill is blocked', async ({ page }) => {
    const billId = '3361'; // Current month (June 2026), has gift card

    await page.locator(PE.billSearchInput).fill(billId);
    await page.locator(PE.billSearchBtn).click();
    await page.waitForTimeout(2000);

    const changeBtn = page.locator('#btnChangeCustomer');
    await expect(changeBtn).toBeVisible();
    await changeBtn.click();

    await expect(page.locator('#toaster .alert-danger')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#toaster .alert-danger')).toContainText('gift voucher issued');
  });

  test('TC-05: Scheme/chit bill is blocked', async ({ page }) => {
    const billId = '3134'; // Current month (June 2026), has chit utilization, no gift card

    await page.locator(PE.billSearchInput).fill(billId);
    await page.locator(PE.billSearchBtn).click();
    await page.waitForTimeout(2000);

    const changeBtn = page.locator('#btnChangeCustomer');
    await expect(changeBtn).toBeVisible();
    await changeBtn.click();

    await expect(page.locator('#toaster .alert-danger')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#toaster .alert-danger')).toContainText('scheme/chit utilized');
  });

  test('TC-07: B2B bill reassign to customer without valid GST is blocked', async ({ page }) => {
    const billId = '3064'; // Current month (June 2026), B2B bill

    await page.locator(PE.billSearchInput).fill(billId);
    await page.locator(PE.billSearchBtn).click();
    await page.waitForTimeout(2000);

    const changeBtn = page.locator('#btnChangeCustomer');
    await expect(changeBtn).toBeVisible();
    await changeBtn.click();

    const modal = page.locator('#customer-change-modal');
    await expect(modal).toBeVisible();

    // Select customer KIRUTHIKA (ID 1299) who has no GST
    await page.locator('#customer-change-modal span.select2-container').click();
    await page.locator('.select2-search__field').fill('KIRUTHIKA');
    const option = page.locator('li.select2-results__option', { hasText: 'KIRUTHIKA-9150027594' });
    await option.waitFor({ state: 'visible', timeout: 5000 });
    await option.click();

    // Type reason
    await page.locator('#cus_change_reason').fill('Test Reassignment GST Validation');

    // Verification: Validation Checklist should show GST check failed
    const valGst = page.locator('#val_gst_status');
    await expect(valGst).toContainText('Invalid');

    // Confirm button should be disabled
    const saveBtn = page.locator('#btnConfirmCusChange');
    await expect(saveBtn).toBeDisabled();

    // Modal should remain open
    await expect(modal).toBeVisible();

    // Close modal
    await page.locator('#customer-change-modal button.close').click();
    await expect(modal).not.toBeVisible();
  });

  test('TC-08: Bill amount >= 2L reassign to customer without PAN/Aadhaar is blocked', async ({ page }) => {
    const billId = '3319'; // Current month (June 2026), B2C bill >= 2L (amount: 313738)

    await page.locator(PE.billSearchInput).fill(billId);
    await page.locator(PE.billSearchBtn).click();
    await page.waitForTimeout(2000);

    const changeBtn = page.locator('#btnChangeCustomer');
    await expect(changeBtn).toBeVisible();
    await changeBtn.click();

    const modal = page.locator('#customer-change-modal');
    await expect(modal).toBeVisible();

    // Select customer ARUNA (ID 1341) who has no PAN/Aadhaar
    await page.locator('#customer-change-modal span.select2-container').click();
    await page.locator('.select2-search__field').fill('ARUNA');
    const option = page.locator('li.select2-results__option', { hasText: 'ARUNA-6381553908' });
    await option.waitFor({ state: 'visible', timeout: 5000 });
    await option.click();

    // Type reason
    await page.locator('#cus_change_reason').fill('Test Reassignment PAN Validation');

    // Verification: Validation Checklist should show PAN/Aadhaar check failed
    const valPan = page.locator('#val_pan_status');
    await expect(valPan).toContainText('Required');

    // Confirm button should be disabled
    const saveBtn = page.locator('#btnConfirmCusChange');
    await expect(saveBtn).toBeDisabled();

    // Modal should remain open
    await expect(modal).toBeVisible();

    // Close modal
    await page.locator('#customer-change-modal button.close').click();
    await expect(modal).not.toBeVisible();
  });

  // ---------------------------------------------------------------
  // HAPPY PATH & CASCADE
  // ---------------------------------------------------------------


  test('TC-01/TC-11: Change customer happy path + cascade + rollback', async ({ page }) => {
    const billId = '3360'; // LOGI (1303), Current month, clean bill

    await page.locator(PE.billSearchInput).fill(billId);
    await page.locator(PE.billSearchBtn).click();
    await page.waitForTimeout(2000);

    // Initial check: verify name is 'LOGI'
    await expect(page.locator(PE.customerName)).toHaveValue('LOGI');

    const changeBtn = page.locator('#btnChangeCustomer');
    await expect(changeBtn).toBeVisible();
    await changeBtn.click();

    // Modal should open
    const modal = page.locator('#customer-change-modal');
    await expect(modal).toBeVisible();

    // Select2 search in modal: click to open dropdown
    await page.locator('#customer-change-modal span.select2-container').click();
    await page.locator('.select2-search__field').fill('KIRUTHIKA');
    
    // Click on KIRUTHIKA in dropdown options (specifically match with phone suffix to avoid duplicates)
    const option = page.locator('li.select2-results__option', { hasText: 'KIRUTHIKA-9150027594' });
    await option.waitFor({ state: 'visible', timeout: 5000 });
    await option.click();

    // Real-time preview panel should show KIRUTHIKA details
    await expect(page.locator('#ccPreviewName')).toContainText('KIRUTHIKA');

    // Impact preview details and warning should be visible
    await expect(page.locator('#ccImpactBody')).toContainText('Bill Header');
    await expect(page.locator('#ccImpactBody')).toContainText('Delivery Record');
    await expect(page.locator('#cusChangeWarning')).toBeVisible();

    // Type a reason (min 5 characters)
    await page.locator('#cus_change_reason').fill('Test Reassignment E2E');

    // Setup dialog listener to click "OK" on confirm window
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('Are you sure you want to change');
      await dialog.accept();
    });

    const saveBtn = page.locator('#btnConfirmCusChange');
    await saveBtn.click();

    // Modal should be hidden first (proves AJAX completed and was processed)
    await expect(modal).not.toBeVisible({ timeout: 10000 });

    // Verify success toaster
    const successToast = page.locator('#toaster .alert-success');
    await expect(successToast).toBeVisible({ timeout: 5000 });
    await expect(successToast).toContainText('Customer changed');

    // Verify UI has updated with new customer details
    await expect(page.locator(PE.customerName)).toHaveValue('KIRUTHIKA');

    // Close success toaster so it doesn't interfere with the next verification
    await successToast.locator('button.close').click();
    await expect(successToast).not.toBeVisible();

    // ---------------------------------------------------------------
    // ROLLBACK / CHANGE BACK TO LOGI (Idempotency guarantee)
    // ---------------------------------------------------------------
    await changeBtn.click();
    await expect(modal).toBeVisible();

    // Select2 search in modal: click to open dropdown
    await page.locator('#customer-change-modal span.select2-container').click();
    await page.locator('.select2-search__field').fill('LOGI');
    
    // Click on LOGI option
    const optionLogi = page.locator('li.select2-results__option', { hasText: 'LOGI-9150064426' });
    await optionLogi.waitFor({ state: 'visible', timeout: 5000 });
    await optionLogi.click();

    // Real-time preview panel should show LOGI details
    await expect(page.locator('#ccPreviewName')).toContainText('LOGI');

    // Type a reason
    await page.locator('#cus_change_reason').fill('Test Reassignment E2E Rollback');

    // Setup dialog listener to click "OK" on confirm window
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('Are you sure you want to change');
      await dialog.accept();
    });

    await saveBtn.click();

    // Modal should be hidden first (proves AJAX completed)
    await expect(modal).not.toBeVisible({ timeout: 10000 });

    // Verify success toaster
    await expect(successToast).toBeVisible({ timeout: 5000 });
    await expect(successToast).toContainText('Customer changed');

    // Verify UI has reverted back to LOGI
    await expect(page.locator(PE.customerName)).toHaveValue('LOGI');
  });

});
