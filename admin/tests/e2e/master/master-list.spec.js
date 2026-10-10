/**
 * Read-only verification: do the config-driven master lists actually render rows?
 *
 * Purpose: confirm/deny the DataTable contract theory — the generic view sets
 * serverSide:true while the engine returns {responseData:[...]} (no data/draw),
 * which would render empty. Navigates + observes only; no data is created/edited.
 */
const { test } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost/etail_v7/admin/index.php';

const MASTERS = [
  { key: 'color', label: 'Color' },
  { key: 'theme', label: 'Theme' },
];

for (const m of MASTERS) {
  test(`master list renders: ${m.label}`, async ({ page }) => {
    const ajax = [];
    const consoleErrors = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', (err) => consoleErrors.push('PAGEERROR: ' + err.message));
    page.on('response', async (resp) => {
      const url = resp.url();
      if (url.includes(`admin_ret_catalog/${m.key}`) && resp.request().method() === 'POST') {
        let body = '';
        try { body = await resp.text(); } catch (e) { body = '(unreadable)'; }
        ajax.push({ status: resp.status(), url,
          snippet: body.slice(0, 800),
          isJson: resp.headers()['content-type']?.includes('json')
        });
      }
    });

    await page.goto(`${BASE}/admin_ret_catalog/${m.key}/list`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#lmx_master_table', { timeout: 15000 });
    await page.waitForTimeout(4000); // let DataTables fire its ajax + render

    const rowCount   = await page.locator('#lmx_master_table tbody tr').count();
    const emptyMark  = await page.locator('#lmx_master_table tbody tr.dataTables_empty').count();
    const tbodyText  = await page.locator('#lmx_master_table tbody').innerText().catch(() => '(no tbody)');

    const dtDebug = await page.evaluate(() => {
      const t = window.$('#lmx_master_table');
      if (t.length && t.DataTable) {
        const api = t.DataTable();
        return { hasInstance: true, rows: api.rows().count(), data: api.data().toArray().length };
      }
      return { hasInstance: false };
    }).catch(e => ({ error: e.message }));

    console.log(`\n===== ${m.label} =====`);
    console.log(`rows=${rowCount}  emptyMark=${emptyMark}  DT=${JSON.stringify(dtDebug)}`);
    console.log(`tbody: ${tbodyText.slice(0, 220).replace(/\s+/g, ' ')}`);
    for (const a of ajax) {
      console.log(`AJAX ${a.status} (${a.isJson ? 'JSON' : '?'}) ${a.url.slice(-50)}`);
      console.log(`  body: ${a.snippet.replace(/\s+/g, ' ').slice(0, 300)}`);
    }
    if (consoleErrors.length) {
      console.log('CONSOLE ERRORS:');
      consoleErrors.slice(0, 3).forEach((e) => console.log('  - ' + e.slice(0, 150)));
    }
    await page.screenshot({ path: `test-results/master-${m.key}.png`, fullPage: true });
  });
}
