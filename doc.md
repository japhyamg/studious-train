# MoniSurv — Working Documentation (`doc.md`)

> Living document of what has been implemented, why, and how to test it.
> Branch of record: `arena/01a09480-studious-train`.

---

## 1. What this app is

MoniSurv is a Laravel 11 AML/CFT transaction-monitoring platform for Nigerian
financial institutions. Core loop:

```
API transaction ingest → customer resolution → watchlist/PEP screen →
[transaction risk scoring → peer-group outlier → AI anomaly → rule engine] →
flagged case → review/classify → export (CSV/Excel/goAML XML STR/CTR)
```

Plus risk rating (CDD/EDD scheduling), RBAC (Spatie), activity logging
(Spatie), customer sync from a core-banking DB, and cron scheduling.

---

## 2. Change log

### 2.1 Fix MySQL `strftime()` error (commits `71ce7e6`, `3c91dbf`)

- **Problem:** `FlaggedCasesAnalyticService::getFalsePositiveData()` used
  SQLite-only `strftime('%Y-%m', created_at)` against MySQL → SQLSTATE 1305.
- **Fix:** replaced with MySQL `DATE_FORMAT(created_at, '%Y-%m')`.
- **Test:** open **Case Management → False Positives**; the page must load and
  show monthly rows without a 500.

### 2.2 Housekeeping (commit `0385c30`)

- `APP_TIMEZONE=UTC` → `Africa/Lagos` in `.env.example`.
- `.env.example` DB defaults switched from SQLite to MySQL.
- Untracked the 50 MB `app.zip` from Git (added to `.gitignore`).

### 2.3 Phase 0 — engine fix + security + queue (commit `3a062ff`)

**Fix: 24-hour rule engine was a silent no-op**
- `TransactionQueryService::processRule()` called `determineSides()`, which
  returned `['account' => null]` for cron runs, so every account was skipped.
  The `$accountNo` from `runViaCronJob()` was never used.
- Now: when there is no transaction (cron), the rule is evaluated against the
  actual `$accountNo`; when there is one (instant API), sides are resolved as
  before.
- **Test:** create/confirm an active `24HrTask` rule, seed transactions that
  should trip it, run `php artisan` → the cron URL (see §4) or
  `php artisan schedule:run`, then confirm a case is created in Case
  Management.

**Fix: account-level duplicate cases**
- Dedupe was `(transaction_id, rule_id)`; account-level rules re-flagged the
  same account daily.
- New helper `checkIfAccountAlreadyFlagged($accountNo, $ruleId, $hours=24)`;
  account-level triggers now dedupe on `(account_no, rule, 24h window)`.
- **Test:** run the 24hr engine twice in a row; the second run must not create
  duplicate cases for the same account+rule.

**Security: HMAC-signed API authentication**
- New middleware `app/Http/Middleware/ApiKeyAuth.php`, aliased `api.key` in
  `bootstrap/app.php`, applied to `POST /api/v1/transactions`.
- Requires headers `X-Client-Id`, `X-Timestamp` (unix seconds, ±5 min),
  `X-Nonce` (single use), `X-Signature`.
- Signature = `hash_hmac('sha256', signString, client_secret)` where signString
  is the newline-joined: `METHOD`, `path`, `timestamp`, `nonce`, raw body.
- Credentials come from `business_details` rows `client_id` / `client_secret`
  (created via Settings → "Generate API keys").
- **Test:** see §3 for a working example request.

**Security: cron endpoints restricted to admins**
- `/cron-job/*` now `auth` + `role:admin`. The scheduler (console.php) calls
  controller methods directly, so scheduled jobs are unaffected.
- **Test:** as a non-admin, hitting `/cron-job/24hr-rule-engine` must 403/302;
  as admin it works.

**Security: audit trail made append-only**
- Removed `AuditTrailController::clearLog()` (was `Activity::truncate()` via
  GET). Added `audit-trail/export` (CSV). Route group now guarded by
  `permission:audit-trail`.
- Search now also matches `properties` and `subject_type`.
- **Test:** Settings → Audit Trail; search a keyword; click **Export CSV**;
  confirm no "clear" action exists.

**Queue: API no longer blocks on long HTTP checks**
- `ApiController::store()` now `RunTransactionQuery::dispatch($transaction)`
  and returns **202 Accepted**.
- **Test:** POST a transaction; response is 202 (was 200); a `jobs` row appears
  in the `jobs` table; `php artisan queue:work` processes it.

