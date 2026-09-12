<?php

namespace App\Console\Commands;

use App\Services\PassScreeningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScreenRecentCustomersCommand extends Command
{
    protected $signature = 'pas:screen-recent-customers
                            {--hours=24 : Screen customers onboarded in the last N hours}';

    protected $description = 'Nightly PAS screening of recently onboarded customers (auto-case on PEP/sanctions hits)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        try {
            $service = new PassScreeningService();
            $stats = $service->screenRecentCustomers($hours ?: 24);

            $message = sprintf(
                'Nightly PAS: %d screened, %d PEP, %d sanctioned, %d cases, %d errors.',
                $stats['screened'], $stats['pep'], $stats['sanctioned'], $stats['cases'], $stats['errors']
            );
            $this->info($message);
            Log::info($message);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Nightly PAS failed: ' . $e->getMessage());
            $this->error('Nightly PAS failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
