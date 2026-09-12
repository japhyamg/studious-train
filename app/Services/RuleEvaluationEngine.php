<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

class RuleEvaluationEngine
{
    public function evaluate(array $conditions, Collection $result, ?Transaction $transaction, ?string $accountNo): RuleEvaluation
    {
        $evaluation = new RuleEvaluation();

        if ($result->isEmpty()) {
            return $evaluation->setTriggered(false);
        }

        $isAccountLevel = collect($conditions)->contains(function ($condition) {
            return in_array($condition['attribute'], [
                'daily_transaction_limit',
                'multiple_beneficiary',
                'multiple_sender',
                'total_amount',
                'total_transactions'
            ]);
        });

        $evaluation->setTriggered(true)
            ->setIsAccountLevel($isAccountLevel);

        if ($transaction) {
            $evaluation->setTransactionId($transaction->id);
            $evaluation->setFlaggedAccount(
                $transaction->sender_account_no ?? $transaction->beneficiary_account_no
            );
        } else {
            $lastTx = $result->last();
            $evaluation->setTransactionId($lastTx->id ?? null);
            $evaluation->setFlaggedAccount(
                $accountNo ?? $lastTx->sender_account_no ?? $lastTx->beneficiary_account_no
            );
        }

        if ($result->count() > 1) {
            $evaluation->setRelatedTransactionIds(
                $result->pluck('id')->unique()->values()->toArray()
            );
        }

        return $evaluation;
    }
}
