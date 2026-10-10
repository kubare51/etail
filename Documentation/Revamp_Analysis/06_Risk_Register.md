# Risk Register

> **Purpose**: Consolidated risk analysis — security, architectural, data integrity, and performance risks.
> **Data sources**: knowledge_brain/_SYSTEM/ (DANGER_ZONES.md, CROSS_MODULE_BUGS.md, VALIDATION_GAPS.md, PERFORMANCE_RISKS.md, CLEANUP_GAPS.md)

---

## Risk Summary

| Category | Critical | High | Medium | Low | Total |
|---|---|---|---|---|---|
| **Security** | 12 | 18 | 15 | — | 45 |
| **Data Integrity** | 5 | 12 | 9 | — | 26 |
| **Architectural** | 5 | 12 | 15 | 25 | 57 |
| **Cross-Module** | 8 | 35 | 50 | 20 | 113 |
| **Validation** | 15 | 45 | 80 | 69 | 209 |
| **Performance** | 2 | 6 | 8 | — | 16 |
| **Total** | **47** | **128** | **177** | **114** | **466** |

---

## Critical Security Risks (Fix Immediately)

| # | Risk | Impact | Location | Effort |
|---|---|---|---|---|
| SEC-001 | **OTP bypass** — hardcoded OTP "123456" accepts any operation | Auth bypass for cancel, high-value ops | Multiple controllers | 1 hour |
| SEC-002 | **OTP comparison** uses `==` instead of `===` | Type juggling bypass (PHP "0e..." == 0) | OTP verification functions | 2 hours |
| SEC-003 | **SQL injection** in `get_access()` | Admin privilege escalation | `admin_settings_model.php` | 1 day |
| SEC-004 | **SQL injection** in chit reports | Arbitrary data access | Chit report controllers | 1 day |
| SEC-005 | **CVV stored in plaintext** | PCI DSS violation | `payment` table | 1 hour (DROP column) |
| SEC-006 | **Card numbers unmasked** | PCI DSS violation | `payment` table | 2 hours |
| SEC-007 | **Passwords stored as base64** | Not hashing, trivially reversible | `customer`, `employee` tables | 3 days |
| SEC-008 | **Admin logs publicly accessible** | PII/credential exposure | `admin/log/` directory | 10 minutes |
| SEC-009 | **`clear_database()` inadequate auth** | Accidental or malicious DB wipe | Settings controller | 1 day |
| SEC-010 | **LFI in customer download** | Arbitrary file read | Customer controller | 2 hours |
| SEC-011 | **Raw `$_POST`/`$_GET`** bypassing CI input filtering | XSS, injection | Multiple controllers | 3 days |
| SEC-012 | **Hardcoded API keys** in source code | Third-party service abuse | Config files, controllers | 1 day |

**Estimated total effort for critical security fixes: 2 weeks**

---

## Critical Architectural Risks

| # | Risk | Impact | Details |
|---|---|---|---|
| ARCH-001 | **No tag_status state machine** | Inventory corruption | 10+ modules write tag_status directly. No central guard validates transitions. Concurrent billing + transfer can both claim same tag. |
| ARCH-002 | **Incomplete cancel/reversal logic** | Orphaned data, wrong balances | 8+ modules have cancel that restores <30% of child table data. Save touches 8 tables, cancel reverses 5 → 3 tables have orphaned records. |
| ARCH-003 | **No transaction wrapping** | Partial writes on error | Many multi-table operations (billing, transfer, payment) lack `trans_begin/trans_complete`. PHP error mid-operation leaves data inconsistent. |
| ARCH-004 | **Save formula ≠ Print formula** | Incorrect invoices to customers | Billing save uses formula A, print template uses formula B. Customer sees different amounts than what's stored. |
| ARCH-005 | **No centralized error handling** | Silent failures | Many operations use `echo exit` or return empty responses. No logging, no error aggregation, no alerting. |

---

## Data Integrity Risks

| # | Risk | Tables Affected | Impact |
|---|---|---|---|
| DATA-001 | Cancel bill doesn't restore tag_status | `ret_taging` | Tags permanently lost after cancelled bill |
| DATA-002 | Cancel bill doesn't delete journal entries | `journal` | Financial reports show phantom transactions |
| DATA-003 | Branch transfer cancel doesn't restore sender stock | `ret_taging` | Inventory mismatch between branches |
| DATA-004 | Scheme pre-close doesn't recalculate benefits | `scheme_account` | Incorrect maturity benefits |
| DATA-005 | Old metal deduction doesn't reverse on bill cancel | `ret_old_metal` | Customer loses old metal value |
| DATA-006 | Payment mode change doesn't update journal | `journal`, `payment` | Ledger doesn't match payment records |
| DATA-007 | Concurrent tag operations (no row locking) | `ret_taging` | Same tag billed twice simultaneously |
| DATA-008 | Credit collection doesn't verify outstanding amount | `ret_billing` | Over-collection possible |

---

## Performance Risks