**Observability: login device logging**
- Login/logout now log `ip` + `device` (user agent) in the activity log.

### 2.4 Automate risk rating (commit `0fd47c7`)

- Migration `2026_09_12_000001_seed_default_risk_profile.php` seeds a default
  **"Risk Profile 1"** (`isDefault`, active, data points for the simple score).
- New command `risk:rate` (`app/Console/Commands/RateCustomersCommand.php`)
  re-rates all customers with the default profile and schedules CDD/EDD
  reviews per risk level.
- Scheduled: `risk:rate` nightly 23:59; `risk:rate --demo` every 2 minutes
  (gated by setting `risk_rating_demo_mode`).
- Seeder sets `risk_rating_demo_mode` = `false` by default.

### 2.5 Phase 1 — risk-level change history + export buttons (commit `6456433`)

**Risk-level change history (CBN 5.4(a)(v))**
- New `risk_level_changes` table (migration `...000002...`) — append-only record
  of every risk classification change with `from_level`, `to_level`, `score`,
  and `driver`.
- `Customer::applyRiskLevel($level, $score, $driver)` centralises level
  updates: records a change when the classification moves and logs it in the
  activity log.
- All rating paths now route through it: `RiskRatingService::rateCustomer()`,
  `risk:rate` command, `CronJobController::riskRateNewCustomers()`, and
  `RiskRatingController::scheduleReviewsForRatedCustomers()`.
- **Test:** run `php artisan risk:rate`; then Risk Levels page →
  **"Risk Level Changes"** downloads an xlsx listing customer, account number,
  from → to level, score, driver. Re-run after editing a customer's data that
  changes their score; only genuine changes appear (no duplicates).

**Event-driven reviews (CBN 5.2(a)(ii))**
- `Customer::scheduleNextReview(bool $fromNow = false)` — periodic reviews are
  scheduled from onboarding/last-review date (matching the register example:
  onboarded Jan 1 → due Feb 1); a risk-category *change* reschedules from the
  change date.
- **Test:** after `risk:rate`, confirm `next_review_date` on a customer equals
  onboarding date + `review_schedule_days`. Change their level; confirm
  `next_review_date` moves to now + schedule days and a `risk_level_changes`
  row is written.

**Export buttons (CBN 5.7(a)(iv))**
- CARRD, Reviewer Performance, and False Positive dashboards now have **CSV**
  and **PDF** export buttons in the page header (new routes
  `carrd.export`, `performance.export`, `false-positive-dashboard.export`).
- **Test:** open each dashboard → click CSV (downloads) and PDF (downloads,
  landscape A4 via DomPDF).

### 2.6 Risk scoring config — modal + behaviour checks (commit `3927a52`)

**UI (`/transactions/risk-scoring-config`)**
- "Add Risk Factor" is now a **modal** (previously an inline side card); the
  Risk Scoring Factors table is now **full width**.
- Edit modal upgraded to match (factor name read-only, window days field).

**New "Behaviour / Derived" check type**
- `TransactionRiskScoringService` now computes behavioural aggregates over a
  configurable look-back window (`window_days`, default 7). Previously only raw
  `transaction`/`customer` columns were available, so checks like "count of
  transactions a customer makes" were impossible.
- New behaviour fields:
  - `transaction_count` — transaction frequency (high_frequency_transaction)
  - `debit_count` / `credit_count` — outgoing/incoming transaction counts
  - `debit_value` / `credit_value` — total outflow/inflow (₦)
  - `str_count` / `ctr_count` — prior STR/CTR cases for the account
  - `pep_receipt_count` — incoming transfers from PEP accounts
  - `midnight_transaction_count` — transactions 23:00–04:00
  - `same_sender_count` — max repeat from a single sender
  - `distinct_sender_count` — number of distinct senders
- Added missing transaction fields (NIN/BVN) and customer fields (occupation,
  source of funds, income range, business activity, employer name, LGA, risk
  score) to the dropdowns.

**Test**
- Open `/transactions/risk-scoring-config` → **Add Risk Factor** → set
  "Checks Against" = Behaviour / Derived → confirm the field list includes
  "Transaction Count (High Frequency)" and a "Window (days)" input appears.
- Create `HIGH_FREQUENCY_TRANSACTION` (≥ 20 in 7 days, weight 15) and confirm
  it shows in the full-width table with "(last 7d)".
- Send a transaction for a customer with ≥ 20 transactions in 7 days and check
  the risk breakdown in `transaction_risks.meta.score_breakdown` includes the
  factor.

