# Existing Tools & Infrastructure Audit

> **Purpose**: What we already have that accelerates the revamp, and how etail_v3 (source) compares to client forks.

---

## 1. etail_v3 (Source) vs mpj (Client) — Key Differences

| Aspect | mpj (Client Fork) | etail_v3 (Source) |
|---|---|---|
| **CI Version** | CodeIgniter **3** | CodeIgniter **2.x** |
| **Controllers** | ~40 active + ~70 date-stamped backups | ~85 active (cleaner, fewer backups) |
| **Date-stamped copies** | ~150 backup files | ~5 backup files |
| **Tests** | Zero | **26 Playwright E2E tests** |
| **CI/CD** | Manual deploy | **Automated deploy.sh** + webhook + migration runner |
| **Migration system** | None | **SQL migration scripts** with `run-migrations.sh` |
| **DevTools** | None | **Full LCA toolchain** |
| **New features** | — | Print Designer, Invoice Builder, POS, KYC, Error Monitor, Regression Tests, Tag Parts, Emp Incentive, Reconstruct, Advanced Booking, IRN/eInvoice, PAN Verification, Stock Age Master |
| **Documentation** | knowledge_brain (28 modules) | knowledge_brain + CLAUDE.md + DEVELOPER_SETUP |

**Strategic implication**: etail_v3 is significantly ahead. The revamp should start from etail_v3, not mpj. Use `lca client-diff` to map mpj customizations and port only genuine client changes.

---

## 2. LCA — Logimax Code Analyzer (25+ Commands)

Location: `logimax-devtools/backend/lca_core/`

This is a **custom-built static analysis platform** for CI3 codebases. It is substantial — here's the full inventory:

### Core Analysis Commands

| Command | What It Does | Revamp Use |
|---|---|---|
| `lca index` | AST-based PHP/JS indexer — builds full call graph | Feature inventory extraction |
| `lca update` | Incremental reindex (changed files only) | Continuous tracking during revamp |
| `lca impact <function>` | Shows what breaks if you change a function | Safe refactoring |
| `lca search <name>` | Find functions by name across index | Fast code navigation |
| `lca crossref` | Maps JS→PHP AJAX calls | API contract extraction |
| `lca modules` | Module dependency analysis | Migration order planning |
| `lca reaper` | Detect dead code (zero callers) | Safe deletion list |

### Quality Analysis Commands

| Command | What It Does | Revamp Use |
|---|---|---|
| `lca health` | 0-100 score across 6 dimensions (A-F grade) | Before/after measurement |
| `lca report` | HTML dashboard with Chart.js charts | Visual progress tracking |
| `lca n1` | Detects N+1 query patterns (DB calls in loops) | Automatic performance issue detection |
| `lca hardcoded` | Finds magic numbers, URLs, IPs, dates | Automatic HARDCODED_VALUES generation |
| `lca smells` | God classes, long methods, deep nesting, high coupling | Priority list for refactoring |
| `lca duplicates` | Duplicated code blocks across files | Extract shared functions |
| `lca sql-map` | Maps table references ↔ PHP files | SHARED_TABLES verification |
| `lca quality` | Quick complexity overview | Fast assessment |
| `lca todo-scan` | Scan for TODO, FIXME, BUG, HACK comments | Track technical debt |
| `lca baseline save` | Save health snapshot for comparison | Track revamp progress |
| `lca diff-health` | Compare two health snapshots | Quantify improvement |

### Documentation Commands

| Command | What It Does | Revamp Use |
|---|---|---|
| `lca brain --module X` | Auto-generates 7 brain docs per module | Module brain generation in hours |
| `lca flowmap --module X` | Mermaid diagrams for any module | Visual documentation for onboarding |
| `lca client-diff` | Compare two client codebases at function level | Map mpj vs source differences |

### Workflow Commands

| Command | What It Does | Revamp Use |
|---|---|---|
| `lca changeset` | Structured changeset from git diff | PR automation |
| `lca risk` | Regression risk score per file | Prioritize testing |
| `lca pr` | Generate PR descriptions | Automated PR workflow |
| `lca release-notes` | Generate release notes from git history | Release management |
| `lca migration-check` | Scan for missing SQL mappings before DB changes | Schema safety |

### Testing & Infrastructure Commands

