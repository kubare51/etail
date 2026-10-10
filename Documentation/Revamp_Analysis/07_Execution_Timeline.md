# Execution Timeline

> **Purpose**: Week-by-week execution plan with milestones, dependencies, and resource requirements.
> **Approach**: Strategy C+D Combined — Module-by-module modernization with API layer.

---

## Timeline Overview

```
Month 1    ████████ Phase 0: Security + BI Extraction
Month 2-3  ████████████████ Phase 1: Foundation 
Month 4-5  ████████████████ Phase 2a: Core Modules (Wave 1-2)
Month 6-8  ████████████████████████ Phase 2b: Business Modules (Wave 3-4)
Month 9-10 ████████████████ Phase 2c: Consumers (Wave 5-6)
Month 11-12 ████████████████ Phase 2d: Support (Wave 7) + Phase 3 Start
Month 13-16 ████████████████████████████████ Phase 3: Frontend + Phase 4: Infrastructure
```

**Total estimated duration: 12-16 months** (accelerated from 18-24 using existing tools)

---

## Month 1: Phase 0 — Security & Business Intelligence

### Week 1: Critical Security Fixes + Automated Analysis

| Day | Task | Tool | Deliverable |
|---|---|---|---|
| Mon | Remove hardcoded OTP, fix OTP `===`, add .htaccess to logs | Manual fix | SEC-001, 002, 008 fixed |
| Mon | DROP payment.cvv, mask card_no | SQL migration | SEC-005, 006 fixed (PCI) |
| Tue | Fix get_access() SQLi, clear_database() auth | Manual fix | SEC-003, 009 fixed |
| Tue | Run LCA health scan (controllers + models) | `lca health + report` | D8: Health Heatmap |
| Wed | Run LCA dead code detection | `lca reaper` | D9: Dead Code List |
| Wed | Run LCA duplicate detection | `lca duplicates` | Duplicate consolidation targets |
| Thu | Run LCA security scan | `lca health` (security dimension) | Security findings report |
| Thu | Run LCA N+1 detection | `lca n1` | Performance fix list |
| Fri | Install SchemaSpy, generate DB docs | Docker | D3: Data Dictionary + ERD |

**Milestone: All P0 security fixes deployed. Full codebase health baseline captured.**

### Week 2: Module Brain Generation + Client Diff

| Day | Task | Tool | Deliverable |
|---|---|---|---|
| Mon-Tue | Auto-generate brains for all 28 modules | `lca brain --module X` | D1: Feature Catalog (all modules) |
| Wed | Generate flow maps for critical modules | `lca flowmap --module X` | Flow diagrams for billing, tagging, estimation |
| Thu | Run client-diff (mpj vs source) | `lca client-diff` | D10: Client Customization Registry |
| Fri | Fix LFI, base64 passwords → bcrypt (start) | Manual fix | SEC-007, 010 in progress |

**Milestone: Complete feature catalog across all modules. mpj customizations mapped.**

### Week 3-4: AI-Assisted Analysis

| Day | Task | Tool | Deliverable |
|---|---|---|---|
| W3 Mon-Wed | Business rules extraction (Billing, Tagging, Estimation) | AI + MODEL_MAP | D2: Business Rules (top 3 modules) |
| W3 Thu-Fri | Configuration matrix (ret_settings + chit_settings) | AI + DB queries | D4: Configuration Matrix |
| W4 Mon-Tue | Business rules extraction (remaining priority modules) | AI + MODEL_MAP | D2: Business Rules (all modules) |
| W4 Wed | Integration map | Grep + AI | D5: Integration Map |
| W4 Thu | Permission/role matrix | DB + code analysis | D6: Permission Matrix |
| W4 Fri | Revenue flow mapping | AI + DATA_FLOW_CHAINS | D7: Revenue Flow Map |

**Milestone: All 10 BI deliverables complete. Management presentation ready.**

---

## Month 2-3: Phase 1 — Foundation

### Week 5-6: Tag Status State Machine

