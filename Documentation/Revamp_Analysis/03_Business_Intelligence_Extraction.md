# Business Intelligence Extraction Plan

> **Purpose**: How to extract every business rule, feature, data flow, and configuration from the codebase — systematically.
> **Management question answered**: "Have we analyzed every part of the code?"

---

## The 10 Deliverables

| # | Deliverable | Answers The Question | Extraction Layer |
|---|---|---|---|
| 1 | **Complete Feature Catalog** | "What does this system do?" | Tool (LCA) |
| 2 | **Business Rules Repository** | "What are the rules embedded in code?" | AI-Assisted |
| 3 | **Data Dictionary + ERD** | "What does the data mean?" | Database |
| 4 | **Configuration Matrix** | "What do the 150+ settings control?" | AI + Database |
| 5 | **Integration Map** | "What external systems do we depend on?" | Tool + AI |
| 6 | **Permission & Role Matrix** | "Who can do what?" | AI + Database |
| 7 | **Revenue Flow Mapping** | "How does money flow through the system?" | AI |
| 8 | **Code Health & Risk Heatmap** | "Where are the biggest risks?" | Tool (LCA) |
| 9 | **Dead Feature Analysis** | "What did we build that nobody uses?" | Tool + Database |
| 10 | **Client Customization Registry** | "How do 70 clients differ?" | Tool (LCA) |

---

## Layer 1: Automated Tool-Based Extraction (LCA)

Zero human interpretation needed — run commands, collect results.

### D1. Complete Feature Catalog

**What**: Every endpoint, form, report, print view, and AJAX action in the system.

**Commands**:
```bash
cd logimax-devtools/backend/lca_core

# Auto-generate module brains for ALL 28 modules
# Each produces 7 documents: CONTROLLER_MAP, MODEL_MAP, AJAX_MAP, 
# VIEW_MAP, SHARED_DEPENDENCIES, MODULE_BRAIN, DATA_FLOW
uv run lca brain . --module billing
uv run lca brain . --module tagging
uv run lca brain . --module estimation
# (repeat for all 28 modules)

# Extract all AJAX cross-references (JS → PHP)
uv run lca crossref admin/assets/js --root .

# Generate flow diagrams for all modules
uv run lca flowmap . --module billing
# (repeat for all modules)
```

**Output per module**:
- CONTROLLER_MAP.md — Every endpoint: Page, AJAX, Action. Params, model calls, line numbers.
- MODEL_MAP.md — Every model method: visibility, return type, tables touched (READ/WRITE).
- AJAX_MAP.md — Every JS→PHP AJAX call: URL, controller, method, JS function name.
- VIEW_MAP.md — Every view file and which controller methods load it.
- DATA_FLOW.md — Call chains: Entry point → model calls → DB reads/writes → views.
- SHARED_DEPENDENCIES.md — Tables shared with other modules, operations, functions.
- MODULE_BRAIN.md — Overview with method index and cross-references.

**Time**: ~4-6 hours automated run, ~2 hours to review.

---

### D8. Code Health & Risk Heatmap

**What**: Quantified health score for every file and module.

**Commands**:
```bash
# Full health scan — 6 dimensions, 0-100 score per file
uv run lca health admin/application/controllers --root .
uv run lca health admin/application/models --root .

# HTML report with charts
uv run lca report admin/application --root . -o full_health_report.html

# N+1 query detection
uv run lca n1 admin/application/models --root .

# Code smells: god classes, long methods, deep nesting
uv run lca smells --index lca_index.json

# Duplicate code detection
uv run lca duplicates admin/application/controllers

# Git churn × complexity = bug magnet score
uv run lca risk . --branch --base PRODUCTION

# SQL-to-code mapping
uv run lca sql-map admin/application/models

# Save baseline for future comparison
uv run lca baseline save v_before_revamp --input health.json
```

**Output**:
- Health Scorecard: 0-100 per file, graded A-F
- Risk Heatmap: Color-coded by regression risk
- Bug Magnet List: Top 20 files most likely to contain bugs
- Security Report: All SQLi, XSS, superglobal findings
- Duplication Report: Clone pairs with % similarity

