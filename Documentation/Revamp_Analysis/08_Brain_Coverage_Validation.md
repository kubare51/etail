# Brain Coverage Validation Plan

> **Purpose**: Verify that the knowledge brain actually covers every part of the codebase.
> **Method**: Cross-validate AI-generated brain documents against automated LCA analysis.
> **Principle**: If LCA finds something the brain doesn't mention, it's a gap.

---

## Why This Matters

The brain was built by an AI reading code files one at a time. This approach has blind spots:

| Risk | Description |
|---|---|
| **Skipped functions** | Large files (12,000+ lines) — AI may have skimmed helper methods |
| **Helpers not covered** | `helpers/`, `libraries/`, `core/` contain shared business logic |
| **View-embedded logic** | PHP views have inline calculations and conditionals |
| **JS logic not audited** | 357KB payment.js has financial formulas the brain never reviewed |
| **Dead code not identified** | Brain documents what it reads, not what's unused |
| **Table references missed** | Raw SQL in concatenated strings can escape pattern matching |

---

## Validation Steps

### Step 1: File Coverage Audit

**Question**: Does every PHP file in the codebase appear in at least one brain document?

**Method**:
```bash
# 1. List ALL PHP files in the application
find admin/application/controllers -name "*.php" | sort > /tmp/all_controllers.txt
find admin/application/models -name "*.php" | sort > /tmp/all_models.txt
find admin/application/helpers -name "*.php" | sort > /tmp/all_helpers.txt
find admin/application/libraries -name "*.php" | sort > /tmp/all_libraries.txt
find admin/application/core -name "*.php" | sort > /tmp/all_core.txt

# 2. List all files referenced in brain documents
grep -roh "admin/application/[^ ]*\.php" knowledge_brain/ | sort -u > /tmp/brain_files.txt

# 3. Find files NOT in any brain document
comm -23 /tmp/all_controllers.txt /tmp/brain_files.txt > /tmp/missing_files.txt
```

**Expected gaps**: 
- Backup files (date-stamped copies) — acceptable to skip
- Helper files — MUST be covered
- Library files — MUST be covered
- Core overrides — MUST be covered

---

### Step 2: Function Coverage Audit (LCA vs Brain)

**Question**: Does every function in the codebase appear in a brain's METHOD_INDEX?

**Method**:
```bash
# 1. Use LCA index (already built — 58MB) to get ALL functions
uv run lca search "*" --type function --format json > /tmp/all_functions.json

# 2. Extract function names from all METHOD_INDEX.md files
grep -roh "[a-z_]*(" knowledge_brain/*/METHOD_INDEX.md | sort -u > /tmp/brain_functions.txt

# 3. Compare — functions in LCA but not in brain = gap
```

**What this catches**:
- Private helper methods the AI skimmed
- Callback functions
- Functions in included/required files
- Functions in helpers, libraries, core

---

### Step 3: Table Coverage Audit (LCA sql-map vs Brain)

**Question**: Does every database table referenced in code appear in a brain's SCHEMA_ANALYSIS or SHARED_TABLES?

**Method**:
```bash
# 1. Use LCA sql-map to find ALL table references in code
uv run lca sql-map admin/application/models --root . --format json > /tmp/all_tables.json
uv run lca sql-map admin/application/controllers --root . --format json >> /tmp/all_tables.json

# 2. Extract table names from brain SCHEMA_ANALYSIS and SHARED_TABLES
grep -roh "ret_[a-z_]*\|chit_[a-z_]*\|payment[a-z_]*\|scheme[a-z_]*\|customer[a-z_]*" \
  knowledge_brain/*/SCHEMA_ANALYSIS.md knowledge_brain/_SYSTEM/SHARED_TABLES.md \
  | sort -u > /tmp/brain_tables.txt

# 3. Compare
```

**What this catches**:
- Tables used in raw SQL that the AI didn't notice
- Tables used only in helpers or libraries
- Cross-module table references missed in SHARED_TABLES
- Orphan tables (in DB but never referenced in code)

---

### Step 4: Security Coverage Audit (LCA security vs Brain)

**Question**: Does every security finding from LCA's automated scanner appear in DANGER_ZONES or VALIDATION_GAPS?

