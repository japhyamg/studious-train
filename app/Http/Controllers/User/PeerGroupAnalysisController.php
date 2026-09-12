<?php

namespace App\Http\Controllers\User;

use App\Models\Setting;
use App\Models\PeerGroupOutlier;
use App\Models\PeerGroupThreshold;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PeerGroupAnalysisService;
use Illuminate\Support\Facades\Cache;

class PeerGroupAnalysisController extends Controller
{
    protected PeerGroupAnalysisService $service;

    public function __construct(PeerGroupAnalysisService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $availableFields = getCustomerDetailsColumnNames();
        $selectedFields = $this->service->selectedFields();

        $thresholds = $this->service->latestThresholds($selectedFields);

        $outliers = PeerGroupOutlier::with(['transaction', 'customer'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $stats = [
            'flagged' => PeerGroupOutlier::where('is_flagged', true)->count(),
            'fields' => count($selectedFields),
            'groups' => PeerGroupOutlier::distinct()->count('customer_group'),
            'last_recompute' => PeerGroupThreshold::max('created_at'),
        ];

        return view('users.peer-grouping.index', compact(
            'availableFields', 'selectedFields', 'thresholds', 'outliers', 'stats'
        ));
    }

    public function updateSettings(Request $request)
    {
        if ($request->has('pg_recompute_interval')) {
            Setting::updateOrCreate(['name' => 'pg_recompute_interval'], ['value' => $request->pg_recompute_interval]);

            // The form was submitted with no group selected → clear the fields.
            if (!$request->has('selected_groups')) {
                Setting::updateOrCreate(['name' => 'pg_selected_fields'], ['value' => '']);
            }
        }

        if ($request->has('selected_groups')) {
            $selectedGroups = implode(', ', (array) $request->input('selected_groups', []));
            Setting::updateOrCreate(['name' => 'pg_selected_fields'], ['value' => $selectedGroups]);
        }

        Cache::forget(config('cache.prefix') . '-settings');
        return redirect(route('peer-grouping.index'))->with('success', 'Peer group settings updated.');
    }

    public function recompute()
    {
        set_time_limit(300);

        $created = $this->service->recompute();
        $count = count($created);

        activity()->log("Peer group thresholds recomputed for {$count} field(s).");

        return redirect(route('peer-grouping.index'))->with(
            'success',
            $count > 0
                ? "Thresholds recomputed for {$count} field(s)."
                : 'No peer-group fields selected — thresholds were not recomputed.'
        );
    }
}
