<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\FlaggedCase;
use Illuminate\Support\Facades\DB;

/**
 * Management Information (MI) report pack for the CCO and Board (CBN 5.8(a)(ii)).
 *
 * Assembles the headline AML/CFT metrics into one exportable view: customers
 * by risk band, case volumes and outcomes, filings, SLA compliance and the
 * rolling 12-month STR/CTR trend.
 */
class ManagementInformationService
{
    public function build(?string $from = null, ?string $to = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : null;
        $to = $to ? Carbon::parse($to)->endOfDay() : null;

        $cases = FlaggedCase::query()->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to));

        return [
            'period' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'generated_at' => now()->toDateTimeString(),
            ],
            'customers' => $this->customerOverview(),
            'cases' => $this->caseOverview(clone $cases),
            'filings' => $this->filingOverview(),
            'sla' => (new FlaggedCasesAnalyticService())->getCaseSlaCompliance(),
            'screening' => $this->screeningOverview(),
            'trend' => $this->monthlyTrend(),
        ];
    }

    private function customerOverview(): array
    {
        return [
            'total' => Customer::count(),
            'pep' => Customer::where('isPep', true)->count(),
            'high' => Customer::where('current_risk_level', 'High')->count(),
            'medium' => Customer::where('current_risk_level', 'Medium')->count(),
            'low' => Customer::where('current_risk_level', 'Low')->count(),
            'unrated' => Customer::whereNull('current_risk_level')->count(),
        ];
    }

    private function caseOverview($cases): array
    {
        $total = (clone $cases)->count();
        $falsePositives = (clone $cases)->where('classification', 'false_positive')->count();

        return [
            'total' => $total,
            'open' => (clone $cases)->where('status', 'open')->count(),
            'escalated' => (clone $cases)->where('status', 'escalated')->count(),
            'closed_filed' => (clone $cases)->where('status', 'closed_filed')->count(),
            'closed_not_filed' => (clone $cases)->where('status', 'closed_not_filed')->count(),
            'str' => (clone $cases)->where('report_type', 'STR')->count(),
            'ctr' => (clone $cases)->where('report_type', 'CTR')->count(),
            'false_positives' => $falsePositives,
            'false_positive_rate' => $total > 0 ? round(($falsePositives / $total) * 100, 1) : 0,
            'by_source' => (clone $cases)->selectRaw('trigger_source, count(*) as total')
                ->groupBy('trigger_source')->pluck('total', 'trigger_source')->toArray(),
        ];
    }

    private function filingOverview(): array
    {
        $str = FlaggedCase::where('report_type', 'STR');
        $cutoff = now()->subDays((int) settings('str_filing_sla_days', config('governance.filing.str_sla_days', 5)));

        return [
            'str_total' => (clone $str)->count(),
            'str_filed' => (clone $str)->where('filing_status', FlaggedCase::FILING_FILED)->count(),
            'str_unfiled' => (clone $str)->where('filing_status', '!=', FlaggedCase::FILING_FILED)->count(),
            'str_overdue' => (clone $str)->where('filing_status', '!=', FlaggedCase::FILING_FILED)
                ->where('created_at', '<', $cutoff)
                ->count(),
        ];
    }

    private function screeningOverview(): array
    {
        return [
            'watchlist_hits' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_WATCHLIST)->count(),
            'pas_cases' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_PAS)->count(),
            'preemptive_alerts' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_PREEMPTIVE)->count(),
            'peer_group_outliers' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_PEER_GROUP)->count(),
            'ai_anomalies' => FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_AI_ANOMALY)->count(),
        ];
    }

    private function monthlyTrend(): array
    {
        $months = collect(range(11, 0))->map(fn($i) => now()->subMonths($i)->format('Y-m'));

        $rows = FlaggedCase::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, report_type, count(*) as total")
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month', 'report_type')
            ->get()
            ->groupBy('month');

        return $months->map(fn($month) => [
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m', $month)->format('M y'),
            'STR' => (int) ($rows[$month]?->where('report_type', 'STR')->sum('total') ?? 0),
            'CTR' => (int) ($rows[$month]?->where('report_type', 'CTR')->sum('total') ?? 0),
        ])->values()->toArray();
    }
}
