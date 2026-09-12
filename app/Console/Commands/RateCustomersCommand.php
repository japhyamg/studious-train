<?php

namespace App\Console\Commands;

use App\Models\RiskRatingCustomerResult;
use App\Services\RiskRatingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RateCustomersCommand extends Command
{
    protected $signature = 'risk:rate
                            {--demo : Only run when demo mode is enabled (settings: risk_rating_demo_mode)}';

    protected $description = 'Re-rate all customers using the default risk profile and schedule CDD/EDD reviews';

    public function handle(): int
    {
        // Demo mode is an opt-in, short-interval cadence for demonstrations.
        if ($this->option('demo') && settings('risk_rating_demo_mode', 'false') !== 'true') {
            $this->info('Demo mode is disabled — skipping.');
            return self::SUCCESS;
        }

        try {
            $service = new RiskRatingService(); // resolves the isDefault profile
            $rating  = $service->rateAllCustomers();

            // Persist each customer's current risk level and schedule their next
            // periodic CDD/EDD review based on the risk level's review schedule.
            $results = RiskRatingCustomerResult::where('risk_rating_id', $rating->id)
                ->with('customer')
                ->get();

            foreach ($results as $result) {
                $customer = $result->customer;
                if (!$customer) continue;

                $level = mapScoreToRiskLevel($result->score);
                if (!$level) continue;

                $customer->applyRiskLevel($level->label, $result->score, 'scheduled_rating');
                $customer->scheduleNextReview();
            }

            $message = sprintf(
                'Risk rating completed for %d customers using profile "%s".',
                $results->count(),
                $rating->risk_profile?->name ?? 'default'
            );

            Log::info($message);
            activity()->log($message);
            $this->info($message);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Scheduled risk rating failed: ' . $e->getMessage());
            $this->error('Risk rating failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