### 2.7 Phase 2 — Customer 360 single view (commit `415e691`)

**Watchlist status label (CBN 5.3(a)(v))**
- Migration `...000003...` adds a `status` column (default `watchlisted`) to
  `internal_watch_lists` and `nibss_watch_lists`, so entries can be marked
  `delisted`.
- `Customer::watchlistStatus()` resolves the customer's standing across both
  lists (internal by account/BVN/NIN/name; NIBSS by BVN/name).
- The customer page header now shows badges: **Internal Watchlist**,
  **NIBSS Watchlisted** (red) or **NIBSS Delisted** (grey).
- **Test:** add a customer's BVN to the NIBSS list → open their profile → red
  "NIBSS Watchlisted" badge; set the entry `status=delisted` → grey "NIBSS
  Delisted" badge.

**Global search + export (CBN 5.9(a)(vi)/(vii))**
- New `customers/search` route: enter name/account/BVN/NIN and it resolves to
  the best-matching customer's 360 view (falls back to the filtered list).
- **CSV** and **PDF** buttons on the customer page export the full 360 record
  (identity, KYC/KYB, PEP, risk, credit/debit totals, STR/CTR, watchlist
  status, review date, risk-change history).
- **Risk-level change history** now renders on the profile (from Phase 1's
  `risk_level_changes`).

**Test**
- Customer page → search bar → type a BVN → lands on that customer's 360.
- Click CSV (downloads) and PDF (downloads, portrait A4).

### 2.8 Watchlist upload + full-width tables (commit `8c1d14a`)

**Upload was a placeholder — now it really imports.**
- Both `WatchListController::upload()` and `NIBSSWatchListController::upload()`
  only flashed "File uploaded successfully" without reading the file.
- New `WatchListImportService` reads CSV/XLSX via `Maatwebsite\Excel` and
  imports rows into `internal_watch_lists` / `nibss_watch_lists`.
  - **Header-aware**: recognises columns like `first_name`/`firstname`/`given
    name`, `account_no`/`account number`, `bvn`, `nin`, `category`, `reason`,
    `requesting_bank`, `watchlisted_date` (any case/underscore/space).
  - **Positional fallback** when there is no header row.
  - Skips empty rows and rows with no name/identifier; reports
    `created / skipped / errors` in the flash message.
- Files accepted: `.csv`, `.xlsx`, `.xls` (validated by **extension** via the
  `extensions` rule — not by finfo MIME sniffing, which mis-reports real Office
  files as `application/zip`/`application/octet-stream` and rejected valid
  uploads).

**Multi-sheet NIBSS BVN workbooks**
- The importer now reads **every worksheet** (via PhpSpreadsheet, which exposes
  sheet titles) instead of only the first sheet.
- Sheet title → status: `…Watchlisted…` → `watchlisted`, `…Delisted…` →
  `delisted`, `…Deceased…` → `deceased`.
- The column header is **located by scanning the first rows** (≥2 recognised
  columns), so title/meta rows above it — e.g. the title row and header row of
  a `BVN, NIN, FIRST NAME, MIDDLE NAME, SURNAME, ACCOUNT NO` sheet — are
  skipped automatically.
- **Internal columns**: `BVN, NIN, FIRST NAME, MIDDLE NAME, SURNAME, ACCOUNT NO`.
- **NIBSS columns**: `BVN, REQUESTING BANK, FIRST NAME, MIDDLE NAME, SURNAME,
  CATEGORY, WATCHLISTED DATE` (REASON optional). `watchlisted_date` is
  normalised to `Y-m-d` (Excel serials + d/m/Y, m/d/Y, d-m-Y, … layouts).
- Both watchlist tables now show a **Status** badge (watchlisted/delisted/
  deceased).

**UI**
- "Add Entry" and "Upload CSV/Excel" are now **modals**; the watchlist is a
  **full-width table** (for both internal and NIBSS pages).

**Test**
- Watch List → Internal → Upload CSV/Excel → choose a CSV with a header row
  (`first_name,last_name,account_no,bvn,nin`) → entries appear and the flash
  reports counts. Repeat on the NIBSS page.
- A headerless CSV (positional order) also imports.

### 2.9 Phase 3.1 — Sanction sources + sync logs

**What was added**
- New `watchlist_entries` table — generic store for downloaded sanction lists
  (OFAC Consolidated, UN Security Council Consolidated, Nigerian sanctions).
