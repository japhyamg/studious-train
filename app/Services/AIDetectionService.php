<?php

namespace App\Services;

use App\Models\AiScore;
use Illuminate\Support\Facades\Http;

class AIDetectionService
{
    protected $mlUrl;

    public function __construct()
    {
        $this->mlUrl = rtrim(env('ML_SERVER_URL', 'http://127.0.0.1:5000'), '/');
    }

    public function processTransaction($transaction)
    {
        $customers = getCustomerFromTransaction($transaction);

        if (empty($customers) || !isset($customers['data']) || empty($customers['data'])) {
            return;
        }

        foreach ($customers['data'] as $key => $acct) {
            $side = substr($key, 0, strpos($key, "_") ?: strlen($key));

            $payload = [
                'account_number' => $acct,
                'txn_amount' => $transaction->amount,
                'txn_time' => $transaction->transaction_datetime,
            ];

            $res = $this->scoreTransaction($payload);

            if (!empty($res['success']) && isset($res['data'])) {
                AiScore::create([
                    'transaction_id' => $transaction->id,
                    'account_number' => $acct,
                    'transaction_side' => $side,
                    'is_anomaly' => $res['data']['is_anomaly'] ?? false,
                    'anomaly_score' => $res['data']['anomaly_score'] ?? 0,
                    'severity' => $res['data']['severity'] ?? null,
                    'anomaly_reason' => $res['data']['anomaly_reason'] ?? null,
                    'raw_response' => json_encode($res['data']),
                ]);
            }
        }
    }

    public function scoreTransaction(array $transaction): array
    {
        try {
            $response = Http::timeout(10)->post($this->mlUrl . '/api/predict', $transaction);

            if ($response->failed()) {
                return ['success' => false, 'error' => 'ML server error: ' . $response->body()];
            }

            return ['success' => true, 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}
