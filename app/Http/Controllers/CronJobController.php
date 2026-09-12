<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\RiskLevel;
use App\Models\Transaction;
use App\Services\TransactionQueryService;
use App\Services\WatchListService;
use App\Services\RiskRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\CustomerSyncService;

class CronJobController extends Controller
{
    /**
     * 1. CDD/EDD Review Scheduler
     *    Runs daily — checks all customers and updates review statuses
     *    - Sets customers past their next_review_date to 'due' or 'overdue'
     *    - Schedules reviews for newly rated customers who don't have one yet
     *
     *    URL: /cron-job/review-scheduler
     */
    public function reviewScheduler()
    {
        $updated = 0;
        $scheduled = 0;

        // Step 1: Mark overdue customers
        $overdueCustomers = Customer::whereNotNull('next_review_date')
            ->where('next_review_date', '<', now()->startOfDay())
            ->where('review_status', '!=', 'completed')
            ->where('review_status', '!=', 'overdue')
            ->get();

        foreach ($overdueCustomers as $customer) {
            $customer->update(['review_status' => 'overdue']);
            $updated++;
        }

        // Step 2: Mark due-today customers
        $dueToday = Customer::whereNotNull('next_review_date')
            ->whereDate('next_review_date', now()->toDateString())
            ->where('review_status', 'pending')
            ->get();

        foreach ($dueToday as $customer) {
            $customer->update(['review_status' => 'due']);
            $updated++;
        }

        // Step 3: Schedule reviews for customers who have a risk level but no next_review_date
        $unscheduled = Customer::whereNotNull('current_risk_level')
            ->whereNull('next_review_date')
            ->get();

        foreach ($unscheduled as $customer) {
            $riskLevel = RiskLevel::where('label', $customer->current_risk_level)->first();
            if (!$riskLevel || !$riskLevel->review_schedule_days) continue;

            $fromDate = $customer->last_reviewed_at ?? $customer->date_onboarded ?? $customer->created_at;
            $nextReview = Carbon::parse($fromDate)->addDays($riskLevel->review_schedule_days);

            $status = 'pending';
            if ($nextReview->lt(now()->startOfDay())) $status = 'overdue';
            elseif ($nextReview->isToday()) $status = 'due';

            $customer->update([
                'next_review_date' => $nextReview,
                'review_status' => $status,
            ]);
            $scheduled++;
        }

        $message = "Review scheduler ran: {$updated} updated, {$scheduled} newly scheduled.";
        Log::info($message);
        activity()->log($message);

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'updated' => $updated,
            'scheduled' => $scheduled,
        ]);
    }

    /**
     * 2. 24-Hour Transaction Rule Engine
     *    Runs daily — evaluates all 24HrTask rules against customer accounts
     *
     *    URL: /cron-job/24hr-rule-engine
     */
    public function dailyRuleEngine()
    {
        $queryService = new TransactionQueryService();
        $customers = Customer::select('account_number')->get();

        $queryService->runViaCronJob('transactions', $customers);

        $message = "24hr rule engine ran for {$customers->count()} customers.";
        Log::info($message);
        activity()->log($message);

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'customers_checked' => $customers->count(),
        ]);
    }

    /**
     * 3. Watchlist Screening (all customers)
     *    Runs periodically — screens all customers against all watchlists
     *
     *    URL: /cron-job/watchlist-screening
     */
    public function watchListScreening()
    {
        try {
            $service = new WatchListService();
            $service->screenCustomers();

            $message = "Watchlist screening completed for all customers.";
            Log::info($message);
            activity()->log($message);

            return response()->json(['status' => 'success', 'message' => $message]);
        } catch (\Exception $e) {
            Log::error("Watchlist screening failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3b. Nightly PAS Screening (recently onboarded customers)
     *     Runs daily — screens customers onboarded in the last 24 h and
     *     auto-creates cases on PEP / sanctions hits.
     *
     *     URL: /cron-job/pas-screening
     */
    public function pasScreening()
    {
        try {
            $service = new \App\Services\PassScreeningService();
            $stats = $service->screenRecentCustomers();

            $message = "Nightly PAS: {$stats['screened']} screened, {$stats['pep']} PEP, {$stats['sanctioned']} sanctioned, {$stats['cases']} cases.";
            Log::info($message);
            activity()->log($message);

            return response()->json(['status' => 'success', 'message' => $message, 'stats' => $stats]);
        } catch (\Exception $e) {
            Log::error("Nightly PAS failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3c. Pre-emptive Behavioural Alert Scoring
     *     Runs daily — scores every customer against pre-emptive factors and
     *     raises PREEMPTIVE cases above threshold.
     *
     *     URL: /cron-job/preemptive-alerts
     */
    public function preemptiveAlerts()
    {
        try {
            $service = new \App\Services\PreemptiveAlertService(app(\App\Services\CustomerBehaviourService::class));
            $stats = $service->scoreAllCustomers();

            $message = "Pre-emptive scoring: {$stats['scored']} scored, {$stats['alerts']} alerts, {$stats['errors']} errors.";
            Log::info($message);
            activity()->log($message);

            return response()->json(['status' => 'success', 'message' => $message, 'stats' => $stats]);
        } catch (\Exception $e) {
            Log::error("Pre-emptive scoring failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 4. Auto Risk Rating (new customers)
     *    Runs daily — rates customers who were added in the last 24 hours
     *
     *    URL: /cron-job/risk-rate-new-customers
     */
    public function riskRateNewCustomers()
    {
        try {
            $service = new RiskRatingService();
            $riskRating = $service->rateCustomers(
                Customer::where('created_at', '>=', now()->subDay())
            );

            // Schedule reviews for newly rated customers
            $results = $riskRating->results()->with('customer')->get();
            foreach ($results as $result) {
                $customer = $result->customer;
                if ($customer) {
                    $riskLevel = mapScoreToRiskLevel($result->score);
                    if ($riskLevel) {
                        $customer->applyRiskLevel($riskLevel->label, $result->score, 'scheduled_rating');
                        $customer->scheduleNextReview();
                    }
                }
            }

            $message = "Auto risk rating completed for new customers. {$results->count()} rated.";
            Log::info($message);
            activity()->log($message);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'customers_rated' => $results->count(),
            ]);
        } catch (\Exception $e) {
            Log::error("Auto risk rating failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 5. Generate CTR (Cash Transaction Reports)
     *    Runs daily — auto-generates CTR cases for accounts whose cash volume
     *    crosses the configured threshold (CBN 5.8(a)(i)).
     *
     *    URL: /cron-job/generate-ctr
     */
    public function generateCtr()
    {
        try {
            $service = new \App\Services\CtrDetectionService();
            $stats = $service->detect();

            $message = "CTR detection: {$stats['accounts_evaluated']} accounts, {$stats['cases_created']} CTR cases created.";
            Log::info($message);
            activity()->log($message);

            return response()->json(['status' => 'success', 'message' => $message, 'stats' => $stats]);
        } catch (\Exception $e) {
            Log::error("CTR detection failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 5b. Audit Trail Archival
     *     Runs monthly — exports append-only audit entries older than the
     *     retention window to CSV storage (CBN 5.9(a)(iii)).
     *
     *     URL: /cron-job/audit-archive
     */
    public function auditArchive()
    {
        try {
            $exit = \Illuminate\Support\Facades\Artisan::call('audit:archive');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());

            return response()->json([
                'status' => 'success',
                'message' => $output ?: 'Audit archive completed.',
            ]);
        } catch (\Exception $e) {
            Log::error("Audit archival failed: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 6. Customer Data Sync
     *    Runs at configurable intervals — syncs customer data from secondary database
     *
     *    URL: /cron-job/customer-sync
     */
    public function customerSync()
    {
        $service = new CustomerSyncService();
        $result = $service->sync();

        $message = $result['message'] ?? 'Customer sync completed.';
        Log::info($message);
        activity()->log($message);

        return response()->json(array_merge(['status' => 'success'], $result));
    }

    /**
     * Dashboard — show status of all cron jobs
     *
     *    URL: /cron-job/status
     */
    public function status()
    {
        $data = [
            'review_scheduler' => [
                'name' => 'CDD/EDD Review Scheduler',
                'description' => 'Checks all customers, marks overdue/due reviews, schedules new ones',
                'frequency' => 'Daily',
                'url' => route('cron-job.review-scheduler'),
                'stats' => [
                    'overdue' => Customer::where('review_status', 'overdue')->count(),
                    'due' => Customer::where('review_status', 'due')->count(),
                    'pending' => Customer::where('review_status', 'pending')->whereNotNull('next_review_date')->count(),
                    'unscheduled' => Customer::whereNotNull('current_risk_level')->whereNull('next_review_date')->count(),
                ],
            ],
            'daily_rule_engine' => [
                'name' => '24-Hour Rule Engine',
                'description' => 'Runs 24HrTask transaction rules against all customer accounts',
                'frequency' => 'Daily',
                'url' => route('cron-job.24hr-rule-engine'),
                'stats' => [
                    'customers' => Customer::count(),
                    'active_24hr_rules' => \App\Models\TransactionRule::where('run_time', '24HrTask')->where('status', true)->count(),
                    'cases_today' => FlaggedCase::whereDate('created_at', today())->count(),
                ],
            ],
            'watchlist_screening' => [
                'name' => 'Watchlist Screening',
                'description' => 'Screens all customers against internal and NIBSS watchlists',
                'frequency' => 'Weekly',
                'url' => route('cron-job.watchlist-screening'),
                'stats' => [
                    'internal_entries' => \App\Models\InternalWatchList::count(),
                    'nibss_entries' => \App\Models\NibssWatchList::count(),
                    'customers' => Customer::count(),
                ],
            ],
            'pas_screening' => [
                'name' => 'Nightly PAS Screening',
                'description' => 'Screens recently onboarded customers (PEP + sanctions) and auto-creates cases',
                'frequency' => 'Daily',
                'url' => route('cron-job.pas-screening'),
                'stats' => [
                    'recent_24h' => Customer::where('created_at', '>=', now()->subDay())->count(),
                    'pep_flags' => Customer::where('isPep', true)->count(),
                    'cases_today' => FlaggedCase::whereDate('created_at', today())->count(),
                ],
            ],
            'preemptive_alerts' => [
                'name' => 'Pre-emptive Behavioural Alerts',
                'description' => 'Scores all customers against pre-emptive factors and raises PREEMPTIVE cases',
                'frequency' => 'Daily',
                'url' => route('cron-job.preemptive-alerts'),
                'stats' => [
                    'customers' => Customer::count(),
                    'alerts_today' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_PREEMPTIVE)->whereDate('created_at', today())->count(),
                    'threshold' => settings('preemptive_alert_threshold', config('preemptive.threshold', 5)),
                ],
            ],
            'auto_risk_rating' => [
                'name' => 'Auto Risk Rating (New Customers)',
                'description' => 'Auto-rates customers created in the last 24 hours',
                'frequency' => 'Daily',
                'url' => route('cron-job.risk-rate-new-customers'),
                'stats' => [
                    'new_customers_24h' => Customer::where('created_at', '>=', now()->subDay())->count(),
                    'unrated' => Customer::whereNull('current_risk_level')->count(),
                ],
            ],
            'generate_ctr' => [
                'name' => 'CTR Detection & Generation',
                'description' => 'Detects accounts whose cash volume crosses the risk-factor amount threshold and raises CTR cases',
                'frequency' => 'Daily',
                'url' => route('cron-job.generate-ctr'),
                'stats' => $this->ctrStats(),
            ],
            'customer_sync' => [
                'name' => 'Customer Data Sync',
                'description' => 'Syncs customer data from secondary database (core banking, KYC system)',
                'frequency' => 'Every ' . settings('customer_sync_interval_hours', 24) . ' hours',
                'url' => route('cron-job.customer-sync'),
                'stats' => [
                    'source_db' => settings('customer_sync_database', 'Not configured'),
                    'source_table' => settings('customer_sync_table', 'customers'),
                    'last_sync' => settings('customer_sync_last_run', 'Never'),
                    'total_customers' => Customer::count(),
                ],
            ],
            'audit_archive' => [
                'name' => 'Audit Trail Archival',
                'description' => 'Archives append-only audit entries older than the retention window to CSV',
                'frequency' => 'Monthly',
                'url' => route('cron-job.audit-archive'),
                'stats' => [
                    'retention_days' => settings('audit_retention_days', config('governance.audit.retention_days', 1825)),
                    'total_entries' => \Spatie\Activitylog\Models\Activity::count(),
                ],
            ],
        ];

        return view('users.cron.status', compact('data'));
    }

    /**
     * CTR detection stats for the Cron Jobs dashboard — thresholds resolved
     * from the risk-scoring factors (TRANSACTION_AMOUNT[_CORPORATE]).
     */
    private function ctrStats(): array
    {
        $thresholds = (new \App\Services\CtrDetectionService())->thresholds();

        return [
            'ctr_cases_today' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_CTR)
                ->whereDate('created_at', today())->count(),
            'individual_threshold' => moneyFormat($thresholds['individual']),
            'corporate_threshold' => moneyFormat($thresholds['corporate']),
        ];
    }
}
