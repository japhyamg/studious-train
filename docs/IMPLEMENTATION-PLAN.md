# MoniSurv — App Review, Directions & Implementation Plan

> Consolidated from the codebase review and the CBN AML Baseline Requirements Register.
> Prior docs (for row-by-row detail): `docs/CBN-baseline-register-gap-analysis.md`, `docs/CBN-AML-baseline-gap-analysis.md`.
> Branch of record: `arena/01a09480-studious-train`. Date: 2026-09-12.

---

## 1. The short version — where to take this app

The app is a working **reactive** AML detector: transactions arrive, hit rules/risk/peer/AI screens, become cases, get reviewed and exported as goAML XML. That foundation is sound.

The register (and your own comments in it) point in one consistent direction:

> **Move from rule-flagging to risk-driven monitoring** — a system that (a) re-rates every customer automatically on a schedule, (b) evaluates alerts in the context of a **single, complete customer view**, (c) generates **pre-emptive** behavioural alerts from scored factors, and (d) is **defensible** end-to-end (SLA, maker-checker, immutable audit, exports everywhere).

So the plan is organised around **six directions**, each closing a cluster of register rows, sequenced after a short stabilisation phase.

### The six directions

| # | Direction | Closes register rows | Effort |
|---|---|---|---|
| **D1** | **Stabilise & secure the engine** (fix the silent no-ops and auth holes) | unblocks 5.5, 5.8, 5.9 "OK"s | S–M |
| **D2** | **Automate risk rating on a schedule** (default profile, nightly re-rating, change history) | 5.2(a)(i), 5.4(a)(i)(ii)(v) | M |
| **D3** | **Build the Customer 360 single view** (KYC/KYB + behaviour + cases + watchlist, searchable + exportable) | 5.2(a)(iii)(iv), 5.5(a)(vi), 5.9(a)(vi), 5.3(a)(v) | L |
| **D4** | **Make screening a service, not a button** (nightly PAS, list updates + logs, fuzzy matching, PEP auto-flag) | 5.3(a)(i)–(vii) | L |
| **D5** | **Pre-emptive behavioural alerting** (scored factors → PREEMPTIVE ALERT; TTR as the trigger reason) | 5.5(a)(i)(ii)(iv), 5.4(a)(iv) | M–L |
| **D6** | **Defensibility & reporting** (case SLA/TAT, maker-checker, exports, CTR, audit retention) | 5.7, 5.8, 5.9 | M–L |

---

## 2. App review — current state

### 2.1 What's genuinely good (keep and build on)

- **Clean detection pipeline** (`ApiController` → `TransactionService` → `RunTransactionQuery` → `TransactionQueryService`/`TransactionRiskScoringService`/`PeerGroupAnalysisService`/`AIDetectionService`), with a unified `FlaggedCase` and a trigger-source taxonomy (`rule`, `watchlist`, `risk_score`, `ai_anomaly`, `peer_group`).
- **goAML XML export + XSD validation** (`XMLExportService`) — a hard, non-trivial compliance feature that is already real.
- **Customer sync** (`CustomerSyncService`) — incremental watermark sync, configurable column mapping, batch chunking, per-row error isolation. This is the right pattern for core-banking integration.
- **Risk rating** (`RiskRatingService`) — profile templates (Excel import) + fallback heuristic, levels with CDD/EDD + `review_schedule_days`, review scheduling (`reviewScheduler`, `reviews-due`).
- **RBAC + activity logging** (Spatie) with a sensible permission catalogue and roles (admin/supervisor/reviewer/auditor).
- **Configurable rules/scoring** — rule engine with column + aggregation ("advance") conditions; weighted risk factors (`risk_scoring_configs`).

### 2.2 What's broken or missing (verified)

**Critical — fix before anything else:**

