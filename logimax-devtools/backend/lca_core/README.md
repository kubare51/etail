# Logimax Code Analyzer (LCA) v2

> Unified static analysis, code quality, and developer tooling for CodeIgniter 3 codebases.

## Quick Start

```bash
cd logimax-devtools/backend/lca_core

# Install (first time)
pip install uv          # if not already installed
uv sync

# Run any command
uv run lca --help
uv run lca health admin/application/controllers --root .
```

---

## Commands Reference

### Core Analysis

| Command | Description | Example |
|---|---|---|
| `index` | Index a file or directory for dependency mapping | `lca index admin/application/controllers` |
| `update` | Incremental reindex (only changed files) | `lca update` |
| `impact` | Analyze impact of changes to a function | `lca impact save_bill --index lca_index.json` |
| `search` | Search functions by name | `lca search billing --index lca_index.json` |
| `crossref` | Extract JS->PHP API calls | `lca crossref admin/assets/js --root .` |
| `modules` | Module dependency analysis | `lca modules admin/application/controllers` |
| `reaper` | Detect dead code (zero callers) | `lca reaper --index lca_index.json` |

### Quality Analysis

| Command | Description | Example |
|---|---|---|
| `n1` | N+1 query patterns (DB calls in loops) | `lca n1 admin/application/models --root .` |
| `hardcoded` | Magic numbers, URLs, IPs, dates | `lca hardcoded admin/application/controllers` |
| `health` | Unified 0-100 health score with grade | `lca health admin/application/controllers --root .` |
| `report` | HTML health report with charts | `lca report admin/application/controllers --root . -o report.html` |
| `quality` | Quick complexity overview | `lca quality admin/application/controllers` |
| `smells` | Code smells (god classes, long methods) | `lca smells --index lca_index.json` |
| `duplicates` | Find duplicated code blocks across files | `lca duplicates admin/application/controllers` |
| `todo-scan` | Scan for TODO, FIXME, BUG, HACK comments | `lca todo-scan admin/application/controllers` |
| `sql-map` | Map table references back to PHP files | `lca sql-map admin/application/models` |
| `baseline` | Save and diff health baselines | `lca baseline save v1.0.0 --input health.json` |
| `diff-health` | Compare two health JSON snapshots | `lca diff-health old.json new.json` |

### Workflow Tools

| Command | Description | Example |
|---|---|---|
| `changeset` | Create structured changeset from git diff | `lca changeset . --format json` |
| `pr` | Generate PR description from git diff | `lca pr . --template default` |
| `risk` | Regression risk score per file | `lca risk . --branch` |
| `migration-check` | Scan for missing SQL mappings before DB changes | `lca migration-check .` |
| `release-notes` | Generate release notes from git history | `lca release-notes .` |

### Documentation Tools

| Command | Description | Example |
|---|---|---|
| `brain` | Auto-generate Module Brain (7 docs) | `lca brain . --module billing` |
| `flowmap` | Generate Mermaid flow diagrams | `lca flowmap . --module billing` |
| `client-diff` | Compare two client codebases | `lca client-diff --base /client_A --target /client_B` |

### Testing & DevOps

| Command | Description | Example |
|---|---|---|
| `gen-test` | Generate PHPUnit test skeleton | `lca gen-test admin/application/models/billing_model.php` |
| `verify` | Run regression test suite | `lca verify` |
| `test-coverage` | E2E test coverage analysis | `lca test-coverage tests/` |

### Infrastructure

| Command | Description | Example |
|---|---|---|
| `serve` | Start MCP server for AI integration | `lca serve` |
| `watch` | Live file watcher with auto-reindex | `lca watch admin/application` |
| `ui` | Launch LCA Cockpit web UI | `lca ui` |

---

## Detailed Usage

### Health Score

Computes a 0-100 score with A-F grade across 6 dimensions:

```bash
# Single directory
uv run lca health admin/application/controllers --root C:\xampp\htdocs\project

# Generate HTML report
uv run lca report admin/application/controllers --root . -o health_report.html
```

**Score dimensions:**
| Dimension | Weight | What it measures |
|---|---|---|
| Complexity | 30% | Cyclomatic complexity of functions |
| Security | 25% | SQL injection, XSS, raw superglobals |
| Dead Code | 15% | Functions with zero callers |
| N+1 Queries | 15% | DB calls inside loops |
| Hardcoded | 10% | Magic numbers, URLs, IPs |
| Code Smells | 5% | God classes, feature envy |

### N+1 Detection

Finds database calls inside loops — a common performance anti-pattern:

```bash
uv run lca n1 admin/application/models --root .
```

Output shows severity (CRITICAL/HIGH/MEDIUM), function name, loop type, and DB call line.

### Module Brain Generator

Auto-generates 7 documentation files for any module:

```bash
uv run lca brain . --module billing
# Creates: CONTROLLER_MAP.md, MODEL_MAP.md, AJAX_MAP.md,
#          VIEW_MAP.md, SHARED_DEPENDENCIES.md, MODULE_BRAIN.md, DATA_FLOW.md
```

**Output directory:** `knowledge_brain/{module}/_auto/` (configurable with `--output`)

### Regression Risk

Scores files by regression risk using churn, complexity, and context:

```bash
# Analyze current branch changes
uv run lca risk . --branch --base PRODUCTION

# Analyze specific files
uv run lca risk . --files "admin/application/models/billing_model.php,admin/application/controllers/billing.php"
```

