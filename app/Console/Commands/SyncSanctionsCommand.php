<?php

namespace App\Console\Commands;

use App\Services\WatchListSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSanctionsCommand extends Command
{
    protected $signature = 'sanctions:sync
                            {source? : Optional source key (ofac_consolidated, un_consolidated, nigeria_sanctions)}';

    protected $description = 'Refresh sanction/watchlist sources (OFAC, UN, Nigerian) and write sync logs';

    public function handle(): int
    {
        $service = new WatchListSyncService();
        $source = $this->argument('source');

        $results = $source
            ? [$source => $service->sync($source)]
            : $service->syncAll();

        foreach ($results as $key => $result) {
            $this->line("[{$key}] {$result['status']}: {$result['message']}");
        }

        $failed = collect($results)->filter(fn($r) => $r['status'] === 'failed');
        $synced = collect($results)->filter(fn($r) => $r['status'] === 'success');

        $message = sprintf(
            'Sanctions sync finished: %d source(s) updated, %d failed.',
            $synced->count(),
            $failed->count()
        );
        Log::info($message);

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
