const { test } = require('@playwright/test');

test('check color list AJAX', async ({ page }) => {
  const BASE = process.env.BASE_URL || 'http://localhost/etail_v7/admin/index.php';
  let ajaxResponse = null;

  page.on('response', async (resp) => {
    const url = resp.url();
    if (url.includes('admin_ret_catalog/color') && resp.status() === 200) {
      ajaxResponse = await resp.text();
    }
  });

  await page.goto(`${BASE}/admin_ret_catalog/color/list`);
  await page.waitForSelector('#lmx_master_table', { timeout: 10000 });
  await page.waitForTimeout(3000);

  console.log('\nPAGE TITLE:', await page.title());
  console.log('TABLE EXISTS:', await page.locator('#lmx_master_table').isVisible());
  console.log('TBODY ROWS:', await page.locator('#lmx_master_table tbody tr').count());

  if (ajaxResponse) {
    console.log('AJAX RESPONSE (first 500 chars):');
    console.log(ajaxResponse.slice(0, 500));
  } else {
    console.log('NO AJAX RESPONSE CAPTURED');
  }

  // Try to read table directly from HTML
  const html = await page.locator('#lmx_master_table').innerHTML();
  console.log('TABLE HTML (first 300 chars):', html.slice(0, 300).replace(/\s+/g, ' '));
});
