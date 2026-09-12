<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\PeerGroupOutlier;
use App\Models\PeerGroupThreshold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PeerGroupAnalysisService
{
    /**
     * Screen a transaction against the configured peer-group fields, flagging
     * any customer whose transaction amount exceeds their group's upper IQR
     * threshold.
     */
    public function processTransaction($transaction)
    {
        $getCustomers = getCustomerFromTransaction($transaction);
        if (!$getCustomers || empty($getCustomers['data'])) return;

        $customers = DB::table('customers')->whereIn('account_number', $getCustomers['data'])->get();
        $peerGroupFields = $this->selectedFields();

        if (empty($peerGroupFields)) return;

        foreach ($customers as $customer) {
            foreach ($peerGroupFields as $field) {
                $this->flagTransaction($transaction, $customer, $field);
            }
        }
    }

    /**
     * The configured peer-group fields, validated against real customer columns
     * so invalid/stale settings never reach a raw SQL expression.
     */
    public function selectedFields(): array
    {
        $valid = getCustomerDetailsColumnNames();
        if (empty($valid)) return [];

        $configured = array_map(
            'trim',
            explode(',', (string) settings('pg_selected_fields', 'gender,customer_type'))
        );

        return array_values(array_filter(
            array_unique($configured),
            fn($field) => $field !== '' && in_array($field, $valid, true)
        ));
    }

    public function flagTransaction($transaction, $customer, string $peerField)
    {
        $thresholdData = PeerGroupThreshold::where('name', "{$peerField}_thresholds")
            ->orderBy('created_at', 'DESC')
            ->first();

        if (!$thresholdData) {
            $thresholdData = $this->computeThresholds($peerField);
        }

        // Recompute if stale.
        $thresholdAge = Carbon::parse($thresholdData->created_at);
        $interval = (int) settings('pg_recompute_interval', 7);
        if ($thresholdAge->diffInDays(now()) > $interval) {
            $thresholdData = $this->computeThresholds($peerField);
        }

        $thresholds = $thresholdData->thresholds ?? [];
        $customerGroup = strtolower(trim((string) ($customer->{$peerField} ?? '')));

        if ($customerGroup === '') return;

        $limit = $thresholds[$customerGroup] ?? null;
        // Thresholds may be stored as a scalar (legacy) or a detail array.
        if (is_array($limit)) {
            $limit = $limit['upper'] ?? null;
        }
        $limit = (float) $limit;

        if ($limit <= 0) return;

        $amount = (float) $transaction->amount;

        PeerGroupOutlier::create([
            'transaction_id' => $transaction->id,
            'customer_id' => $customer->id,
            'peer_group' => $peerField,
            'customer_group' => $customerGroup,
            'threshold' => $limit,
            'exceeded_by' => $amount > $limit ? $amount - $limit : 0,
            'is_flagged' => $amount > $limit,
        ]);
    }

    /**
     * (Re)compute the upper IQR threshold per group for a field and persist it.
     */
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
            $amounts = $groupTransactions->pluck('amount')->map(fn($v) => (float) $v)->sort()->values();
            if ($amounts->isEmpty()) continue;

            $q1 = $this->calculatePercentile($amounts, 0.25);
            $q3 = $this->calculatePercentile($amounts, 0.75);
            $iqr = $q3 - $q1;
            $upperThreshold = $q3 + (1.5 * $iqr);

            if ($groupedName !== '') {
                $thresholds[strtolower($groupedName)] = [
                    'q1' => round($q1, 2),
                    'q3' => round($q3, 2),
                    'iqr' => round($iqr, 2),
                    'upper' => round($upperThreshold, 2),
                    'sample' => $amounts->count(),
                ];
            }
        }

        return PeerGroupThreshold::create([
            'name' => "{$field}_thresholds",
            'name_key' => "{$field}_thresholds_" . Carbon::now()->toISOString(),
            'thresholds' => $thresholds,
        ]);
    }

    /**
     * Latest computed thresholds for each given field.
     *
     * @return array<string, ?PeerGroupThreshold>
     */
    public function latestThresholds(array $fields = []): array
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = PeerGroupThreshold::where('name', "{$field}_thresholds")
                ->latest('created_at')
                ->first();
        }
        return $result;
    }

    /**
     * Recompute thresholds for every selected field.
     *
     * @return array<string, PeerGroupThreshold>
     */
    public function recompute(): array
    {
        $created = [];
        foreach ($this->selectedFields() as $field) {
            $created[$field] = $this->computeThresholds($field);
        }
        return $created;
    }

    private function calculatePercentile(Collection $values, float $percentile): float
    {
        $count = $values->count();
        if ($count === 0) return 0;
        if ($count === 1) return (float) $values->first();

        $index = ($count - 1) * $percentile;
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($upper >= $count) return (float) $values->get($lower);

        return (float) $values->get($lower) * (1 - $weight) + (float) $values->get($upper) * $weight;
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
