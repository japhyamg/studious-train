<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WatchListEntry;
use App\Models\WatchListSyncLog;
use App\Services\WatchListSyncService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SanctionsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('role:admin|Super-Admin'),
        ];
    }

    public function index()
    {
        $sources = config('sanctions.sources', []);

        $counts = WatchListEntry::selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $latest = WatchListSyncLog::whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from((new WatchListSyncLog)->getTable())->groupBy('source');
            })
            ->get()
            ->keyBy('source');

        $logs = WatchListSyncLog::orderByDesc('synced_at')->paginate(20);

        return view('users.sanctions.index', compact('sources', 'counts', 'latest', 'logs'));
    }

    public function sync(Request $request)
    {
        $source = $request->input('source');

        set_time_limit(300);

        $service = new WatchListSyncService();
        $results = $source
            ? [$source => $service->sync($source)]
            : $service->syncAll();

        $success = collect($results)->filter(fn($r) => $r['status'] === 'success')->count();
        $skipped = collect($results)->filter(fn($r) => $r['status'] === 'skipped')->count();
        $failed  = collect($results)->filter(fn($r) => $r['status'] === 'failed')->count();

        $message = "Sanctions sync complete: {$success} updated, {$skipped} skipped, {$failed} failed.";
        $key = $failed > 0 ? 'error' : 'success';

        return redirect()->route('sanctions.index')->with($key, $message);
    }
}
