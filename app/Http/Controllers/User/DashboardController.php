<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\TransactionAnalyticService;
use App\Services\FlaggedCasesAnalyticService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $txnService;
    protected $caseService;

    public function __construct()
    {
        $this->txnService = new TransactionAnalyticService();
        $this->caseService = new FlaggedCasesAnalyticService();
    }

    public function index(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        $data['from'] = $from;
        $data['to'] = $to;

        // Transaction KPIs
        $data['totalTransactions'] = $this->txnService->getTotalTransactionCount($from, $to);
        $data['totalAmount'] = $this->txnService->totalTransactionsAmount($from, $to);
        $data['totalCredit'] = $this->txnService->getTotalCreditTransactionCount($from, $to);
        $data['totalCreditAmount'] = $this->txnService->getTotalCreditTransactionAmount($from, $to);
        $data['totalDebit'] = $this->txnService->getTotalDebitTransactionCount($from, $to);
        $data['totalDebitAmount'] = $this->txnService->getTotalDebitTransactionAmount($from, $to);
        $data['totalCustomers'] = $this->txnService->getCustomerCount($from, $to);
        $data['flaggedTransactions'] = $this->txnService->getFlaggedTransactionsCount();

        // Flagged summaries
        [$chartData, $top5Summary] = $this->txnService->getTop5FlaggedTransactionsSummary($from, $to);
        $data['top5FlaggedChart'] = $chartData;
        $data['top5FlaggedSummary'] = $top5Summary;

        [$top10Summary, $labels, $counts] = $this->txnService->getTop10FlaggedAccounts($from, $to);
        $data['top10FlaggedAccounts'] = $top10Summary;
        $data['top10Labels'] = $labels;
        $data['top10Counts'] = $counts;

        // Channel summary
        $data['channelSummary'] = $this->txnService->getTransactionChannelSummary($from, $to);

        // Customer distributions
        $data['genderDist'] = $this->txnService->getGenderDistribution($from, $to);
        $data['customerTypeDist'] = $this->txnService->getCustomerTypeDistribution($from, $to);
        $data['tierDist'] = $this->txnService->getTierDistribution($from, $to);
        $data['accountTypeDist'] = $this->txnService->getAccountTypeDistribution($from, $to);

        // Case counts
        $data['caseCounts'] = [
            'all' => $this->caseService->getAllCasesCount(),
            'open' => $this->caseService->getOpenCasesCount(),
            'closed_filed' => $this->caseService->getClosedFiledCasesCount(),
            'closed_not_filed' => $this->caseService->getClosedNotFiledCasesCount(),
            'escalated' => $this->caseService->getEscalatedCasesCount(),
        ];

        return view('users.dashboard.index', $data);
    }

    public function analytics(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        $data['from'] = $from;
        $data['to'] = $to;
        $data['regionChartData'] = $this->txnService->getRegionTransactionChartData($from, $to);
        $data['genderDist'] = $this->txnService->getGenderDistribution($from, $to);
        $data['customerTypeDist'] = $this->txnService->getCustomerTypeDistribution($from, $to);
        $data['tierDist'] = $this->txnService->getTierDistribution($from, $to);

        return view('users.dashboard.analytics', $data);
    }

    public function getStateAnalytics(Request $request)
    {
        $state = $request->state;
        if (!$state) {
            return response()->json(['status' => 'failed']);
        }
        $data = $this->txnService->getStateTransactionSummary($state);
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function getStateLgas(Request $request)
    {
        // Simple lookup - can expand with a proper LGA table
        return response()->json(['status' => 'success', 'data' => []]);
    }
}
