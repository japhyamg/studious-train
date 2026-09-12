<?php

namespace App\Console\Commands;

use App\Services\PreemptiveAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScorePreemptiveAlertsCommand extends Command
{
    protected $signature = 'preemptive:score';

    protected $description = 'Score all customers against pre-emptive behavioural factors and raise PREEMPTIVE cases';

    public function handle(): int
    {
        try {
            $service = new PreemptiveAlertService(app(\App\Services\CustomerBehaviourService::class));
            $stats = $service->scoreAllCustomers();

            $message = sprintf(
                'Pre-emptive scoring: %d scored, %d alerts, %d errors.',
                $stats['scored'], $stats['alerts'], $stats['errors']
            );
            $this->info($message);
            Log::info($message);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Pre-emptive scoring failed: ' . $e->getMessage());
            $this->error('Pre-emptive scoring failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
