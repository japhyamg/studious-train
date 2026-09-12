<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FlaggedCase;
use Illuminate\Support\Facades\Log;

/**
 * Nightly PAS screening of recently onboarded customers (CBN 5.3(a)(vii)).
 *
 * Runs ScreeningService for each customer onboarded in the last N hours and:
 *  - creates a case on sanctions (LISTED) or PEP (MATCH) results,
 *  - PEP flags set isPep, bump risk and force an EDD review (5.3(a)(vi)).
 */
class PassScreeningService
{
    public function screenRecentCustomers(int $hours = 24): array
    {
        $screening = new ScreeningService();
        $customers = $this->recentCustomers($hours);

        $stats = ['screened' => 0, 'pep' => 0, 'sanctioned' => 0, 'cases' => 0, 'errors' => 0];

        foreach ($customers as $customer) {
            try {
                $result = $screening->screen([
                    'first_name' => $customer->first_name,
                    'middle_name' => $customer->middle_name,
                    'last_name' => $customer->last_name,
                    'entity_type' => 'individual',
                    'date_of_birth' => $customer->date_of_birth?->toDateString(),
                    'country' => 'nigeria',
                ], $customer->id);

                $stats['screened']++;

                if ($result->sanctions_detected) {
                    $stats['sanctioned']++;
                    $this->applySanctionsHit($customer, $result);
                }

                if ($result->pep_detected) {
                    $stats['pep']++;
                    $this->applyPepHit($customer, $result);
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning("PAS screening failed for customer #{$customer->id}: " . $e->getMessage());
            }
        }

        $message = sprintf(
            'Nightly PAS: %d screened, %d PEP, %d sanctioned, %d cases, %d errors.',
            $stats['screened'], $stats['pep'], $stats['sanctioned'], $stats['cases'], $stats['errors']
        );
        Log::info($message);
        activity()->log($message);

        return $stats;
    }

    private function recentCustomers(int $hours)
    {
        return Customer::query()
            ->where(function ($q) use ($hours) {
                $q->whereDate('date_onboarded', '>=', now()->subHours($hours)->toDateString())
                  ->orWhere(function ($qq) use ($hours) {
                      $qq->whereNull('date_onboarded')
                         ->where('created_at', '>=', now()->subHours($hours));
                  });
            })
            ->get();
    }

    private function applyPepHit(Customer $customer, $result): void
    {
        $this->createCase($customer, $result, 'pep');

        if (!$customer->isPep) {
            $customer->update(['isPep' => true]);
        }

        // Keep any higher level, otherwise raise to HIGH and force an EDD review.
        $level = in_array($customer->current_risk_level, ['CRITICAL', 'HIGH'], true)
            ? $customer->current_risk_level
            : 'HIGH';

        $customer->applyRiskLevel($level, max((float) $customer->current_risk_score, 80), 'pep_flag');
    }

    private function applySanctionsHit(Customer $customer, $result): void
    {
        $this->createCase($customer, $result, 'sanctions');
        $customer->applyRiskLevel('CRITICAL', 100, 'sanctions_screening');
    }

    private function createCase(Customer $customer, $result, string $kind): void
    {
        $exists = FlaggedCase::where('customer_id', $customer->id)
            ->where('trigger_source', FlaggedCase::SOURCE_PAS)
            ->whereDate('created_at', today())
            ->exists();

        if ($exists) return;

        FlaggedCase::create([
            'slug' => createCaseSlug(),
            'user_id' => getReviewer(),
            'customer_id' => $customer->id,
            'account_no' => $customer->account_number,
            'type' => 'customer',
            'trigger_source' => FlaggedCase::SOURCE_PAS,
            'trigger_details' => [
                'kind' => $kind,
                'risk_level' => $result->risk_level,
                'pep_status' => $result->pep_status,
                'sanction_status' => $result->sanction_status,
            ],
            'report_type' => 'STR',
        ]);
    }
}
