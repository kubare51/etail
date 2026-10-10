# Database Views — Canonical Definitions

This directory contains the **single source of truth** for all MySQL view definitions used by the Retail ERP application.

## Directory Structure

```
database/views/
├── README.md                              ← You are here
├── ret_view_supplier_ledger.sql           ← Supplier transaction ledger
├── ret_view_smith_ledger.sql              ← Smith/karigar ledger
├── ret_view_smith_combined_ledger.sql     ← Combined smith + supplier ledger
├── ret_view_supplier_approval_ledger.sql  ← Supplier approval ledger
├── ret_view_smith_metal_ledger.sql        ← Smith metal-only ledger
├── ret_view_smith_metal_amt_ledger.sql    ← Smith metal + amount ledger
├── ret_view_grn_pay_ledger.sql            ← GRN payment ledger
├── v_nontag_stock_balance.sql             ← Non-tagged stock balance
└── vw_nontag_stock_compat.sql             ← Non-tagged stock compat view
```

## Workflow: How to Change a View

### 1. Edit the canonical file

Make your changes directly in the `.sql` file in this directory. The file uses `CREATE OR REPLACE VIEW` syntax.

### 2. Create a thin migration

Create a migration in `database/migrations/` that simply applies the canonical file:

```sql
-- Migration: <describe your change>
-- Date: DD-MM-YYYY
-- UP
-- Applied by run-migrations.sh via database/views/<view_name>.sql
-- Change: <one-line summary of what changed>
```

The migration runner (`run-migrations.sh`) automatically applies all view files from this directory **after** processing standard migrations. The migration file serves as a changelog entry.

### 3. Commit both files

```bash
git add database/views/<view_name>.sql
git add database/migrations/<migration_file>.sql
git commit -m "fix: <describe change> in <view_name>"
```

## Why This Approach?

| Before (fat migrations) | After (canonical files) |
|---|---|
| 75KB migration for a 1-line WHERE clause change | `git diff` shows the exact line changed |
| View definition scattered across 4 migration files | Single source of truth per view |
| Impossible to review in PRs | Clean, diffable changes |
| Parallel edits = silent overwrites | Standard git merge resolves conflicts |

## Rules

1. **Never put `CREATE OR REPLACE VIEW` in a migration file** — always edit the canonical file here instead
2. **Always create a migration entry** — even though views are auto-applied, the migration serves as a changelog and prevents re-apply on unchanged schemas
3. **Don't delete old view migrations** — they're historical records, leave them as-is
4. **Format views for readability** — use UNION ALL on separate lines, break long WHERE clauses

## How Views Are Applied

The `run-migrations.sh` script applies views in two phases:

1. **Phase 1**: Standard migrations from `database/migrations/*.sql` (tracked in `schema_migrations`)
2. **Phase 2**: All view definitions from `database/views/*.sql` (always re-applied, idempotent)

Since views use `CREATE OR REPLACE`, re-applying them is always safe and idempotent.
