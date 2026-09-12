<?php

namespace App\Services;

use Exception;
use App\Models\Customer;
use App\Models\FlaggedCase;
use App\Models\Transaction;
use App\Models\TransactionRule;
use Illuminate\Support\Collection;
use App\Notifications\FlaggedAccountAlert;
use App\Notifications\FlaggedTransactionAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class TransactionQueryService
{
    public const RUNTIME_INSTANT = 'Instantly';
    public const RUNTIME_24HR = '24HrTask';

    private RuleEvaluationEngine $evaluationEngine;

    public function __construct()
    {
        $this->evaluationEngine = new RuleEvaluationEngine();
    }

    /**
     * Run instant rules against a single incoming transaction
     * Also screens BOTH sides (sender + beneficiary) for watchlist/customer-level rules
     */
    public function runViaApi(Transaction $transaction): void
    {
        $rules = $this->getActiveRules(self::RUNTIME_INSTANT);

        // Evaluate each rule
        foreach ($rules as $rule) {
            try {
                $this->processRule($rule, $transaction, null);
            } catch (Exception $e) {
                Log::error("Rule {$rule->id} error: " . $e->getMessage(), [
                    'rule_id' => $rule->id,
                    'transaction_id' => $transaction->id,
                ]);
            }
        }
    }

    /**
     * Run 24hr rules against all customer accounts
     */
    public function runViaCronJob(string $tableName, Collection $customers): void
    {
        $rules = $this->getActiveRules(self::RUNTIME_24HR);
        foreach ($customers as $customer) {
            foreach ($rules as $rule) {
                try {
                    $this->processRule($rule, null, $customer->account_number);
                } catch (Exception $e) {
                    Log::error("24hr Rule {$rule->id} error for {$customer->account_number}: " . $e->getMessage());
                }
            }
        }
    }

    private function getActiveRules(string $runtime): Collection
    {
        return TransactionRule::where([
            'type' => 'Global',
            'run_time' => $runtime,
            'status' => 1
        ])->get();
    }

    private function processRule(TransactionRule $rule, ?Transaction $transaction, ?string $accountNo): void
    {
        $conditions = is_string($rule->search_attributes)
            ? json_decode($rule->search_attributes, true)
            : $rule->search_attributes;

        if (!$conditions) {
            Log::warning("Invalid conditions for rule {$rule->id}");
            return;
        }

        // Determine which side(s) to evaluate
        $sides = $this->determineSides($conditions, $transaction);

        foreach ($sides as $side => $acctNo) {
            if (!$acctNo) continue;

            $queryBuilder = new TransactionQueryBuilder();
            $result = $queryBuilder
                ->applyRuntimeFilters($rule->run_time, $conditions, $transaction)
                ->applyConditions($conditions, $transaction, $acctNo)
                ->execute();

            if ($result->isEmpty()) continue;

            // Use evaluation engine
            $evaluation = $this->evaluationEngine->evaluate($conditions, $result, $transaction, $acctNo);

            if ($evaluation->shouldTrigger()) {
                $this->handleRuleTrigger($rule, $evaluation, $transaction, $acctNo, $side);
            }
        }
    }

    /**
     * Determine which sides of the transaction to evaluate
     * Rules with sender_account → evaluate sender side
     * Rules with beneficiary_account → evaluate beneficiary side
     * Rules with neither → evaluate the transaction as-is (both sides)
     */
    private function determineSides(array $conditions, ?Transaction $transaction): array
    {
        if (!$transaction) return ['account' => null]; // 24hr cron provides accountNo directly

        $hasSenderAttr = collect($conditions)->contains(fn($c) => ($c['attribute'] ?? '') === 'sender_account');
        $hasBeneficiaryAttr = collect($conditions)->contains(fn($c) => ($c['attribute'] ?? '') === 'beneficiary_account');
        $hasWatchlist = collect($conditions)->contains(fn($c) => in_array($c['attribute'] ?? '', ['in_internal_watchlist', 'in_nibss_watchlist']));
        $hasSelfTransfer = collect($conditions)->contains(fn($c) => ($c['attribute'] ?? '') === 'self_transfer');

        // Self transfer checks the transaction itself
        if ($hasSelfTransfer) {
            return ['both' => $transaction->sender_account_no];
        }

        // Watchlist rules screen both sides
        if ($hasWatchlist) {
            return [
                'sender' => $transaction->sender_account_no,
                'beneficiary' => $transaction->beneficiary_account_no,
            ];
        }

        // Explicit sender-side rule
        if ($hasSenderAttr && !$hasBeneficiaryAttr) {
            return ['sender' => $transaction->sender_account_no];
        }

        // Explicit beneficiary-side rule
        if ($hasBeneficiaryAttr && !$hasSenderAttr) {
            return ['beneficiary' => $transaction->beneficiary_account_no];
        }

        // No specific side — evaluate the transaction as a whole
        return ['transaction' => $transaction->sender_account_no];
    }

    private function handleRuleTrigger(
        TransactionRule $rule,
        RuleEvaluation $evaluation,
        ?Transaction $transaction,
        ?string $accountNo,
        string $side
    ): void {
        $txnId = $evaluation->getTransactionId() ?? $transaction?->id;

        // Check if already flagged
        if (checkIfTransactionAlreadyFlagged($txnId, $rule->id)) return;

        // Resolve customer for this account
        $customer = Customer::where('account_number', $accountNo)->first();

        $case = FlaggedCase::create([
            'slug' => createCaseSlug(),
            'user_id' => getReviewer($rule->id),
            'transaction_rule_id' => $rule->id,
            'transaction_id' => $txnId,
            'transaction_ids' => $evaluation->hasRelatedTransactions() ? $evaluation->getRelatedTransactionIds() : null,
            'account_no' => $evaluation->getFlaggedAccount() ?: $accountNo,
            'customer_id' => $customer?->id,
            'flagged_side' => $side,
            'type' => $evaluation->isAccountLevel() ? 'account' : 'transaction',
            'trigger_source' => FlaggedCase::SOURCE_RULE,
            'trigger_details' => [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'side' => $side,
                'conditions_matched' => true,
            ],
            'report_type' => $rule->report_type ?? 'STR',
        ]);

        $this->sendNotification($rule, $case, $evaluation);

        Log::info("Case {$case->slug} created by Rule {$rule->name} for account {$accountNo} (side: {$side})");
    }

    private function sendNotification(TransactionRule $rule, FlaggedCase $case, RuleEvaluation $evaluation): void
    {
        try {
            $recipients = getNotificationRecipients($rule->id);
            $message = $evaluation->getNotificationMessage($rule, $case);

            $notificationClass = $evaluation->isAccountLevel()
                ? FlaggedAccountAlert::class
                : FlaggedTransactionAlert::class;

            Notification::send(
                $recipients,
                new $notificationClass($message, route('case-management.show', $case->slug))
            );
        } catch (\Exception $e) {
            Log::warning("Notification failed for case {$case->slug}: " . $e->getMessage());
        }
    }

    /**
     * Create a case from a non-rule source (watchlist, risk score, AI, peer group)
     */
    public static function createCaseFromSource(
        string $triggerSource,
        array $triggerDetails,
        ?Transaction $transaction,
        string $accountNo,
        ?int $ruleId = null,
        string $reportType = 'STR',
        string $side = 'transaction'
    ): FlaggedCase {
        $customer = Customer::where('account_number', $accountNo)->first();

        $case = FlaggedCase::create([
            'slug' => createCaseSlug(),
            'user_id' => getReviewer($ruleId),
            'transaction_rule_id' => $ruleId,
            'transaction_id' => $transaction?->id,
            'account_no' => $accountNo,
            'customer_id' => $customer?->id,
            'flagged_side' => $side,
            'type' => 'transaction',
            'trigger_source' => $triggerSource,
            'trigger_details' => $triggerDetails,
            'report_type' => $reportType,
        ]);

        Log::info("Case {$case->slug} created from {$triggerSource} for account {$accountNo}");

        return $case;
    }
}