| Command | What It Does | Revamp Use |
|---|---|---|
| `lca gen-test` | Generate PHPUnit test skeleton | Automated test generation |
| `lca verify` | Run regression test suite | CI/CD integration |
| `lca test-coverage` | E2E test coverage analysis | Coverage tracking |
| `lca serve` | Start MCP server for AI integration | AI agents query codebase index |
| `lca watch` | Live file watcher with auto-reindex | Keep index fresh |
| `lca ui` | Launch LCA Cockpit web UI | Visual analysis dashboard |

### Health Score Dimensions

| Dimension | Weight | What It Measures |
|---|---|---|
| Complexity | 30% | Cyclomatic complexity of functions |
| Security | 25% | SQL injection, XSS, raw superglobals (8 SAST rules) |
| Dead Code | 15% | Functions with zero callers |
| N+1 Queries | 15% | DB calls inside loops |
| Hardcoded | 10% | Magic numbers, URLs, IPs |
| Code Smells | 5% | God classes, feature envy |

### Pre-Built Indexes (Already Generated)

| Index File | Size | Contents |
|---|---|---|
| `admin_app_index.json` | 58 MB | Full admin PHP index |
| `admin_js_index.json` | 16 MB | Full JavaScript index |
| `master_index.json` | 26 MB | Merged PHP + JS index |
| `lca_index.json` | 12 MB | JSON symbol index |

These indexes represent the full call graph, dependency map, and function inventory. No need to re-scan.

---

## 3. DevTools Dashboard (React + FastAPI)

Location: `logimax-devtools/`

| Component | Technology | Purpose |
|---|---|---|
| Frontend | React + TypeScript + Vite + Tailwind | Dashboard UI |
| Backend | FastAPI (Python) | API server |
| Architecture | Plugin-based | Extensible |
| Deployment | Docker Compose | One-command startup |

### Existing Plugins

| Plugin | Purpose |
|---|---|
| LCA Cockpit | Code analysis UI — visual health scores, call graphs, impact analysis |
| Test Runner | Execute and monitor tests from dashboard |
| Log Viewer | View and search application logs |

### Docker Setup

```bash
cd logimax-devtools
docker compose up -d          # Start all services
# Frontend: http://localhost:5173
# Backend:  http://localhost:8800
```

---

## 4. E2E Test Suite (Playwright)

Location: `tests/`

### Test Coverage

| Test File | Module | What It Tests |
|---|---|---|
| `test_billing.py` | Billing | Bill creation, save, cancel flows |
| `test_estimation.py` | Estimation | Estimation creation and conversion |
| `test_tagging.py` | Tagging | Tag creation and status updates |
| `test_lot_inward.py` | LOT | Lot inward processing |
| `test_stock_issue.py` | Stock Issue | Stock issue and receipt |
| `test_section_transfer.py` | Section Transfer | Inter-section transfers |
| `test_old_metal_process.py` | Old Metal | Old metal exchange |
| `test_customer_order.py` | Customer Order | Order placement and management |
| `test_purchase_po.py` | Purchase | Purchase order creation |
| `test_purchase_return.py` | Purchase | Purchase return processing |
| `test_grn_entry.py` | Purchase | Goods receipt note |
| `test_masters.py` | Masters | Master data CRUD (8+ sub-tests) |
| `test_login.py` | Auth | Login flow verification |
| `test_vendor.py` | Vendor | Vendor management |
| `test_approval_rate_fixing.py` | Purchase Approval | Rate fixing and approval |
| `test_approval_to_invoice.py` | Purchase Approval | Approval to invoice conversion |
| `test_debit_credit_entry.py` | Account | Journal entry creation |
| `test_repair_order.py` | Repair | Repair order lifecycle |
| `test_hm_issue_receipt.py` | Hallmarking | Issue and receipt |
| `test_karigar_allotment.py` | Manufacturing | Smith/karigar allotment |
| `test_qc_issue_receipt.py` | Quality Control | QC issue and receipt |
| `test_smith_company_op_bal.py` | Manufacturing | Opening balance |
| `test_smith_metal_issue.py` | Manufacturing | Metal issue to smith |
| `test_smith_supplier_payment.py` | Manufacturing | Supplier payment |
| `test_supplier_bill_entry.py` | Purchase | Supplier bill entry |

### Infrastructure Features

