<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Customer;
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
                $actualValue = $this->getFieldValue($checkType, $field, $transaction, $customer);

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
     * Get field value from either the transaction or the customer
     */
    protected function getFieldValue(string $checkType, string $field, Transaction $transaction, Customer $customer): mixed
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

        return null;
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
