# `chit_services` Module Brain

> **Brain Version:** 2.0 (Full build — 2026-07-02, upgraded from partial)
> **Controller:** `admin/application/controllers/admin_services.php`
> **Lines:** 7,776 | **Methods:** 73 | **Models:** 14 loaded
> **Source Files:** MODULE_BRAIN.md (v1), BUG_REGISTER.md, COVERAGE_TRACKER.md, DATA_FLOW.md, ROUND2_MODEL_JS_ANALYSIS.md
> **Status:** ⭐⭐⭐⭐ FULL BRAIN (upgraded from ⭐)

---

## 1. Module Scale

| Metric | Value |
|---|---|
| Controller lines | **7,776** |
| Public methods | **73** |
| Models loaded | **14** (12 constants + 2 manual loads) |
| Duplicate model constants | **2** (MODEL=SMS_MODEL, EMAIL_MODEL=MAIL_MODEL) |
| `curl_init()` calls | **18** (all payment gateways and OneSignal) |
| `CURLOPT_SSL_VERIFYPEER = FALSE` | **18/18** — SSL verification disabled on ALL external calls |
| Active `print_r();exit;` halts | **10** — P0: crashes production crons |
| Active `print_r()` without exit | **25** — dumps data to logs |
| Raw `$_GET` usages | **5** — flows into DB queries |
| Raw `$_POST` usages | **5** — flows into DB queries |
| `trans_begin()` calls | **12** |
| `file_put_contents` log writes | **10** |
| `mkdir(0777)` calls | **4** |
| Bug count | **27** (5 P0, 7 P1, 9 P2, 6 P3) |
| Risk level | CRITICAL |

---

## 2. What This Module Does (Business View)

`chit_services` is the **background engine** of the Chit/Scheme domain. It runs jobs that normal users never see but that the entire system depends on daily:

| Function Group | What it Does |
|---|---|
| **Cron / Automation** | Nightly jobs: due date classification, free instalment processing, daily collection rollup |
| **Payment Verification** | Webhook receiver and manual verify for Cashfree, Easebuzz, Razorpay |
| **Notifications** | Push (OneSignal) and SMS (5 gateways) for dues, birthdays, anniversaries, wallet reminders |
| **Refund Engine** | Initiate and poll refunds from payment gateways |
| **Sync / Migration** | Customer data sync from legacy system, inter-branch data migration |
| **Due Type Classification** | Classifies every scheme payment as: ND/AD/PD/MND/PN/AN (Normal/Advance/Pending Due) |

---

## 3. Method Groups

### Group A — Cron / Scheduled Jobs

| Method | Line | Purpose | Risk |
|---|---|---|---|
| `check_expiry()` | 102 | Deactivate expired new-arrival items | Low |
| `send_notification()` | 330 | Due alert + birthday + wedding notifications | Medium |
| `send_smsdue()` | 605 | SMS due reminder | Medium |
| `add_daily_collection()` | 1566 | Nightly branch collection rollup | HIGH — active echo print_r at L1663 |
| `scheme_freepayment()` | 961 | Auto-process free instalment payments | HIGH |
| `dayDurationSchemeService()` | 6979 | Classify due_type for all active scheme payments | P0 — 10 active print_r;exit halts |
| `deleteNoPayments_Acc()` | 3322 | Delete scheme accounts with no payment records | HIGH — irreversible |
| `deleteInvalidPay()` | 5688 | Delete invalid/zero-amount payments | HIGH — irreversible |
| `deleteNoPayAcc()` | 5647 | Delete accounts with no payments | HIGH — irreversible |

### Group B — Payment Verification (Webhook + Manual)

| Method | Line | Purpose | Risk |
|---|---|---|---|
| `new_webhook()` | 7494 | Unified webhook receiver | HIGH — SSL disabled |
| `new_verification()` | 7522 | New unified payment verification engine | HIGH — no signature check |
| `payment_update()` | 7658 | Final payment status update after verification | HIGH |
| `cf_verify_pay_manual()` | 5212 | Manual Cashfree verify by transaction ID | HIGH |
| `verifyEasebuzzPayments()` | 4724 | NEW Easebuzz auto-verify (5-day window) | HIGH |
| `razorPay_verify()` | 5325 | Razorpay order verify | HIGH |
| `oldverify_cashfreepayment()` | 3436 | OLD Cashfree verify — LEGACY | Medium |
| `oldverifyEasebuzzPayments()` | 4208 | OLD Easebuzz verify — LEGACY | Medium |

