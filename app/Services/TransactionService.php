<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\Transaction;

class TransactionService
{
    public function handle(array $data): Transaction
    {
        $transaction = Transaction::create([
            "bankId" => $data['bankId'] ?? '',
            "sender_first_name" => $data['sender_first_name'],
            "sender_middle_name" => $data['sender_middle_name'] ?? null,
            "sender_last_name" => $data['sender_last_name'],
            "sender_account_no" => $data['sender_account_no'],
            "sender_account_type" => $data['sender_account_type'] ?? null,
            "sender_nin" => $data['sender_nin'] ?? null,
            "sender_bvn" => $data['sender_bvn'] ?? null,
            "beneficiary_first_name" => $data['beneficiary_first_name'],
            "beneficiary_middle_name" => $data['beneficiary_middle_name'] ?? null,
            "beneficiary_last_name" => $data['beneficiary_last_name'],
            "beneficiary_account_no" => $data['beneficiary_account_no'],
            "beneficiary_account_type" => $data['beneficiary_account_type'] ?? null,
            "beneficiary_nin" => $data['beneficiary_nin'] ?? null,
            "beneficiary_bvn" => $data['beneficiary_bvn'] ?? null,
            "amount" => $data['amount'],
            "transaction_type" => $data['transaction_type'],
            "narration" => $data['narration'] ?? null,
            "channel" => $data['channel'],
            "location" => $data['location'] ?? null,
            "transaction_ref" => $data['transaction_ref'] ?? null,
            "transaction_datetime" => Carbon::parse($data['transaction_datetime'])->toDateTimeString(),
        ]);

        // Resolve/create customers for BOTH sides
        $this->handleCustomer($transaction);

        return $transaction;
    }

    public function handleCustomer(Transaction $transaction): array
    {
        $customers = [];

        // Sender side
        if ($this->hasIdentity($transaction->sender_nin, $transaction->sender_bvn)) {
            $customers['sender'] = $this->resolveCustomer(
                $this->mapSender($transaction),
                $transaction,
                'sender'
            );
        }

        // Beneficiary side
        if ($this->hasIdentity($transaction->beneficiary_nin, $transaction->beneficiary_bvn)) {
            $customers['beneficiary'] = $this->resolveCustomer(
                $this->mapBeneficiary($transaction),
                $transaction,
                'beneficiary'
            );
        }

        return $customers;
    }

    private function resolveCustomer(array $data, Transaction $transaction, string $side): Customer
    {
        $query = Customer::query();
        if (!empty($data['bvn'])) {
            $query->where('bvn', $data['bvn']);
        } elseif (!empty($data['nin'])) {
            $query->where('nin', $data['nin']);
        }

        $customer = $query->first();

        if ($customer) {
            $customer->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'account_number' => $data['account_no'],
            ]);
        } else {
            $customer = Customer::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'account_number' => $data['account_no'],
                'nin' => $data['nin'],
                'bvn' => $data['bvn'],
            ]);
        }

        // Screen THIS SIDE against watchlists
        $this->triggerWatchlist($customer, $transaction, $side);

        return $customer;
    }

    private function triggerWatchlist(Customer $customer, Transaction $transaction, string $side): void
    {
        try {
            app(WatchListService::class)->run([
                'first_name' => $customer->first_name,
                'middle_name' => $customer->middle_name,
                'last_name' => $customer->last_name,
                'account_no' => $customer->account_number,
                'nin' => $customer->nin,
                'bvn' => $customer->bvn,
            ], $transaction, $side);
        } catch (\Exception $e) {
            logger()->warning("Watchlist check failed for {$side}: " . $e->getMessage());
        }
    }

    private function hasIdentity(?string $nin, ?string $bvn): bool
    {
        return !empty($nin) || !empty($bvn);
    }

    private function mapSender(Transaction $t): array
    {
        return [
            'first_name' => $t->sender_first_name, 'middle_name' => $t->sender_middle_name,
            'last_name' => $t->sender_last_name, 'account_no' => $t->sender_account_no,
            'nin' => $t->sender_nin, 'bvn' => $t->sender_bvn,
        ];
    }

    private function mapBeneficiary(Transaction $t): array
    {
        return [
            'first_name' => $t->beneficiary_first_name, 'middle_name' => $t->beneficiary_middle_name,
            'last_name' => $t->beneficiary_last_name, 'account_no' => $t->beneficiary_account_no,
            'nin' => $t->beneficiary_nin, 'bvn' => $t->beneficiary_bvn,
        ];
    }
}
