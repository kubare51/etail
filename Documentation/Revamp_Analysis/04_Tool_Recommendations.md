# Tool Recommendations

> **Purpose**: What tools to use, what to skip, and why — filtered for this specific tech stack (CI3 PHP, MySQL, jQuery, 70+ clients, jewelry ERP).

---

## Decision Framework

Every tool recommendation below is evaluated against three criteria:
1. **Does it solve a problem we actually have?** (Not a hypothetical one)
2. **Does it work with our stack?** (CI3 PHP, jQuery, MySQL — not React, not microservices)
3. **Is the ROI worth the setup cost?** (Free tools need minimal ROI; paid tools need clear payback)

---

## Tier 1: Use Immediately — Already Have

### LCA (Logimax Code Analyzer)

| | |
|---|---|
| **What** | Custom static analysis toolkit — 25+ CLI commands, AST-based indexing, impact analysis, health scoring, dead code detection, security scanning, module brain auto-generation |
| **Why irreplaceable** | No off-the-shelf tool understands CI3 patterns. PHPStan doesn't know what `$this->db->get('ret_taging')` means. SonarQube doesn't know the module naming convention. LCA does — built specifically for this codebase structure. |
| **Unique capabilities** | `client-diff` (compare 70 clients), `brain` (auto-generate CI3-specific docs), `crossref` (map jQuery AJAX → PHP endpoints), `sql-map` (table → code mapping). No commercial tool offers these. |
| **Status** | Indexes pre-built (96MB SQLite, 58MB PHP, 16MB JS). Ready to use. |
| **Verdict** | **Single most valuable tool. Everything else supplements it.** |

### Playwright (E2E Test Suite)

| | |
|---|---|
| **What** | Browser automation framework. 26 tests covering billing, estimation, tagging, lot, stock issue, purchases, orders. |
| **Why** | Zero unit tests and CI3 makes unit testing painful (tight framework coupling). E2E tests are the only realistic regression safety net. Submits a bill through UI and verifies the database — that's the test that matters. |
| **Why not Cypress** | JavaScript-only, worse at multi-tab/iframe scenarios. The ERP has popup windows. |
| **Why not Selenium** | Slower, more fragile, worse debugging tools. |
| **Action** | Expand from 26 to ~50 tests covering the 12 flow checklists. |
| **Verdict** | **Keep. Expand coverage.** |

---

## Tier 2: Install This Week — Free, High Impact

### PHPStan (Static Type Analyzer)

| | |
|---|---|
| **What** | Catches type errors, undefined variables, wrong argument counts, dead code paths — at compile time, before anything runs. |
| **Why** | Codebase has functions returning `array` sometimes and `false` other times. Models that return `$query->result()` in one branch and `null` in another. Controllers passing 4 args to a function expecting 5. PHPStan catches all of this without running the code. |
| **Start at Level 1** | Level 1 catches obvious errors only. Codebase will have thousands of Level 5+ violations — don't want that noise yet. Fix Level 1, move to 2. |
| **Why not Psalm** | More powerful but harder to configure and slower. PHPStan has better CI3 community support and gentler learning curve. |
| **Install** | `composer require --dev phpstan/phpstan` (30 seconds) |
| **Cost** | Free |
| **Verdict** | **Install today. Start at Level 1. Catches real bugs before runtime.** |

### Rector (Automated PHP Refactoring)

| | |
|---|---|
| **What** | Reads PHP code, applies transformation rules, rewrites files automatically. |
| **Why game-changing** | Hundreds of files with deprecated PHP patterns, inconsistent style, legacy constructs. Rector batch-transforms: `array()` → `[]`, adds return type declarations, replaces `isset($x) ? $x : $default` → `$x ?? $default`, removes dead `else` after `return`, enforces strict `===`. |
| **Why it matters for revamp** | Need consistent code before safe refactoring. Rector normalizes everything first. Manual reformatting of 85 controllers = weeks. Rector = minutes. |
| **Why not just PHP CS Fixer** | CS Fixer only fixes formatting (whitespace, braces). Rector transforms code logic. Use both: Rector for logic, CS Fixer for formatting. |
| **Install** | `composer require --dev rector/rector` |
| **Cost** | Free |
| **Verdict** | **Install today. Run standardization rules across entire codebase.** |

### SchemaSpy (Database Documentation)

| | |
|---|---|
| **What** | Auto-generates interactive HTML database documentation from live MySQL schema. |
| **Why** | 200+ tables and no ERD. Nobody can visualize the full data model. SchemaSpy connects to MySQL, reads schema, detects foreign keys, counts rows, generates browsable HTML site. |
| **Why not dbdiagram.io** | Requires manual DDL input, doesn't auto-detect relationships. SchemaSpy is fully automated. |
| **Why not MySQL Workbench** | Produces static diagrams. SchemaSpy produces navigable HTML shareable with entire team. |
| **Install** | One Docker command |
| **Cost** | Free |
| **Verdict** | **Install this week. One command gives complete database documentation.** |

---

## Tier 3: Set Up This Month — Free/Cheap, Strategic

### Sentry (Error Tracking)

