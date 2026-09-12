# CBN AML Baseline Standards — Codebase Review & Implementation Plan

> **Scope:** `studious-train` (Laravel 11 AML/CFT transaction-monitoring application, NFIU goAML reporting).
> **Reference standard:** CBN "Baseline Standards for Automated AML/CFT/CPF Solutions" (circular **BSD/DIR/PUB/LAB/019/002**, 10 March 2026) and the accompanying CBN AML Baseline Requirements Register.
> **Date:** 2026-09-12

---

## 0. How this document maps to your spreadsheet

The register (`CBN_AML_Baseline_Requirements_Register`) is a requirement-by-requirement checklist of the CBN Baseline Standards. This review groups the codebase against the same pillars the register uses, and for each pillar states:

- **What exists** in the app today
- **Gap** (what the baseline requires but the app does not do)
- **Action** (concrete implementation step)

If you paste the CSV rows (or share the sheet as "Anyone with the link → Viewer"), I can produce a **line-by-line requirement → code → gap → task** matrix in the same format as the register.

---

## 1. Executive summary

The app is a solid **Laravel 11** foundation for an AML/CFT monitoring system. It already implements the core detection loop end-to-end:

```
API transaction ingestion → customer resolution → watchlist screen →
[risk scoring → peer-group outlier → AI anomaly → rule engine] → flagged case →
review/comment/classify → export (CSV/Excel/goAML XML STR/CTR)
```

It also has risk rating + CDD/EDD review scheduling, team/roles/permissions (Spatie), activity logging (Spatie), customer sync from a core-banking DB, and cron scheduling.

However, against the CBN Baseline Standards it has **material gaps**, and there are **several correctness/security defects** that must be fixed before the functional gaps are layered on. The highest-priority items are:

| # | Severity | Finding |
|---|----------|---------|
| 1 | 🔴 Critical | **24-hour rule engine cron is a no-op** — `determineSides()` returns `['account' => null]` for cron and the `$accountNo` argument is never used in the loop. |
| 2 | 🔴 Critical | **Transaction ingestion API is unauthenticated** — API keys are generated in Settings but never validated on `POST /api/v1/transactions`. |
| 3 | 🔴 High | **Cron job endpoints are public GET routes** (`/cron-job/*`) that mutate state — unauthenticated and CSRF-bypassed; anyone can trigger heavy jobs. |
| 4 | 🔴 High | **Audit log can be truncated by a GET route** (`audit-trail/clear`) — directly violates the tamper-proof/audit-retention baseline. |
| 5 | 🟠 Medium | `RunTransactionQuery` (a `ShouldQueue` job) is executed **synchronously** via `->handle()`, so AI HTTP calls (10s timeouts) block the API request. |
| 6 | 🟠 Medium | Duplicate-case logic is per-transaction; 24hr account-level rules can create repeat cases across daily runs. |
| 7 | 🟠 Medium | Case-list pagination is done in PHP after loading **all** rows; watchlist matching is naive `LIKE` with no scoring/hit-management. |
| 8 | 🟠 Medium | CTR generation is a **placeholder**; no KYB/beneficial-ownership module; no real-time list blocking. |
| 9 | 🟠 Medium | Zero automated tests for an AML system. |
| 10 | 🟡 Low | `app.zip` (50 MB) committed to Git; default-Laravel README; timezone default UTC. |

---

## 2. Codebase review

### 2.1 Architecture overview

