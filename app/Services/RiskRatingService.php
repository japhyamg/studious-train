<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\RiskLevel;
use App\Models\RiskRating;
use App\Models\RiskProfile;
use App\Models\RiskRatingCustomerResult;
use Illuminate\Support\Collection;

class RiskRatingService
{
    protected RiskProfile $riskProfile;
    protected Collection $riskLevels;

    public function __construct(?int $profileId = null)
    {
        $this->riskProfile = $this->resolveRiskProfile($profileId);
        $this->riskLevels = RiskLevel::orderBy('min_score')->get();
    }

    public function rateAllCustomers(): RiskRating
    {
        return $this->rateCustomers(Customer::query());
    }

    public function rateCustomers($query): RiskRating
    {
        $riskRating = RiskRating::create([
            'risk_profile_id' => $this->riskProfile->id,
            'user_id' => auth()->id() ?? 1,
        ]);

        $query->chunk(200, function ($customers) use ($riskRating) {
            foreach ($customers as $customer) {
                $this->rateCustomer($customer, $riskRating);
            }
        });

        return $riskRating;
    }

    public function rateCustomer(Customer $customer, RiskRating $riskRating): float
    {
        $score = $this->calculateScore($customer);

        RiskRatingCustomerResult::create([
            'risk_rating_id' => $riskRating->id,
            'customer_id' => $customer->id,
            'score' => $score,
        ]);

        // Update customer's current risk level (records a change when the
        // classification moves, and triggers an event-driven review).
        $level = $this->findRiskLevel($score);
        $customer->applyRiskLevel($level?->label, $score, 'scheduled_rating');

        return $score;
    }

    protected function calculateScore(Customer $customer): float
    {
        $templateData = $this->riskProfile->template_data;

        if ($templateData->isEmpty()) {
            // No template — use simple scoring based on data points
            return $this->simpleScore($customer);
        }

        $template = $templateData->groupBy('data_point');
        $scoreMap = $this->buildScoreMap($template);

        $scores = [];
        foreach ($template as $dataPoint => $rules) {
            $value = strtolower(trim((string) ($customer->{$dataPoint} ?? '')));
            $scores[] = $scoreMap[$dataPoint][$value] ?? 0;
        }

        return count($scores) ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    /**
     * Simple scoring when no template is uploaded — uses data point values directly
     */
    protected function simpleScore(Customer $customer): float
    {
        $score = 0;
        $factors = 0;

        foreach ($this->riskProfile->data_points ?? [] as $dp) {
            $value = strtolower(trim((string) ($customer->{$dp} ?? '')));
            $factors++;

            // Basic scoring heuristics
            if ($dp === 'isPep' && $value === 'yes') {
                $score += 80;
            } elseif ($dp === 'tier_level') {
                $score += match($value) {
                    'tier_1' => 60,
                    'tier_2' => 40,
                    'tier_3' => 20,
                    default => 30,
                };
            } elseif ($dp === 'customer_type') {
                $score += $value === 'corporate' ? 50 : 30;
            } elseif ($dp === 'account_type') {
                $score += $value === 'current' ? 40 : 25;
            } else {
                $score += 30; // neutral score for unknown fields
            }
        }

        return $factors > 0 ? round($score / $factors, 2) : 0;
    }

    protected function findRiskLevel(float $score): ?RiskLevel
    {
        return $this->riskLevels->first(fn($level) =>
            $score >= $level->min_score && $score <= $level->max_score
        );
    }

    protected function buildScoreMap(Collection $dataPoints): array
    {
        $map = [];
        foreach ($dataPoints as $point => $rules) {
            foreach ($rules as $rule) {
                $map[$point][strtolower(trim($rule->type))] = (float) $rule->value;
            }
        }
        return $map;
    }

    protected function resolveRiskProfile(?int $profileId): RiskProfile
    {
        $query = RiskProfile::with('template_data');

        if ($profileId) {
            return $query->where('id', $profileId)->firstOrFail();
        }

        return $query->where('isDefault', true)->firstOrFail();
    }
}
