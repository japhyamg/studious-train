<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FlaggedCase;
use Illuminate\Support\Facades\Log;

/**
 * Pre-emptive behavioural alerting (CBN 5.5(a)(i)/(ii)).
 *
 * Scores every customer against config-driven behavioural factors. When the
 * total points reach the threshold, a PREEMPTIVE case is raised carrying the
 * full factor breakdown in trigger_details (explainability for 5.4(a)(iv)).
 */
class PreemptiveAlertService
{
    public function __construct(protected CustomerBehaviourService $metrics)
    {
    }

    public function scoreAllCustomers(): array
    {
        $stats = ['scored' => 0, 'alerts' => 0, 'errors' => 0];

        Customer::chunk(200, function ($customers) use (&$stats) {
            foreach ($customers as $customer) {
                try {
                    $stats['scored']++;
                    if ($this->scoreCustomer($customer)) {
                        $stats['alerts']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::warning("Pre-emptive scoring failed for customer #{$customer->id}: " . $e->getMessage());
                }
            }
        });

        $message = sprintf(
            'Pre-emptive scoring: %d scored, %d alerts, %d errors.',
            $stats['scored'], $stats['alerts'], $stats['errors']
        );
        Log::info($message);
        activity()->log($message);

        return $stats;
    }

    /**
     * Score one customer and raise a pre-emptive case when the threshold is
     * met. Returns true when a case was created.
     */
    public function scoreCustomer(Customer $customer): bool
    {
        $threshold = (int) settings('preemptive_alert_threshold', config('preemptive.threshold', 5));
        $factors = config('preemptive.factors', []);
        if (empty($factors)) return false;

        $triggered = [];
        $points = 0;

        foreach ($factors as $key => $factor) {
            $field = $factor['field'] ?? null;
            if (!$field) continue;

            $actual = $this->metrics->value($field, $customer, [
                'window_days' => (int) ($factor['window_days'] ?? 7),
            ]);

            $matched = $this->evaluate($actual, $factor['operator'] ?? 'greater_than', $factor['value'] ?? null);
            if (!$matched) continue;

            $pts = (int) ($factor['points'] ?? 1);
            $points += $pts;

            $triggered[] = [
                'factor' => $key,
                'label' => $factor['label'] ?? $key,
                'field' => $field,
                'operator' => $factor['operator'] ?? 'greater_than',
                'expected' => $factor['value'] ?? null,
                'actual' => $actual,
                'window_days' => (int) ($factor['window_days'] ?? 7),
                'points' => $pts,
            ];
        }

        if ($points < $threshold || empty($triggered)) return false;

        // Dedup: at most one pre-emptive alert per customer per day.
        $exists = FlaggedCase::where('customer_id', $customer->id)
            ->where('trigger_source', FlaggedCase::SOURCE_PREEMPTIVE)
            ->whereDate('created_at', today())
            ->exists();

        if ($exists) return false;

        FlaggedCase::create([
            'slug' => createCaseSlug(),
            'user_id' => getReviewer(),
            'customer_id' => $customer->id,
            'account_no' => $customer->account_number,
            'type' => 'account',
            'trigger_source' => FlaggedCase::SOURCE_PREEMPTIVE,
            'trigger_details' => [
                'score' => $points,
                'threshold' => $threshold,
                'trigger_reason' => 'Pre-emptive ' . $points,
                'factors' => $triggered,
            ],
            'report_type' => 'STR',
        ]);

        activity()->performedOn($customer)->log(
            sprintf('Pre-emptive alert raised for %s (score %d/%d).', $customer->name, $points, $threshold)
        );

        return true;
    }

    protected function evaluate(mixed $actual, string $operator, mixed $expected): bool
    {
        if ($actual === null) return false;

        $a = is_string($actual) ? strtolower(trim($actual)) : $actual;
        $e = is_string($expected) ? strtolower(trim($expected)) : $expected;

        return match ($operator) {
            'equal_to' => $a == $e,
            'not_equal_to' => $a != $e,
            'greater_than' => is_numeric($a) && is_numeric($e) && (float) $a > (float) $e,
            'less_than' => is_numeric($a) && is_numeric($e) && (float) $a < (float) $e,
            'greater_than_equal' => is_numeric($a) && is_numeric($e) && (float) $a >= (float) $e,
            'less_than_equal' => is_numeric($a) && is_numeric($e) && (float) $a <= (float) $e,
            'contains' => is_string($a) && str_contains($a, (string) $e),
            default => false,
        };
    }
}
