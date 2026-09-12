<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\NfiuIndicator;
use App\Models\FlaggedCaseComment;
use App\Services\XMLExportService;
use App\Http\Controllers\Controller;
use App\Services\FlaggedCasesAnalyticService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CaseManagementController extends Controller implements HasMiddleware
{
    protected $service;

    public function __construct()
    {
        $this->service = new FlaggedCasesAnalyticService();
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:case-list', only: ['index']),
            new Middleware('permission:case-view|case-add-comment', only: ['show', 'update']),
            new Middleware('permission:case-add-comment', only: ['update']),
            new Middleware('permission:case-disposition-approve', only: ['approveDisposition', 'rejectDisposition']),
            new Middleware('permission:case-file', only: ['markFiled']),
            new Middleware('permission:case-performance', only: ['casePerformance', 'exportPerformance']),
            new Middleware('permission:case-export', only: ['export']),
            new Middleware('permission:case-carrd', only: ['carrd', 'exportCarrd']),
            new Middleware('permission:case-false-positive-dashboard', only: ['falsePositiveDashboard', 'setFalsePositiveThreshold', 'exportFalsePositive']),
        ];
    }

    public function index()
    {
        $data['from'] = $from = request()->from ?? null;
        $data['to'] = $to = request()->to ?? null;

        $defaultStatus = Auth::user()->hasRole('reviewer') ? 'open' : (Auth::user()->hasRole('supervisor') ? 'escalated' : 'all');
        $data['status'] = $status = request()->status ?? $defaultStatus;
        $data['search'] = $search = request()->search ?? null;
        $data['trigger_source'] = $triggerSource = request()->trigger_source ?? null;

        if (auth()->user()->hasRole('reviewer')) {
            $data['reviewer'] = $reviewer = auth()->user()->id;
        } else {
            $data['reviewer'] = $reviewer = request()->reviewer ?? 'all';
        }

        $data['reviewers'] = getTeamReviewers();
        $data['counts'] = [
            'all' => $this->service->getAllCasesCount(),
            'reviewer_all' => $this->service->getReviewerAllCasesCount(auth()->user()->id),
            'open' => $this->service->getOpenCasesCount(),
            'reviewer_open' => $this->service->getReviewerOpenCasesCount(auth()->user()->id),
            'closed_filed' => $this->service->getClosedFiledCasesCount(),
            'reviewer_closed_filed' => $this->service->getReviewerClosedFiledCasesCount(auth()->user()->id),
            'closed_not_filed' => $this->service->getClosedNotFiledCasesCount(),
            'reviewer_closed_not_filed' => $this->service->getReviewerClosedNotFiledCasesCount(auth()->user()->id),
            'escalated' => $this->service->getEscalatedCasesCount(),
            'reviewer_escalated' => $this->service->getReviewerEscalatedCasesCount(auth()->user()->id),
        ];

        $cases = $this->service->getFilteredFlaggedTransactions($from, $to, $status, $reviewer, $search, $triggerSource);
        $data['triggerSourceCounts'] = $this->service->getCasesByTriggerSource();

        $currentPage = Paginator::resolveCurrentPage();
        $perPage = 10;
        $startingPoint = ($currentPage - 1) * $perPage;
        $currentPageData = array_slice($cases, $startingPoint, $perPage);

        $data['paginator'] = new LengthAwarePaginator(
            $currentPageData, count($cases), $perPage, $currentPage,
            ['path' => Paginator::resolveCurrentPath()]
        );
        $data['paginator']->appends(request()->all());

        return view('users.case-management.index', $data);
    }

    public function show($case_id)
    {
        $case = FlaggedCase::with(['transaction_rule', 'comments', 'nfiu_indicator'])
            ->where('slug', $case_id)->first();

        if (is_null($case)) {
            return redirect(route('case-management.index'))->with('error', 'Unauthorized action.');
        }

        $case = $this->service->getFlaggedTransaciton($case_id);
        $transaction = Transaction::with('risk_scores')->find($case->transaction_id);
        $nfiu_indicators = NfiuIndicator::all();

        // Build customer intelligence for ALL involved customers
        $customerContexts = [];

        if ($transaction) {
            $sides = [
                'sender' => Customer::where('account_number', $transaction->sender_account_no)->first(),
                'beneficiary' => Customer::where('account_number', $transaction->beneficiary_account_no)->first(),
            ];

            foreach ($sides as $side => $cust) {
                if (!$cust) continue;

                $customerContexts[$side] = [
                    'customer' => $cust,
                    'side' => $side,
                    'is_flagged' => ($case->flagged_side ?? '') === $side,
                    'txn_stats' => $cust->transactionStats(),
                    'str_count' => $cust->strCount(),
                    'ctr_count' => $cust->ctrCount(),
                    'total_cases' => $cust->flaggedCases()->count(),
                    'prior_cases' => $cust->flaggedCases()
                        ->where('id', '!=', $case->id)
                        ->with('transaction_rule')
                        ->latest()
                        ->limit(5)
                        ->get(),
                ];
            }
        }

        return view('users.case-management.show', compact(
            'case', 'transaction', 'nfiu_indicators', 'customerContexts'
        ));
    }

    public function update(Request $request, $slug)
    {
        $request->validate(['comment' => 'required']);

        $case = FlaggedCase::where('slug', $slug)->first();

        if ($case) {
            $comment = FlaggedCaseComment::create([
                'flagged_case_id' => $case->id,
                'comment' => $request->comment,
                'user_id' => Auth::id()
            ]);

            activity()->performedOn($comment)->log('A comment was added for Case: ' . $case->slug);

            if ($case->comments->count() == 1) {
                $case->user_id = Auth::id();
            }

            $action = $request->action;

            // Maker-checker: a terminal disposition proposed by a maker is held
            // for supervisor/checker approval instead of being applied directly
            // (CBN 5.7(a)(ii)).
            if (
                in_array($action, FlaggedCase::PROPOSABLE_STATUSES)
                && settingBool('maker_checker_enabled', config('governance.maker_checker.enabled', true))
                && !Auth::user()->hasPermissionTo('case-disposition-approve')
            ) {
                $case->proposed_status = $action;
                $case->proposed_by = Auth::id();
                $case->proposed_at = now();
                $case->approved_by = null;
                $case->approved_at = null;
                $case->update();

                activity()->performedOn($case)->log(
                    $case->slug . ' disposition proposed: ' . Str::headline($action) . ' (awaiting approval)'
                );

                return redirect(route('case-management.show', $case->slug))
                    ->with('success', 'Disposition proposed — awaiting supervisor approval.');
            }

            $case->status = $action === 'open' ? 'open' : $action;
            if (in_array($action, ['closed_filed', 'closed_not_filed'])) {
                $case->closed_by = Auth::id();
            }

            // Checkers/approvers apply directly and record their approval.
            if (in_array($action, FlaggedCase::PROPOSABLE_STATUSES)) {
                $case->approved_by = Auth::id();
                $case->approved_at = now();
            }
            $case->proposed_status = null;
            $case->proposed_by = null;
            $case->proposed_at = null;
            $case->update();

            activity()->performedOn($case)->log($case->slug . ' status changed to ' . Str::headline($case->status));

            return redirect(route('case-management.show', $case->slug))->with('success', 'Comment added successfully.');
        }

        return redirect(route('case-management.index'))->with('error', 'Unauthorized Action!!');
    }

    /**
     * Approve a pending maker disposition (checker action) — CBN 5.7(a)(ii).
     */
    public function approveDisposition(Request $request, $slug)
    {
        $case = FlaggedCase::where('slug', $slug)->first();

        if ($case && $case->proposed_status) {
            $action = $case->proposed_status;

            $case->status = $action;
            if (in_array($action, ['closed_filed', 'closed_not_filed'])) {
                $case->closed_by = $case->proposed_by ?? Auth::id();
            }
            $case->approved_by = Auth::id();
            $case->approved_at = now();
            $case->proposed_status = null;
            $case->proposed_by = null;
            $case->proposed_at = null;
            $case->update();

            activity()->performedOn($case)->log(
                $case->slug . ' disposition approved: ' . Str::headline($action)
            );

            return redirect(route('case-management.show', $case->slug))
                ->with('success', 'Disposition approved and applied.');
        }

        return redirect(route('case-management.index'))->with('error', 'No pending disposition to approve.');
    }

    /**
     * Reject a pending maker disposition (checker action) — CBN 5.7(a)(ii).
     */
    public function rejectDisposition(Request $request, $slug)
    {
        $case = FlaggedCase::where('slug', $slug)->first();

        if ($case && $case->proposed_status) {
            $rejected = Str::headline($case->proposed_status);

            $case->proposed_status = null;
            $case->proposed_by = null;
            $case->proposed_at = null;
            $case->update();

            activity()->performedOn($case)->log($case->slug . ' disposition rejected: ' . $rejected);

            return redirect(route('case-management.show', $case->slug))
                ->with('success', 'Disposition rejected — the case remains open.');
        }

        return redirect(route('case-management.index'))->with('error', 'No pending disposition to reject.');
    }

    /**
     * Mark a case's report as filed with the FIU (goAML) — CBN 5.8(a)(i).
     */
    public function markFiled(Request $request, $slug)
    {
        $case = FlaggedCase::where('slug', $slug)->first();

        if ($case) {
            $case->filing_status = FlaggedCase::FILING_FILED;
            $case->filed_at = now();
            $case->filed_by = Auth::id();
            $case->filing_reference = $request->input('filing_reference');
            $case->update();

            activity()->performedOn($case)->log(
                $case->slug . ' marked as filed' . ($case->filing_reference ? ' (ref: ' . $case->filing_reference . ')' : '')
            );

            return redirect(route('case-management.show', $case->slug))
                ->with('success', 'Case marked as filed.');
        }

        return redirect(route('case-management.index'))->with('error', 'Unauthorized Action!!');
    }

    public function toogleClass(Request $request, $id)
    {
        $case = FlaggedCase::where('slug', $id)->first();
        if ($case) {
            $case->classification = $case->classification == 'true_positive' ? 'false_positive' : 'true_positive';
            $case->update();
            activity()->performedOn($case)->log('Case classification updated to ' . $case->classification);
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }

    public function setNfiuIndicator(Request $request, $id)
    {
        $case = FlaggedCase::where('slug', $id)->first();
        $nfiu_indicator = NfiuIndicator::where('id', $request->indicator_id)->first();

        if ($case && $nfiu_indicator) {
            $case->indicator_id = $nfiu_indicator->id;
            $case->update();
            activity()->performedOn($case)->log('Case NFIU indicator updated to ' . $case->nfiu_indicator->code);
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }

    /**
     * Toggle the account interdiction (block/freeze) flag on a case.
     * Post-facto workflow — records the decision until a real-time
     * core-banking integration exists (CBN 5.3(a)(viii)).
     */
    public function toggleInterdiction(Request $request, $id)
    {
        $case = FlaggedCase::where('slug', $id)->first();

        if ($case) {
            $isFrozen = $case->interdiction_status === FlaggedCase::INTERDICTION_FROZEN;

            $case->interdiction_status = $isFrozen
                ? FlaggedCase::INTERDICTION_LIFTED
                : FlaggedCase::INTERDICTION_FROZEN;
            $case->interdicted_at = $isFrozen ? null : now();
            $case->interdicted_by = $isFrozen ? null : Auth::id();
            $case->update();

            activity()->performedOn($case)->log(
                $isFrozen ? 'Account freeze lifted for case ' . $case->slug
                          : 'Account frozen (post-facto) for case ' . $case->slug
            );

            return Response::json(['status' => 'success', 'interdiction_status' => $case->interdiction_status]);
        }

        return Response::json(['status' => 'failed']);
    }

    public function export(Request $request)
    {
        $request->validate([
            'format' => 'required',
            'selected_cases' => 'required'
        ]);

        $cases_id = explode(',', $request->input('selected_cases'));
        $cases = FlaggedCase::with(['transaction', 'transaction_rule', 'nfiu_indicator'])->whereIn('id', $cases_id)->get();
        $formatType = $request->input('format');

        return match ($formatType) {
            'CSV' => $this->handleCSVExport($cases),
            'Excel' => $this->handleCSVExport($cases, 'xlsx'),
            'XML' => $this->handleXMLExport($cases),
            default => redirect()->back()->with('error', 'Invalid export type.'),
        };
    }

    public function handleCSVExport($cases, $ext = 'csv')
    {
        try {
            $headers = ['Case ID', 'Rule', 'Account', 'Amount', 'Type', 'Status', 'Classification', 'Report Type', 'Sender', 'Sender Account', 'Beneficiary', 'Beneficiary Account', 'Date'];
            $rows = [];
            foreach ($cases as $case) {
                $t = $case->transaction;
                $rows[] = [
                    $case->slug, $case->transaction_rule?->name ?? '-', $case->account_no,
                    $t ? number_format($t->amount, 2) : '0', $t?->transaction_type ?? '-',
                    $case->status, $case->classification, $case->report_type,
                    $t?->sender_name ?? '-', $t?->sender_account_no ?? '-',
                    $t?->beneficiary_name ?? '-', $t?->beneficiary_account_no ?? '-',
                    $case->created_at->format('Y-m-d H:i'),
                ];
            }

            $filename = 'flagged_cases_' . date('Y-m-d_H-i-s') . '.' . $ext;
            $handle = fopen('php://temp', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) fputcsv($handle, $row);
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            activity()->log('Cases exported to ' . strtoupper($ext));

            return response($content, 200, [
                'Content-Type' => $ext === 'csv' ? 'text/csv' : 'application/vnd.ms-excel',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function handleXMLExport($cases)
    {
        try {
            $generator = new XMLExportService();

            if ($cases->count() === 1) {
                $case = $cases->first();
                $reportData = $generator->buildReportData($case);
                $filePath = $generator->generate($reportData, $case->report_type);
                return response()->download($filePath)->deleteFileAfterSend(true);
            }

            $zipPath = $generator->generateBulk($cases);
            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', 'XML Export failed: ' . $th->getMessage());
        }
    }

    public function casePerformance()
    {
        $data['from'] = $from = request()->from ?? null;
        $data['to'] = $to = request()->to ?? null;
        $data['performance'] = $this->service->getReviewerPerformance($from, $to);
        $data['chartData'] = $this->service->getReviewerPerformanceChartData($from, $to);

        $data['totalCasesCount'] = FlaggedCase::count();
        $data['unassignedCasesCount'] = FlaggedCase::whereNull('user_id')->count();
        $data['openCasesCount'] = FlaggedCase::where('status', 'open')->count();
        $data['closedCasesCount'] = FlaggedCase::where('status', 'closed_filed')->count();
        $data['closedNotFiledCasesCount'] = FlaggedCase::where('status', 'closed_not_filed')->count();
        $data['escalatedCasesCount'] = FlaggedCase::where('status', 'escalated')->count();
        $data['reviewersCount'] = User::role('reviewer')->count();

        return view('users.case-management.performance', $data);
    }

    public function carrd()
    {
        $chartData = $this->service->getAllFlaggedTransForCARRDChart();
        return view('users.case-management.carrd', compact('chartData'));
    }

    public function carrdData()
    {
        $data = $this->service->getCARRDTableData();
        return response()->json(['data' => $data]);
    }

    public function falsePositiveDashboard()
    {
        $data = $this->service->getFalsePositiveData();
        return view('users.case-management.falsepositive', compact('data'));
    }

    public function setFalsePositiveThreshold(Request $request)
    {
        $validator = Validator::make($request->all(), ['threshold' => 'required|numeric']);

        if ($validator->fails()) {
            return back()->with('error', 'Threshold is required');
        }

        Setting::where(['name' => 'false_positive_threshold'])->update(['value' => $request->threshold]);
        Cache::forget(config('cache.prefix') . '-settings');

        return redirect(route('case-management.false-positive-dashboard'))->with('success', 'Threshold set successfully.');
    }

    // ─── Dashboard exports (CSV / PDF) ───────────────────────────

    public function exportCarrd(Request $request)
    {
        return $this->exportTable($this->service->getCARRDTableData(), 'CARRD', $request->input('format', 'csv'));
    }

    public function exportPerformance(Request $request)
    {
        $rows = $this->service->getReviewerPerformance($request->from ?? null, $request->to ?? null);
        return $this->exportTable($rows, 'Reviewer Performance', $request->input('format', 'csv'));
    }

    public function exportFalsePositive(Request $request)
    {
        return $this->exportTable($this->service->getFalsePositiveData(), 'False Positive', $request->input('format', 'csv'));
    }

    /**
     * Export an associative row set as CSV or PDF.
     */
    private function exportTable(array $rows, string $title, string $format)
    {
        if (empty($rows)) {
            return back()->with('error', 'No data to export.');
        }

        $format = strtolower($format);
        $headers = array_keys($rows[0]);
        $labels  = array_map(fn($h) => Str::headline($h), $headers);

        if ($format === 'pdf') {
            if (!class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
                return back()->with('error', 'PDF export is unavailable.');
            }

            $html = '<h4>' . e($title) . '</h4><table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:11px;">';
            $html .= '<thead><tr>' . implode('', array_map(fn($l) => '<th>' . e($l) . '</th>', $labels)) . '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>' . implode('', array_map(function ($h) use ($row) {
                    return '<td>' . e((string) ($row[$h] ?? '')) . '</td>';
                }, $headers)) . '</tr>';
            }
            $html .= '</tbody></table>';

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape');
            return $pdf->download(Str::slug($title) . '_' . now()->format('Y-m-d_H-i-s') . '.pdf');
        }

        $filename = Str::slug($title) . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://temp', 'w');
        fputcsv($handle, $labels);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn($h) => $row[$h] ?? '', $headers));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
