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
- ⬜ **Phase 2** — Customer 360 single view (search + PDF/CSV export).
- ⬜ **Phase 3** — screening as a service (nightly PAS, list-update logs,
  fuzzy matching, PEP auto-flag).
- ⬜ **Phase 4** — pre-emptive alert engine + multi-condition TTR scoring.
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
