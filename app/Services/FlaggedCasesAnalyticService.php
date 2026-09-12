<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\FlaggedCase;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class FlaggedCasesAnalyticService
{
    // ─── Case Counts ─────────────────────────────────────────────
    public function getAllCasesCount(): int { return FlaggedCase::count(); }
    public function getReviewerAllCasesCount(int $userId): int { return FlaggedCase::where('user_id', $userId)->count(); }
    public function getOpenCasesCount(): int { return FlaggedCase::where('status', 'open')->count(); }
    public function getReviewerOpenCasesCount(int $userId): int { return FlaggedCase::where('status', 'open')->where('user_id', $userId)->count(); }
    public function getClosedFiledCasesCount(): int { return FlaggedCase::where('status', 'closed_filed')->count(); }
    public function getReviewerClosedFiledCasesCount(int $userId): int { return FlaggedCase::where('status', 'closed_filed')->where('user_id', $userId)->count(); }
    public function getClosedNotFiledCasesCount(): int { return FlaggedCase::where('status', 'closed_not_filed')->count(); }
    public function getReviewerClosedNotFiledCasesCount(int $userId): int { return FlaggedCase::where('status', 'closed_not_filed')->where('user_id', $userId)->count(); }
    public function getEscalatedCasesCount(): int { return FlaggedCase::where('status', 'escalated')->count(); }
    public function getReviewerEscalatedCasesCount(int $userId): int { return FlaggedCase::where('status', 'escalated')->where('user_id', $userId)->count(); }

    // ─── Trigger Source Counts ───────────────────────────────────
    public function getCasesByTriggerSource(): array
    {
        return FlaggedCase::selectRaw('trigger_source, count(*) as count')
            ->groupBy('trigger_source')
            ->pluck('count', 'trigger_source')
            ->toArray();
    }

    // ─── Filtered Cases ──────────────────────────────────────────
    public function getFilteredFlaggedTransactions($from = null, $to = null, $status = 'all', $reviewer = 'all', $search = null, $triggerSource = null): array
    {
        $query = FlaggedCase::with(['transaction_rule', 'transaction', 'reviewer', 'customer']);

        if ($from && $to) {
            $query->whereDate('created_at', '>=', Carbon::parse($from))
                  ->whereDate('created_at', '<=', Carbon::parse($to));
        }
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($reviewer && $reviewer !== 'all') $query->where('user_id', $reviewer);
        if ($triggerSource && $triggerSource !== 'all') $query->where('trigger_source', $triggerSource);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'LIKE', "%{$search}%")
                  ->orWhere('account_no', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', fn($cq) => $cq->where('first_name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%"));
            });
        }

        $cases = $query->orderBy('created_at', 'DESC')->get();

        return $cases->map(function ($case) {
            return [
                'id' => $case->id,
                'case_id' => $case->slug,
                'transaction_rule' => $case->transaction_rule?->name ?? '—',
                'account_no' => $case->account_no ?? '—',
                'customer_name' => $case->customer?->name ?? '—',
                'status' => $case->status,
                'classification' => $case->classification,
                'report_type' => $case->report_type,
                'trigger_source' => $case->trigger_source,
                'trigger_label' => $case->trigger_label,
                'flagged_side' => $case->flagged_side,
                'reviewer' => $case->reviewer?->name ?? 'Unassigned',
                'closed_by' => $case->closed_by,
                'created_at' => $case->created_at,
                'updated_at' => $case->updated_at,
            ];
        })->toArray();
    }

    public function getFlaggedTransaciton(string $slug)
    {
        return FlaggedCase::with(['transaction_rule', 'transaction', 'comments.user', 'reviewer', 'nfiu_indicator', 'customer'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    // ─── Reviewer Performance ────────────────────────────────────
    public function getReviewerPerformance($from = null, $to = null): array
    {
        $reviewers = User::role('reviewer')->get();
        $performance = [];

        foreach ($reviewers as $reviewer) {
            $query = FlaggedCase::where('user_id', $reviewer->id);
            if ($from && $to) {
                $query->whereDate('created_at', '>=', Carbon::parse($from))
                      ->whereDate('created_at', '<=', Carbon::parse($to));
            }

            $total = (clone $query)->count();
            $open = (clone $query)->where('status', 'open')->count();
            $closedFiled = (clone $query)->where('status', 'closed_filed')->count();
            $closedNotFiled = (clone $query)->where('status', 'closed_not_filed')->count();
            $escalated = (clone $query)->where('status', 'escalated')->count();

            $closedCases = FlaggedCase::where('user_id', $reviewer->id)
                ->whereIn('status', ['closed_filed', 'closed_not_filed'])->get();

            $avgResolution = 0;
            if ($closedCases->count() > 0) {
                $totalHours = $closedCases->sum(fn($c) => $c->created_at->diffInHours($c->updated_at));
                $avgResolution = round($totalHours / $closedCases->count(), 1);
            }

            $performance[] = [
                'id' => $reviewer->id, 'name' => $reviewer->name,
                'total' => $total, 'open' => $open,
                'closed_filed' => $closedFiled, 'closed_not_filed' => $closedNotFiled,
                'escalated' => $escalated, 'avg_resolution_hours' => $avgResolution,
            ];
        }
        return $performance;
    }

    public function getReviewerPerformanceChartData($from = null, $to = null): array
    {
        $performance = $this->getReviewerPerformance($from, $to);
        return [
            'labels' => array_column($performance, 'name'),
            'open' => array_column($performance, 'open'),
            'closed_filed' => array_column($performance, 'closed_filed'),
            'closed_not_filed' => array_column($performance, 'closed_not_filed'),
            'escalated' => array_column($performance, 'escalated'),
        ];
    }

    // ─── False Positive Data ─────────────────────────────────────
    public function getFalsePositiveData(): array
    {
        $threshold = (float) settings('false_positive_threshold', 30);

        // strftime() is SQLite-only; MySQL uses DATE_FORMAT(). Pick the right
        // expression for the active connection so this works in both environments.
        $driver = FlaggedCase::getConnection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $months = FlaggedCase::selectRaw("{$monthExpr} as month")
            ->groupBy('month')->orderBy('month')->pluck('month');

        $data = [];
        foreach ($months as $index => $month) {
            $total = FlaggedCase::whereRaw("{$monthExpr} = ?", [$month])->count();
            $fp = FlaggedCase::whereRaw("{$monthExpr} = ?", [$month])
                ->where('classification', 'false_positive')->count();
            $fpRate = $total > 0 ? round(($fp / $total) * 100, 1) : 0;

            $data[] = [
                'index' => $index + 1, 'month' => $month,
                'total_cases' => $total, 'true_positives' => $total - $fp,
                'false_positives' => $fp, 'false_positive_rate' => $fpRate,
                'threshold' => $threshold, 'status' => $fpRate > $threshold ? 'above' : 'below',
            ];
        }
        return $data;
    }

    public function getAllFlaggedTransForCARRDChart(): array
    {
        $statuses = ['open', 'closed_filed', 'closed_not_filed', 'escalated'];
        $labels = [];
        $series = [];
        foreach ($statuses as $s) {
            $labels[] = Str::headline($s);
            $series[] = FlaggedCase::where('status', $s)->count();
        }
        return ['labels' => $labels, 'series' => $series];
    }

    public function getCARRDTableData(): array
    {
        $cases = FlaggedCase::with(['transaction_rule', 'reviewer'])
            ->orderBy('created_at', 'DESC')
            ->get();

        return $cases->map(function ($case) {
            $daysOpen = $case->created_at->diffInDays(
                in_array($case->status, ['closed_filed', 'closed_not_filed'])
                    ? $case->updated_at
                    : now()
            );

            $closedByUser = null;
            if ($case->closed_by) {
                $closedByUser = User::find($case->closed_by)?->name ?? '—';
            }

            return [
                'id' => $case->id,
                'caseid' => $case->slug,
                'generated_on' => $case->created_at->format('M d, Y H:i'),
                'last_action_date' => $case->updated_at->format('M d, Y H:i'),
                'days_open' => $daysOpen,
                'status' => $case->status,
                'closed_by' => $closedByUser ?? '—',
            ];
        })->toArray();
    }
}