| # | Risk | Location | Impact |
|---|---|---|---|
| PERF-001 | **N+1 queries** — DB calls inside loops | Multiple models | Pages take 5-30 seconds on large datasets |
| PERF-002 | **`SELECT *` on large tables** | Multiple queries | Fetches 50+ columns when 5 are needed |
| PERF-003 | **No pagination on unbounded queries** | Reports, lists | Memory exhaustion on large result sets |
| PERF-004 | **SMS/email sent synchronously** | Billing, schemes | User waits 2-5 seconds for external API response |
| PERF-005 | **Missing indexes** on frequently filtered columns | Various tables | Full table scans on tag_status, bill_date, etc. |
| PERF-006 | **Settings loaded per-request** | Every page load | Same settings queried 1000+ times per page |
| PERF-007 | **Report queries without date boundaries** | Report controllers | Scans entire table history |

---

## Cross-Module Risk Matrix

This matrix shows which modules are most at risk from changes in other modules:

| If You Change... | These Break... |
|---|---|
| **Tagging (tag_status)** | Billing, Estimation, LOT, Branch Transfer, Stock Issue, Section Transfer, Sales Transfer, Reports |
| **Billing (ret_billing)** | Payment, Account/Journal, Reports, Dashboard, Scheme Utilization, Customer |
| **Scheme (scheme_account)** | Payment, Billing (utilization), Account, Chit Reports, Dashboard |
| **Catalog (item_master)** | Tagging, LOT, Estimation, Billing, Purchase, Reports |
| **Settings (ret_settings)** | ALL modules — every module reads configuration |
| **Customer** | Billing, Scheme, Order, App API, SMS, WhatsApp |
| **Payment** | Billing, Scheme, Account, Reports, POS |

---

## Validation Gap Summary (by Category)

| Category | Count | Examples |
|---|---|---|
| **Server-side numeric validation** | 45 | Amount fields accepted as strings, no parseFloat |
| **Required field checks** | 38 | Bill created without customer, tag without lot |
| **Range/boundary validation** | 32 | Negative quantities accepted, dates in future |
| **Cross-field validation** | 28 | GST without state, discount > total, payment > due |
| **SQL parameterization** | 25 | String concatenation in WHERE clauses |
| **XSS output escaping** | 22 | Variables echoed without htmlspecialchars |
| **File upload validation** | 10 | No server-side type/size check |
| **Auth/permission checks** | 9 | Endpoints missing get_access() call |

---

## Risk Mitigation Priority

### Immediate (Week 1-2): Security

| Action | Effort | Risks Mitigated |
|---|---|---|
| Remove hardcoded OTP | 1 hour | SEC-001 |
| Fix OTP comparison to `===` | 2 hours | SEC-002 |
| Add .htaccess to admin/log/ | 10 min | SEC-008 |
| DROP payment.cvv column | 1 hour | SEC-005 |
| Mask payment.card_no | 2 hours | SEC-006 |
| Fix get_access() SQLi | 1 day | SEC-003 |
| Fix clear_database() auth | 1 day | SEC-009 |
| Fix LFI in customer download | 2 hours | SEC-010 |

### Short-term (Week 3-8): Architecture

| Action | Effort | Risks Mitigated |
|---|---|---|
| Implement tag_status state machine | 2 weeks | ARCH-001, DATA-001, DATA-003, DATA-007 |
| Audit all cancel operations | 2 weeks | ARCH-002, DATA-001 through DATA-008 |
| Add transaction wrapping | 1 week | ARCH-003 |
| Audit print vs save formulas | 1 week | ARCH-004 |
| Replace base64 passwords with bcrypt | 3 days | SEC-007 |

### Medium-term (Week 9-16): Validation

| Action | Effort | Risks Mitigated |
|---|---|---|
| Add server-side validation layer | 2 weeks | 209 validation gaps |
| Parameterize all SQL | 1 week | 25 SQLi risks |
| Add output escaping | 1 week | 22 XSS risks |

### Long-term (Week 17+): Performance

| Action | Effort | Risks Mitigated |
|---|---|---|
| Fix N+1 queries (LCA identifies all) | 2 weeks | PERF-001 |
| Add pagination to unbounded queries | 1 week | PERF-003 |
| Add missing indexes | 3 days | PERF-005 |
| Implement async SMS/email | 1 week | PERF-004 |
| Cache settings per-request | 2 days | PERF-006 |

---

## Compliance Risks

| Regulation | Risk | Status | Fix |
|---|---|---|---|
| **PCI DSS** | CVV storage, unmasked card numbers | NON-COMPLIANT | Drop CVV, mask card_no |
| **RBI (Cash limits)** | validate_cash_amt disabled on some clients | VARIES BY CLIENT | Enforce via settings audit |
| **GST** | e-Invoice errors, wrong CGST/SGST/IGST calculation | PARTIAL COMPLIANCE | Fix GST logic in billing |
| **Data Protection** | Passwords not hashed, logs publicly accessible | NON-COMPLIANT | bcrypt + .htaccess |
| **Aadhaar** | Aadhaar numbers potentially stored in plaintext | NEEDS AUDIT | Encrypt or tokenize |
