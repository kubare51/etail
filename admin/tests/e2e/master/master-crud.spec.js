/**
 * Generic CRUD + validation coverage for every simple (non-child-table)
 * config-driven master — see helpers/master-config.js for the field data
 * driving this loop, and helpers/api.js for the AJAX helpers. One spec
 * file exercises all 34 masters instead of 34 near-identical spec files,
 * mirroring the app's own "one engine, many configs" approach.
 *
 * Per master, in order (test.describe.serial so a failed Add skips the
 * steps that depend on it):
 *   1. add: valid data succeeds
 *   2. add: a missing required field is rejected
 *   3. add: a duplicate unique value is rejected (only if a unique field exists)
 *   4. add: a value violating its format rule is rejected (only if a rule field exists)
 *   5. add: a value outside min/max range is rejected (only if a ranged field exists)
 *   6. edit: updates the record created in step 1
 *   7. status: deactivates then reactivates the record (only if statusCol exists)
 *
 * The 4 child-table masters (tgrp, attribute, diamond_rate, selling_diamond_rate)
 * are excluded here — see master-special.spec.js.
 */
const { test, expect } = require('@playwright/test');
const { masters, valueFor, CHILD_TABLE_MASTERS } = require('./helpers/master-config');
const api = require('./helpers/api');

const RULE_BAD_VALUES = {
  nameField: '<script>alert(1)</script>',
  shortCode: '<script>',
  wholeNumber: '12.5x',
};

/** Loose equality for row values: numeric compare when both sides parse as
 * numbers (decimal-typed DB columns return more decimal places than were
 * submitted, e.g. purity's decimal(10,4) turning "13.09" into "13.0900"),
 * otherwise string compare. */
function valuesMatch(a, b) {
  const na = Number(a);
  const nb = Number(b);
  if (a !== '' && b !== '' && !Number.isNaN(na) && !Number.isNaN(nb)) return na === nb;
  return String(a) === String(b);
}

/** Applies a field's server-side transform (e.g. ret_qc_cancel_reason's
 * strtoupper) to a submitted value, so matching against the STORED row
 * compares like with like. */
function expectedStoredValue(field, submitted) {
  if (field.transform === 'uppercase') return String(submitted).toUpperCase();
  return submitted;
}