- **Session-scoped auth**: Login once, reuse across all tests
- **Screenshot on failure**: Automatic screenshot capture
- **Video recording**: Record test runs for debugging
- **Trace capture**: Playwright traces for failure analysis
- **Database verification**: Direct MySQL connection for data assertions
- **YAML test data**: Data-driven test cases per module
- **HTML reports**: Rich failure reports with screenshots

### Running Tests

```bash
cd tests
pip install -r requirements.txt
pytest tests/ --html=reports/results.html -v
```

---

## 5. CI/CD Pipeline

### Deployment (`deploy.sh`)

| Feature | Description |
|---|---|
| Auto-detect environment | Reads folder name (dev/qa/staging/prod) |
| Branch mapping | Default branch per environment |
| Lock file | Prevents concurrent deployments |
| SSH key management | Client-specific or generic SSH keys |
| Migration runner | Auto-runs numbered SQL migration files |
| Cache clearing | Clears CI cache after deploy |
| Permission fixing | Sets www-data ownership |
| Deploy logging | Structured log with timestamp, commit, author |
| Port_Max detection | Auto-deploys frontend if port_max files changed |

### Migration System

Location: `migration/retail/`

- Numbered SQL files (001, 002, 003...)
- `scripts/run-migrations.sh` — Tracks applied migrations, runs pending ones
- `scripts/seed-migrations.sh` — Seed initial migration state

### CI/CD Setup

| Script | Purpose |
|---|---|
| `scripts/setup-cicd.sh` | Linux server CI/CD setup |
| `scripts/setup-cicd.ps1` | Windows server CI/CD setup |
| `scripts/deploy-pr.sh` | Deploy from PR branch |
| `scripts/pre-review.sh` | Pre-merge review checks |

---

## 6. Other Analysis Tools

| Tool | Location | Purpose | Size |
|---|---|---|---|
| `fingerprint.php` | `AI_Bug_Fix_System/fingerprint/` | Compare source vs client at function level | — |
| `smoke_tests.py` | `lca_core/` | Smoke test logic | 30 KB |
| `security_scanner.py` | `lca_core/` | 8-rule SAST security scanner | 12.5 KB |
| `api_mapper.py` | `lca_core/` | Maps all REST API endpoints | 8.6 KB |
| `regression_test.php` | `controllers/` | In-app regression test controller | 17.7 KB |
| `test_data_gen.php` | `controllers/` | Test data generator | 15.8 KB |
| `error_monitor.php` | `controllers/` | Runtime error monitoring | 8.1 KB |

---

## 7. Cloud Infrastructure, Version Control & AI IDEs

To maximize leverage and eliminate redundant expenditures, the ERP revamp will run on and utilize the following existing assets already paid for and active in the developer environment:

### AI Development IDE: Antigravity Ultra Plan
*   **Availability**: Active for all developers.
*   **Capability**: Multi-file code generation, workspace-wide symbol indexing, context-aware PHP-to-TypeScript translations, shell execution for local build/linters, and visual verification via browser subagents (critical for React/Flutter).
*   **Revamp Utility**: Replaces the need for external AI IDEs (e.g. Cursor). Serves as the primary driver for manual controller/model conversion.

### Infrastructure: AWS EC2 & RDS (Aurora MySQL / Managed Databases)
*   **Availability**: Existing AWS footprint.
*   **Compute (EC2)**: Can be leveraged to host NestJS APIs, Python FastAPI services, and the Next.js frontend (either as node servers or as static builds behind Nginx).
*   **Database (RDS)**: Reused to host the new revamped schemas alongside legacy client databases, avoiding new managed database service fees.

### Version Control & CI/CD: GitHub
*   **Availability**: Existing organization repository hosting.
*   **CI/CD (GitHub Actions)**: Will drive build testing, linting (PHPStan/Rector), and deploy automation to EC2.

---

## 8. Key Takeaway

> **80% of the analysis and development infrastructure is already active and paid for.** By utilizing the existing **Antigravity Ultra** IDEs, **AWS EC2/RDS** infrastructure, and **GitHub** pipelines, the net new software licensing costs for the revamp drop from ~$1,000/month to **~$100-360/month** (representing only specialized UI tools like v0.dev and FlutterFlow). The technical stack is already set up to deploy and scale without friction.

