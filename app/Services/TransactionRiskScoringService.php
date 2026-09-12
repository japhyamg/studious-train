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

            // Evaluate each active factor dynamically (multi-condition AND/OR).
            foreach ($this->factors as $factor) {
                $raw = is_array($factor->conditions)
                    ? $factor->conditions
                    : json_decode($factor->conditions, true);

                if (!$raw || !is_array($raw)) continue;

                $normalized = $this->normalizeConditions($raw);
                if (empty($normalized['conditions'])) continue;

                $evaluation = $this->evaluateFactorConditions($normalized, $transaction, $customer);

                if (!$evaluation['triggered']) continue;

                $matchedConditions = array_values(array_filter(
                    $evaluation['results'],
                    fn($r) => $r['matched']
                ));
                $first = $matchedConditions[0] ?? ($normalized['conditions'][0] ?? []);

                $scoreBreakdown[$factor->factor_name] = [
                    'factor' => $factor->factor_description,
                    'score' => $factor->weight,
                    'logic' => $normalized['logic'],
                    'conditions' => $matchedConditions,
                    // Legacy top-level keys kept for compatibility.
                    'check_type' => $first['check_type'] ?? null,
                    'field' => $first['field'] ?? null,
                    'operator' => $first['operator'] ?? null,
                    'expected' => $first['expected'] ?? null,
                    'actual' => $first['actual'] ?? null,
                ];
                $totalScore += $factor->weight;
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
     * Normalise a factor's conditions into a canonical shape:
     * ['logic' => 'AND'|'OR', 'conditions' => [ sub-condition, … ]].
     *
     * Accepts both the legacy single-condition object
     * ({check_type, field, operator, value, window_days}) and the new
     * multi-condition object ({logic, conditions: [...]}).
     */
    protected function normalizeConditions(array $raw): array
    {
        // Legacy single-condition shape.
        if (isset($raw['check_type']) || isset($raw['field']) || isset($raw['operator'])) {
            return ['logic' => 'AND', 'conditions' => [$raw]];
        }

        // New multi-condition shape.
        if (isset($raw['conditions']) && is_array($raw['conditions'])) {
            return [
                'logic' => (strtoupper($raw['logic'] ?? 'AND') === 'OR') ? 'OR' : 'AND',
                'conditions' => array_values(array_filter($raw['conditions'], 'is_array')),
            ];
        }

        return ['logic' => 'AND', 'conditions' => []];
    }

    /**
     * Evaluate a factor's conditions with AND/OR semantics. Returns whether the
     * factor triggered and the per-condition results (for the breakdown).
     */
    protected function evaluateFactorConditions(array $normalized, Transaction $transaction, Customer $customer): array
    {
        $logic = $normalized['logic'];
        $results = [];
        $triggered = false;

        foreach ($normalized['conditions'] as $condition) {
            $checkType = $condition['check_type'] ?? 'transaction';
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equal_to';
            $expectedValue = $condition['value'] ?? null;

            if (!$field) continue;

            $actualValue = $this->getFieldValue($checkType, $field, $transaction, $customer, $condition);
            $matched = $this->evaluateCondition($actualValue, $operator, $expectedValue);

            $results[] = [
                'check_type' => $checkType,
                'field' => $field,
                'operator' => $operator,
                'expected' => $expectedValue,
                'actual' => $actualValue,
                'matched' => $matched,
            ];

            if ($logic === 'OR' && $matched) {
                $triggered = true;
            }
        }

        if ($logic === 'AND') {
            $triggered = count($results) > 0
                && collect($results)->every(fn($r) => $r['matched']);
        }

        return ['triggered' => $triggered, 'results' => $results];
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
        return app(CustomerBehaviourService::class)->value($field, $customer, $conditions);
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
