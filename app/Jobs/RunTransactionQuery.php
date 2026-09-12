<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\FlaggedCase;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Services\TransactionQueryService;
use App\Services\TransactionRiskScoringService;
use App\Services\PeerGroupAnalysisService;
use App\Services\AIDetectionService;

class RunTransactionQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function handle(): void
    {
        $txn = $this->transaction;

        // 1. Transaction Risk Scoring
        try {
            $riskService = new TransactionRiskScoringService();
            $riskService->processTransaction($txn);

            // Check if risk score exceeded threshold → create case
            $this->checkRiskScoreThreshold($txn);
        } catch (\Exception $e) {
            Log::warning('Risk scoring failed for txn ' . $txn->id . ': ' . $e->getMessage());
        }

        // 2. Peer Group Analysis
        try {
            $peerService = new PeerGroupAnalysisService();
            $peerService->processTransaction($txn);

            // Check if peer group flagged → create case
            $this->checkPeerGroupOutliers($txn);
        } catch (\Exception $e) {
            Log::warning('Peer group analysis failed for txn ' . $txn->id . ': ' . $e->getMessage());
        }

        // 3. AI Detection
        try {
            $aiService = new AIDetectionService();
            $aiService->processTransaction($txn);

            // Check if AI flagged anomaly → create case
            $this->checkAIAnomalies($txn);
        } catch (\Exception $e) {
            Log::warning('AI detection failed for txn ' . $txn->id . ': ' . $e->getMessage());
        }

        // 4. Transaction Rule Engine (handles its own case creation)
        try {
            $queryService = new TransactionQueryService();
            $queryService->runViaApi($txn);
        } catch (\Exception $e) {
            Log::warning('Rule engine failed for txn ' . $txn->id . ': ' . $e->getMessage());
        }

        Log::info('Transaction processing complete for ID: ' . $txn->id);
    }

    /**
     * If risk score >= threshold, create a case
     */
    private function checkRiskScoreThreshold(Transaction $txn): void
    {
        $threshold = (int) settings('risk_scoring_threshold', 100);

        $risks = \App\Models\TransactionRisk::where('transaction_id', $txn->id)
            ->where('total_score', '>=', $threshold)
            ->get();

        foreach ($risks as $risk) {
            $customer = Customer::find($risk->customer_id);
            if (!$customer) continue;

            // Don't duplicate — check if already flagged for this reason
            $exists = FlaggedCase::where('transaction_id', $txn->id)
                ->where('trigger_source', FlaggedCase::SOURCE_RISK_SCORE)
                ->where('account_no', $customer->account_number)
                ->exists();

            if ($exists) continue;

            TransactionQueryService::createCaseFromSource(
                FlaggedCase::SOURCE_RISK_SCORE,
                [
                    'total_score' => $risk->total_score,
                    'threshold' => $threshold,
                    'risk_level' => $risk->risk_level,
                    'trigger_reason' => 'TTR ' . $risk->total_score,
                    'breakdown' => $risk->meta['score_breakdown'] ?? [],
                ],
                $txn,
                $customer->account_number,
                null,
                'STR',
                $customer->account_number === $txn->sender_account_no ? 'sender' : 'beneficiary'
            );
        }
    }

    /**
     * If peer group flagged an outlier, create a case
     */
    private function checkPeerGroupOutliers(Transaction $txn): void
    {
        $outliers = \App\Models\PeerGroupOutlier::where('transaction_id', $txn->id)
            ->where('is_flagged', true)
            ->get();

        foreach ($outliers as $outlier) {
            $customer = Customer::find($outlier->customer_id);
            if (!$customer) continue;

            $exists = FlaggedCase::where('transaction_id', $txn->id)
                ->where('trigger_source', FlaggedCase::SOURCE_PEER_GROUP)
                ->where('account_no', $customer->account_number)
                ->exists();

            if ($exists) continue;

            TransactionQueryService::createCaseFromSource(
                FlaggedCase::SOURCE_PEER_GROUP,
                [
                    'peer_group' => $outlier->peer_group,
                    'customer_group' => $outlier->customer_group,
                    'threshold' => $outlier->threshold,
                    'exceeded_by' => $outlier->exceeded_by,
                ],
                $txn,
                $customer->account_number,
                null,
                'STR'
            );
        }
    }

    /**
     * If AI detected a high-severity anomaly, create a case
     */
    private function checkAIAnomalies(Transaction $txn): void
    {
        $anomalies = \App\Models\AiScore::where('transaction_id', $txn->id)
            ->where('is_anomaly', true)
            ->whereIn('severity', ['high', 'critical'])
            ->get();

        foreach ($anomalies as $anomaly) {
            $exists = FlaggedCase::where('transaction_id', $txn->id)
                ->where('trigger_source', FlaggedCase::SOURCE_AI_ANOMALY)
                ->where('account_no', $anomaly->account_number)
                ->exists();

            if ($exists) continue;

            TransactionQueryService::createCaseFromSource(
                FlaggedCase::SOURCE_AI_ANOMALY,
                [
                    'anomaly_score' => $anomaly->anomaly_score,
                    'severity' => $anomaly->severity,
                    'reason' => $anomaly->anomaly_reason,
                    'side' => $anomaly->transaction_side,
                ],
                $txn,
                $anomaly->account_number,
                null,
                'STR',
                $anomaly->transaction_side === 'sender' ? 'sender' : 'beneficiary'
            );
        }
    }
}