| # | Defect | Proof in code |
|---|--------|---------------|
| B1 | **24-hour rule engine is a silent no-op.** Cron runs get `['account' => null]` from `determineSides()` and the loop `continue`s — the `$accountNo` from `runViaCronJob()` is never used. | `TransactionQueryService::processRule()` |
| B2 | **Transaction API is unauthenticated.** API keys exist in Settings but nothing validates them. | `routes/api.php` (no middleware) |
| B3 | **Cron endpoints are public GETs that mutate data.** | `routes/web.php` cron group |
| B4 | **Audit log can be wiped by GET.** `Activity::truncate()` on `audit-trail/clear`, unguarded. | `AuditTrailController::clearLog()` |
| B5 | **Queue job runs in-request.** `(new RunTransactionQuery($txn))->handle()` — AI/PEP/OFAC HTTP calls block the API. | `ApiController::store()` |
| B6 | **Account-level cases duplicate across daily runs.** Dedupe key is `(transaction_id, rule_id)` only. | `checkIfTransactionAlreadyFlagged()` |
| B7 | **Case list paginates in PHP after loading all rows.** | `CaseManagementController::index()` |
| B8 | **CTR generation is a placeholder** (logs and returns). | `CronJobController::generateCtr()` |
| B9 | **No automated tests** for an AML system. | `tests/` = stock examples only |
| B10 | `app.zip` (50 MB) in Git; stock README; `APP_TIMEZONE=UTC`; `.env.example` defaults to SQLite. | repo |

**Register-driven gaps (your own comments, verified):**

- **Risk rating is manual-only.** The UI is a "Generate" button + profile dropdown; there is **no default `RiskProfile` seeded** (service does `firstOrFail` on `isDefault`), and the only automated path rates *last-24-h customers* at 04:00. Your ask: default profile runs nightly 23:59 (demo: every 2 min).
- **No risk-change history.** Nothing records *old → new level and the driver*, so 5.4(a)(v) can't be met (the export does include account number today — good).
- **KYC/KYB enrichment fields exist but are empty.** `occupation`, `source_of_funds`, `income_range`, `business_activity`, `employer_name` are in the `Customer` fillable + sync mapping, but the demo data doesn't populate them. The Customer 360 depends on the core-banking sync actually delivering these.
- **Customer 360 is partial.** Case view shows STR/CTR counts, credit/debit stats, PEP, risk level — but there's no standalone searchable customer page, no watchlist-status label, no export.
- **Screening is on-demand only.** PEP/media/sanctions run when a user clicks "Screen" in PAS. Your ask: nightly auto-screen of customers onboarded in the last 24 h; PEP auto-flag.
- **Watchlist matching is naive** (`LIKE %x%`), **no list-update logs**, **no UN/Nigerian sanction list**, **no delist status** on the customer.
- **No pre-emptive alerting.** The team's "score factors → 5/7 → PREEMPTIVE ALERT" engine doesn't exist.
- **Case workflow lacks SLA/maker-checker.** Assignment is `getReviewer()` = first match (no round-robin); no TAT countdown; close is single-user.
- **No exports** on CARRD / case-performance / false-positive dashboards; audit-trail search is reported broken and has no export; no device logging; no 5-year retention.
- **No API docs, no stress test, no RTO/RPO, no encryption of BVN/NIN** at rest.

### 2.3 What "OK" in the register really means

Several rows are marked OK/Ok that are **present but not defensible yet**:
- **5.3(a)(i)/(ii)** "Ok" — lists exist, but matching is `LIKE`, no UN/NG lists, no update logs.
- **5.4(a)(i)** "OK" — configurable, but no versioning/change-control on rules and thresholds.
- **5.7(a)(ii)/(iii)** "OK" — roles + activitylog exist, but no maker-checker and the audit can be truncated.
- **5.9(a)(i)–(iii)** "OK" — activitylog exists, but wipeable, no device capture, no retention policy.
- **5.11(a)(iii)/(iv)** "OK" — RBAC/MFA exist, but route permission gaps and plaintext BVN/NIN remain.

Plan treats these as "finish, don't assume".

---

## 3. Directions (recommended approach per workstream)

### D1 — Stabilise & secure (approach)
- **Fix the engine bug first.** The single highest-leverage change is making `runViaCronJob()` actually evaluate: thread `$accountNo` through `processRule()` and drop the `determineSides()` null branch for cron. Then prove it with a test that seeds a 24Hr rule + transactions and asserts a case is created.
- **Auth model:** API uses an **HMAC signature** (client_id + timestamp + nonce + `hash_hmac('sha256', payload, secret)`) validated by middleware, plus per-client rate limits. Reuse the already-generated `client_id`/`client_secret` in `business_details`.
- **Cron:** make `/cron-job/*` routes `signed` (or auth+permission) so only `schedule:run` (server cron) triggers them; the `/cron-jobs` status page stays behind `auth`.
- **Audit:** delete `clearLog()`; treat `activity_log` as append-only (DB grants: insert/select, no update/delete); add `user_agent` to login/logout logging.
- **Queue:** `RunTransactionQuery::dispatch($txn)` and return `202 Accepted` with a transaction id; run `queue:listen/work` in production.

