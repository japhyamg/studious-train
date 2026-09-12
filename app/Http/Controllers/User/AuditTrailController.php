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
        $query = $this->buildQuery($request);

        $activities = $query->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('users.audit-trail.index', compact('activities', 'users'));
    }

    /**
     * Export the filtered audit trail as CSV.
     *
     * The audit trail is append-only (there is intentionally no clear/truncate
     * action) per CBN baseline 5.9(a)(i)/(iii).
     */
    public function export(Request $request)
    {
        $query = $this->buildQuery($request);
        $activities = $query->with('causer')->limit(50000)->get();

        $filename = 'audit_trail_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $handle = fopen('php://temp', 'w');
        fputcsv($handle, ['Date', 'User', 'Event', 'Subject Type', 'Subject ID', 'Properties']);

        foreach ($activities as $activity) {
            fputcsv($handle, [
                $activity->created_at?->format('Y-m-d H:i:s'),
                $activity->causer?->name ?? 'System',
                $activity->description,
                class_basename($activity->subject_type ?? ''),
                $activity->subject_id ?? '',
                json_encode($activity->properties ?? []),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, $headers);
    }

    /**
     * Shared, robust filter for both the index and export views.
     *
     * Searches the human-readable description plus the JSON properties and the
     * subject model so that end-to-end traces are retrievable (CBN 5.9(a)(iv)).
     */
    private function buildQuery(Request $request)
    {
        $query = Activity::latest();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhere('properties', 'LIKE', "%{$search}%")
                  ->orWhere('subject_type', 'LIKE', "%{$search}%");
            });
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

        return $query;
    }
}