| Layer | Files | Notes |
|-------|-------|-------|
| API | `app/Http/Controllers/Api/V1/ApiController.php`, `routes/api.php` | `POST /v1/transactions` → `TransactionService` → `RunTransactionQuery` |
| Ingestion | `app/Services/TransactionService.php` | Creates `Transaction`, resolves/creates `Customer` for both legs, screens both legs against watchlists |
| Detection pipeline | `app/Jobs/RunTransactionQuery.php` | Risk scoring → peer group → AI anomaly → rule engine |
| Rule engine | `TransactionQueryService`, `TransactionQueryBuilder`, `RuleEvaluationEngine`, `RuleEvaluation` | Instant + 24HrTask rules, column-level + "advance" (aggregation) checks |
| Risk scoring | `TransactionRiskScoringService.php` | Configurable weighted factors (`risk_scoring_configs`) vs threshold setting |
| Peer group | `PeerGroupAnalysisService.php` | IQR-based outlier thresholds per demographic group |
| AI anomaly | `AIDetectionService.php` | External ML server (env `ML_SERVER_URL`) |
| Watchlists | `WatchListService.php`, `config/watchlists.php` | Internal + NIBSS lists, name/BVN/NIN/account matching |
| PEP / Sanctions / Media | `ScreeningService.php`, `config/screening.php` | OFAC consolidated XML, Google SearchAPI keywords, Google News RSS |
| Case management | `CaseManagementController.php`, `FlaggedCasesAnalyticService.php` | Review, comment, classify (TP/FP), NFIU indicator, performance, CARRD, false-positive dashboard |
| Risk rating | `RiskRatingService.php`, `RiskRatingController.php`, `RiskLevel` | Profile templates, score → level, CDD/EDD review scheduling (`reviews-due`) |
| Reporting | `XMLExportService.php` | goAML 5.0.2 XML for STR/CTR/EFT/IFT/TFR/BCR/UTR/AIF/SAR + XSD validation |
| Team/RBAC | `TeamController.php`, `RolesAndPermissionsSeeder.php` | Spatie roles: admin/supervisor/reviewer/auditor |
| Audit | `AuditTrailController.php` | Spatie activitylog view + (dangerous) clear |
| Customer sync | `CustomerSyncService.php` | Incremental sync from secondary MySQL DB with column mapping |
| Cron | `CronJobController.php`, `routes/console.php` | Review scheduler, 24hr engine, watchlist, auto-risk-rate, CTR (stub), customer sync |

### 2.2 Strengths

- Clean separation of concerns: models → services → controllers, with a real rule-evaluation pipeline.
- Trigger-source taxonomy (`FlaggedCase::SOURCE_*`) lets every alert source feed one auditable case record.
- goAML XML export + XSD validation is a strong, non-trivial compliance feature.
- Customer sync is genuinely good (incremental `updated_at` watermarks, configurable column mapping, batch chunks, per-row error isolation).
- Risk rating supports imported profile templates (Excel via maatwebsite/excel) plus a fallback heuristic.
- Sensible use of Spatie permission + activitylog.

### 2.3 Defects (detailed)

**D1 — 24hr rule engine is a no-op (Critical).**
`TransactionQueryService::processRule()` builds `$sides = $this->determineSides($conditions, $transaction)` and iterates `$sides`. For cron, `determineSides()` returns `['account' => null]`, so `if (!$acctNo) continue;` skips everything. The `$accountNo` parameter (which `runViaCronJob` correctly passes as `$customer->account_number`) is dead code. **Net effect:** `CronJobController::dailyRuleEngine()` runs zero evaluations. Fix: pass `$accountNo` through and evaluate against it directly.

**D2 — Unauthenticated transaction API (Critical).**
`routes/api.php` has `Route::post('/transactions', [ApiController::class, 'store'])` with **no middleware**. `SettingsController::generateApiKeys()` / `storeBusinessClientIdAndSecret()` create client credentials, but nothing checks them. Any caller can inject transactions. Fix: add an `auth:sanctum`-style or HMAC/API-key middleware (client_id + secret, timestamp, nonce, signature), plus rate limiting.

