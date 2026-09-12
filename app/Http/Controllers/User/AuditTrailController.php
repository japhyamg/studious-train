<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest();

        if ($request->search) {
            $query->where('description', 'LIKE', "%{$request->search}%");
        }

        if ($request->user_id && $request->user_id !== 'all') {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->from) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from));
        }

        if ($request->to) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to));
        }

        $activities = $query->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('users.audit-trail.index', compact('activities', 'users'));
    }

    public function clearLog()
    {
        Activity::truncate();
        return redirect(route('audit-trail.index'))->with('success', 'Audit trail cleared.');
    }
}