- New `watchlist_sync_logs` table — per-source refresh audit (status, record
  count, version, last-updated) to evidence CBN 5.3(a)(iii)/(iv).
- `WatchListSyncService` downloads + parses each source (OFAC `CONS_ENHANCED.XML`,
  UN `consolidated.xml`), replaces that source's entries in one transaction, and
  writes a sync log + activity() entry per refresh.
- `sanctions:sync {source?}` artisan command + daily 03:15 schedule
  (`routes/console.php`).
- Admin page **Watchlists → Sanction Lists** (`/sanctions`, `role:admin`) showing
  per-source record counts / last sync / version, a per-source and "Sync All"
  refresh, and the sync-log table.
- Sources config lives in `config/sanctions.php`. The **Nigerian sanctions** source
  ships disabled (no canonical machine-readable feed) — set `NIGERIA_SANCTIONS_URL`
  and `SANCTIONS_NIGERIA_ENABLED=true` to wire it up. OFAC/UN are enabled by
  default.

**Test**
- `php artisan migrate` then `php artisan sanctions:sync` (or the admin page →
  "Sync All Sources") → OFAC and UN rows appear in `watchlist_entries` and
  `watchlist_sync_logs`; the Nigerian source reports "skipped (no URL)".
- Watchlists → Sanction Lists page shows counts + last sync + log rows.

### 2.10 Phase 3.2 — fuzzy/scored watchlist matching

**What changed**
- New `WatchListMatcher` replaces plain `LIKE` matching in `WatchListService`.
  - 0–100 score: exact (100), token-set/reorder (98), subset (80–95), Jaccard
    overlap and phonetic (metaphone) overlap.
  - Exact BVN/NIN/account matches are decisive (score 100 + the matched field).
- `searchList()` now uses a broad DB prefilter (identifier equality OR first-word
  name prefix) and refines candidates with the matcher.
- Hits carry `match_score` and `matched_fields` in the case `trigger_details`.
- Threshold is `config('watchlists.match_threshold')` (default 75), overridable
  at runtime via the `watchlist_match_threshold` setting.

**Test**
- With an internal watchlist entry "John Doe / BVN 123…", a transaction from
  "John Doe" (or "Doe, John") triggers a watchlist case whose `trigger_details`
  includes a match_score ≥ 75 and the matched fields.
- A clearly different name (e.g. "Jane Smith") produces no case.

### 2.11 Phase 3.3 + 3.4 — nightly PAS + PEP auto-flag → EDD

**What changed**
- New `FlaggedCase::SOURCE_PAS` ("PEP / Sanctions Screening").
- `PassScreeningService::screenRecentCustomers()` screens customers onboarded in
  the last 24 h (PEP + sanctions + adverse media via `ScreeningService`), then:
  - sanctions hit → case (SOURCE_PAS) + risk bump to CRITICAL;
  - PEP hit → case + set `isPep` + raise risk to HIGH (or keep higher) + force an
    event-driven EDD review via `applyRiskLevel()`.
  - One PAS case per customer per day (dedup), so nightly re-runs don't spam.
- `pas:screen-recent-customers {--hours=24}` artisan command + daily 02:30
  schedule; manual trigger at `/cron-job/pas-screening` + a card on the Cron
  Jobs dashboard.

**Test**
- `php artisan pas:screen-recent-customers` (or Cron Jobs → Nightly PAS →
  "Run Now") → customers created in the last 24 h get `screening_results` rows;
  a PEP/sanctions hit creates a `pas` case and bumps risk.
- Re-running the same day does not duplicate the case.

### 2.12 Phase 3.5 — account interdiction (block/freeze) workflow

**What changed**
- `flagged_cases` gains `interdiction_status` (`none`/`frozen`/`lifted`),
  `interdicted_at`, `interdicted_by`.
- Case detail sidebar shows an **Account Interdiction** card: current status,
  who/when it was set, and a **Freeze Account / Lift Freeze** toggle
  (`POST /case-management/interdict/{slug}`), all logged via activity().
- Documented as **post-facto** — the flag records the decision; real-time
  core-banking enforcement needs a future integration (CBN 5.3(a)(viii)).

**Test**
- Open a case → sidebar → Freeze Account → status flips to "Account Frozen"
  with timestamp + user; activity log records the freeze. Toggle again to lift.

### 2.13 Phase 4.2 — multi-condition risk factors + TTR as trigger reason

