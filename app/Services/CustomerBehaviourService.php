<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\Transaction;

/**
 * Shared behavioural aggregates over a rolling window.
 *
 * Used by both the transaction risk scorer and the pre-emptive alert engine so
 * activity-pattern factors (frequency, volume, STR/CTR history, PEP receipts,
 * midnight activity, repeat senders) are computed consistently in one place.
 */
class CustomerBehaviourService
{
    /**
     * Compute a behavioural aggregate for a customer.
     *
     * @param string $field      metric key (e.g. transaction_count, str_count)
     * @param array  $conditions may carry window_days (default 7)
     */
    public function value(string $field, Customer $customer, array $conditions = []): mixed
    {
        $account = $customer->account_number;
        $days = max(1, (int) ($conditions['window_days'] ?? 7));
        $since = now()->subDays($days);

        return match ($field) {
            // Frequency — total transactions involving this account.
            'transaction_count' => Transaction::where(function ($q) use ($account) {
                $q->where('sender_account_no', $account)->orWhere('beneficiary_account_no', $account);
            })->where('created_at', '>=', $since)->count(),

            'debit_count' => Transaction::where('sender_account_no', $account)
                ->where('created_at', '>=', $since)->count(),

            'credit_count' => Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)->count(),

            'debit_value' => (float) Transaction::where('sender_account_no', $account)
                ->where('created_at', '>=', $since)->sum('amount'),

            'credit_value' => (float) Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)->sum('amount'),

            // Regulatory history — prior STR/CTR cases for this account.
            'str_count' => FlaggedCase::where('account_no', $account)
                ->where('report_type', 'STR')->where('created_at', '>=', $since)->count(),

            'ctr_count' => FlaggedCase::where('account_no', $account)
                ->where('report_type', 'CTR')->where('created_at', '>=', $since)->count(),

            // Received money from a PEP account within the window.
            'pep_receipt_count' => Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)
                ->whereIn('sender_account_no', function ($q) {
                    $q->select('account_number')->from('customers')
                      ->whereRaw('LOWER(isPep) = ?', ['yes']);
                })->count(),

            // Transactions between 23:00 and 04:00 within the window.
            'midnight_transaction_count' => Transaction::where(function ($q) use ($account) {
                $q->where('sender_account_no', $account)->orWhere('beneficiary_account_no', $account);
            })->where('created_at', '>=', $since)
              ->where(function ($q) {
                  $q->whereRaw('TIME(transaction_datetime) >= ?', ['23:00:00'])
                    ->orWhereRaw('TIME(transaction_datetime) <= ?', ['04:00:00']);
              })->count(),

            // Highest number of incoming transactions from any single sender.
            'same_sender_count' => (int) (Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)
                ->whereNotNull('sender_account_no')
                ->groupBy('sender_account_no')
                ->selectRaw('COUNT(*) as c')
                ->pluck('c')->max() ?? 0),

            // Number of distinct senders paying into this account.
            'distinct_sender_count' => Transaction::where('beneficiary_account_no', $account)
                ->where('created_at', '>=', $since)
                ->whereNotNull('sender_account_no')
                ->distinct()->count('sender_account_no'),

            default => null,
        };
    }
}
