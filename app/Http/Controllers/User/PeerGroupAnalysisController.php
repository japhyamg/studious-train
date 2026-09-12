<?php

namespace App\Http\Controllers\User;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\PeerGroupAnalysisService;
use Illuminate\Support\Facades\Cache;

class PeerGroupAnalysisController extends Controller
{
    public function index()
    {
        $availablePeerGroupFields = getCustomerDetailsColumnNames();
        $peerGroupField = array_map('trim', explode(',', settings('pg_selected_fields')));

        return view('users.peer-grouping.index', [
            'selectedField' => $peerGroupField,
            'availableFields' => $availablePeerGroupFields,
        ]);
    }

    public function updateSettings(Request $request)
    {
        if ($request->has('pg_recompute_interval')) {
            Setting::updateOrCreate(['name' => 'pg_recompute_interval'], ['value' => $request->pg_recompute_interval]);
        }

        if ($request->has('selected_groups')) {
            $selectedGroups = implode(', ', $request->selected_groups);
            Setting::updateOrCreate(['name' => 'pg_selected_fields'], ['value' => $selectedGroups]);
        }

        Cache::forget(config('cache.prefix') . '-settings');
        return redirect(route('peer-grouping.index'))->with('success', 'Updated successfully');
    }
}