**Time**: ~2-3 hours.

---

### D9. Dead Feature Analysis (Automated)

```bash
# Dead code — functions with zero callers
uv run lca reaper --index lca_index.json

# Abandoned work — TODO/FIXME/BUG/HACK comments
uv run lca todo-scan admin/application/controllers
```

**Time**: ~1 hour.

---

### D10. Client Customization Registry

```bash
# Compare source vs any client at function level
uv run lca client-diff --base . --target C:\xampp\htdocs\mpj

# Module-specific diff
uv run lca client-diff --base . --target C:\xampp\htdocs\mpj --module billing -o mpj_billing_diff.md
```

**Output**: Added/removed/modified functions per module with % similarity.

**Time**: ~1 hour per client (scriptable for batch runs).

---

## Layer 2: AI-Assisted Analysis

AI reads code and extracts meaning — not just pattern matching.

### D2. Business Rules Repository

**What**: Every business rule embedded in code, stated in plain English.

**Process**:
1. For each module, feed model file + MODULE_BRAIN.md to AI
2. AI extracts conditionals, validations, calculations, state transitions
3. Output: Plain-English rule statements with code references

**Example output**:
```
BR-BIL-001: A sales bill (bill_type=1) requires at least one tagged item.
  Source: ret_billing_model.php::save_bill() L245
  
BR-BIL-002: GST is calculated as CGST+SGST when customer state matches 
  branch state, otherwise IGST is applied.
  Source: ret_billing_model.php::calculate_gst() L890
  
BR-BIL-003: Bills above ₹2,00,000 cash require PAN card number.
  Source: admin_ret_billing.php::validate_billing() L1205
  
BR-BIL-004: Tag sold in bill → tag_status changes from 0 to bill_type.
  Tag cannot be sold if tag_status != 0.
  Source: ret_billing_model.php::update_tag_status() L1567
```

**Scaling options**:
- **Manual AI**: Read each model, extract rules. ~2-4 hours/module × 28 = 56-112 hours
- **Semi-automated**: Feed LCA MODEL_MAP to AI batch. ~30 min/module × 28 = 14 hours
- **AWS Bedrock batch**: Upload all models, batch process with Claude Haiku. ~2 hours for $5-10

**Time**: 2-14 hours depending on approach.

---

### D4. Configuration Matrix

**Two configuration sources**:
1. `chit_settings` table — Single row, 60+ columns, controls ALL modules
2. `ret_settings` table — 90+ key-value pairs, controls retail modules

**Process**:
1. Query DB for all setting names and current values
2. Grep codebase for all references to each setting
3. AI interprets each setting's business effect

**Example output**:
```
SETTING: validate_cash_amt (ret_settings)
  Current Value: 0 (DISABLED)
  Referenced By: admin_ret_billing.php (L1205), billing.js (L445)
  Business Effect: Enforces ₹2,00,000 cash limit per RBI compliance.
  Risk: COMPLIANCE — currently disabled.
  
SETTING: enable_estimation_hidden_fields (chit_settings)  
  Current Value: 1 (ENABLED)
  Referenced By: admin_ret_estimation.php, estimation/add_estimation.php
  Business Effect: Controls visibility of 28 estimation form fields.
```

**Time**: ~8-12 hours.

---

### D5. Integration Map

**External systems identified by pattern**:

| Pattern | Integration |
|---|---|
| `curl_init` / `curl_exec` | External HTTP calls |
| `sendSMS_*` | SMS gateways (Msg91, NettyFish, SpearUC) |
| `send_whatsApp` | WhatsApp API (QikChat) |
| `postcurlPOSRequests` | POS device integration (Pinelab) |
| `eInvoice` / `IRN` | GST e-Invoice API (NIC/TaxPro) |
| `ZOOP` | Aadhaar verification |
| `Cashfree` / `Razorpay` | Payment gateway |
| `OneSignal` | Push notifications |
| `SMTP` / `send_email` | Email gateway |

**Time**: ~4-6 hours.

---