**D3 — Public state-changing cron GET routes (High).**
`routes/web.php` registers `/cron-job/*` with **no auth middleware** (only the `/cron-jobs` status page is auth'd). These are GETs that mutate data (review scheduler, rule engine, screening). Fix: move execution behind a signed URL / token (`signed` middleware) or auth+permission, and keep the scheduler as the only trigger.

**D4 — Audit log truncation via GET (High / compliance).**
`AuditTrailController::clearLog()` runs `Activity::truncate()` on `GET audit-trail/clear` with no permission guard in the route. CBN baseline requires **traceable, tamper-proof** audit records. Fix: remove the clear feature, add an immutable (append-only) audit store, and protect audit routes with the `audit-trail` permission.

**D5 — Synchronous job execution (Medium).**
`ApiController::store()` calls `(new RunTransactionQuery($transaction))->handle()` instead of `RunTransactionQuery::dispatch($transaction)`. The job implements `ShouldQueue` but the queue is bypassed; the AI HTTP call (10s timeout, ×2 sides) and OFAC/PEP HTTP calls run in the request thread. Fix: dispatch to the queue; make the API return 202 Accepted.

**D6 — Duplicate-case logic (Medium).**
`checkIfTransactionAlreadyFlagged()` dedupes on `(transaction_id, rule_id)` only. Account-level 24hr rules compare across a window of transactions, so successive daily runs re-flag the same account with the *latest* transaction id, creating repeat cases. Fix: for account-level triggers, dedupe on `(account_no, rule_id, window/date)`, not just transaction.

**D7 — PHP-side pagination + naive matching (Medium).**
`CaseManagementController::index()` pulls all filtered cases and slices in PHP (`array_slice` + `LengthAwarePaginator`). `WatchListService::searchList()` uses `LIKE %x%` across fields with `orWhere`, producing unranked fuzzy hits. Fix: DB-level pagination (DataTables/yajra is already a dependency), and add name-matching scoring (phonetic/n-gram) + hit review workflow.

**D8 — CTR stub + missing KYB (Medium, functional gap).**
`CronJobController::generateCtr()` logs and returns — no CTR generation despite CTR being a core reporting obligation and a seeded NFIU indicator category.

**D9 — Zero test coverage (Medium).**
Only stock `ExampleTest`. Fix: Pest/PHPUnit suites for the rule engine, scoring, dedupe, and exports.

**D10 — Housekeeping (Low).**
`app.zip` (50 MB) is committed; README is stock Laravel; `.env.example` defaults to SQLite/UTC.

### 2.4 Cross-cutting engineering notes

- **SQL-portability bug (already fixed this session):** `getFalsePositiveData()` used SQLite `strftime()` on MySQL → error 1305; replaced with `DATE_FORMAT()`.
- `Customer::transactions()`/`flaggedCases()` are builder-returning pseudo-relations (cannot eager-load); refactor to `hasMany` via `morph`/two `belongsTo`.
- `getReviewer()` does a plain `first()` (no round-robin despite the docblock); `getReviewerPerformance()` issues 2×N+1 count queries per reviewer.
- `createCaseSlug()` is `count()+1` — racy under concurrent queue workers; use a UUID/sequence or DB unique constraint + retry.
- `TransactionQueryBuilder` interpolates the rule attribute name into `whereRaw` in places (`has_fractions`); validate column names against a whitelist.
- `AuditTrailController`, `SettingsController` route permissions are incomplete relative to the seeder's permission catalog (e.g., rule CRUD routes aren't guarded by `rule-*` permissions — check `TransactionRuleController` middleware).
- OFAC screening loads the full `CONS_ENHANCED.XML` into memory per check; move to a pre-indexed table / job.
- Timezone: `APP_TIMEZONE=UTC` while Nigerian business rules (daily cutoffs, report dates) assume local time; standardize on `Africa/Lagos`.

---

## 3. Gap analysis vs CBN Baseline Standards

Legend: ✅ present · 🟡 partial · ❌ absent

### A. Overall AML solution & governance

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Solution commensurate with size/risk | 🟡 | No documented risk assessment or sizing rationale | Add an institution risk-assessment config page + evidence store |
| Availability / resilience / DR | ❌ | Single DB, no HA/DR artifacts | Operational: queue/cache/db HA, backup policy; document |
| Governance, model validation, change control | ❌ | Rules edited in place; no versions, no approval flow | Rule versioning + draft→approved lifecycle, model/rules change log |
| Independent audit review | 🟡 | `auditor` role + audit-trail exist but logs are deletable | Immutable audit store; audit-report export |

