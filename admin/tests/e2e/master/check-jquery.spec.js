const { test } = require('@playwright/test');

test('check jQuery and inline JS', async ({ page }) => {
  const BASE = process.env.BASE_URL || 'http://localhost/etail_v7/admin/index.php';

  await page.goto(`${BASE}/admin_ret_catalog/color/list`);
  await page.waitForTimeout(1000);

  const state = await page.evaluate(() => ({
    jQueryLoaded: typeof window.$ !== 'undefined',
    jQueryVersion: window.$ && window.$.fn && window.$.fn.jquery,
    dataTablesLoaded: typeof window.DataTable !== 'undefined' || (window.$ && window.$.fn && window.$.fn.DataTable),
    tableExists: document.querySelector('#lmx_master_table') !== null,
    bodyHTML: document.body.innerHTML.slice(0, 500)
  }));

  console.log('\nPage state:');
  console.log('  jQuery loaded:', state.jQueryLoaded);
  if (state.jQueryVersion) console.log('  jQuery version:', state.jQueryVersion);
  console.log('  DataTables loaded:', state.dataTablesLoaded);
  console.log('  Table exists:', state.tableExists);
  console.log('\nBody HTML snippet:', state.bodyHTML.replace(/\s+/g, ' '));
});