### D6. Permission & Role Matrix

**Data sources**: `profile` table (role definitions), `get_access($url)` calls in controllers.

**Output**: Matrix format — Role × Feature → Allowed/Denied.

**Time**: ~6-8 hours.

---

### D7. Revenue Flow Mapping

**Traces financial data flow**:
```
Customer → Estimation → Billing (bill_type=1)
  ├── Cash Payment → payment table → receipt
  ├── Card/POS Payment → POS device → payment table
  ├── Scheme Utilization → scheme_account.utilized_amt
  ├── Old Metal Deduction → old_metal_process → bill adjustment
  ├── Advance Adjustment → ret_issue_receipt → ret_advance_utilized
  └── Credit Sale → receivables → credit_collection (bill_type=8)

Each path → Journal Entry (debit + credit) → Ledger → Reports
```

**Time**: ~4-6 hours.

---

## Layer 3: Database Mining

### D3. Data Dictionary + ERD

**Tool**: SchemaSpy (one Docker command)

```bash
docker run -v ./docs/schema:/output \
  schemaspy/schemaspy \
  -t mysql \
  -host 127.0.0.1 -port 3306 \
  -db retail_dev -u root -p "" \
  -s retail_dev
```

**Output**: Interactive HTML with table list, column definitions, FK relationships, row counts, visual ERD.

**AI enrichment adds**: Business meaning for each table and column.

**Time**: ~4-6 hours (SchemaSpy = 30 min, AI enrichment = 4-6 hours).

---

### D9. Dead Feature Analysis (Database Part)

**SQL queries against production**:
```sql
-- Tables with zero rows
SELECT table_name, table_rows FROM information_schema.tables 
WHERE table_schema = 'retail_db' AND table_rows = 0;

-- Bill types never created
SELECT DISTINCT bill_type FROM ret_billing;
-- Compare against all 14 defined types

-- Settings all disabled
SELECT name, value FROM ret_settings WHERE value = '0';

-- Payment modes never used
SELECT DISTINCT payment_mode FROM payment;
```

**Time**: ~2-3 hours.

---

## Layer 4: Runtime Analysis (Optional)

### Lightweight Access Logger

Add a single middleware (3 lines) that logs endpoint hits. After 30 days:
- Top 20 most-used features
- Features with zero hits (dead features)
- Peak usage times
- Per-role usage patterns

**Time**: ~2 hours to implement, 30 days to collect data.

---

## Execution Plan

| Week | Phase | Deliverables | Effort |
|---|---|---|---|
| **Week 1** | Automated Scans | D1 (Feature Catalog), D8 (Health), D9 (Dead Code) | 2-3 days |
| **Week 1** | Database Docs | D3 (Data Dictionary + ERD) | 1 day |
| **Week 1** | Client Diff | D10 (Customization — mpj first) | 1 day |
| **Week 2** | AI Analysis | D2 (Business Rules — top 10 modules) | 3-4 days |
| **Week 2** | AI Analysis | D4 (Configuration Matrix) | 2 days |
| **Week 3** | AI Analysis | D5 (Integration Map), D6 (Permission Matrix) | 3 days |
| **Week 3** | AI Analysis | D7 (Revenue Flow Mapping) | 2 days |
| **Week 4** | Synthesis | Package all deliverables into management report | 2-3 days |

**Total: ~4 weeks for comprehensive BI extraction.**

---

## Management Presentation Format

### Executive Summary (1 page)
- System size: X modules, Y endpoints, Z tables
- Health grade: Overall score/100
- Risk areas: Critical / High / Medium counts
- Dead code: X% removable
- Security: Y critical vulnerabilities

### Module Portfolio (per module)
- Feature count, complexity score, health grade
- Business rules count, integration dependencies
- Risk level, modernization priority

### Risk Register
- Top 20 danger zones by business impact
- Security vulnerabilities with compliance implications
- Performance bottlenecks with user-facing impact

### Modernization ROI
- Dead code removal = X% codebase reduction
- Duplicate consolidation = Y fewer maintenance points
- Security fixes = Z compliance risks eliminated