### B. CDD / KYC / KYB

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| End-to-end CDD/KYC | 🟡 | `Customer` holds name/BVN/NIN/DOB/address/tier; sync from core DB | Add KYC document status, identity verification status, KYC level |
| BVN/NIN verification vs NIBSS/NIMC | ❌ | Stored but never verified against NIBSS/NIMC | Integrate NIBSS BVN lookup + NIMC NIN verification (or a provider) |
| KYB / legal persons | ❌ | `customer_type` only; no entity/BO model | Add corporate entity + beneficial-ownership register (BO names, % ownership, PEP status) |
| Risk-based CDD & EDD | 🟡 | Risk levels + review scheduling (time-based only) | Add **event-driven** reviews (material change, BO change, new patterns) |
| PEP / high-risk handling | 🟡 | `isPep` flag + ScreeningService keyword search | Structured PEP data (tier, position, country) + EDD approval workflow |
| Data synchronisation KYC↔risk↔txn | 🟡 | `CustomerSyncService` good; risk score stored on customer | Ensure sync updates trigger re-rating + review events |

### C. Sanctions, internal watchlist & PEP screening

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Domestic + international lists | 🟡 | Internal + NIBSS lists; OFAC XML; PEP via SearchAPI | Add UN/EU/UK consolidated lists; scheduled refresh job |
| Real-time / near-real-time list updates | ❌ | Lists uploaded manually; OFAC cached 24h | Add list-version tracking + automated refresh + last-updated metadata |
| Real-time blocking of hits | ❌ | Hits create a case; nothing blocks the transaction/account | Add blocking action (freeze flag) + decision workflow |
| Hit management / disposition | ❌ | No hit review states or false-positive resolution on watchlist hits | Hit queue with confirm/discard/audit disposition |
| Name-matching quality | ❌ | `LIKE %x%` only | Phonetic + transliteration + alias matching with scores & thresholds |

### D. Risk assessment (enterprise & customer)

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Customer risk classification | ✅ | `RiskRatingService` + profiles + levels | — (improve: event-driven re-rating) |
| Enterprise/inherent risk assessment | ❌ | No product/channel/jurisdiction risk model | Add institution-level ML/TF risk assessment module |
| Dynamic / scenario-driven | ❌ | Static score templates | Add scenario library + what-if re-scoring |
| Unified customer risk view | 🟡 | `Customer` has stats/str/ctr/cases but no single consolidated screen | Build a 360° customer risk profile page (KYC + risk + behaviour + alerts + cases) |

### E. Transaction monitoring & risk-based analysis

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Rules-based monitoring | ✅ | `TransactionQueryBuilder` + advance aggregations | Fix D1 (24hr no-op) before anything else |
| Behavioural pattern / typology analysis | 🟡 | Peer-group IQR + AI anomaly (external) | Add peer/velocity/structuring typologies natively; baseline customer behaviour profiles |
| Historical data use | 🟡 | Peer group uses history; rules use fixed windows | Add 30/60/90-day comparative windows (CBN RBS guidance) |
| False-positive management | 🟡 | False-positive dashboard + threshold exists | Add FP feedback loop → rule tuning metrics, explainability |
| Fraud/AML logical separation | 🟡 | Rules share one engine | Add a `domain` tag (AML vs fraud) and separation controls |

### F. Case management

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Structured, auditable workflow | ✅ | Cases + comments + status + classification + activity log | Add state machine + SLA/aging escalations |
| Reviewer assignment | 🟡 | `getReviewer()` first-match | Round-robin / workload-balanced assignment |
| Consolidated investigation | 🟡 | Case show builds partial context | 360° view (D pillar) linked from every case |

### G. Reporting

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| STR generation (goAML) | ✅ | `XMLExportService` + XSD validation | Add 24h-from-detection SLA tracking |
| CTR generation | ❌ | Placeholder | Implement CTR detection (cash thresholds) + generation + filing status |
| Other returns (EFT/IFT/etc.) | 🟡 | XML supports types but no triggers | Wire report types to detection paths |
| Filing/acknowledgement tracking | ❌ | Export only, no submission status | Add NFIU submission + ack tracking |

### H. Audit trails & data retention

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Traceable, tamper-proof records | 🟡 | Activitylog present but truncatable | Immutable, append-only audit store; hash-chaining optional |
| Retention policy | ❌ | No retention/purge config | Implement per-record-type retention with scheduled archival |
| Searchable audit evidence | 🟡 | Basic filters | Add who/what/when/before-after diff view (activitylog `properties` already supports it) |