| Task | Effort | Dependencies |
|---|---|---|
| Design state machine (valid transitions: 0→1→2, 0→3, etc.) | 2 days | TAG_STATUS_MAP.md review |
| Implement `TagStatusGuard` model with validation | 3 days | None |
| Replace all direct tag_status writes in 10+ modules | 5 days | TagStatusGuard model |
| Add row-level locking for concurrent tag operations | 2 days | TagStatusGuard model |
| Write E2E tests for tag lifecycle | 2 days | Playwright suite |
| Deploy and monitor | 1 day | All above |

**Milestone: Tag status corruption impossible. Central state machine enforced.**

### Week 7-8: Transaction & Reversal Audit

| Task | Effort |
|---|---|
| Audit all multi-table operations for transaction wrapping | 3 days |
| Add `trans_begin/trans_complete` to all identified gaps | 3 days |
| Audit all cancel operations against CLEANUP_GAPS.md | 3 days |
| Fix incomplete reversals (26 gaps) | 5 days |

**Milestone: All cancel operations properly reverse all affected tables.**

### Week 9-10: Input Validation Layer

| Task | Effort |
|---|---|
| Build centralized validation helper (reusable across controllers) | 3 days |
| Apply to billing module (highest risk) | 3 days |
| Apply to tagging module | 2 days |
| Apply to estimation module | 2 days |
| Apply to remaining modules (batch) | 5 days |

**Milestone: Server-side validation on all critical inputs.**

### Week 11-12: Tooling & Standards

| Task | Effort |
|---|---|
| Install PHPStan, fix Level 1 errors | 2 days |
| Install Rector, run standardization rules | 1 day |
| Set up GitHub Actions (syntax + PHPStan + LCA health gate) | 2 days |
| Set up Sentry for 3 pilot clients | 1 day |
| Delete date-stamped backup files | 1 day |
| Establish git branching strategy | 1 day |
| Standardize error handling (replace echo exit) | 3 days |
| Expand Playwright tests to ~40 (add scheme, payment, transfer) | 3 days |

**Milestone: CI/CD pipeline with automated quality gates. All PRs checked automatically.**

---

## Month 4-5: Phase 2a — Foundation Modules (Wave 1-2)

### Week 13-16: Settings, Catalog, Customer, Masters

| Task | Effort |
|---|---|
| Extract settings access into centralized service | 3 days |
| Modernize catalog CRUD (validation, transactions, API) | 5 days |
| Modernize customer module (validation, security, API) | 5 days |
| Build REST API endpoints for masters CRUD | 3 days |
| Write E2E tests for new functionality | 2 days |
| LCA health diff (measure improvement) | 1 day |

### Week 17-20: LOT, Tagging, Tag Parts

| Task | Effort |
|---|---|
| Modernize LOT module (service layer, API, tests) | 5 days |
| Modernize Tagging module (largest by table dependencies) | 8 days |
| Modernize Tag Parts | 2 days |
| LCA health diff (measure improvement) | 1 day |

**Milestone: Foundation modules modernized. Tag lifecycle fully controlled.**

---

## Month 6-8: Phase 2b — Business Modules (Wave 3-4)

### Week 21-28: Estimation, Billing

| Task | Effort |
|---|---|
| Extract billing formulas into testable service | 5 days |
| Fix print vs save formula mismatches | 3 days |
| Modernize estimation module | 5 days |
| Modernize billing module (largest, most complex) | 10 days |
| Build REST API for bill creation/retrieval/cancel | 5 days |
| Comprehensive E2E test expansion for billing flows | 5 days |
| LCA health diff | 1 day |

### Week 29-34: Branch Transfer, Stock Issue, Section Transfer, Sales Transfer

| Task | Effort |
|---|---|
| Modernize all transfer modules | 10 days |
| Fix cross-branch data consistency | 3 days |
| Build REST APIs | 3 days |
| E2E tests for transfer flows | 3 days |

**Milestone: All core business and movement modules modernized.**

---

## Month 9-10: Phase 2c — Consumers & Scheme (Wave 5-6)

### Week 35-38: Reports, Dashboard