**Method**:
```bash
# 1. Run LCA security scan on entire codebase
uv run lca health admin/application/controllers --root . --format json > /tmp/security_scan.json
uv run lca health admin/application/models --root . --format json >> /tmp/security_scan.json

# 2. Extract security findings (rule SEC-001 through SEC-008)
# Each finding has: file, line, rule, severity

# 3. Cross-reference each finding against DANGER_ZONES.md and VALIDATION_GAPS.md
# Any finding NOT in brain = undocumented security risk
```

**What this catches**:
- SQL injection patterns the AI missed (LCA uses regex, catches more patterns)
- Raw superglobals the AI didn't flag
- XSS in echo statements
- eval/exec calls in obscure files
- New vulnerabilities introduced after the brain was last updated (2026-03-26)

---

### Step 5: AJAX Coverage Audit (LCA crossref vs Brain)

**Question**: Does every JS→PHP AJAX call appear in a brain's AJAX_MAP or CROSS_MODULE_MAP?

**Method**:
```bash
# 1. Use LCA crossref to find ALL AJAX calls
uv run lca crossref admin/assets/js --root . --format json > /tmp/all_ajax.json

# 2. Extract AJAX endpoints from brain CROSS_MODULE_MAP
grep -roh "admin_[a-z_]*/[a-z_]*" knowledge_brain/*/CROSS_MODULE_MAP.md \
  | sort -u > /tmp/brain_ajax.txt

# 3. Compare — AJAX calls in code but not in brain = undocumented dependency
```

**What this catches**:
- AJAX calls added after the brain was built
- Cross-module AJAX calls the AI missed
- JS files not associated with any module brain

---

### Step 6: Dead Code Identification (LCA reaper)

**Question**: Which functions exist in code but have zero callers?

**Method**:
```bash
# LCA reaper finds functions with no callers in the entire codebase
uv run lca reaper --index lca_index.json --format json > /tmp/dead_code.json
```

**What this catches**:
- Functions the brain documented but are actually dead (wasted documentation effort)
- Functions that were once used but are now orphaned
- This is NOT in the brain at all — the brain doesn't track dead code

---

### Step 7: Helper/Library/Core Audit

**Question**: What business logic lives outside controllers and models?

**Method**:
```bash
# List all helper files and their function count
for f in admin/application/helpers/*.php; do
  echo "$f: $(grep -c 'function ' $f) functions"
done

# List all library files
for f in admin/application/libraries/*.php; do
  echo "$f: $(grep -c 'function ' $f) functions"  
done

# List core overrides
for f in admin/application/core/*.php; do
  echo "$f: $(grep -c 'function ' $f) functions"
done
```

**What this catches**:
- Shared validation functions in helpers
- Custom session/auth libraries
- Core CI3 overrides that change framework behavior
- None of these have brain documents today

---

### Step 8: View Logic Audit

**Question**: How much business logic is embedded in views?

**Method**:
```bash
# Find views with significant PHP logic (more than just echo/foreach)
for dir in admin/application/views/*/; do
  count=$(grep -rl '\$this->\|if(\|switch\|foreach\|function ' "$dir" 2>/dev/null | wc -l)
  total=$(find "$dir" -name "*.php" | wc -l)
  echo "$dir: $count/$total files with PHP logic"
done
```

**What this catches**:
- Print templates with calculation logic (known issue — EST-026, BIL print vs save mismatch)
- Form views with inline validation
- Dashboard widgets with embedded queries

---

### Step 9: Code Changes Since Brain (Post-Build Delta)

**Question**: What code changed AFTER the brain was last updated (2026-03-26)?

**Method**:
```bash
# Find all files modified after 2026-03-26
git log --since="2026-03-26" --name-only --pretty=format: | sort -u > /tmp/changed_since_brain.txt

# These files may have NEW bugs, features, or behavior the brain doesn't know about
```

**What this catches**:
- New features added after brain build
- Bug fixes that changed behavior the brain documents
- New files (controllers, models) not in any brain

---

## Validation Report Template

After running all 9 steps, produce a report:

