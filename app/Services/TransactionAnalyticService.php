<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\State;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\Transaction;
use App\Models\TransactionRule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransactionAnalyticService
{
    private function dateRange($from, $to): ?string
    {
        return (!empty($from) && !empty($to)) ? 'Available' : null;
    }

    public function getTotalTransactionCount($from = null, $to = null)
    {
        $q = Transaction::query();
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->count();
    }

    public function getCustomerCount($from = null, $to = null)
    {
        $q = Customer::query();
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->count();
    }

    public function totalTransactionsAmount($from = null, $to = null)
    {
        $q = Transaction::query();
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->sum('amount');
    }

    public function getTotalCreditTransactionCount($from = null, $to = null)
    {
        $q = Transaction::whereRaw('LOWER(transaction_type) = ?', ['credit']);
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->count();
    }

    public function getTotalCreditTransactionAmount($from = null, $to = null)
    {
        $q = Transaction::whereRaw('LOWER(transaction_type) = ?', ['credit']);
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->sum('amount');
    }

    public function getTotalDebitTransactionCount($from = null, $to = null)
    {
        $q = Transaction::whereRaw('LOWER(transaction_type) = ?', ['debit']);
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->count();
    }

    public function getTotalDebitTransactionAmount($from = null, $to = null)
    {
        $q = Transaction::whereRaw('LOWER(transaction_type) = ?', ['debit']);
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->sum('amount');
    }

    public function getFlaggedTransactionsCount()
    {
        return FlaggedCase::distinct('transaction_id')->count('transaction_id');
    }

    public function getTop5FlaggedTransactionsSummary($from = null, $to = null)
    {
        $q = FlaggedCase::with('transaction_rule');
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }

        $grouped = $q->get()->groupBy('transaction_rule_id');
        $summary = [];
        $ruleNames = [];
        $ruleCounts = [];

        foreach ($grouped as $ruleId => $cases) {
            $rule = TransactionRule::find($ruleId);
            if (!$rule) continue;
            $summary[$rule->name] = $cases->count();
            $ruleNames[$ruleId] = $rule->name;
            $ruleCounts[$ruleId] = $cases->count();
        }

        arsort($ruleCounts);
        $top5 = array_slice($ruleCounts, 0, 5, true);

        $chartData = [[], []];
        foreach ($top5 as $id => $count) {
            $chartData[0][] = $ruleNames[$id] ?? "Rule $id";
            $chartData[1][] = $count;
        }

        arsort($summary);
        $top5Summary = array_slice($summary, 0, 5, true);

        return [$chartData, $top5Summary];
    }

    public function getTop10FlaggedAccounts($from = null, $to = null)
    {
        $grouped = FlaggedCase::get()->groupBy('account_no');
        $summary = [];
        foreach ($grouped as $account => $cases) {
            if (empty($account)) continue;
            $summary[$account] = $cases->count();
        }
        arsort($summary);
        $summary = array_slice($summary, 0, 10, true);

        return [$summary, array_keys($summary), array_values($summary)];
    }

    public function getTransactionChannelSummary($from = null, $to = null)
    {
        $q = Transaction::select('channel', DB::raw('SUM(amount) as total'));
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        return $q->groupBy('channel')->get();
    }

    public function getGenderDistribution($from = null, $to = null)
    {
        $q = Customer::select('gender', DB::raw('COUNT(*) as total'));
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        $result = $q->groupBy('gender')->get();

        $chartData = ['Total' => 0, 'Female' => 0, 'Male' => 0];
        foreach ($result as $row) {
            $key = Str::headline($row->gender);
            $chartData[$key] = $row->total;
            $chartData['Total'] += $row->total;
        }
        $chartData['Female%'] = $chartData['Total'] > 0 ? round(($chartData['Female'] / $chartData['Total']) * 100, 1) : 0;
        $chartData['Male%'] = $chartData['Total'] > 0 ? round(($chartData['Male'] / $chartData['Total']) * 100, 1) : 0;

        return $chartData;
    }

    public function getCustomerTypeDistribution($from = null, $to = null)
    {
        $q = Customer::select('customer_type', DB::raw('COUNT(*) as total'));
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        $result = $q->groupBy('customer_type')->get();

        $chartData = [];
        foreach ($result as $row) {
            $chartData[Str::headline($row->customer_type)] = (int) $row->total;
        }
        return $chartData;
    }

    public function getTierDistribution($from = null, $to = null)
    {
        $q = Customer::select('tier_level', DB::raw('COUNT(*) as total'));
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        $result = $q->groupBy('tier_level')->get();

        $chartData = [];
        foreach ($result as $row) {
            $chartData[Str::headline($row->tier_level)] = (int) $row->total;
        }

        $chartData['data'] = [
            'labels' => !empty($chartData) ? array_keys($chartData) : [],
            'series' => !empty($chartData) ? array_values($chartData) : [],
        ];
        return $chartData;
    }

    public function getAccountTypeDistribution($from = null, $to = null)
    {
        $q = Customer::select('account_type', DB::raw('COUNT(*) as total'));
        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }
        $result = $q->groupBy('account_type')->get();

        $chartData = [];
        foreach ($result as $row) {
            $chartData[Str::headline($row->account_type)] = (int) $row->total;
        }
        $chartData['data'] = [
            'labels' => !empty($chartData) ? array_keys(array_filter($chartData, 'is_int')) : [],
            'series' => !empty($chartData) ? array_values(array_filter($chartData, 'is_int')) : [],
        ];
        return $chartData;
    }

    public function getStateTransactionSummary($state, $from = null, $to = null)
    {
        $customers = Customer::where('state_of_residence', strtolower($state))->pluck('account_number');
        if ($customers->isEmpty()) return false;

        $q = Transaction::where(function ($query) use ($customers) {
            $query->whereIn('sender_account_no', $customers)
                  ->orWhereIn('beneficiary_account_no', $customers);
        });

        if ($this->dateRange($from, $to)) {
            $q->whereDate('created_at', '>=', Carbon::createFromFormat('d-m-Y', $from))
              ->whereDate('created_at', '<=', Carbon::createFromFormat('d-m-Y', $to));
        }

        $grouped = $q->get()->groupBy('transaction_type');

        return [
            'credit_volume' => $grouped->get('credit', collect())->count(),
            'credit_value' => moneyFormat($grouped->get('credit', collect())->sum('amount')),
            'debit_volume' => $grouped->get('debit', collect())->count(),
            'debit_value' => moneyFormat($grouped->get('debit', collect())->sum('amount')),
        ];
    }

    public function getRegionTransactionSummary($from = null, $to = null)
    {
        $regions = State::select('zone')->distinct()->pluck('zone');
        $regionData = [];

        foreach ($regions as $region) {
            $states = State::where('zone', $region)->pluck('name');
            foreach ($states as $state) {
                $regionData[$region][$state] = $this->getStateTransactionSummary($state, $from, $to) ?: [
                    'credit_volume' => 0, 'credit_value' => '₦0.00',
                    'debit_volume' => 0, 'debit_value' => '₦0.00',
                ];
            }
        }
        return $regionData;
    }

    public function getRegionTransactionChartData($from = null, $to = null)
    {
        $summary = $this->getRegionTransactionSummary($from, $to);

        foreach ($summary as $regionName => $region) {
            foreach ($region as $stateName => $state) {
                if (isset($state['credit_value'])) {
                    $summary[$regionName][$stateName]['credit_value'] = (float) str_replace([',', '₦'], '', $state['credit_value']);
                }
                if (isset($state['debit_value'])) {
                    $summary[$regionName][$stateName]['debit_value'] = (float) str_replace([',', '₦'], '', $state['debit_value']);
                }
            }
        }

        $data = [];
        foreach ($summary as $regionName => $region) {
            $data[$regionName] = [
                'credit_volume' => array_sum(array_column($region, 'credit_volume')),
                'credit_value' => array_sum(array_column($region, 'credit_value')),
                'debit_volume' => array_sum(array_column($region, 'debit_volume')),
                'debit_value' => array_sum(array_column($region, 'debit_value')),
            ];
        }

        $order = ['North West', 'North East', 'North Central', 'South West', 'South East', 'South South'];
        $sorted = [];
        foreach ($order as $region) {
            if (isset($data[$region])) $sorted[$region] = $data[$region];
        }

        $chartData = ['credit_volume' => [], 'credit_value' => [], 'debit_volume' => [], 'debit_value' => []];
        foreach ($sorted as $st) {
            foreach (['credit_volume', 'credit_value', 'debit_volume', 'debit_value'] as $key) {
                $chartData[$key][] = $st[$key];
            }
        }
        return $chartData;
    }
}
