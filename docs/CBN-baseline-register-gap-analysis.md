# CBN AML Baseline Requirements Register — MoniSurv Review & Implementation Plan

> **Source:** `CBN_AML_Baseline_Requirements_Register.xlsx` (CBN Baseline Standards for Automated AML/CFT/CPF Solutions, circular BSD/DIR/PUB/LAB/019/002).
> **App:** `studious-train` — Laravel 11 AML transaction-monitoring + goAML reporting platform ("MoniSurv").
> **Date:** 2026-09-12

---

## 0. Reading notes

The register is a requirement-by-requirement checklist with columns: **Section · Requirement Area · Obligation Type · Sub-Requirement Ref · Requirement Detail · Applicability · "Does Monisurv have this?" · Comment** (the Comment column carries the product team's own asks).

- Rows with a status of **Ok / OK** are treated as *present but to be verified/stabilised*, not necessarily finished.
- Rows **6–9, 18–20, 27–28** contain embedded screenshots in their Comment cells and did not parse into text. They fall between 5.2(a)(iv)→5.3(a)(i), 5.3(a)(viii)→5.4(a)(i), and 5.4(a)(vi)→5.5(a)(i). From the CBN circular these are almost certainly the **"Institutions shall" (5.x(b)) obligations** (governance/oversight duties) plus a few additional "AML Solution shall" items. **Please paste those rows so I can add them**; the plan below covers every requirement I could read.

**Verdict:** the app already implements the core AML loop (ingest → screen → score → alert → case → report) but the register shows the product team wants it to move from *reactive rule-flags* to **risk-driven monitoring**: scheduled auto risk-rating, a 360° single customer view, pre-emptive behavioural alerts, list-update logging, case TAT/SLA, and export everywhere. Those are all concrete, buildable features — mapped to files below.

---

## 1. Blockers that must be fixed first (affect many register rows)

These defects invalidate otherwise "OK" rows, so they gate the rest of the plan.

| # | Sev | Defect | Evidence | Fix |
|---|-----|---------|----------|-----|
| B1 | 🔴 | **24-hour rule engine is a no-op.** For cron runs `determineSides()` returns `['account' => null]` and the loop `continue`s — the `$accountNo` passed by `runViaCronJob()` is never used. So `dailyRuleEngine()` evaluates nothing. | `TransactionQueryService::processRule()` / `determineSides()` | Pass `$accountNo` into evaluation; loop over the real account. |
| B2 | 🔴 | **Transaction API is unauthenticated.** API keys are generated in Settings but nothing validates them on `POST /api/v1/transactions`. | `routes/api.php`, `SettingsController::generateApiKeys()` | Add API-auth middleware (client_id + secret + HMAC signature + replay nonce) + rate limit. |
| B3 | 🔴 | **Cron endpoints are public GETs that mutate state** (`/cron-job/*`). | `routes/web.php` (cron group has no `auth`) | Protect with `signed` URLs or auth+permission; rely on `schedule:run`. |
| B4 | 🔴 | **Audit log can be wiped by a GET** — `audit-trail/clear` runs `Activity::truncate()` unguarded. Directly contradicts 5.9(a)(i)/(iii). | `AuditTrailController::clearLog()` | Remove clear; make activity records append-only; guard routes with `audit-trail` permission. |
| B5 | 🟠 | **Queue job runs synchronously.** `RunTransactionQuery` (ShouldQueue) is invoked via `->handle()`, so AI (10s timeout ×2) and HTTP screens block the API request. | `ApiController::store()` | `RunTransactionQuery::dispatch($txn)`; return 202. |
| B6 | 🟠 | **Duplicate cases on account-level rules.** Dedupe key is `(transaction_id, rule_id)`; 24hr/account rules re-flag the same account daily. | `checkIfTransactionAlreadyFlagged()` | For account-level triggers dedupe on `(account_no, rule_id, window)`. |
| B7 | 🟠 | **Case list paginated in PHP after loading all rows.** | `CaseManagementController::index()` | Use DB pagination (yajra DataTables already installed). |
| B8 | 🟠 | **CTR generation is a placeholder** (`generateCtr()` only logs). | `CronJobController::generateCtr()` | Implement cash-threshold CTR detection + goAML CTR output. |
| B9 | 🟠 | **Zero automated tests** for an AML system. | `tests/` has only stock examples | Pest/PHPUnit suites for engine/scoring/dedupe/export. |
| B10 | 🟡 | `app.zip` (50 MB) committed; stock README; `APP_TIMEZONE=UTC` (should be `Africa/Lagos`); `.env.example` defaults to SQLite. | repo | Housekeeping. |

> The SQLite `strftime()` → MySQL `DATE_FORMAT()` bug in `getFalsePositiveData()` was **already fixed and pushed** this session (commits `71ce7e6`, `3c91dbf`).

---

## 2. Register → codebase → action (row by row)

### 5.2 — CDD, KYC and KYB

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.2(a)(i) | End-to-end CDD/EDD/KYC/KYB + automated risk profiling + behavioural pattern analysis + historical data + ML/TF/PF typologies | 🟡 Risk profiling (`RiskRatingService`), scoring (`TransactionRiskScoringService`), rule typologies exist. **KYB absent** (no corporate/BO model). Risk rating runs **on-demand only** — not scheduled. | "Risk Profile 1 should be working automatically once per day, 11:59pm" | ① Schedule default-profile rating nightly 23:59 (new console command/cron). ② Add KYB entity + beneficial-owner models. |
| 5.2(a)(ii) | Risk-based CDD/EDD, **periodic and event-driven** reviews | 🟡 Time-based reviews exist: `RiskLevel.review_schedule_days` (+ "CDD/EDD Review Schedule (days)" input already in `risk-level.blade.php`), `Customer::scheduleNextReview()` keys off onboarding date, `reviewScheduler()` cron marks due/overdue. **Event-driven reviews absent.** | Review-due dates must follow each customer's own onboarding date (Jan 1 → Feb 1; Jan 15 → Feb 15) | ① Verify the scheduler end-to-end (it already keys off `date_onboarded`). ② Add **event-driven** triggers: risk-level change, PEP-status change, BO change, new transaction pattern, list match. |
| 5.2(a)(iii) | Continuous sync/linkage KYC/KYB ↔ risk profile ↔ txn data | 🟡 `CustomerSyncService` pulls from core bank (one-way, incremental). Risk score stored on customer; transactions separate. No consolidated linkage view. | Single view: STR/CTR history + KYC/KYB (occupation, source of funds, income range, business activity, geography) + credit/debit totals + PEP + risk level | Build the **Customer 360 / Single View** (also 5.2(a)(iv), 5.5(a)(vi), 5.9(a)(vi)). Re-rate/review on sync change. |
| 5.2(a)(iv) | Investigators see KYC/KYB + txn history + prior case outcomes in one interface | 🟡 `CaseManagementController::show()` builds partial `customerContexts` (`transactionStats()`, `strCount()`, `ctrCount()`, prior cases, PEP, risk level). | Same single view | Complete the 360 page and link it from every case/alert. |

### 5.3 — Sanction Lists & PEP Screening

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.3(a)(i) | Domestic + global lists for customers, BOs, related parties, transactions | 🟡 Internal list, NIBSS list, OFAC `CONS_ENHANCED.XML`. Missing **UN** and **Nigerian sanction list**; no BO screening. | "Ok" | Add UN Consolidated + Nigerian sanction list sources; screen BOs once KYB lands. |
| 5.3(a)(ii) | Fuzzy/name-variation matching with transparent config | 🟡 `WatchListService::searchList()` is naive `LIKE %x%` with no scoring/aliases. | "Ok" | Add phonetic/n-gram/alias matching with a configurable threshold + match score. |
| 5.3(a)(iii) | Real-time/near-real-time list updates | ❌ OFAC cached 24 h; others manual upload. | "Review Nigerian Sanction List, OFAC and UN Sanction List" | List-version registry + scheduled refresh job + "last updated" metadata. |
| 5.3(a)(iv) | Logs evidencing list updates & screening effectiveness | ❌ No list-update log. | "Logs to see update of the various sanction lists" | Log every list refresh (source, version, count, status) + screening-run stats. |
| 5.3(a)(v) | Institution-specific internal watchlists | ✅ Internal watchlist CRUD + upload exist. | Label on customer view: internal/NIBSS **watchlisted or delisted** | Add watchlist status badge to customer view (+ delist status). |
| 5.3(a)(vi) | Auto-flag PEPs & high-risk lists | 🟡 `isPep` flag + keyword PEP search (`ScreeningService::checkPep`) but **no auto-flag→EDD** pipeline. | "Our API connectivity should flag PEP automatically" | Auto PEP detection → flag `isPep` + create case + force EDD. |
| 5.3(a)(7) | Adverse media / negative news monitoring | 🟡 `ScreeningService::checkAdverseMedia()` is **on-demand only** (PAS screen form). | "Every 11:59pm, screen all customers onboarded in last 24h against PAS" | New nightly cron: PAS-screen last-24 h onboarded customers; auto-create cases on MATCH. |
| 5.3(a)(viii) | Interdiction/hold or block onboarding on confirmed match | ❌ Post-facto system. | "We cannot hold transactions — system is after the fact" | Document as a known constraint; still add an **account freeze/block flag** + workflow for future integration. |

### 5.4 — Risk Assessment

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.4(a)(i) | Configurable risk appetite (rules, scenarios, thresholds) | ✅ Thresholds + `RiskScoringConfig` factors + rule engine config. | "Just needs reset for stability" | Stabilise + document config; snapshot risk-appetite configs with versions. |
| 5.4(a)(ii) | Automated risk assessment at onboarding; dynamic adjustment | 🟡 On-demand `riskRate()`; `riskRateNewCustomers` cron rates **only last-24 h customers** daily 04:00. Default profile has **no seeder** (service `firstOrFail` on `isDefault`). | "Default risk profile should run in background — demo every 2 min, prod 11:59pm" | Seed default "Risk Profile 1"; schedule full re-rating nightly 23:59 (5 min demo mode via settings); re-rate on material change. |
| 5.4(a)(iii) | Enterprise-level ML/TF/PF risk assessment (products/segments/channels) | ❌ Absent. | — | Enterprise risk-assessment module (inherent risk by product/channel/jurisdiction). |
| 5.4(a)(iv) | AI/ML governance, human oversight, explainability | 🟡 `AiScore` stores `anomaly_reason`; no governance artifacts. | "Documented notes of explainability of how AI Alerts work" | AI explainability notes + model version + human-override audit trail. |
| 5.4(a)(v) | Reports on **changes** in risk classification + drivers | ❌ No risk-change history. (`RiskRatingResultsExport` already includes account number.) | "Export file must include customer account number" | Add `risk_level_change` history table (old→new, driver, date) + change report export. |
| 5.4(a)(vi) | External data sources | N/A | "Not a must have" | Skip (document as N/A). |

### 5.5 — Transaction Monitoring & Risk-Based Analyses

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.5(a)(i) | Predictive analytics / behavioural recognition / automated scoring | 🟡 Reactive rule engine + risk scoring + peer IQR + external AI. **No pre-emptive scoring engine.** | Pre-emptive alert engine: factors = past STR count, CTR, received money from a PEP account, ≥5 midnight txns in 7 days, same sender→same customer in 7 days, … score ≥5/7 → **PREEMPTIVE ALERT**; daily cron | Build `PreemptiveAlertService`: configurable factor list → daily score → pre-emptive case (`trigger_source = preemptive`). |
| 5.5(a)(ii) | Pre-emptive alerts enabling preventive action | ❌ | Same engine | Same; add "enhanced verification / hold" dispositions. |
| 5.5(a)(iii) | Multi-scenario rules + customer segmentation + typologies | 🟡 Peer grouping incomplete. | "Peer grouping not complete" | Finish peer-grouping (below) + segmentation-aware rules. |
| 5.5(a)(iv) | Scenarios use CDD/KYC/KYB attributes, not just raw patterns | 🟡 Factors are single-condition; no KYC/history-derived factors; trigger shows rule name, not TTR. | "Add button → add another parameter (multi-condition factors)"; base triggers on STR/CTR history + KYC/KYB; **"decommission rules in favour of transaction risk scoring"**; "Reason flagged → TTR 40" | ① Make `RiskScoringConfig.conditions` an **array** (multi-condition, AND/OR). ② Add factors: `high_frequency_transaction` (count of customer txns), PEP, risk-level, STR/CTR history. ③ Surface the **TTR score** as the case trigger reason. |
| 5.5(a)(v) | Related-party mapping, network analysis, peer-grouping + explainability | 🟡 Peer grouping works but undocumented. | "Proper documentation explaining how Peer Grouping works" | Peer-grouping docs + related-party/network analysis (shared account/BVN/device links). |
| 5.5(a)(vi) | Consolidated alert view for human decision | 🟡 Partial case context. | "Related to single view" | The Customer 360 page (once). |

### 5.6 — Fraud Monitoring (applicable only where the solution is used for fraud)

5.6(a)(i)–(vi): real-time channel fraud monitoring, fraud rule/model updates, unified AML/fraud workflow with segregation, fraud-registry interfaces, historical fraud trend analysis, fraud-event traceability. **The register leaves these empty** and the app has no dedicated fraud module — treat as **out of scope** unless the bank opts in. If opted in: add a `domain` (AML/CFT/CPF/fraud) tag on rules/cases and reuse the same case engine with segregated RBAC.

### 5.7 — Case Management

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.7(a)(i) | ECM: auto create/assign/prioritise/track + TAT | 🟡 Cases auto-created, but assignment is `getReviewer()` first-match (no round-robin), no prioritisation, **no TAT/SLA**. | "Setting for case TAT (e.g., 24 h for high-risk) → countdown clock on case; changing TAT affects **new cases only**, with a prompt" | ① `case_tat_hours` setting (per risk level). ② SLA countdown on case view + ageing columns. ③ Round-robin/workload assignment. ④ Change-prompt semantics (apply to new cases). |
| 5.7(a)(ii) | Role-based workflows, maker-checker, escalation | 🟡 Roles + permissions + escalate status exist; no formal maker-checker. | "OK" | Formal 4-eyes: reviewer disposition → supervisor approve before `closed_filed`/report. |
| 5.7(a)(iii) | Full audit trail of case actions | ✅ Activitylog per action. | "OK" | Ensure every case action logs actor+timestamp+rationale (make rationale a required field). |
| 5.7(a)(iv) | Reports on volumes, ageing, outcomes, trends | 🟡 CARRD/performance/false-positive dashboards exist; **no export**. | "CARRD + performance + false-positive should have **export buttons (PDF)**" | Add PDF (and CSV) export to CARRD, case-performance, and false-positive dashboards. |

### 5.8 — Reporting

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.8(a)(i) | STR/SAR/CTR/FTR generation, configurable formats/schedules | 🟡 goAML XML export + XSD validation (`XMLExportService`), STR + CTR rules exist; CTR cron is a stub. | "Ok — NFIU reporting" | Wire CTR detection→generation (B8); add FTR type; submission + acknowledgement tracking + 24 h-from-detection SLA. |
| 5.8(a)(ii) | Periodic MI reporting to CCO/SM/ECO/Board | ❌ Absent. | — | MI report pack (volumes, ageing, outcomes, trends) export. |
| 5.8(a)(iii) | Restrict external reporting to lawful authorities | N/A | — | Document (exports are role-permissioned). |

### 5.9 — Audit and Governance

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.9(a)(i) | Tamper-proof, immutable audit trail (config changes, access events, dispositions, report generation) | 🟡 Activitylog present but **truncatable** (B4); login logs only IP. | "Capture **user login devices**; check for other unlogged activities" | Remove truncate; add user-agent/device to login/logout; log config/rule/threshold/list changes (mostly present — audit gaps list). |
| 5.9(a)(ii) | Record user identity, date, timestamp, nature | ✅ | "OK" | Keep; ensure `causer` always set for system events. |
| 5.9(a)(iii) | Retention per regulatory periods | ❌ No retention policy. | "Maintain logs ≥5 years" | Retention config + scheduled archival (5-year floor). |
| 5.9(a)(iv) | Search & retrieval end-to-end | 🟡 Search form exists; reported **not working**; no export. | "Fix audit-trail search + export report" | Fix filter wiring (search `description` + `properties` + subject); add CSV/PDF export. |
| 5.9(a)(v) | Automated audit/governance reports | ❌ | "Report export capability" | Audit report export (date range, user, event type). |
| 5.9(a)(vi) | Forensic linkages across customer/txn/alert/actions/returns | 🟡 No unified view. | "Unified Single View page with search bar + PDF/CSV export" | Customer 360 page + global search + export. |
| 5.9(a)(vii) | Retrieve logs/trails without disrupting ops | 🟡 | "Export capability" | Async/streamed export. |

### 5.10 — System Integration & Scalability

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.10(a)(i) | Secure bidirectional integration with core banking/KYC | 🟡 One-way pull (`CustomerSyncService`). | "Integration squarely" | Add outbound/event push + webhook/API callbacks; make sync bidirectional. |
| 5.10(a)(ii) | Real-time monitoring not degraded by other tasks | 🟡 Currently blocked by B5 (sync jobs). | "System capability" | Queue workers + horizontal scaling; move all HTTP calls off request thread. |
| 5.10(a)(iii) | Well-documented standards-based APIs | ❌ | "API docs creation" | Generate API docs (Scribe/Swagger) for `POST /api/v1/transactions`. |
| 5.10(a)(iv)/(v) | Standardised data-exchange formats per CBN | 🟡 Ingest JSON; exports goAML/CSV. | — | Publish API schema + sample payloads (docs). |
| 5.10(a)(vi) | Legacy/third-party integration flexibility | 🟡 `CustomerSyncService` column-mapping is a good base. | — | Extend mapping-driven sync to other sources. |
| 5.10(a)(vii) | Scale with volume/products/channels | ❌ Untested. | "Stress test" | Load/stress test + document results; add indexes on hot queries. |

### 5.11 — Security & Data Protection

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.11(a)(i) | Collect/store only necessary data | 🟡 | — | Data-minimisation review. |
| 5.11(a)(ii) | Encryption at rest/in use/in transit | ❌ BVN/NIN stored plaintext; no `encrypted` casts. | "Data encryption" | `encrypted` casts for BVN/NIN; TLS/HSTS; DB/TDE; mask in UI. |
| 5.11(a)(iii) | RBAC | ✅ Spatie roles/permissions (minor route gaps). | "OK" | Close permission gaps (audit, cron, some rule/settings routes). |
| 5.11(a)(iv) | Secure auth incl. MFA | 🟡 Email OTP toggle exists (`email_otp_enabled`). | "OK" | Verify email-OTP flow; optionally TOTP (2FA app). |
| 5.11(a)(v) | NDPA compliance / sovereignty | 🟡 | — | DPA/consent handling; data-residency config; DPIA doc. |
| 5.11(a)(vi) | RTO/RPO via BIA | ❌ | "CTO to define timelines" | DR runbook + backup/restore verification (ops deliverable). |

### 5.12 — User Interface & Customisation

| Ref | Requirement | MoniSurv today | Register's ask | Action |
|---|---|---|---|---|
| 5.12(a)(i) | Real-time dashboards of metrics/alerts/cases | ✅ | "Ok" | — |
| 5.12(a)(ii) | User-friendly interface | ✅ | "Ok" | — |
| 5.12(a)(iii) | Multi-entity/currency/jurisdiction | N/A | "Not in scope for now" | Skip. |
| 5.12(a)(iv) | Configurable workflows/escalations under change-control | 🟡 | "OK" | Rule versioning + draft→approved lifecycle + change log (governance). |

---

## 3. Implementation plan (phased)

### Phase 0 — Stabilise & secure (do first; unblocks "OK" rows)

1. **B1** Fix 24hr rule engine (`processRule`/`determineSides` use `$accountNo`).
2. **B2** API auth middleware + rate limit.
3. **B3** Protect cron routes (`signed` or auth+permission).
4. **B4** Remove `audit-trail/clear`; append-only audit; guard audit routes.
5. **B5** Dispatch `RunTransactionQuery` to queue; API returns 202.
6. **B6** Account-level case dedupe on `(account_no, rule_id, window)`.
7. **B7** DB pagination for case list.
8. Timezone → `Africa/Lagos`; `.env.example` DB → MySQL; remove `app.zip`; real README.
9. **B9** First test suite: rule engine + scoring + dedupe + XML export.

### Phase 1 — Risk rating automation & the Customer 360 (5.2, 5.4, 5.5(a)(vi), 5.9(a)(vi))

10. Seed default **Risk Profile 1** (`isDefault`); fix `RiskRatingService` when no profile.
11. **Nightly auto-rating cron 23:59** (default profile) + demo interval setting (2 min).
12. **Risk-level change history** table + change/driver report export (5.4(a)(v)).
13. **Customer 360 / Single View page**: KYC/KYB (occupation, source of funds, income range, business activity, geography), PEP status, risk level + score, credit/debit totals, STR/CTR counts, prior cases/alerts, watchlist status (internal/NIBSS + delisted) — with **search bar + PDF/CSV export**.
14. Event-driven review triggers (risk-level change, PEP change, list match) on top of the periodic scheduler.

### Phase 2 — Screening upgrades (5.3)

15. Add **UN + Nigerian sanction lists** to `ScreeningService` sources.
16. **Fuzzy name matching** (phonetic/alias/n-gram) with configurable threshold + match scoring in `WatchListService`.
17. **List-version registry + refresh log** (source, version, count, timestamp) → satisfies 5.3(a)(iii)/(iv).
18. **Nightly PAS screening cron** (customers onboarded in last 24 h) + auto-case on match (5.3(a)(vii)).
19. **Auto PEP flag → EDD** pipeline (5.3(a)(vi)); watchlist status badge on customer view (5.3(a)(v)).

### Phase 3 — Monitoring intelligence (5.5)

20. **Pre-emptive alert engine** (`PreemptiveAlertService` + daily cron): configurable factors (past STR/CTR count, PEP-linked receipts, ≥5 midnight txns in 7 days, same-sender repeat in 7 days, …) → weighted score → **PREEMPTIVE ALERT** case (5.5(a)(i)/(ii)).
21. **Multi-condition risk factors** (AND/OR), plus `high_frequency_transaction` / KYC-history factors; show **TTR score** as trigger reason (5.5(a)(iv)).
22. Finish **peer grouping** + written explainability docs; related-party/network analysis (5.5(a)(iii)/(v)).
23. AI explainability notes + model-version governance (5.4(a)(iv)).

### Phase 4 — Case, reporting & governance (5.7, 5.8, 5.9)

24. **Case TAT/SLA**: `case_tat_hours` per risk level, countdown on case, "applies to new cases only" prompt, round-robin assignment, ageing metrics (5.7(a)(i)).
25. Maker-checker disposition flow (5.7(a)(ii)).
26. **Export buttons (PDF/CSV)** on CARRD, case-performance, false-positive dashboards (5.7(a)(iv)).
27. CTR generation + FTR + submission/ack tracking + STR 24 h SLA (5.8(a)(i)); MI report pack (5.8(a)(ii)).
28. Audit: fix search, add export, device logging, retention/archival (5.9(a)(i)–(v)).

### Phase 5 — Platform & security (5.10, 5.11, 5.12)

29. API docs (Scribe) + payload schema (5.10(a)(iii)–(v)); bidirectional sync (5.10(a)(i)).
30. Encrypted BVN/NIN, TLS/HSTS, UI masking (5.11(a)(ii)); NDPA docs.
31. Rule versioning + change-control workflow (5.12(a)(iv)).
32. Stress test + performance pass + index review (5.10(a)(vii)); DR/RTO-RPO runbook (5.11(a)(vi)).
33. Enterprise-level ML/TF risk-assessment module (5.4(a)(iii)).

---

## 4. Quick wins (low effort, high register impact)

| Task | Touches | Register rows |
|---|---|---|
| Nightly default-profile risk-rating cron | `routes/console.php`, `CronJobController` | 5.2(a)(i), 5.4(a)(ii) |
| Customer watchlist-status badge | customer show blade + model helper | 5.3(a)(v) |
| Audit-trail search fix + export | `AuditTrailController` + blade | 5.9(a)(iv) |
| Export buttons on CARRD/performance/false-positive | `CaseManagementController` + blades | 5.7(a)(iv) |
| Login device (user-agent) logging | `LoginController` | 5.9(a)(i) |
| Case TAT setting + countdown | `Setting` + case show blade | 5.7(a)(i) |
| Risk-level change history | new migration + `RiskRatingService` | 5.4(a)(v) |
| Pre-emptive alert engine (config-driven) | new service + cron + `FlaggedCase::SOURCE_PREEMPTIVE` | 5.5(a)(i)/(ii) |

---

*Next: if you paste rows 6–9, 18–20, 27–28 (the screenshot rows), I'll fold them into §2. Also happy to start implementing Phase 0 + the quick wins on `arena/01a09480-studious-train`.*
