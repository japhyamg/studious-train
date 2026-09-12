<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\PeerGroupOutlier;
use App\Models\PeerGroupThreshold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PeerGroupAnalysisService
{
    public function processTransaction($transaction)
    {
        $getCustomers = getCustomerFromTransaction($transaction);
        if (!$getCustomers || empty($getCustomers['data'])) return;

        $customers = DB::table('customers')->whereIn('account_number', $getCustomers['data'])->get();
        $peerGroupField = array_map('trim', explode(',', settings('pg_selected_fields', 'gender,customer_type')));

        foreach ($customers as $customer) {
            foreach ($peerGroupField as $field) {
                if (empty($field)) continue;
                $this->flagTransaction($transaction, $customer, $field);
            }
        }
    }

    public function flagTransaction($transaction, $customer, string $peerField)
    {
        $name = "{$peerField}_thresholds";

        $thresholdData = PeerGroupThreshold::where('name', $name)
            ->orderBy('created_at', 'DESC')
            ->first();

        if (!$thresholdData) {
            $thresholdData = $this->computeThresholds($peerField);
        }

        // Recompute if stale
        $thresholdAge = Carbon::parse($thresholdData->created_at);
        $interval = (int) settings('pg_recompute_interval', 7);
        if ($thresholdAge->diffInDays(now()) > $interval) {
            $thresholdData = $this->computeThresholds($peerField);
        }

        $thresholds = $thresholdData->thresholds;
        $customerGroup = strtolower($customer->{$peerField} ?? '');
        $limit = $thresholds[$customerGroup] ?? null;

        if (is_null($limit)) return;

        PeerGroupOutlier::create([
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'peer_group' => $peerField,
            'customer_group' => $customerGroup,
            'threshold' => $limit,
            'exceeded_by' => $transaction->amount > $limit ? $transaction->amount - $limit : 0,
            'is_flagged' => $transaction->amount > $limit,
        ]);
    }

    public function computeThresholds(string $field)
    {
        $thresholds = [];

        $transactions = DB::table('transactions as t')
            ->leftJoin('customers as cd_sender', 't.sender_account_no', '=', 'cd_sender.account_number')
            ->leftJoin('customers as cd_beneficiary', 't.beneficiary_account_no', '=', 'cd_beneficiary.account_number')
            ->whereNotNull(DB::raw("COALESCE(cd_sender.{$field}, cd_beneficiary.{$field})"))
            ->select('t.*', DB::raw("COALESCE(cd_sender.{$field}, cd_beneficiary.{$field}) as {$field}"))
            ->get();

        $grouped = $transactions->groupBy($field);

        foreach ($grouped as $groupedName => $groupTransactions) {
            $amounts = $groupTransactions->pluck('amount')->sort()->values();
            if ($amounts->isEmpty()) continue;

            $q1 = $this->calculatePercentile($amounts, 0.25);
            $q3 = $this->calculatePercentile($amounts, 0.75);
            $iqr = $q3 - $q1;
            $upperThreshold = $q3 + (1.5 * $iqr);

            if ($groupedName !== '') {
                $thresholds[strtolower($groupedName)] = $upperThreshold;
            }
        }

        return PeerGroupThreshold::create([
            'name' => "{$field}_thresholds",
            'name_key' => "{$field}_thresholds_" . Carbon::now()->toISOString(),
            'thresholds' => $thresholds,
        ]);
    }

    private function calculatePercentile(Collection $values, float $percentile): float
    {
        $count = $values->count();
        if ($count === 0) return 0;
        if ($count === 1) return $values->first();

        $index = ($count - 1) * $percentile;
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($upper >= $count) return $values->get($lower);

        return $values->get($lower) * (1 - $weight) + $values->get($upper) * $weight;
    }

    public function getOutliers(?bool $isFlagged = true, ?string $group = null, ?string $peerGroup = null)
    {
        $query = PeerGroupOutlier::with(['transaction', 'customer']);

        if (!is_null($isFlagged)) {
            $query->where('is_flagged', $isFlagged ? 1 : 0);
        }
        if (!is_null($group) && $group !== 'All') {
            $query->where('customer_group', $group);
        }
        if (!is_null($peerGroup)) {
            $query->where('peer_group', $peerGroup);
        }

        return $query->orderBy('created_at', 'DESC')->get();
    }
}
