/**
 * Generic AJAX helpers for the config-driven master engine
 * (admin/application/helpers/lmx/engines/master_engine_helper.php).
 *
 * All 38 migrated masters share ONE controller contract:
 *   POST admin_ret_catalog/{key}/add
 *   POST admin_ret_catalog/{key}/edit/{id}
 *   POST admin_ret_catalog/{key}/update_status/{id}/{currentStatus}
 *   POST admin_ret_catalog/{key}/check_unique
 *   GET  admin_ret_catalog/{key}/child_rows/{id}
 * so one generic set of request helpers works for every master — same
 * reasoning as why the engine itself is one config-driven implementation
 * instead of 38 hand-written controllers.
 *
 * Uses page.request (Playwright's APIRequestContext) rather than driving the
 * actual <form> — it shares the browser context's session/cookies, so it's a
 * real authenticated request through the real controller, just without
 * clicking through the modal. This mirrors the $.ajax()-from-devtools method
 * used to verify this engine live throughout its development.
 */

function baseUrl() {
  return process.env.BASE_URL || 'http://localhost/etail_v7/admin/index.php';
}

/** Almost every master's controller method name matches its config key
 * exactly — 'size' is the one exception (controller method is ret_size(),
 * see master-config.js's urlKey). Resolve here so every helper below gets
 * the right URL automatically instead of each call site remembering to. */
function urlKeyFor(key) {
  const { masters } = require('./master-config');
  const m = masters.find((mm) => mm.key === key);
  return (m && m.urlKey) || key;
}

function masterUrl(key, action) {
  return `${baseUrl()}/admin_ret_catalog/${urlKeyFor(key)}${action ? '/' + action : ''}`;
}

/** Flattens {a:1, b:{0:{c:2}}} style nested objects into PHP-array form data. */
function toFormData(data) {
  const form = {};
  function walk(prefix, value) {
    if (value !== null && typeof value === 'object' && !(value instanceof Date)) {
      for (const k of Object.keys(value)) {
        walk(prefix ? `${prefix}[${k}]` : k, value[k]);
      }
    } else {
      form[prefix] = value === undefined || value === null ? '' : String(value);
    }
  }
  walk('', data);
  delete form[''];
  return form;
}

async function addRecord(page, key, data) {
  const res = await page.request.post(masterUrl(key, 'add'), { form: toFormData(data) });
  return parseResult(res);
}

async function editRecord(page, key, id, data) {
  const res = await page.request.post(masterUrl(key, `edit/${id}`), { form: toFormData(data) });
  return parseResult(res);
}

/** currentStatus is the record's status BEFORE the call (engine flips it).
 * X-Requested-With mirrors what the real toggle button's $.ajax() call sends —
 * the controller now requires it (FR-CAT-011) to reject cross-site GET/POSTs. */