### I. Data security & protection

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Confidentiality/integrity/availability | 🟡 | Standard Laravel; no special handling of PII | Encrypt BVN/NIN at rest (Laravel `encrypted` cast), mask in UI, TLS enforcement |
| Access control / segregation | 🟡 | Spatie RBAC partial; some routes unguarded | Complete route-level permissions (esp. rules, settings, audit) |
| API security | ❌ | Unauthenticated ingestion | API-key/HMAC auth + rate limiting (D2) |

### J. Third-party & vendor management

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Vendor/OEM governance | ❌ | AI server, SearchAPI, OFAC used with no vendor records | Vendor registry (name, service, DPA, uptime, access) + credential vault |
| Outsourcing accountability | ❌ | n/a | Document responsibilities; keep decision evidence in-app |

### K. Training

| Baseline expectation | Status | Evidence / gap | Action |
|---|---|---|---|
| Documented, retained training | ❌ | No training module | Training records (course, attendees, dates, materials) with retention |

---

## 4. Implementation plan

Phased so that correctness/security land first (they block a defensible compliance posture), then functional coverage.

### Phase 0 — Stabilize & secure (Week 1)

1. Fix D1: pass `$accountNo` into the 24hr evaluation loop (repair `processRule`/`determineSides`).
2. Fix D2: add API authentication middleware (client_id + secret + signature + replay protection) and rate limiting.
3. Fix D3: protect cron execution routes (`signed` URLs or auth+permission); keep `schedule:run` as primary trigger.
4. Fix D4: remove audit-truncate; make activity records immutable; guard audit routes.
5. Fix D5: dispatch `RunTransactionQuery` to the queue; API returns 202.
6. Fix D6: account-level dedupe keyed on `(account_no, rule_id, window)`.
7. Fix D7: DB pagination for case list.
8. Add `Africa/Lagos` timezone default; `.env.example` alignment.
9. Housekeeping: remove `app.zip` from Git; write real README.

### Phase 1 — Reporting & detection correctness (Weeks 2–3)

10. Implement CTR: cash-transaction threshold detection → `FlaggedCase` (`report_type=CTR`) → goAML CTR XML.
11. Report filing/acknowledgement status fields + SLA timers (STR within 24h of detection).
12. Rule versioning + draft/approved/published state + change log (governance).
13. Wire `rule-*`/`settings-*`/`audit-trail` permissions to their routes.
14. Test suite: rule engine, scoring, dedupe, watchlist, XML export.

### Phase 2 — CDD/KYC/KYB & screening (Weeks 3–6)

15. BVN/NIN verification integration (NIBSS/NIMC or provider) with status stored per customer.
16. KYB model: corporate entities + beneficial owners + BO register + PEP flags.
17. Watchlist upgrades: list versions + scheduled refresh; name matching (phonetic/alias) with scores; hit disposition workflow; real-time blocking (freeze).
18. Event-driven reviews: triggers on material change / BO change / new patterns re-schedule reviews.
19. 360° customer risk view: KYC + risk classification + behaviour + alerts + case history on one page.

### Phase 3 — Risk assessment & analytics (Weeks 6–8)

20. Institution-level ML/TF risk assessment module (products, channels, jurisdictions).
21. Behavioural profiling: per-customer baselines + velocity/structuring/smurfing typologies + 30/60/90-day windows.
22. False-positive feedback loop: FP disposition feeds rule-tuning KPIs (precision/recall per rule).
23. AI anomaly: configurable threshold/severity, explainability capture, model-version tracking.

### Phase 4 — Governance, security & retention (Weeks 8–10)

24. Immutable, hash-chained audit store with retention policy + scheduled archival.
25. PII protection: encrypted casts for BVN/NIN, UI masking, TLS/HSTS.
26. Vendor management registry + credential vault.
27. Training records module.
28. DR/HA documentation and runbooks; backup verification.

---

## 5. If you share the register rows

Paste the CSV content (or set the Google Sheet to "Anyone with the link → Viewer" and re-share), and I'll convert this into a **requirement-by-requirement matrix**: each register row → its app status (✅/🟡/❌) → the exact file/method → the specific task, so it can be tracked as a delivery plan for the CBN submission.
