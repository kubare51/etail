const { test } = require('@playwright/test');

test('debug DataTables init', async ({ page }) => {
  const BASE = process.env.BASE_URL || 'http://localhost/etail_v7/admin/index.php';
  const requests = [];
  const consoleMessages = [];

  page.on('request', (req) => {
    if (req.url().includes('admin_ret_catalog/color')) {
      const h = req.headers();
      requests.push({ method: req.method(), url: req.url().slice(-80), 'x-req-with': h['x-requested-with'] });
    }
  });

  page.on('console', (msg) => {
    consoleMessages.push({ type: msg.type(), text: msg.text() });
  });

  await page.goto(`${BASE}/admin_ret_catalog/color/list`);
  await page.waitForSelector('#lmx_master_table', { timeout: 10000 });
  await page.waitForTimeout(2000);

  console.log('\n=== PAGE STATE ===');
  console.log('All requests to /admin_ret_catalog/color:');
  requests.forEach((r, i) => {
    console.log(`  [${i}] ${r.method} ${r.url.slice(-80)}`);
    console.log(`       X-Requested-With: ${r.headers['x-requested-with'] || '(none)'}`);
  });

  console.log('\nDataTables state on page:');
  const dtState = await page.evaluate(() => {
    if (!window.$) return { error: 'no jQuery' };
    const t = window.$('#lmx_master_table');
    if (!t.length) return { error: 'table not found' };
    return {
      tableId: t.attr('id'),
      hasDataTable: t.hasClass('dataTable'),
      tbodyRows: t.find('tbody tr').length,
      tbodyText: t.find('tbody').text().slice(0, 100)
    };
  });
  console.log(JSON.stringify(dtState, null, 2));

  if (consoleMessages.filter(m => m.type === 'error').length) {
    console.log('\nConsole errors:');
    consoleMessages.filter(m => m.type === 'error').forEach(m => console.log('  -', m.text.slice(0, 150)));
  }
});
