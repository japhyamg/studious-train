<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\RiskScoringConfig;
use App\Models\TransactionRisk;
use Illuminate\Support\Facades\Log;

class TransactionRiskScoringService
{
    protected $factors;
    protected int $threshold;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    protected function loadConfiguration(): void
    {
        // Load active risk factors with their conditions
        $this->factors = RiskScoringConfig::where('is_active', true)->get();

        // Threshold comes from settings table — NOT from sum of weights
        $this->threshold = (int) settings('risk_scoring_threshold', 100);
    }

    /**
     * Process a transaction: score it against all active factors for each customer involved
     */
    public function processTransaction(Transaction $transaction): Transaction
    {
        try {
            $riskAnalysis = $this->calculateRiskScore($transaction);

            if (!empty($riskAnalysis)) {
                foreach ($riskAnalysis as $risk) {
                    TransactionRisk::create([
                        'transaction_id' => $transaction->id,
                        'customer_id' => $risk['customer_id'],
                        'total_score' => $risk['total_score'],
                        'risk_level' => $risk['risk_level'],
                        'meta' => [
                            'total_score' => $risk['total_score'],
                            'threshold' => $this->threshold,
                            'risk_level' => $risk['risk_level'],
                            'requires_alert' => $risk['total_score'] >= $this->threshold,
                            'exceeds_threshold' => $risk['total_score'] >= $this->threshold,
                            'score_breakdown' => $risk['score_breakdown'],
                        ]
                    ]);

                    Log::info("Transaction risk scored", [
                        'transaction_id' => $transaction->id,
                        'customer_id' => $risk['customer_id'],
                        'score' => $risk['total_score'],
                        'threshold' => $this->threshold,
                        'level' => $risk['risk_level'],
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Risk scoring failed for transaction {$transaction->id}: " . $e->getMessage());
        }

        return $transaction;
    }

    /**
     * Calculate risk score for a transaction against all active factors
     */
    public function calculateRiskScore(Transaction $transaction): array
    {
        $customers = getCustomerFromTransaction($transaction);
        if (!$customers || empty($customers['data'])) return [];

        $result = [];

        foreach ($customers['data'] as $accountNo) {
            $customer = Customer::where('account_number', $accountNo)->first();
            if (!$customer) continue;

            $scoreBreakdown = [];
            $totalScore = 0;

            // Evaluate each active factor dynamically
            foreach ($this->factors as $factor) {
                $conditions = is_array($factor->conditions)
                    ? $factor->conditions
                    : json_decode($factor->conditions, true);

                if (!$conditions) continue;

                $checkType = $conditions['check_type'] ?? 'transaction';
                $field = $conditions['field'] ?? null;
                $operator = $conditions['operator'] ?? 'equal_to';
                $expectedValue = $conditions['value'] ?? null;

                if (!$field) continue;

                // Get the actual value from the right source
                $actualValue = $this->getFieldValue($checkType, $field, $transaction, $customer, $conditions);

                // Evaluate the condition
                $triggered = $this->evaluateCondition($actualValue, $operator, $expectedValue);

                if ($triggered) {
                    $scoreBreakdown[$factor->factor_name] = [
                        'factor' => $factor->factor_description,
                        'score' => $factor->weight,
                        'check_type' => $checkType,
                        'field' => $field,
                        'operator' => $operator,
                        'expected' => $expectedValue,
                        'actual' => $actualValue,
                    ];
                    $totalScore += $factor->weight;
                }
            }

            $result[] = [
                'transaction_id' => $transaction->id,
                'customer_id' => $customer->id,
                'total_score' => $totalScore,
                'risk_level' => $totalScore >= $this->threshold ? 'EXCEEDED THRESHOLD' : 'BELOW THRESHOLD',
                'score_breakdown' => $scoreBreakdown,
            ];
        }

        return $result;
    }

    /**
     * Get field value from the transaction, the customer, or a computed
     * behavioural aggregate (e.g. how many transactions the customer has made).
     */
    protected function getFieldValue(string $checkType, string $field, Transaction $transaction, Customer $customer, array $conditions = []): mixed
    {
        if ($checkType === 'transaction') {
            // Direct transaction field
            return $transaction->{$field} ?? null;
        }

        if ($checkType === 'customer') {
            // Direct customer field
            if ($field === 'current_risk_level') {
                // Check if customer has a risk rating result
                $latestRating = $customer->risk_rating()->latest()->first();
                if ($latestRating) {
                    $riskLevel = mapScoreToRiskLevel($latestRating->score);
                    return strtolower($riskLevel->label ?? '');
                }
                return $customer->current_risk_level ?? null;
            }

            return $customer->{$field} ?? null;
        }

        if ($checkType === 'behaviour') {
            return $this->getBehaviourValue($field, $customer, $conditions);
        }

        return null;
    }

    /**
     * Compute behavioural aggregates over a rolling window.
     *
     * These enable risk factors that depend on a customer's activity pattern
     * rather than a single field — e.g. transaction frequency, credit/debit
     * volume, STR/CTR history, PEP-linked receipts, midnight activity and
     * repeat senders. The window is `window_days` from the factor conditions
     * (default 7 days).
     */
    protected function getBehaviourValue(string $field, Customer $customer, array $conditions): mixed
    {
        $account = $customer->account_number;
        $days = max(1, (int) ($conditions['window_days'] ?? 7));
        $since = now()->subDays($days);

        return match ($field) {
            // Frequency — total transactions involving this account.
            'transaction_count' => Transaction::where(function ($q) use ($account) {
                $q->where('sender_account_no', $account)->orWhere('beneficiary_account_no', $account);
            })->where('created_at', '>=', $since)->count(),

            'debit_count' => Transaction::where('sender_account_no', $account)
                ->where('created_at', '>=', $since)->count(),

            'credit_count' => Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)->count(),

            'debit_value' => (float) Transaction::where('sender_account_no', $account)
                ->where('created_at', '>=', $since)->sum('amount'),

            'credit_value' => (float) Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)->sum('amount'),

            // Regulatory history — prior STR/CTR cases for this account.
            'str_count' => FlaggedCase::where('account_no', $account)
                ->where('report_type', 'STR')->where('created_at', '>=', $since)->count(),

            'ctr_count' => FlaggedCase::where('account_no', $account)
                ->where('report_type', 'CTR')->where('created_at', '>=', $since)->count(),

            // Received money from a PEP account within the window.
            'pep_receipt_count' => Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)
                ->whereIn('sender_account_no', function ($q) {
                    $q->select('account_number')->from('customers')
                      ->whereRaw('LOWER(isPep) = ?', ['yes']);
                })->count(),

            // Transactions between 23:00 and 04:00 within the window.
            'midnight_transaction_count' => Transaction::where(function ($q) use ($account) {
                $q->where('sender_account_no', $account)->orWhere('beneficiary_account_no', $account);
            })->where('created_at', '>=', $since)
              ->where(function ($q) {
                  $q->whereRaw('TIME(transaction_datetime) >= ?', ['23:00:00'])
                    ->orWhereRaw('TIME(transaction_datetime) <= ?', ['04:00:00']);
              })->count(),

            // Highest number of incoming transactions from any single sender.
            'same_sender_count' => (int) (Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)
                ->whereNotNull('sender_account_no')
                ->groupBy('sender_account_no')
                ->selectRaw('COUNT(*) as c')
                ->pluck('c')->max() ?? 0),

            // Number of distinct senders paying into this account.
            'distinct_sender_count' => Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)
                ->whereNotNull('sender_account_no')
                ->distinct()->count('sender_account_no'),

            default => null,
        };
    }

    /**
     * Evaluate a condition: does the actual value match the expected value using the operator?
     */
    protected function evaluateCondition(mixed $actualValue, string $operator, mixed $expectedValue): bool
    {
        if ($actualValue === null) return false;

        // Normalize for comparison
        $actual = is_string($actualValue) ? strtolower(trim((string) $actualValue)) : $actualValue;
        $expected = is_string($expectedValue) ? strtolower(trim((string) $expectedValue)) : $expectedValue;

        return match ($operator) {
            'equal_to' => $actual == $expected,
            'not_equal_to' => $actual != $expected,
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'greater_than_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'less_than_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'is_true' => in_array($actual, ['yes', 'true', '1'], true),
            default => false,
        };
    }
}