### D2 — Automate risk rating (approach)
- **Seed a default profile** ("Risk Profile 1", `isDefault=true`) so `resolveRiskProfile()` never `firstOrFail`s into a 500.
- **Schedule:** a new console command `risk:rate --profile=default` invoked at **23:59**; a settings key (`risk_rating_demo_interval_minutes`) drives a 2-min demo cadence without code changes. Use `withoutOverlapping()` and chunking (already chunked).
- **Change history:** new `risk_level_changes` table (customer_id, from_level, to_level, score, driver, rated_at) written by `RiskRatingService::rateCustomer()`; export from it. This is what makes 5.4(a)(v) real, not just "a report of current levels".

### D3 — Customer 360 single view (approach)
- One route (`customers/show/{id}` exists — extend it, or add `customers/360/{account}`) rendering: identity + KYC/KYB, PEP, risk level/score + change history, credit/debit totals, STR/CTR counts, prior + open cases/alerts, watchlist status (internal / NIBSS — watchlisted **or delisted**).
- **Data dependency:** the page is only as good as the sync. Ensure `CustomerSyncService` mapping delivers `occupation`, `source_of_funds`, `income_range`, `business_activity`, `employer_name` (it already maps them — confirm the core-bank side provides them). Add a global search bar + PDF/CSV export (DomPDF is already a dependency).
- **Reuse the model helpers** already written (`transactionStats()`, `strCount()`, `ctrCount()`, `flaggedCases()`) but refactor `transactions()`/`flaggedCases()` from query-builder-returning methods into real relations so they can eager-load and be reused everywhere.

### D4 — Screening as a service (approach)
- **Lists:** add UN Consolidated + Nigerian sanction list sources beside OFAC; store per-list **version + last-updated + count** in a `watchlist_sync_logs` table; refresh on schedule (not only on demand). Every refresh writes an audit line → 5.3(a)(iii)/(iv).
- **Matching:** replace `LIKE` with a scored matcher (exact, normalized, phonetic/metaphone, token reorder, alias) with a configurable threshold; store the match score and matched fields on the hit.
- **Nightly PAS cron:** `pas:screen-recent-customers` (last 24 h) → runs `ScreeningService::screen()` → creates cases on MATCH/LISTED.
- **PEP auto-flag:** when a screening or sync marks PEP, set `isPep`, bump risk, force EDD, create a case, and log it.
- **Interdiction:** the register notes the system is post-facto. Document this as an explicit limitation and add an **account block/freeze flag + workflow** so it's ready when real-time integration exists.

### D5 — Pre-emptive behavioural alerting (approach)
- New `PreemptiveAlertService` + `FlaggedCase::SOURCE_PREEMPTIVE`. Factors are **config-driven** (a new table or JSON setting): past STR count, past CTR count, received from a PEP account, ≥5 midnight transactions in 7 days, same sender → same customer in 7 days, etc. Daily cron scores each customer; ≥ threshold (e.g. 5/7) creates a PREEMPTIVE ALERT case with the factor breakdown in `trigger_details` (this *is* the explainability for 5.4(a)(iv)).
- **Align with your "decommission rules → TTR" comment:** extend `risk_scoring_configs` to **multi-condition factors** (AND/OR) and add KYC/history-derived factors (`high_frequency_transaction`, PEP, risk-level, STR/CTR history). Surface the **TTR score** as the case trigger reason instead of just a rule name.
- **Peer grouping:** finish it + publish a plain-language explainer (what fields, how thresholds are computed — IQR — and how to read it).

### D6 — Defensibility & reporting (approach)
- **Case SLA/TAT:** settings per risk level (`case_tat_hours`); countdown + ageing on the case page; a change affects **new cases only** (capture TAT at case creation); round-robin assignment; escalation reminders.
- **Maker-checker:** reviewer proposes disposition → supervisor approves for `closed_filed`/report. Keep the full activity trail of each step.
- **Exports:** PDF/CSV buttons on CARRD, case-performance, false-positive (and the 360 page + audit trail).
- **CTR:** implement cash-threshold detection (the CTR rules already exist — wire them to the stub cron) and emit goAML CTR XML; add filing status + STR 24-h-from-detection SLA tracking.
- **Audit:** fix the search (search `description` + `properties` + subject), add export, device logging, 5-year retention config + archival.
- **API docs:** Scribe/Swagger for the ingestion API (5.10(a)(iii)–(v)).
- **Security:** `encrypted` casts for BVN/NIN, TLS/HSTS, UI masking, NDPA note, stress test + RTO/RPO runbook.