```markdown
# Brain Coverage Validation Report

## Summary
- Total PHP files: X
- Files covered by brain: Y (Z%)
- Uncovered files: A

## Function Coverage
- Total functions (LCA): X
- Functions in brain METHOD_INDEX: Y (Z%)
- Missing functions: A (list top 20)

## Table Coverage  
- Total tables referenced in code: X
- Tables in brain SCHEMA_ANALYSIS: Y (Z%)
- Missing tables: A (list all)

## Security Coverage
- Total LCA security findings: X
- Findings in DANGER_ZONES/VALIDATION_GAPS: Y (Z%)
- Undocumented findings: A (list all CRITICAL/HIGH)

## AJAX Coverage
- Total AJAX calls (LCA): X
- AJAX calls in brain: Y (Z%)
- Missing: A

## Dead Code
- Dead functions found: X
- Dead code LOC: Y
- % of codebase that is dead: Z%

## Helper/Library Gap
- Helper files not in any brain: X
- Total helper functions undocumented: Y
- Library files not in any brain: Z

## Post-Brain Changes
- Files changed since brain build: X
- New files added: Y
- Functions added/modified: Z
```

---

## Automation: One-Command Validation

All 9 steps can be scripted into a single validation command:

```bash
# Proposed: lca brain-validate
uv run lca brain-validate \
  --brain-dir knowledge_brain \
  --project-root . \
  --output brain_coverage_report.md
```

This command would:
1. Load the LCA index (already built)
2. Parse all brain documents
3. Cross-reference every function, table, AJAX call, security finding
4. Output a coverage report with gaps highlighted

**This command doesn't exist yet** — but the pieces are all there (LCA index + brain parser). Building it would take ~2-3 days and give permanent, repeatable coverage validation.

---

## Quick Manual Validation (Do This Now)

While the full automated pipeline is being built, here are 5 quick checks you can do immediately:

### Check 1: Count controllers vs brain modules
```bash
# How many active controllers exist?
ls admin/application/controllers/admin_*.php | wc -l

# How many module brains exist?
ls knowledge_brain/*/MODULE_BRAIN.md | wc -l
```
If controllers > brains, some controllers have no brain.

### Check 2: Check helpers coverage
```bash
# List all helpers — are ANY referenced in brain docs?
ls admin/application/helpers/
grep -rl "helpers/" knowledge_brain/ | head -5
```
If helpers aren't referenced, all shared utility logic is undocumented.

### Check 3: Check for files modified after brain
```bash
git log --since="2026-03-26" --name-only --pretty=format: \
  -- admin/application/ | sort -u | head -20
```
These files have changes the brain doesn't know about.

### Check 4: Check for new controllers/models
```bash
# Files created after brain build date
find admin/application/controllers -name "*.php" -newer knowledge_brain/_SYSTEM/SYSTEM_COVERAGE.md
find admin/application/models -name "*.php" -newer knowledge_brain/_SYSTEM/SYSTEM_COVERAGE.md
```

### Check 5: Run LCA health and compare against brain risks
```bash
uv run lca health admin/application/controllers --root .
# Compare findings count against DANGER_ZONES (57) + VALIDATION_GAPS (209)
# If LCA finds MORE, brain is incomplete
```

---

## Expected Outcome

Based on the architecture, I predict:

| Area | Brain Coverage | LCA Would Find |
|---|---|---|
| Controller functions | ~85-90% | The remaining 10-15% in private helpers and callbacks |
| Model functions | ~90-95% | Minor methods in large models |
| Table references | ~80-85% | Raw SQL in helpers, views, and concatenated strings |
| Security findings | ~70-80% | LCA regex catches more patterns than manual review |
| AJAX calls | ~85-90% | Dynamic URL construction in JS escapes manual review |
| Helper functions | **~0%** | Helpers have NO brain documents |
| Library functions | **~0%** | Libraries have NO brain documents |
| View logic | **~5%** | Only print vs save mismatch documented |
| Dead code | **~0%** | Brain doesn't track dead code at all |

**Bottom line**: The brain is ~80-85% complete for controllers/models, but has **zero coverage** for helpers, libraries, views, and dead code. Running LCA fills these gaps mechanically.
