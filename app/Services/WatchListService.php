<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\Transaction;
use App\Models\TransactionRule;
use Illuminate\Support\Facades\DB;
use App\Notifications\FlaggedAccountAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class WatchListService
{
    /**
     * Screen a subject (customer data) against all watchlists
     * Called for BOTH sides of a transaction
     */
    public function run(array $subject, ?Transaction $transaction = null, string $side = 'transaction')
    {
        $result = $this->screen($subject);

        if (empty($result['is_match'])) return null;

        $matchedLists = collect($result['results'])
            ->filter(fn($r) => $r['matched'])
            ->keys()
            ->toArray();

        $primaryAccount = $subject['account_no'];

        foreach ($matchedLists as $list) {
            // Check if already flagged for this watchlist + transaction
            $exists = FlaggedCase::where('transaction_id', $transaction?->id)
                ->where('trigger_source', FlaggedCase::SOURCE_WATCHLIST)
                ->where('account_no', $primaryAccount)
                ->where('trigger_details->watchlist', $list)
                ->exists();

            if ($exists) continue;

            // Try to find a matching rule for this watchlist type
            $rule = TransactionRule::whereAttribute($list)->first();

            $case = TransactionQueryService::createCaseFromSource(
                FlaggedCase::SOURCE_WATCHLIST,
                [
                    'watchlist' => $list,
                    'matched_records' => $result['results'][$list]['count'] ?? 0,
                    'subject' => $subject,
                ],
                $transaction,
                $primaryAccount,
                $rule?->id,
                'STR',
                $side
            );

            // Notify
            try {
                $recipients = getNotificationRecipients($rule?->id);
                $message = "Account {$primaryAccount} matched {$list} watchlist. Case #{$case->slug} created.";
                Notification::send($recipients, new FlaggedAccountAlert($message, route('case-management.show', $case->slug)));
            } catch (\Exception $e) {
                Log::warning("Notification failed for watchlist case {$case->slug}: " . $e->getMessage());
            }
        }
    }

    /**
     * Screen all customers against all watchlists (cron job)
     */
    public function screenCustomers(): void
    {
        Customer::chunk(200, function ($customers) {
            foreach ($customers as $customer) {
                $subject = [
                    'first_name' => $customer->first_name,
                    'middle_name' => $customer->middle_name,
                    'last_name' => $customer->last_name,
                    'account_no' => $customer->account_number,
                    'nin' => $customer->nin,
                    'bvn' => $customer->bvn,
                ];

                $this->run($subject, null, 'customer');
            }
        });
    }

    public function screen(array $subject, array $lists = []): array
    {
        $lists = $lists ?: array_keys(config('watchlists', []));
        $results = [];

        foreach ($lists as $list) {
            try {
                $results[$list] = $this->searchList($list, $subject);
            } catch (\Exception $e) {
                Log::warning("Watchlist check failed for {$list}: " . $e->getMessage());
                $results[$list] = ['matched' => false, 'count' => 0, 'records' => collect()];
            }
        }

        return [
            'is_match' => collect($results)->contains(fn($r) => $r['matched']),
            'results' => $results,
        ];
    }

    protected function searchList(string $listKey, array $subject): array
    {
        $config = config("watchlists.{$listKey}");
        if (!$config) return ['matched' => false, 'count' => 0, 'records' => collect()];

        $query = DB::table($config['table']);
        $query->where(function ($q) use ($config, $subject) {
            foreach ($config['fields'] as $field => $operator) {
                if (empty($subject[$field])) continue;
                if ($operator === 'like') {
                    $q->orWhere($field, 'LIKE', '%' . $subject[$field] . '%');
                } else {
                    $q->orWhere($field, $subject[$field]);
                }
            }
        });

        $matches = $query->get();

        return [
            'matched' => $matches->isNotEmpty(),
            'count' => $matches->count(),
            'records' => $matches,
        ];
    }
}