### Group C — Sync / Data Migration

| Method | Line | Purpose |
|---|---|---|
| `syncExistingCusData()` | 2275 | Sync customers from legacy system |
| `sync_existing_data()` | 2414 | Sync individual customer data |
| `ajaxtempToMain()` | 1739 | AJAX: temp-to-main migration |
| `import_off_cusData()` | 3338 | Import offline customer CSV |

### Group D — Due Type Engine

| Method | Line | Purpose |
|---|---|---|
| `dayDurationSchemeService()` | 6979 | CORE: classify ND/AD/PD/MND/PN/AN for all payments |
| `old_dayDurationSchemeService()` | 6887 | LEGACY version — still called in some paths |
| `upd_total_paid_ins()` | 7277 | Update total paid installments per account |
| `upd_firstPayAs_startDate()` | 7315 | Set first payment as scheme start date |
| `update_dueMonthYear()` | 5719 | Batch update due_month/due_year |

### Group E — Notification / SMS

| Method | Line | Gateway / Channel |
|---|---|---|
| `send_singlealert_notification()` | 758 | OneSignal — single player ID |
| `onesignalNotificationToAll()` | 1501 | OneSignal — broadcast all |
| `send_sms()` | 1235 | SMS router (5 gateways, config-driven) |
| `send_wishesToCus()` | 5799 | Birthday/anniversary single customer |

### Group F — Refund Engine

| Method | Line | Purpose |
|---|---|---|
| `createRefund()` | 6028 | Initiate refund via Cashfree/Easebuzz |
| `getRefundStatusHook()` | 6333 | Poll refund status via webhook-style |
| `getRefundStatus()` | 6498 | Poll refund status from gateway API |

---

## 4. Critical Architecture: `dayDurationSchemeService()` (L6979)

This is the most complex and most dangerous method in the controller. It runs as a cron and classifies due types for every active scheme payment.

Due type classification:

- ND = Normal Due (this month's installment)
- AD = Advance Due (paid ahead of schedule)
- PD = Pending Due (overdue)
- MND = Multiple Normal Due (multi-chance schemes)
- PN = Pending + Normal combined
- AN = Advance + Normal combined

**N+1 pattern:** ~3 queries fired per payment record. For 10,000 accounts = 30,000 queries per cron run.

**10 active `print_r();exit;` blocks** inside this method halt the entire cron when triggered. The cron will silently stop processing all remaining scheme accounts — leaving them in the wrong due state.

---

## 5. Critical Architecture: `new_verification()` (L7522)

Unified payment verification engine for Cashfree, Easebuzz, and Razorpay:

1. checkTransExist() — prevent duplicate processing
2. curl to gateway API (SSL_VERIFYPEER=FALSE on all 3)
3. IF SUCCESS: insert_common_data() + insert_referral_data() + payment_update()
4. Write to gateway-specific log directory

**CRITICAL:** Cashfree signature verification is commented out. Forged payment webhooks will be accepted as legitimate successful payments.

---

## 6. SMS Gateway Architecture

Config-driven gateway selection via `config->item('sms_gateway')`:

| Code | Gateway | Method |
|---|---|---|
| 1 | MSG91 | `sms_model->sendSMS_MSG91()` |
| 2 | Nettyfish | `sms_model->sendSMS_Nettyfish()` |
| 3 | SpearUC | `sms_model->sendSMS_SpearUC()` |
| 4 | Asterixt | `sms_model->sendSMS_Asterixt()` |
| 5 | Qikberry | `sms_model->sendSMS_Qikberry()` |

Changing gateway requires only one config value update. Clean design.

---

## 7. Raw Input Security Map

| Raw Access | Line | Risk |
|---|---|---|
| `$_GET['id_branch']` | L1686, L1874 | Flows into DB query — SQL injection if not cast |
| `$_GET['date']` | L1688, L1876 | Date field in SQL WHERE — SQL injection |
| `$_POST['id_branch']` | L1716, L1744 | DB query param |
| `$_POST['entry_date']` | L1718, L1746 | Date filter in query |
| `$_POST['till_updated']` | L1724, L1748 | Sync column marker |

All 10 raw accesses bypass CI3's input helper. None are sanitized.

---

## 8. Bug Register Summary (27 Bugs Total)

### P0 — Catastrophic (5)

| ID | Bug | Location | Impact |
|---|---|---|---|
| SVC-BUG-001 | Active `print_r();exit;` in cron | `dayDurationSchemeService()` L7056+ | Halts cron — all subsequent scheme accounts skip due classification |
| SVC-BUG-002 | `echo print_r()` dumps financial data | `add_daily_collection()` L1663 | Branch financial data leaked to log or HTTP response |
| SVC-BUG-003 | Cashfree signature verification commented out | `new_verification()` | Forged payment callbacks accepted as valid |
| SVC-BUG-004 | Undefined variable crash in notification | `send_notification()` | Notification cron crashes on specific customers |
| SVC-BUG-005 | Broken `scheme_freepayment()` condition | L961 | Valid accounts skip free instalment |

### P1 — Critical (7)

| ID | Bug | Impact |
|---|---|---|
| SVC-BUG-006 | Raw `$_GET['id_branch']` into DB query | SQL injection |
| SVC-BUG-007 | Raw `$_POST` fields in ajaxtempToMain | SQL injection |
| SVC-BUG-008 | SSL verification disabled on all 18 curl calls | MITM attack on payment data |
| SVC-BUG-009 | N+1 query pattern in dayDurationSchemeService | 30,000+ queries per run for 10K accounts |
| SVC-BUG-010 | SMS double-fire on retry | Duplicate SMS to customers |
| SVC-BUG-011 | No gate on mass-delete cron methods | Irreversible data loss triggered by URL hit |
| SVC-BUG-012 | `-2 days` offset bug in daily collection | Missing one day of collection data |

See [BUG_REGISTER.md](file:///c:/xampp/htdocs/etail_development_src/knowledge_brain/chit_services/BUG_REGISTER.md) for P2 and P3 details.

---

## 9. Cross-Module Dependencies

| Module | Relation | Tables / Methods |
|---|---|---|
| `payment` | Direct: verify and update payments | `payment`, `transaction`, `receipt` |
| `masters` | Settings on every request | `chit_settings`, `notification`, `branch` |
| `customer` | Fetch tokens/mobiles for push/SMS | `customer`, `customer_reg` |
| `account` | Read scheme account data | `scheme_account`, `wallet_account` |
| `scheme` | Free payment processing | `scheme`, `scheme_account` |
| `chit_collection_app` | Webhook/verify overlap | Mobile payment webhook handlers |
| `Billing_v2` | Daily collection rollup | `daily_collection`, branch summaries |
| External: OneSignal | Push notifications | 18 curl calls |
| External: SMS gateways (5) | SMS dispatch | Via `sms_model` |
| External: Cashfree | Payment verify + refund | `new_verification()`, `createRefund()` |
| External: Easebuzz | Payment verify + refund | `verifyEasebuzzPayments()` |
| External: Razorpay | Payment verify | `razorPay_verify()` |

---

## 10. Danger Zones

| Zone | Why Dangerous |
|---|---|
| `dayDurationSchemeService()` L6979 | 10 active print_r;exit — cron halts leaving accounts in wrong due state |
| `deleteNoPayments_Acc()` / `deleteInvalidPay()` | Irreversible mass deletes — no confirmation, no soft delete |
| `new_webhook()` + `new_verification()` | No signature check — financial fraud via forged callbacks |
| `add_daily_collection()` | -2 days offset bug + echo print_r() — data gap + data leak |
| All 18 curl calls | SSL_VERIFYPEER=FALSE — MITM vulnerable on every external call |

---

## 11. Recommended Next Actions

| Priority | Action |
|---|---|
| P0 | Remove all 10 `print_r();exit;` from `dayDurationSchemeService()` |
| P0 | Remove `echo print_r($instoday)` from `add_daily_collection()` L1663 |
| P0 | Re-enable Cashfree signature verification in `new_verification()` |
| P0 | Fix `-2 days` to `-1 day` in `add_daily_collection()` |
| P1 | Replace all raw `$_GET`/`$_POST` with CI3 input helpers |
| P1 | Add confirmation gate to all 3 mass-delete cron methods |
| P1 | Fix SSL_VERIFYPEER to TRUE with proper CA bundle |
| P2 | Refactor `dayDurationSchemeService()` to batch-query instead of N+1 |
| P2 | Build sub-documents: CROSS_MODULE_MAP, METHOD_INDEX, SCHEMA_ANALYSIS, FLOW_RISK_MATRIX, INVARIANT_MATRIX |
