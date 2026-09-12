<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Jobs\RunTransactionQuery;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Http\Requests\CreateTransactionRequest;

class ApiController extends Controller
{
    public $transactionService;

    public function __construct()
    {
        $this->transactionService = new TransactionService();
    }

    public function store(CreateTransactionRequest $request)
    {
        $transaction = $this->transactionService->handle($request->all());

        if ($transaction) {
            // Process transaction through rule engine, risk scoring, peer group,
            // and AI on the queue so the API returns immediately and long-running
            // HTTP checks (AI, PEP, sanctions) never block the request.
            RunTransactionQuery::dispatch($transaction);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction accepted for processing.',
                'data' => ['transaction' => $transaction],
            ], 202);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process transaction',
        ], 400);
    }
}