---

## 4. Implementation roadmap

Effort: S = ≤1 day · M = 2–4 days · L = 1–2 weeks. "Reg" = register refs closed.

### Phase 0 — Stabilise & secure (≈1 week) — *start here*
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 0.1 | Fix 24hr rule engine (thread `$accountNo`) | `TransactionQueryService`, `RuleEvaluationEngine` | M | 5.5 |
| 0.2 | API HMAC auth middleware + rate limit | new middleware, `routes/api.php` | M | 5.10, 5.11 |
| 0.3 | Protect cron routes (`signed`) | `routes/web.php` | S | 5.9 |
| 0.4 | Remove audit truncate; append-only | `AuditTrailController`, migration | S | 5.9 |
| 0.5 | Dispatch job to queue; 202 response | `ApiController`, `RunTransactionQuery` | S | 5.10(a)(ii) |
| 0.6 | Account-level dedupe key | `TransactionQueryService`, helper | S | 5.5 |
| 0.7 | DB pagination for case list | `CaseManagementController` | S | 5.12 |
| 0.8 | Timezone/`.env`/README/`app.zip` cleanup | repo | S | — |
| 0.9 | First test suite (engine, scoring, dedupe, XML) | `tests/` | M | — |

**Exit criteria:** 24hr cron provably creates cases; API rejects unsigned requests; cron/audit routes protected; tests green in CI.

### Phase 1 — Automated risk rating + change history (≈1–1.5 weeks)
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 1.1 | Seed default "Risk Profile 1" | `DemoDataSeeder` + migration | S | 5.2(a)(i), 5.4(a)(ii) |
| 1.2 | Nightly `risk:rate` command at 23:59 + demo interval setting | `routes/console.php`, new command | M | 5.4(a)(ii) |
| 1.3 | `risk_level_changes` table + write on re-rate + export | migration, `RiskRatingService`, export | M | 5.4(a)(v) |
| 1.4 | Event-driven review triggers (risk/PEP/list change) | `Customer::scheduleNextReview` hooks | M | 5.2(a)(ii) |

### Phase 2 — Customer 360 (≈1.5–2 weeks)
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 2.1 | Refactor customer relations (real `hasMany`) | `Customer` | M | 5.2(a)(iii) |
| 2.2 | 360 page + global search + watchlist label + export | controller, blades, export | L | 5.2(a)(iv), 5.5(a)(vi), 5.9(a)(vi), 5.3(a)(v) |
| 2.3 | Confirm sync delivers KYC/KYB fields | `CustomerSyncService` config | S | 5.2(a)(iii) |

### Phase 3 — Screening service (≈2 weeks)
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 3.1 | UN + Nigerian lists; `watchlist_sync_logs` + refresh cron | `ScreeningService`, migration, cron | M | 5.3(a)(i)(iii)(iv) |
| 3.2 | Fuzzy/scored matching + hit scoring | `WatchListService` | M | 5.3(a)(ii) |
| 3.3 | Nightly PAS cron (last-24h onboarded) + auto-case | `CronJobController`, command | M | 5.3(a)(vii) |
| 3.4 | PEP auto-flag → EDD pipeline | `ScreeningService`, `Customer` | M | 5.3(a)(vi) |
| 3.5 | Block/freeze flag + workflow (post-facto note) | `FlaggedCase`, blade | S | 5.3(a)(viii) |

### Phase 4 — Pre-emptive alerts + TTR scoring (≈2 weeks)
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 4.1 | `PreemptiveAlertService` + daily cron + `SOURCE_PREEMPTIVE` | new service, model const, cron | M | 5.5(a)(i)(ii) |
| 4.2 | Multi-condition risk factors + KYC/history factors + TTR as reason | `RiskScoringConfig`, `TransactionRiskScoringService`, blades | M–L | 5.5(a)(iv) |
| 4.3 | Peer-grouping docs + network/related-party analysis | docs, `PeerGroupAnalysisService` | M | 5.5(a)(iii)(v) |
| 4.4 | AI explainability notes + model version | `AiScore`, `AIDetectionService` | S | 5.4(a)(iv) |

