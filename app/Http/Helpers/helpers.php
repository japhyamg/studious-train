<?php

use App\Models\AssignedRule;
use App\Models\BusinessDetails;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\RiskLevel;
use App\Models\RiskRatingCustomerResult;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Format money with Naira symbol
 */
function moneyFormat($amount): string
{
    return '₦' . number_format((float) $amount, 2);
}

/**
 * Get paginate count
 */
function getPaginate($default = 15): int
{
    return request()->per_page ?? $default;
}

/**
 * Get settings value by name
 */
function settings(string $name, $default = null)
{
    $settings = Cache::remember(config('cache.prefix', 'app') . '-settings', 3600, function () {
        return Setting::all()->pluck('value', 'name')->toArray();
    });

    return $settings[$name] ?? $default;
}

/**
 * Get business details
 */
function getBusinessDetails(string $name = null)
{
    if ($name) {
        $detail = BusinessDetails::where('name', $name)->first();
        return $detail ? $detail->value : null;
    }

    return BusinessDetails::all();
}

/**
 * Create case slug
 */
function createCaseSlug(): string
{
    $prefix = getBusinessDetails('case_prefix') ?? 'CASE';
    $count = FlaggedCase::count() + 1;
    return sprintf('%s-%s-%05d', $prefix, date('Ymd'), $count);
}

/**
 * Get reviewer for a rule (round-robin or first assigned)
 */
function getReviewer(?int $ruleId = null): ?int
{
    $assignedRule = AssignedRule::where('transaction_rule_id', $ruleId)->first();
    if ($assignedRule) {
        return $assignedRule->user_id;
    }

    // Fallback: get any user with reviewer role
    $reviewer = User::role('reviewer')->first();
    return $reviewer?->id;
}

/**
 * Get notification recipients for a rule
 */
function getNotificationRecipients(?int $ruleId = null)
{
    $assignedUsers = AssignedRule::where('transaction_rule_id', $ruleId)->pluck('user_id');

    if ($assignedUsers->isNotEmpty()) {
        return User::whereIn('id', $assignedUsers)->get();
    }

    // Fallback: all supervisors
    return User::role(['supervisor', 'admin'])->get();
}

/**
 * Get team reviewers
 */
function getTeamReviewers()
{
    return User::role('reviewer')->get();
}

/**
 * Check if transaction already flagged for a rule
 */
function checkIfTransactionAlreadyFlagged(?int $transactionId, ?int $ruleId = null): bool
{
    if (!$transactionId) return false;

    return FlaggedCase::where('transaction_id', $transactionId)
        ->where('transaction_rule_id', $ruleId)
        ->exists();
}

/**
 * Check if an account already has a case for a rule within a rolling window.
 *
 * Account-level rules evaluate a window of transactions on each run (e.g. the
 * daily 24HrTask engine), so de-duplication must be scoped to the account and
 * window rather than a single transaction id.
 */
function checkIfAccountAlreadyFlagged(?string $accountNo, ?int $ruleId = null, int $hours = 24): bool
{
    if (!$accountNo) return false;

    return FlaggedCase::where('account_no', $accountNo)
        ->where('transaction_rule_id', $ruleId)
        ->where('created_at', '>=', now()->subHours($hours))
        ->exists();
}

/**
 * Get customer from transaction
 */
function getCustomerFromTransaction($transaction): array
{
    $data = [];
    $accounts = [];

    if ($transaction->sender_account_no) {
        $accounts['sender_account'] = $transaction->sender_account_no;
    }
    if ($transaction->beneficiary_account_no) {
        $accounts['beneficiary_account'] = $transaction->beneficiary_account_no;
    }

    if (empty($accounts)) {
        return [];
    }

    return ['data' => $accounts];
}

/**
 * Map score to risk level
 */
function mapScoreToRiskLevel(float $score): ?RiskLevel
{
    return RiskLevel::where('min_score', '<=', $score)
        ->where('max_score', '>=', $score)
        ->first();
}

/**
 * Get risk rating chart data
 */
function getRiskRatingChartData(int $riskRatingId): array
{
    $results = RiskRatingCustomerResult::where('risk_rating_id', $riskRatingId)->get();
    $riskLevels = RiskLevel::orderBy('min_score')->get();

    $chartData = [];
    foreach ($riskLevels as $level) {
        $count = $results->filter(function ($r) use ($level) {
            return $r->score >= $level->min_score && $r->score <= $level->max_score;
        })->count();
        $chartData[$level->label] = $count;
    }

    return $chartData;
}

/**
 * Get customer details column names
 */
function getCustomerDetailsColumnNames(): array
{
    if (!Schema::hasTable('customers')) return [];

    $excluded = ['id', 'bankId', 'created_at', 'updated_at', 'first_name', 'middle_name', 'last_name', 'account_number', 'bvn', 'nin', 'date_of_birth', 'date_onboarded'];
    $columns = Schema::getColumnListing('customers');

    return array_values(array_diff($columns, $excluded));
}

/**
 * Get distinct values for a customer column
 */
function getCustomerColumnDistinctValues(string $column): array
{
    return Customer::select($column)->distinct()->pluck($column)->filter()->toArray();
}

/**
 * Store business client ID and secret
 */
function storeBusinessClientIdAndSecret(string $uuid = null): bool
{
    try {
        $clientId = \Illuminate\Support\Str::uuid()->toString();
        $secret = \Illuminate\Support\Str::random(64);

        BusinessDetails::updateOrCreate(['name' => 'client_id'], ['value' => $clientId]);
        BusinessDetails::updateOrCreate(['name' => 'client_secret'], ['value' => $secret]);

        return true;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Get XML file path for a filename
 */
function getXMLFilePath(string $filename): string
{
    return now()->format('Y/m/d');
}

/**
 * Check if an account number belongs to a customer
 */
function is_customer(?string $accountNo): bool
{
    if (!$accountNo) return false;
    return \App\Models\Customer::where('account_number', $accountNo)->exists();
}