| Task | Effort |
|---|---|
| Performance optimize all report queries | 5 days |
| Add read replica support | 2 days |
| Modernize dashboard | 3 days |

### Week 39-42: Scheme, Account, Payment

| Task | Effort |
|---|---|
| Modernize scheme module | 5 days |
| Modernize payment module (2nd largest controller) | 8 days |
| Modernize account/journal module | 3 days |
| Build REST APIs | 3 days |

**Milestone: Scheme/chit modules modernized. Financial flow fully validated.**

---

## Month 11-12: Phase 2d + Phase 3 Start (Wave 7 + Frontend)

### Week 43-48: Support Modules + Frontend Kickoff

| Task | Effort |
|---|---|
| Modernize purchase, order, old metal, other inventory | 10 days |
| Choose frontend stack (React recommended) | 1 week |
| Design system / component library | 2 weeks |
| Build authentication + routing | 1 week |
| Start migrating first views (dashboard, reports) | Ongoing |

**Milestone: All 28 modules modernized. Frontend development started.**

---

## Month 13-16: Phase 3-4 — Frontend Migration + Infrastructure

| Task | Effort |
|---|---|
| Frontend migration (view by view, module by module) | 8-12 weeks |
| AWS RDS migration (dev → staging → production) | 1-2 weeks |
| CloudFront + S3 for static assets | 1 week |
| Performance optimization (indexes, query caching) | 2 weeks |
| Async SMS/email (job queue) | 1 week |
| Expand E2E tests to ~80 | 2 weeks |
| Roll out to pilot clients | 2 weeks |
| Full rollout across all 70 clients | 4 weeks |

**Milestone: Modernized system deployed to all clients.**

---

## Key Dependencies

```
Security Fixes ─────────────────────────────────────────────→ Always first
     │
     ▼
Tag State Machine ──→ LOT/Tagging ──→ Estimation ──→ Billing
     │                                                    │
     ▼                                                    ▼
Transaction Audit ──→ All Modules                   Payment/Account
     │                                                    │
     ▼                                                    ▼
Validation Layer ──→ All Modules                    Reports/Dashboard
     │
     ▼
CI/CD Pipeline ──→ Every subsequent deployment
     │
     ▼
REST APIs (per module) ──→ Frontend Migration
```

---

## Resource Requirements

| Role | Count | Duration | Purpose |
|---|---|---|---|
| **Senior Backend Dev** | 2 | Full 16 months | Module modernization, API development |
| **Mid Backend Dev** | 1-2 | Month 3-16 | Validation, transaction fixes, testing |
| **Frontend Dev** | 1-2 | Month 11-16 | Frontend migration (React/Vue) |
| **QA / Test Engineer** | 1 | Full 16 months | E2E test expansion, regression testing |
| **DevOps** | 0.5 | Month 1, 13-16 | CI/CD, AWS setup, deployment |
| **AI/Automation** | 1 (Antigravity) | Full 16 months | Code analysis, brain generation, fix suggestions |

---

## Success Metrics

| Metric | Baseline (Now) | Target (Month 16) | How to Measure |
|---|---|---|---|
| LCA Health Score | TBD (run baseline) | 80+ (Grade A/B) | `lca health` + `lca diff-health` |
| Security Findings (CRITICAL) | 12+ | 0 | `lca health` security dimension |
| E2E Test Count | 26 | 80+ | Playwright test suite |
| Dead Code % | TBD | <5% | `lca reaper` |
| N+1 Query Count | TBD | 0 critical, <5 medium | `lca n1` |
| Module Brain Coverage | 28/28 | 28/28 (refreshed) | knowledge_brain/ |
| Cancel Reversal Completeness | ~30% of tables | 100% of tables | CLEANUP_GAPS.md audit |
| REST API Coverage | 0% | 80%+ of endpoints | OpenAPI spec |
| Average Page Load | TBD | <2 seconds | Browser DevTools / APM |
| Client Deployment Time | Manual (30+ min) | Automated (<5 min) | CI/CD pipeline metrics |