### Phase 5 — Case, reporting & governance (≈2–3 weeks)
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 5.1 | Case TAT/SLA per risk level + countdown + round-robin | migration, `CaseManagementController`, helpers | M | 5.7(a)(i) |
| 5.2 | Maker-checker disposition flow | `CaseManagementController`, blades | M | 5.7(a)(ii) |
| 5.3 | Export buttons (CARRD/performance/false-positive/360/audit) | controllers, blades | M | 5.7(a)(iv), 5.9(a)(iv)(v) |
| 5.4 | CTR detection + goAML CTR + filing status + STR SLA | `CronJobController`, `XMLExportService`, migration | M | 5.8(a)(i) |
| 5.5 | Audit search fix + device logging + retention/archival | `AuditTrailController`, `LoginController` | M | 5.9(a)(i)–(v) |
| 5.6 | MI report pack (CCO/Board) | new service + views | M | 5.8(a)(ii) |

### Phase 6 — Platform & security (≈2 weeks)
| # | Task | Files | Effort | Reg |
|---|------|-------|--------|-----|
| 6.1 | API docs (Scribe) + payload schema | docs | M | 5.10(a)(iii)–(v) |
| 6.2 | Encrypted BVN/NIN + TLS/HSTS + UI masking | `Customer`, config | M | 5.11(a)(ii) |
| 6.3 | Rule versioning + change-control workflow | `TransactionRule`, migrations | L | 5.12(a)(iv) |
| 6.4 | Enterprise ML/TF risk assessment module | new models/views | L | 5.4(a)(iii) |
| 6.5 | Stress test + index review; DR/RTO-RPO runbook | ops | M | 5.10(a)(vii), 5.11(a)(vi) |
| 6.6 | NDPA/consent documentation | docs | S | 5.11(a)(v) |

---

## 5. Dependencies & sequencing

```
Phase 0 (stabilise)  ──────────────────────────────► everything depends on this
   │
   ├─► Phase 1 (risk rating automation) ──► Phase 2 (360) needs rating + change history
   │
   ├─► Phase 3 (screening service) ──► 360 shows watchlist status (5.3(a)(v))
   │
   └─► Phase 4 (pre-emptive/TTR) ──► needs Phase 1 ratings + Phase 2 KYC data (5.5(a)(iv))
   
Phase 5 (case/report/governance) can start after Phase 0, in parallel with 1–4.
Phase 6 (platform/security) runs last, except 6.2 (encryption) which is independent and can go early.
```

**Two quick wins worth doing in the first sprint** (highest register payoff per line of code):
1. **Default risk profile + nightly 23:59 rating cron** (5.2(a)(i), 5.4(a)(ii)) — small, and it's the first comment in the register.
2. **Export buttons + audit search fix** (5.7(a)(iv), 5.9(a)(iv)) — small, and they turn existing "OK" rows into actually-finished rows.

---

## 6. Risks & open questions

1. **Screenshot rows 6–9, 18–20, 27–28** — need pasting to complete the register mapping (likely the "Institutions shall" governance duties).
2. **Fraud scope (5.6)** — is the solution being offered for fraud too? If yes, add the domain tag + segregation; if no, document out-of-scope.
3. **KYB source** — beneficial ownership has no data source yet; confirm where BO data comes from (core bank? manual entry?).
4. **Interdiction (5.3(a)(viii))** — confirm the "post-facto, cannot hold" position is documented to CBN as a limitation, since the baseline expects the capability.
5. **Core-bank data contract** — the Customer 360 and 5.5(a)(iv) depend on `occupation`/`source_of_funds`/`income_range`/`business_activity` actually arriving via sync. Confirm the source system provides them.
6. **23:59 vs 04:00** — current crons run 01:00–05:00; your comments ask for 23:59. Decide the canonical batch window (single nightly batch is cleaner operationally).

---

## 7. Definition of done (per register)

- Every register row has one of: ✅ implemented + test, 🟡 documented partial with a dated gap, or ❌ documented out-of-scope with business sign-off.
- The 24hr engine, PEP/media screening, and pre-emptive alerts are **provably creating cases** (automated tests + demo data).
- Any "OK" in the register is backed by a **screen recording / test** showing it working, not assumed.
- Audit is append-only with device logging, search, export, and a 5-year retention config.
- API is documented and authenticated; BVN/NIN are encrypted at rest.

---

*Ready to execute: say the word and I'll start on **Phase 0** (fixes + tests) plus the two quick wins on `arena/01a09480-studious-train`, committing and pushing as I go.*