**What changed**
- `risk_scoring_configs.conditions` now supports **multiple conditions with
  AND/OR logic**: a factor can be either the legacy single-condition object or
  `{logic: "AND"|"OR", conditions: [ … ]}`. The scoring engine normalises both
  shapes, so existing factors keep working unchanged.
- `TransactionRiskScoringService` evaluates factors with AND/OR semantics and
  records every matched sub-condition in the score breakdown (explainability).
- Risk-scoring config UI: **Match Logic (ALL/ANY)** selector + dynamic
  "Add Condition" rows in both the Add and Edit modals; the factors table
  renders every condition (joined by AND/OR).
- KYC/history-derived factors seeded: `HIGH_FREQUENCY_TRANSACTION`
  (`transaction_count` > 20 / 7d), `PRIOR_STR_HISTORY` (STR in 30d),
  `PRIOR_CTR_HISTORY` (CTR in 30d) — via migration, so existing installs get
  them.
- **TTR score as trigger reason**: risk-scored cases now carry
  `trigger_reason => "TTR {score}"` in `trigger_details`, and the case badge
  shows `TTR 40` instead of a plain "Risk Score Exceeded" (CBN 5.5(a)(iv)).

**Test**
- `php artisan migrate` → the three behaviour factors appear on the risk-scoring
  config page.
- Add a factor with two conditions joined by AND and one with OR → the table
  shows both conditions; scoring only awards the weight when the logic is
  satisfied.
- A risk-scored case shows `TTR {score}` as its trigger label.

### 2.14 Phase 4.3 — peer-group analysis completion