| | |
|---|---|
| **What** | Catches PHP errors, exceptions, warnings in production with full stack traces, request context, user info. |
| **Why** | Currently: client reports "billing page blank" → SSH into server → tail error log → find relevant line → guess context. Sentry captures automatically: full stack trace, POST data, user who triggered it, occurrence count. |
| **Why for 70 clients** | One Sentry project per client. Dashboard shows error rates across all clients. See which clients hit same bug. Prioritize by impact. |
| **Why not CloudWatch/Datadog/New Relic** | All overkill for this stage. Sentry does one thing — error tracking — and does it perfectly. |
| **PHP integration** | 3 lines of code |
| **Cost** | Free (5,000 events/month). Self-hosted option for unlimited. |
| **Verdict** | **Set up this month. Transforms bug triage from guesswork to data-driven.** |

### GitHub Actions (CI/CD Pipeline)

| | |
|---|---|
| **What** | CI/CD pipelines that run on every push/PR. |
| **Why** | Currently: code goes from laptop → `git push` → `deploy.sh` → production. No automated checks. With Actions, every PR automatically runs: `php -l` syntax check, PHPStan Level 1, LCA health score check, LCA security scan, Playwright smoke tests. |
| **Why this prevents regression** | #1 problem is "fixed X, broke Y." Automated checks on every PR catch breakage before it reaches any client. |
| **Why not Jenkins** | Server to maintain. |
| **Why not GitLab CI** | Already on GitHub. |
| **Why not AWS CodePipeline** | AWS lock-in for no benefit at this scale. |
| **Cost** | Free (2,000 min/month on private repos) |
| **Verdict** | **Set up this month. Every PR gets automated quality gates.** |

---

## Tier 4: Set Up During Revamp — Paid, Worth It

### AWS RDS (Managed MySQL)

| | |
|---|---|
| **What** | Same MySQL engine, AWS handles backups, patching, replication, failover. |
| **Why** | Clients run MySQL on VPS. No automated backups. No read replicas for reports. No point-in-time recovery. When disk fails, data is gone. RDS: automated daily backups, read replicas for heavy reports, automated patching, Multi-AZ for HA. |
| **Why during revamp** | Migration requires testing all queries against RDS. Do it when already touching database layer. |
| **Cost** | db.t3.medium = ~$50/month per client. Multi-tenant possible (one instance, multiple DBs). |
| **Verdict** | **During revamp. Critical for data safety.** |

### AWS CloudFront + S3 (Static Asset CDN)

| | |
|---|---|
| **What** | CDN for JS/CSS/images. |
| **Why** | Every page loads jQuery, Bootstrap, DataTables, Select2, AdminLTE from VPS. 20+ HTTP requests to a single-core server also running PHP. CloudFront serves from edge nodes globally — 40-60% page load improvement. |
| **Why during revamp** | Requires changing asset paths in all views. Easy during view refactoring; painful standalone. |
| **Cost** | ~$5-15/month per client |
| **Verdict** | **During frontend refactoring phase.** |

---

## What NOT To Use — And Why

| Tool | Why Not For This Project |
|---|---|
| **SonarQube** | LCA already covers security + smells + complexity for CI3. SonarQube's PHP rules are generic — doesn't understand CI3 active record, module naming, or business domain. Adds noise without insight. |
| **AWS Lambda** | App uses PHP sessions, shared state, long-running processes. Lambda's cold starts, 15-min timeout, stateless model are hostile to this architecture. Would require complete rewrite. |
| **Docker (production)** | deploy.sh works. Dockerizing 70 CI3 apps with Apache/PHP/MySQL adds ops complexity without benefit. Docker is useful for dev environments, not for running a PHP monolith. |
| **Kubernetes / ECS** | Not running microservices. Running a PHP app. A $10/month VPS handles it. K8s is massive overkill. |
| **DynamoDB** | Data is relational. Orders have line items, bills have tags, schemes have installments. DynamoDB makes JOINs impossible. |
| **GraphQL** | Frontend is jQuery + server-rendered HTML. GraphQL is for React/Vue SPAs that need flexible querying. AJAX calls are simple POST→JSON→DOM-update. REST is the right fit. |
| **Terraform / CloudFormation** | Only valuable if provisioning servers frequently. 5-10 new clients/year. setup-cicd.sh is sufficient. |
| **Microservices** | 36 shared tables. Billing calls tagging calls lot calls catalog. Splitting = distributed transactions, eventual consistency, 10x infrastructure complexity — zero user-facing benefit. |
| **Redis / ElastiCache** | Premature. Actual bottleneck is N+1 queries and unindexed MySQL — fix those first (LCA `n1` identifies them). Caching bad queries hides the problem. |
| **TypeScript retrofit** | Adding TypeScript to jQuery spaghetti doesn't fix architecture. If rebuilding frontend (Phase 3), use TypeScript from scratch. Don't retrofit. |

---

## Summary — The Recommended Stack

| Priority | Tool | Cost | Time to Value |
|---|---|---|---|
| Already have | **LCA** (Logimax Code Analyzer) | Free | Immediate |
| Already have | **Playwright** (26 E2E tests) | Free | Immediate |
| Install today | **PHPStan** | Free | 30 minutes |
| Install today | **Rector** | Free | 30 minutes |
| Install this week | **SchemaSpy** | Free | 1 hour |
| Set up this month | **Sentry** | Free (5K events) | 2 hours |
| Set up this month | **GitHub Actions** | Free (2K min) | 4 hours |
| During revamp | **AWS RDS** | ~$50/mo/client | 1-2 days |
| During revamp | **CloudFront + S3** | ~$10/mo/client | 1 day |

**Total cost for immediate stack: $0.** Paid services only during actual revamp deployment.