**Risk factors:**
| Factor | Max Points | Threshold |
|---|---|---|
| Git churn (3 months) | 30 | 30+ commits = max |
| Author diversity | 15 | 5+ authors = max |
| Cyclomatic complexity | 25 | CC >= 100 = max |
| Financial module | 15 | billing, estimation, payment |
| Shared model/helper | 15 | common_model, helpers/ |

**Classification:** CRITICAL (70+), HIGH (50-69), MEDIUM (30-49), LOW (0-29)

### Cross-Client Diff

Compare two CI3 client codebases at the function level:

```bash
uv run lca client-diff --base C:\xampp\htdocs\client_A --target C:\xampp\htdocs\client_B
uv run lca client-diff --base /client_A --target /client_B --module billing -o diff_report.md
```

Shows added/removed/modified functions with similarity percentage.

### Flow Maps

Generate Mermaid diagrams for developer onboarding:

```bash
uv run lca flowmap . --module billing
```

Produces:
1. **Module Overview** — Controllers -> Models -> Tables
2. **AJAX Dependency Map** — JS functions -> Controller methods
3. **Per-Endpoint Flows** — Entry -> model calls -> DB reads/writes -> views

### MCP Server (AI Integration)

Start the MCP server for use with AI coding agents:

```bash
uv run lca serve
```

**Available MCP tools:**
| Tool | What it does |
|---|---|
| `get_function_info` | Get function details from index |
| `get_impact` | Analyze function change impact |
| `find_callers` | Find all callers of a function |
| `get_dead_code` | Get dead code report |
| `search_functions` | Search by name pattern |
| `get_dependencies` | Get function dependencies |
| `scan_n1` | Scan for N+1 patterns |
| `scan_hardcoded` | Scan for hardcoded values |
| `get_health_score` | Compute health score |
| `get_changeset` | Create changeset manifest |

---

## Architecture

```
lca_core/
├── lca/                       # Main package (pip-installable)
│   ├── cli.py                 # 25 CLI commands (Typer)
│   ├── mcp_server.py          # MCP tools for AI agents
│   ├── watcher.py             # Live file watcher
│   │
│   ├── core/                  # Indexer + impact analysis
│   │   ├── indexer.py         # AST-based PHP/JS indexer
│   │   └── impact.py          # Change impact analyzer
│   │
│   ├── parsers/               # Language parsers
│   │   ├── php_parser.py      # PHP AST parser
│   │   ├── js_parser.py       # JS AST parser
│   │   └── crossref.py        # Cross-language reference mapper
│   │
│   ├── analysis/              # All analysis engines
│   │   ├── metrics.py         # Cyclomatic complexity
│   │   ├── security.py        # SAST (12 rules)
│   │   ├── intelligence.py    # Dead code, duplicates, smells, churn, API docs
│   │   ├── n1.py              # N+1 query pattern detector
│   │   ├── hardcoded.py       # Magic value scanner (8 rules)
│   │   ├── health.py          # Unified 0-100 scoring + trends
│   │   └── test_coverage.py   # E2E test coverage analysis
│   │
│   ├── reporting/             # Report generators
│   │   └── html.py            # Dark-theme HTML with Chart.js
│   │
│   ├── workflow/              # Dev workflow tools
│   │   ├── changeset.py       # Git diff -> structured manifest
│   │   ├── pr.py              # PR body + release notes
│   │   └── regression.py      # Regression risk scorer
│   │
│   ├── tools/                 # Standalone tools
│   │   ├── brain.py           # Auto Module Brain (7 docs)
│   │   ├── flowmap.py         # Mermaid flow diagram generator
│   │   ├── client_diff.py     # Cross-client codebase comparison
│   │   ├── module_mapper.py   # Module architecture mapper
│   │   ├── reaper.py          # Dead code detector
│   │   └── testgen.py         # PHPUnit test generator
│   │
│   ├── storage/               # Data persistence
│   │   ├── json_store.py      # JSON index storage
│   │   └── sqlite_store.py    # SQLite fullstack index
│   │
│   └── cockpit/               # Web UI
│       ├── app.py             # FastAPI server
│       └── static/            # Frontend assets
│
├── pyproject.toml             # Package config + dependencies
├── uv.lock                    # Locked dependencies
└── README.md                  # This file
```

## Dependencies

Core dependencies (auto-installed via `uv sync`):

| Package | Purpose |
|---|---|
| `typer` + `rich` | CLI framework with rich terminal output |
| `mcp[cli]` | Model Context Protocol server |
| `watchdog` | File system watcher |
| `fastapi` + `uvicorn` | Cockpit web UI server |
| `chart.js` (CDN) | Charts in HTML reports |

## Environment

- **Python**: 3.10+ (tested on 3.12)
- **OS**: Windows, Linux, macOS
- **Target**: CodeIgniter 3 projects

## Troubleshooting

### Unicode errors on Windows

If you see `UnicodeEncodeError` on Windows terminals, the tool handles this internally. All stdout output uses ASCII-safe characters.

### MCP server won't start

The MCP server uses stdio transport. Don't print anything to stdout before the server handshake. All logs go to stderr.

### Large codebase scans are slow

For full-project scans (e.g., `lca n1 models/`), expect 30-60s for large codebases. Use `--root` to limit scope.

### Index is stale

Run `uv run lca update` to incrementally reindex changed files, or `uv run lca index` to do a full reindex.