**What was missing / fixed**
- The peer-grouping page previously only showed a settings form. It now includes:
  - **Outliers table** — every screened transaction with customer, group, amount,
    threshold, amount-exceeded and an Outlier / Within-Range badge (paginated).
  - **Current thresholds table** — per field and group: sample size, Q1, Q3,
    IQR and the upper threshold (Tukey's fences), plus computed time.
  - **Manual "Recompute Thresholds"** action (`POST /peer-grouping/recompute`).
  - **Plain-language explainer** of the IQR method (what fields, how thresholds
    are computed, how to read them) — CBN 5.5(a)(iii).
- Robustness: peer-group fields are now validated against real customer columns
  before being used in SQL; `pg_selected_fields` has a safe default; thresholds
  are stored with full Q1/Q3/IQR detail (backward-compatible with legacy scalar
  rows); the settings page handles clearing all selections.
- Related-party / network analysis (graph of counterparties) is tracked as a
  separate follow-up and not part of this completion.

**Test**
- Peer Groups → select fields → Save → "Recompute Thresholds" → thresholds
  table populates per group.
- Process a transaction above its group's upper threshold → an "Outlier" row
  appears and a peer-group case is created.

### 2.15 Phase 4.1 — pre-emptive behavioural alert engine

**What changed**
- New `FlaggedCase::SOURCE_PREEMPTIVE` ("Pre-emptive Alert").
- `config/preemptive.php` — config-driven factors (prior STR/CTR, PEP receipts,
  midnight activity, repeat sender, high frequency, high credit volume) each
  with operator/value/window/points; threshold defaults to 5 and is overridable
  via the `preemptive_alert_threshold` setting.
- `PreemptiveAlertService::scoreAllCustomers()` scores every customer (chunked)
  and raises a PREEMPTIVE case when points ≥ threshold, carrying the full factor
  breakdown (`factor`, expected vs actual, points) in `trigger_details`. One
  alert per customer per day.
- Behaviour metrics extracted to `CustomerBehaviourService` and now shared with
  the transaction risk scorer (single source of truth).
- `preemptive:score` command + daily 02:45 schedule; manual trigger
  `/cron-job/preemptive-alerts` + a Cron Jobs status card.
- Fix: `flagged_cases.transaction_rule_id` made nullable (non-rule sources —
  watchlist/risk/peer/AI/preemptive/PAS — create cases without a rule).
- Fix: PAS cases use `type = account` (the column's enum is transaction|account).

**Test**
- `php artisan migrate` then `php artisan preemptive:score` → customers meeting
  the factor threshold get a "Pre-emptive Alert" case with the factor breakdown.

### 2.16 Phase 4.4 — AI explainability + model version

**What changed**
- `ai_scores` gains `model_version` and `explanation` columns.
- `AIDetectionService` captures the ML response's `model_version` and builds a
  plain-language `explanation` (anomaly decision, score, severity, reason, model).
- AI cases carry `model_version` + `explanation` in `trigger_details`; the AI
  alerts page shows the model version under the score (CBN 5.4(a)(iv)).

**Test**
- With the ML server reachable, a flagged anomaly records its model version and
  explanation; the AI alerts page shows `v…` beneath the score.

---

## 3. Testing the authenticated API (worked example)

1. **Get credentials.** Settings → "Generate API keys" writes `client_id` and
   `client_secret` to `business_details`. (Or in `tinker`:
   `storeBusinessClientIdAndSecret();` then read both rows.)
2. **Build a signed request.** PHP reference client:

```php
$clientId  = '<client_id from business_details>';
$secret    = '<client_secret from business_details>';
$body      = json_encode([
    'bankId' => 'BANK1',
    'sender_first_name' => 'Ada', 'sender_last_name' => 'Obi',
    'sender_account_no' => '1000000001', 'sender_nin' => 'NIN1', 'sender_bvn' => 'BVN1',
    'beneficiary_first_name' => 'Bola', 'beneficiary_last_name' => 'Ade',
    'beneficiary_account_no' => '1000000002', 'beneficiary_nin' => 'NIN2', 'beneficiary_bvn' => 'BVN2',
    'amount' => 1500000,
    'transaction_type' => 'credit',
    'narration' => 'Test transfer',
    'channel' => 'bank',
    'transaction_datetime' => now()->toIso8601String(),
]);
$timestamp = (string) time();
$nonce     = bin2hex(random_bytes(16));
$signature = hash_hmac('sha256',
    "POST\napi/v1/transactions\n{$timestamp}\n{$nonce}\n{$body}", $secret);

$ch = curl_init('https://your-host/api/v1/transactions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "X-Client-Id: {$clientId}",
        "X-Timestamp: {$timestamp}",
        "X-Nonce: {$nonce}",
        "X-Signature: {$signature}",
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$res = curl_exec($ch);
var_dump($res); // expect 202 {"status":"success"}
```

**Negative tests:** missing headers → 401; wrong signature → 401; replayed
nonce → 401; expired timestamp → 401.

---

## 4. Deployment checklist (after pulling)

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate                  # seeds default Risk Profile 1 + risk_level_changes
php artisan db:seed --class=DemoDataSeeder   # demo users/roles/settings (optional)

# Verify schedulers are registered (expect risk-rating, risk-rating-demo, …)
php artisan schedule:list

# Manual run of the automated risk rating
php artisan risk:rate

# Enable demo cadence (re-rate every 2 min) — then disable after demo
# (Settings table: risk_rating_demo_mode = true)
```

**Environment notes**
- `.env` should use MySQL, `APP_TIMEZONE=Africa/Lagos`.
- The queue must run for transaction processing:
  `php artisan queue:work` (production: supervisor, `database` driver).

---

## 5. Roadmap (see `docs/IMPLEMENTATION-PLAN.md` for full detail)

- ✅ **Phase 0** — stabilise & secure.
- ✅ **Quick win 1** — automated risk rating (default profile + nightly 23:59).
- ✅ **Quick win 2** — export buttons on CARRD / case-performance /
  false-positive dashboards.
- ✅ **Phase 1** — risk-level change history + event-driven reviews.
- ✅ **Phase 2** — Customer 360 single view (search + PDF/CSV export).
- ✅ **Phase 3** — screening as a service (3.1 sanction sources + sync logs;
  3.2 fuzzy/scored matching; 3.3 nightly PAS; 3.4 PEP auto-flag; 3.5
  block/freeze flag — all done).
- ✅ **Phase 4** — pre-emptive alert engine + TTR scoring (4.1 pre-emptive
  alerts, 4.2 multi-condition factors + TTR-as-reason, 4.3 peer-group
  completion, 4.4 AI explainability — all done).
- ⬜ **Phase 5** — case SLA/TAT, maker-checker, CTR generation, audit
  retention + exports.
- ⬜ **Phase 6** — API docs, encryption, rule versioning, stress test, DR.

---

## 6. Known caveats / follow-ups

- No automated test suite yet (planned in Phase 0 task 0.9).
- `getReviewer()` picks the first assigned user (no round-robin yet).
- `createCaseSlug()` uses `count()+1` — fine for low concurrency, revisit for
  high-volume queue workers.
- BVN/NIN are stored in plaintext — encryption lands in Phase 6.
- Register screenshot rows 6–9, 18–20, 27–28 still need pasting to complete
  the gap matrix.
