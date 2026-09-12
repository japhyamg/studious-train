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
            // Process transaction through rule engine, risk scoring, peer group, AI
            (new RunTransactionQuery($transaction))->handle();

            return response()->json([
                'status' => 'success',
                'data' => ['transaction' => $transaction],
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process transaction',
        ], 400);
    }
}