for (const m of masters) {
  if (CHILD_TABLE_MASTERS.includes(m.key)) continue;

  test.describe.serial(`${m.label} (${m.key})`, () => {
    let createdId;
    // Snapshot of the FIRST successful add's unique-field values — captured
    // once, deliberately never overwritten by later buildValidPayload() calls
    // (each of which generates its own fresh values), so the duplicate test
    // always reuses the original value it needs to collide with.
    const firstUniqueValues = {};
    const fkCache = {};

    async function resolveFieldValue(page, f, suffix) {
      if (f.kind === 'fk') {
        if (!(f.name in fkCache)) {
          fkCache[f.name] = await api.firstDropdownOption(page, m.key, f.name);
        }
        return fkCache[f.name];
      }
      if (f.kind === 'fixed-select') return f.options[0];
      return valueFor(f, suffix);
    }

    async function buildValidPayload(page, suffix) {
      const data = {};
      for (const f of m.fields) {
        data[f.name] = await resolveFieldValue(page, f, suffix);
      }
      return data;
    }

    function identityField() {
      return m.fields.find((f) => f.unique) || m.fields[0];
    }

    // Defensive fallback for any master with no unique field at all (every
    // current one has at least one, but a scoped/composite-unique field can
    // still match multiple existing rows via identityField() alone — e.g.
    // branch_floor's floor_name is only unique per branch, so several
    // branches' floors could share the same name). Picking the highest-pk
    // match (the most recently inserted one) is what actually finds "the row
    // THIS test just created" instead of some unrelated pre-existing row —
    // silently operating on the wrong row previously caused a real
    // in_use_check block to look like a test-harness failure.
    function findJustCreatedRow(rows, idf, value) {
      const candidates = rows.filter((r) => valuesMatch(r[idf.name], value));
      if (!candidates.length) return undefined;
      return candidates.reduce((best, r) => (Number(r[m.pk]) > Number(best[m.pk]) ? r : best));
    }

    test('add: creates a record with valid data', async ({ page }) => {
      // A few masters have a multi-FK composite-uniqueness constraint (e.g.
      // Diamond Master's clarity+color+cut+shape) that a single random FK
      // pick can still collide with existing data on — retry a couple of
      // times with fresh picks before treating it as a real failure.
      let res;
      let data;
      for (let attempt = 0; attempt < 3; attempt++) {
        const suffix = api.uniqueSuffix() + attempt;
        data = await buildValidPayload(page, suffix);
        res = await api.addRecord(page, m.key, data);
        if (res.ok) break;
        for (const f of m.fields) {
          if (f.kind === 'fk') delete fkCache[f.name];
        }
      }
      expect(res.ok, `add failed: ${JSON.stringify(res.body)}`).toBeTruthy();

      const idf = identityField();
      const rows = await api.listRows(page, m.key);
      const expectedIdentity = expectedStoredValue(idf, data[idf.name]);
      const match = findJustCreatedRow(rows, idf, expectedIdentity);
      expect(match, `created row not found via ${idf.name}=${expectedIdentity}`).toBeTruthy();
      createdId = match[m.pk];
      for (const f of m.fields) {
        // Read back the STORED value, not the submitted one — a decimal(10,4)
        // column reformats "13.09" to "13.0900"; resubmitting the original
        // "13.09" would then string-mismatch the stored value and slip past
        // the duplicate check, which is a false negative in the test, not a
        // real product gap (a real duplicate-check flow — blur-check, or
        // editing a row seen in the table — always works from the stored form).
        if (f.unique) firstUniqueValues[f.name] = match[f.name];
      }
    });

    const requiredField = m.fields.find((f) => f.required);
    if (requiredField) {
      test(`add: rejects when required field "${requiredField.name}" is missing`, async ({ page }) => {
        const suffix = api.uniqueSuffix();
        const data = await buildValidPayload(page, suffix);
        delete data[requiredField.name];
        const res = await api.addRecord(page, m.key, data);
        expect(res.ok, `should reject missing ${requiredField.name}: ${JSON.stringify(res.body)}`).toBeFalsy();
      });
    }

    const uniqueField = m.fields.find((f) => f.unique);
    if (uniqueField) {
      test(`add: rejects a duplicate value for unique field "${uniqueField.name}"`, async ({ page }) => {
        test.skip(!createdId, 'no record was created to duplicate against');
        const suffix = api.uniqueSuffix();
        const data = await buildValidPayload(page, suffix);
        data[uniqueField.name] = firstUniqueValues[uniqueField.name];
        const res = await api.addRecord(page, m.key, data);
        expect(res.ok, `duplicate ${uniqueField.name} should be rejected: ${JSON.stringify(res.body)}`).toBeFalsy();
      });
    }

    const ruleField = m.fields.find((f) => f.kind === 'text' && RULE_BAD_VALUES[f.rule]);
    if (ruleField) {
      test(`add: rejects an invalid-format value for "${ruleField.name}" (rule: ${ruleField.rule})`, async ({ page }) => {
        const suffix = api.uniqueSuffix();
        const data = await buildValidPayload(page, suffix);
        data[ruleField.name] = RULE_BAD_VALUES[ruleField.rule];
        const res = await api.addRecord(page, m.key, data);
        expect(res.ok, `invalid ${ruleField.name} should be rejected: ${JSON.stringify(res.body)}`).toBeFalsy();
      });
    }

    const rangeField = m.fields.find((f) => f.kind === 'text' && (f.minVal !== undefined || f.maxVal !== undefined));
    if (rangeField) {
      test(`add: rejects an out-of-range value for "${rangeField.name}"`, async ({ page }) => {
        const suffix = api.uniqueSuffix();
        const data = await buildValidPayload(page, suffix);
        data[rangeField.name] = rangeField.maxVal !== undefined ? Number(rangeField.maxVal) + 1000 : Number(rangeField.minVal) - 1000;
        const res = await api.addRecord(page, m.key, data);
        expect(res.ok, `out-of-range ${rangeField.name} should be rejected: ${JSON.stringify(res.body)}`).toBeFalsy();
      });
    }

    test('edit: updates the created record', async ({ page }) => {
      test.skip(!createdId, 'no record was created to edit');
      const suffix = api.uniqueSuffix();
      const data = await buildValidPayload(page, suffix);
      const res = await api.editRecord(page, m.key, createdId, data);
      expect(res.ok, `edit failed: ${JSON.stringify(res.body)}`).toBeTruthy();

      const rows = await api.listRows(page, m.key);
      const row = rows.find((r) => String(r[m.pk]) === String(createdId));
      expect(row, 'edited row missing from list').toBeTruthy();
      const idf = identityField();
      const expected = expectedStoredValue(idf, data[idf.name]);
      expect(valuesMatch(row[idf.name], expected), `expected ${idf.name}=${expected}, got ${row[idf.name]}`).toBeTruthy();
    });

    if (m.statusCol) {
      test('status: deactivates then reactivates the record', async ({ page }) => {
        test.skip(!createdId, 'no record was created to toggle');

        let rows = await api.listRows(page, m.key);
        let row = rows.find((r) => String(r[m.pk]) === String(createdId));
        const before = Number(row[m.statusCol]);

        await api.updateStatus(page, m.key, createdId, before);
        rows = await api.listRows(page, m.key);
        row = rows.find((r) => String(r[m.pk]) === String(createdId));
        expect(Number(row[m.statusCol]), 'status did not flip').toBe(before === 1 ? 0 : 1);

        await api.updateStatus(page, m.key, createdId, before === 1 ? 0 : 1);
        rows = await api.listRows(page, m.key);
        row = rows.find((r) => String(r[m.pk]) === String(createdId));
        expect(Number(row[m.statusCol]), 'status did not restore').toBe(before);
      });
    }
  });
}
