<?php

namespace App\Http\Controllers\User;

use App\Models\Customer;
use App\Models\RiskLevel;
use App\Models\RiskRating;
use App\Models\RiskProfile;
use App\Models\RiskRatingCustomerResult;
use App\Services\RiskRatingService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use App\Exports\RiskProfileExport;
use App\Imports\RiskProfileTemplateImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RiskRatingResultsExport;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class RiskRatingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:risk-rating-list', only: ['index']),
            new Middleware('permission:risk-rating-view', only: ['viewRiskRating']),
            new Middleware('permission:risk-rating-generate', only: ['riskRate']),
            new Middleware('permission:risk-level-list', only: ['listRiskLevel']),
            new Middleware('permission:risk-level-create', only: ['storeRiskLevel']),
            new Middleware('permission:risk-level-update', only: ['updateRiskLevel']),
            new Middleware('permission:risk-level-delete', only: ['destroyRiskLevel']),
            new Middleware('permission:risk-profile-list', only: ['listRiskProfile']),
            new Middleware('permission:risk-profile-create', only: ['storeRiskProfile']),
            new Middleware('permission:risk-profile-view', only: ['showRiskProfile']),
            new Middleware('permission:risk-profile-update', only: ['updateRiskProfile']),
            new Middleware('permission:risk-profile-delete', only: ['destroyRiskProfile']),
        ];
    }

    public function index()
    {
        $riskprofile = RiskProfile::where('status', true)->get();
        $ratings = RiskRating::with(['generated_by', 'risk_profile'])->latest()->paginate(10);
        return view('users.risk-rating.index', compact('riskprofile', 'ratings'));
    }

    public function riskRate(Request $request)
    {
        if ($request->has('risk_profile') && $profile = RiskProfile::find($request->risk_profile)) {
            $riskRating = (new RiskRatingService($profile->id))->rateAllCustomers();

            // After rating, schedule reviews for all customers based on their risk level
            $this->scheduleReviewsForRatedCustomers($riskRating);

            return back()->with('success', 'Risk rating successful. Review schedules updated.');
        }
        return back()->with('error', 'Please select a profile');
    }

    /**
     * After a risk rating run, update customer risk levels and schedule reviews
     */
    private function scheduleReviewsForRatedCustomers(RiskRating $riskRating): void
    {
        $results = RiskRatingCustomerResult::where('risk_rating_id', $riskRating->id)->get();

        foreach ($results as $result) {
            $customer = $result->customer;
            if (!$customer) continue;

            $riskLevel = mapScoreToRiskLevel($result->score);
            if (!$riskLevel) continue;

            $customer->update([
                'current_risk_level' => $riskLevel->label,
                'current_risk_score' => $result->score,
            ]);

            $customer->scheduleNextReview();
        }
    }

    public function viewRiskRating($riskratingId)
    {
        $riskrating = RiskRating::find($riskratingId);
        if (!$riskrating) return redirect(route('risk-rating.index'))->with('error', 'Invalid Action');

        $results = RiskRatingCustomerResult::where('risk_rating_id', $riskrating->id)->with('customer')->paginate(20);
        $chartData = getRiskRatingChartData($riskrating->id);
        return view('users.risk-rating.view', compact('riskrating', 'results', 'chartData'));
    }

    public function exportRiskRating($id, $format)
    {
        $riskrating = RiskRating::findOrFail($id);
        $fileName = 'MoniSurv_RiskRating_' . $riskrating->id . '_' . now()->format('Y-m-d');

        if (strtolower($format) === 'csv') {
            return Excel::download(new RiskRatingResultsExport($riskrating->id), $fileName . '.csv');
        }

        if (strtolower($format) === 'excel') {
            return Excel::download(new RiskRatingResultsExport($riskrating->id), $fileName . '.xlsx');
        }

        if (strtolower($format) === 'pdf') {
            // PDF export: try DomPDF if installed, otherwise fallback to CSV
            if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                $results = RiskRatingCustomerResult::where('risk_rating_id', $riskrating->id)
                    ->with('customer')->get();
                $chartData = getRiskRatingChartData($riskrating->id);

                $html = view('users.risk-rating.pdf', compact('riskrating', 'results', 'chartData'))->render();
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                return $pdf->download($fileName . '.pdf');
            }

            // Fallback: export as CSV with .pdf extension note
            return Excel::download(new RiskRatingResultsExport($riskrating->id), $fileName . '.csv');
        }

        return back()->with('error', 'Unsupported format.');
    }

    public function destroyRiskRating(Request $request)
    {
        if ($request->has('id')) {
            $rating = RiskRating::find($request->id);
            if ($rating) {
                RiskRatingCustomerResult::where('risk_rating_id', $rating->id)->delete();
                $rating->delete();
                return Response::json(['status' => 'success']);
            }
        }
        return Response::json(['status' => 'failed']);
    }

    // ─── CDD/EDD Reviews Due ─────────────────────────────────────
    public function reviewsDue(Request $request)
    {
        // Handle mark reviewed action
        if ($request->has('mark_reviewed') && $request->isMethod('post')) {
            $customer = Customer::find($request->mark_reviewed);
            if ($customer) {
                $customer->markReviewed();
                activity()->performedOn($customer)->log("CDD/EDD review completed for {$customer->name}");
                return redirect(route('risk-rating.reviews-due'))->with('success', "Review completed for {$customer->name}. Next review scheduled.");
            }
        }

        $overdue = Customer::whereNotNull('next_review_date')
            ->where('next_review_date', '<', now()->startOfDay())
            ->where('review_status', '!=', 'completed')
            ->get();

        $dueToday = Customer::whereNotNull('next_review_date')
            ->whereDate('next_review_date', now()->toDateString())
            ->where('review_status', '!=', 'completed')
            ->get();

        $upcoming = Customer::reviewsUpcoming(7)->get();

        $completed = Customer::where('review_status', 'completed')
            ->where('last_reviewed_at', '>=', now()->startOfMonth())
            ->get();

        // Combine overdue + due today + upcoming for the table
        $allDue = $overdue->merge($dueToday)->merge($upcoming)->unique('id')->sortBy('next_review_date');

        return view('users.risk-rating.reviews-due', compact('overdue', 'dueToday', 'upcoming', 'completed', 'allDue'));
    }

    // ─── Risk Level CRUD ─────────────────────────────────────────
    public function listRiskLevel()
    {
        $levels = RiskLevel::orderBy('min_score')->get();
        return view('users.risk-rating.risk-level', compact('levels'));
    }

    public function storeRiskLevel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|unique:risk_levels,label',
            'min_score' => 'required|numeric|lte:max_score',
            'max_score' => 'required|numeric|gte:min_score',
            'review_schedule_days' => 'required|integer|min:1',
            'diligence_type' => 'required|in:CDD,EDD',
        ]);
        if ($validator->fails()) return back()->with('error', $validator->errors()->first());

        $overlap = RiskLevel::where(function ($q) use ($request) {
            $q->where('min_score', '<=', $request->max_score)
              ->where('max_score', '>=', $request->min_score);
        })->exists();

        if ($overlap) return back()->with('error', 'Score range overlaps with an existing risk level.');

        RiskLevel::create($request->only('label', 'min_score', 'max_score', 'review_schedule_days', 'diligence_type'));
        return redirect(route('risk-rating.manage-risk-level.index'))->with('success', 'Risk Level added.');
    }

    public function showRiskLevel(Request $request)
    {
        if ($request->has('id')) {
            $riskLevel = RiskLevel::find($request->id);
            if ($riskLevel) return Response::json(['status' => 'success', 'data' => $riskLevel]);
        }
        return Response::json(['status' => 'failed']);
    }

    public function updateRiskLevel(Request $request)
    {
        $level = RiskLevel::find($request->label_id);
        if (!$level) return back()->with('error', 'Unauthorized action.');

        $validator = Validator::make($request->all(), [
            'label' => 'required|string|unique:risk_levels,label,' . $request->label_id,
            'min_score' => 'required|numeric|lte:max_score',
            'max_score' => 'required|numeric|gte:min_score',
            'review_schedule_days' => 'nullable|integer|min:1',
            'diligence_type' => 'nullable|in:CDD,EDD',
        ]);
        if ($validator->fails()) return back()->with('error', $validator->errors()->first());

        $level->update($request->only('label', 'min_score', 'max_score', 'review_schedule_days', 'diligence_type'));
        return redirect(route('risk-rating.manage-risk-level.index'))->with('success', 'Risk level updated.');
    }

    public function destroyRiskLevel(Request $request)
    {
        if ($request->has('id')) {
            $level = RiskLevel::find($request->id);
            if ($level) { $level->delete(); return Response::json(['status' => 'success']); }
        }
        return Response::json(['status' => 'failed']);
    }

    // ─── Risk Profile CRUD ───────────────────────────────────────
    public function listRiskProfile()
    {
        $riskProfiles = RiskProfile::paginate(10);
        $datapoints = getCustomerDetailsColumnNames();
        return view('users.risk-rating.risk-profile', compact('datapoints', 'riskProfiles'));
    }

    public function storeRiskProfile(Request $request)
    {
        $validator = Validator::make($request->all(), ['name' => 'required', 'data_points' => 'required']);
        if ($validator->fails()) return back()->with('error', 'All Fields are required');

        RiskProfile::create(['name' => $request->name, 'data_points' => $request->data_points]);
        return redirect(route('risk-rating.manage-risk-profile.index'))->with('success', 'Risk Profile added.');
    }

    public function showRiskProfile($profileId)
    {
        $profile = RiskProfile::with('template_data')->find($profileId);
        if (!$profile) return back()->with('error', 'Invalid Action');

        $datapoints = getCustomerDetailsColumnNames();
        return view('users.risk-rating.view-risk-profile', compact('profile', 'datapoints'));
    }

    public function updateRiskProfile(Request $request)
    {
        $profile = RiskProfile::find($request->profile_id);
        if (!$profile) return back()->with('error', 'Unauthorized action.');

        $profile->update(['name' => $request->name, 'data_points' => $request->data_points]);
        return back()->with('success', 'Risk Profile updated.');
    }

    public function destroyRiskProfile(Request $request)
    {
        if ($request->has('id')) {
            $profile = RiskProfile::find($request->id);
            if ($profile) { $profile->delete(); return Response::json(['status' => 'success']); }
        }
        return Response::json(['status' => 'failed']);
    }

    // ─── AJAX get risk profile ──────────────────────
    public function ajaxRiskProfile(Request $request)
    {
        if ($request->has('id')) {
            $riskprofile = RiskProfile::where('id', $request->id)->first();
            if ($riskprofile) {
                return Response::json(['status' => 'success', 'data' => $riskprofile]);
            }
        }
        return Response::json(['status' => 'failed']);
    }

    // ─── Download Template Excel ────────────────────
    public function getRiskProfileTemplate($profileId)
    {
        if ($profileId && $profile = RiskProfile::where('id', $profileId)->first()) {
            $exportData = [];
            foreach ($profile->data_points as $datapoint) {
                $columns = [];
                $da = getCustomerColumnDistinctValues($datapoint);
                foreach ($da as $col) {
                    $columns[] = ['type' => $col, 'value' => 0];
                }
                $exportData[$datapoint] = $columns;
            }
            return Excel::download(new RiskProfileExport($exportData), $profile->name . '.xlsx');
        }
        return back()->with('error', 'Invalid Action');
    }

    // ─── Import Template Excel ──────────────────────
    public function importRiskProfileTemplate(Request $request)
    {
        if ($request->profile_id && $profile = RiskProfile::where('id', $request->profile_id)->first()) {
            if ($request->hasFile('file')) {
                $import = new RiskProfileTemplateImport($profile->id, $request->file('file'));
                Excel::import($import, $request->file('file'));
                $profile->update(['status' => true, 'template_uploaded' => true]);
                return back()->with('success', 'Template uploaded successfully.');
            }
            return back()->with('error', 'Please select a file');
        }
        return back()->with('error', 'Invalid Action');
    }

    // ─── Delete Template Data ───────────────────────
    public function deleteRiskProfileTemplate(Request $request)
    {
        if ($request->has('id')) {
            $profile = RiskProfile::where('id', $request->id)->first();
            if ($profile) {
                $profile->template_data()->delete();
                $profile->update(['status' => false, 'template_uploaded' => false]);
                return Response::json(['status' => 'success']);
            }
        }
        return Response::json(['status' => 'failed']);
    }
}
