<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Cash Transaction Report (CTR) detection — CBN 5.8(a)(i).
 *
 * Aggregates cash-channel transactions per account over a rolling window and
 * raises a CTR case when an account's cash volume crosses the threshold
 * (individual vs corporate, both configurable). One CTR case per account per
 * window.
 */
class CtrDetectionService
{
    public function detect(): array
    {
        $individual = (float) settings('ctr_threshold_individual', config('governance.ctr.threshold_individual', 5000000));
        $corporate  = (float) settings('ctr_threshold_corporate', config('governance.ctr.threshold_corporate', 10000000));
        $channels   = $this->cashChannels();
        $windowDays = (int) settings('ctr_window_days', config('governance.ctr.window_days', 1));
        $since      = now()->subDays($windowDays);

        $transactions = Transaction::whereIn('channel', $channels)
            ->where('transaction_datetime', '>=', $since)
            ->get();

        // Aggregate per account: credit = cash deposit received, debit = cash withdrawal.
        $byAccount = [];
        foreach ($transactions as $t) {
            $this->accumulate($byAccount, $t->beneficiary_account_no, $t, 'credit');
            $this->accumulate($byAccount, $t->sender_account_no, $t, 'debit');
        }

        $created = 0;
        $skipped = 0;

        foreach ($byAccount as $acct => $data) {
            $total = $data['credit'] + $data['debit'];

            $customer = Customer::where('account_number', $acct)->first();
            $customerType = strtolower((string) ($customer->customer_type ?? 'individual'));
            $threshold = $customerType === 'corporate' ? $corporate : $individual;

            if ($total < $threshold) continue;

            // Dedup — one CTR per account per window.
            $exists = FlaggedCase::where('trigger_source', FlaggedCase::SOURCE_CTR)
                ->where('account_no', $acct)
                ->where('created_at', '>=', $since)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $side = $data['credit'] >= $data['debit'] ? 'beneficiary' : 'sender';

            TransactionQueryService::createCaseFromSource(
                FlaggedCase::SOURCE_CTR,
                [
                    'total_cash_volume' => $total,
                    'credit_volume' => $data['credit'],
                    'debit_volume' => $data['debit'],
                    'transaction_count' => $data['count'],
                    'threshold' => $threshold,
                    'customer_type' => $customerType,
                    'window_days' => $windowDays,
                    'trigger_reason' => 'Cash volume ' . moneyFormat($total),
                ],
                $data['last_txn'],
                $acct,
                null,
                'CTR',
                $side
            );

            $created++;
            Log::info("CTR case created for account {$acct} (cash volume " . moneyFormat($total) . ")");
        }

        return [
            'scanned_transactions' => $transactions->count(),
            'accounts_evaluated' => count($byAccount),
            'cases_created' => $created,
            'already_flagged' => $skipped,
            'window_days' => $windowDays,
        ];
    }

    private function cashChannels(): array
    {
        $raw = (string) settings(
            'ctr_cash_channels',
            implode(',', config('governance.ctr.cash_channels', ['atm', 'bank', 'cash']))
        );

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function accumulate(array &$byAccount, ?string $acct, Transaction $txn, string $side): void
    {
        if (!$acct) return;

        if (!isset($byAccount[$acct])) {
            $byAccount[$acct] = ['credit' => 0.0, 'debit' => 0.0, 'count' => 0, 'last_txn' => $txn];
        }

        $byAccount[$acct][$side] += (float) $txn->amount;
        $byAccount[$acct]['count']++;
        $byAccount[$acct]['last_txn'] = $txn;
    }
}