async function updateStatus(page, key, id, currentStatus) {
  const res = await page.request.post(masterUrl(key, `update_status/${id}/${currentStatus}`), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  return parseResult(res);
}

/** Mirrors the Delete button's target URL — note the real button is unusable by
 * automation (native confirm() dialog hangs the browser), so tests call this
 * AJAX endpoint directly, exactly as the click handler's $.ajax call does. */
async function deleteRecord(page, key, id) {
  const res = await page.request.post(masterUrl(key, `delete/${id}`));
  return parseResult(res);
}

async function checkUnique(page, key, field, value, excludeId, extraValue) {
  const form = { field, value };
  if (excludeId) form.exclude_id = excludeId;
  if (extraValue !== undefined) form.extra_value = extraValue;
  const res = await page.request.post(masterUrl(key, 'check_unique'), { form });
  return res.json();
}

async function childRows(page, key, id) {
  const res = await page.request.get(masterUrl(key, `child_rows/${id}`));
  return res.json();
}

/** Fetches the list page and returns the row array straight from the DataTable AJAX contract. */
async function listRows(page, key) {
  const res = await page.request.post(masterUrl(key), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const body = await res.json();
  return body.responseData || [];
}

/**
 * The add/edit modal's <select> options are rendered server-side from
 * config['dropdowns'] — scrape the Add modal for a field's available option
 * values so FK tests never need a hardcoded, dev-data-dependent id.
 */
async function allDropdownOptions(page, key, fieldName) {
  await page.goto(masterUrl(key, 'list'), { waitUntil: 'domcontentloaded' });
  const select = page.locator(`#lmx-modal-add [name="${fieldName}"]`);
  await select.waitFor({ state: 'attached', timeout: 10000 });
  const options = await select.locator('option').all();
  const values = [];
  for (const opt of options) {
    const val = await opt.getAttribute('value');
    if (val) values.push(val);
  }
  return values;
}

/**
 * Picks a RANDOM available option rather than always the first — masters
 * with a composite-uniqueness constraint spanning several FK fields (e.g.
 * Diamond Master's clarity+color+cut+shape) would otherwise deterministically
 * collide with an existing combination on every single test run.
 */
async function firstDropdownOption(page, key, fieldName) {
  const values = await allDropdownOptions(page, key, fieldName);
  if (!values.length) return null;
  return values[Math.floor(Math.random() * values.length)];
}

function parseResult(res) {
  return res.json().then((body) => ({
    ok: body.status === true || body.class === 'success',
    body,
    status: res.status(),
  }));
}

// base36-encoded — a handful of chars instead of 16+ decimal digits, since
// several masters have tight max_length limits (e.g. old_metal_cat: 25,
// size.name: 20) that a raw Date.now()+random string can blow past.
const uniqueSuffix = () => Date.now().toString(36) + Math.floor(Math.random() * 36).toString(36);

/** Finds a row in a listRows() result by a field's expected value (loose string
 * compare, numeric-aware) — a fixed-precision DECIMAL column (e.g. purity's
 * DECIMAL(x,4)) echoes back "77.1200" for a submitted "77.12"; falling back to
 * Number() comparison when both sides parse as finite numbers keeps this from
 * false-negative-ing on trailing zero padding. */
function findRow(rows, field, value) {
  const numValue = Number(value);
  const numComparable = value !== '' && Number.isFinite(numValue);
  return rows.find((r) => {
    if (String(r[field]) === String(value)) return true;
    return numComparable && Number.isFinite(Number(r[field])) && Number(r[field]) === numValue;
  });
}

/**
 * Builds a valid minimal payload for `key` from master-config.js's field data
 * (FK fields auto-resolved to their first available dropdown option, unless
 * overridden), creates the record, and returns its full row (including pk) —
 * for tests in master-special.spec.js that need a fresh prerequisite record
 * in another master (e.g. a Tax before creating a Tax Group that uses it).
 * `overrides` sets/replaces specific field values (e.g. a specific FK id).
 */
async function createRecord(page, key, overrides = {}) {
  const { masters, valueFor } = require('./master-config');
  const target = masters.find((m) => m.key === key);
  if (!target) throw new Error(`No master-config entry for "${key}"`);
  const suffix = uniqueSuffix();
  const data = {};
  for (const f of target.fields) {
    if (f.name in overrides) {
      data[f.name] = overrides[f.name];
    } else if (f.kind === 'fk') {
      data[f.name] = await firstDropdownOption(page, key, f.name);
    } else if (f.kind === 'fixed-select') {
      data[f.name] = f.options[0];
    } else {
      data[f.name] = valueFor(f, suffix);
    }
  }
  const res = await addRecord(page, key, data);
  if (!res.ok) throw new Error(`createRecord(${key}) failed: ${JSON.stringify(res.body)}`);
  const idf = target.fields.find((f) => f.unique) || target.fields[0];
  const rows = await listRows(page, key);
  const row = findRow(rows, idf.name, data[idf.name]);
  if (!row) throw new Error(`createRecord(${key}) succeeded but row not found via ${idf.name}=${data[idf.name]}`);
  return row;
}

module.exports = {
  baseUrl, masterUrl, addRecord, editRecord, updateStatus, deleteRecord, checkUnique,
  childRows, listRows, firstDropdownOption, uniqueSuffix, findRow, createRecord,
};
