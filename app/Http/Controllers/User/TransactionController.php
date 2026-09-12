<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\Transaction;
use App\Models\RiskScoringConfig;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class TransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:transaction-list', only: ['index', 'ajaxGet', 'importTransactions']),
        ];
    }

    public function index()
    {
        $transactions = $this->filterTransactions();
        $transactions = $transactions->paginate(getPaginate(15));

        $transactionsQuery = Transaction::orderBy('id', 'desc');
        $total = (clone $transactionsQuery)->count();
        $credit = (clone $transactionsQuery)->whereRaw('LOWER(transaction_type) = LOWER(?)', ['credit'])->count();
        $debit = (clone $transactionsQuery)->whereRaw('LOWER(transaction_type) = LOWER(?)', ['debit'])->count();

        return view('users.transactions.index', compact('transactions', 'total', 'credit', 'debit'));
    }

    public function ajaxGet(Request $request)
    {
        if ($request->has('id')) {
            $transaction = Transaction::find($request->id);
            if ($transaction) return Response::json(['status' => 'success', 'data' => $transaction]);
        }
        return Response::json(['status' => 'failed']);
    }

    public function importTransactions(Request $request)
    {
        $request->validate(['file' => 'required|file']);
        return redirect(route('transactions.index'))->with('success', 'Transactions imported.');
    }

    // ─── Risk Scoring Config CRUD ────────────────────────────────

    public function listRiskConfig()
    {
        $data = RiskScoringConfig::all();
        $total_threshold = $data->where('is_active', true)->sum('weight');
        return view('users.transactions.manage-risk-scoring-config', compact('data', 'total_threshold'));
    }

    public function storeRiskConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'factor_name' => 'required|string|unique:risk_scoring_configs,factor_name',
            'factor_description' => 'required|string',
            'weight' => 'required|integer|min:1',
            'check_type' => 'required|in:transaction,customer,behaviour',
            'check_field' => 'required|string',
            'condition_operator' => 'required|string',
            'logic' => 'nullable|in:AND,OR',
            'cond' => 'nullable|array',
            'window_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) return back()->with('error', $validator->errors()->first());

        RiskScoringConfig::create([
            'factor_name' => strtoupper(str_replace(' ', '_', $request->factor_name)),
            'factor_description' => $request->factor_description,
            'weight' => $request->weight,
            'is_active' => $request->has('is_active'),
            'conditions' => json_encode($this->buildConditions($request)),
        ]);

        activity()->log('Risk scoring factor created: ' . $request->factor_name);
        return redirect(route('transactions.risk-scoring-config'))->with('success', 'Risk scoring factor added.');
    }

    public function updateRiskConfig(Request $request, $id)
    {
        $config = RiskScoringConfig::find($id);
        if (!$config) return back()->with('error', 'Not found.');

        $validator = Validator::make($request->all(), [
            'factor_description' => 'required|string',
            'weight' => 'required|integer|min:1',
            'check_type' => 'required|in:transaction,customer,behaviour',
            'check_field' => 'required|string',
            'condition_operator' => 'required|string',
            'logic' => 'nullable|in:AND,OR',
            'cond' => 'nullable|array',
            'window_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) return back()->with('error', $validator->errors()->first());

        $config->update([
            'factor_description' => $request->factor_description,
            'weight' => $request->weight,
            'is_active' => $request->has('is_active'),
            'conditions' => json_encode($this->buildConditions($request)),
        ]);

        activity()->log('Risk scoring factor updated: ' . $config->factor_name);
        return redirect(route('transactions.risk-scoring-config'))->with('success', 'Risk scoring factor updated.');
    }

    /**
     * Build the conditions payload from the primary condition plus any
     * additional condition rows. One condition is stored in the legacy flat
     * shape for backward compatibility; multiple conditions are stored as
     * {logic, conditions: [...]} (AND/OR).
     */
    private function buildConditions(Request $request): array
    {
        $primary = [
            'check_type' => $request->check_type,
            'field' => $request->check_field,
            'operator' => $request->condition_operator,
            'value' => $request->condition_value,
            'window_days' => $request->window_days ? (int) $request->window_days : 7,
        ];

        $extra = [];
        foreach ((array) $request->input('cond', []) as $row) {
            if (!is_array($row) || empty($row['field']) || empty($row['operator'])) continue;
            $extra[] = [
                'check_type' => in_array($row['check_type'] ?? null, ['transaction', 'customer', 'behaviour'], true)
                    ? $row['check_type']
                    : 'transaction',
                'field' => $row['field'],
                'operator' => $row['operator'],
                'value' => $row['value'] ?? null,
                'window_days' => !empty($row['window_days']) ? (int) $row['window_days'] : 7,
            ];
        }

        $all = array_merge([$primary], $extra);

        if (count($all) === 1) {
            return $all[0];
        }

        return [
            'logic' => strtoupper($request->input('logic', 'AND')) === 'OR' ? 'OR' : 'AND',
            'conditions' => $all,
        ];
    }

    public function destroyRiskConfig($id)
    {
        $config = RiskScoringConfig::find($id);
        if ($config) {
            $config->delete();
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }

    public function updateThreshold(Request $request)
    {
        $request->validate(['threshold' => 'required|integer|min:1']);

        \App\Models\Setting::updateOrCreate(
            ['name' => 'risk_scoring_threshold'],
            ['value' => $request->threshold]
        );

        \Illuminate\Support\Facades\Cache::forget(config('cache.prefix', 'app') . '-settings');
        activity()->log('Risk scoring threshold updated to ' . $request->threshold);

        return redirect(route('transactions.risk-scoring-config'))->with('success', 'Threshold updated to ' . $request->threshold . '.');
    }

    // ─── Filtering ───────────────────────────────────────────────

    protected function filterTransactions()
    {
        $request = request();
        $transactions = Transaction::orderBy('created_at', 'DESC');

        if ($request->search) {
            $search = $request->search;
            $transactions->where(function ($q) use ($search) {
                $q->where('transaction_ref', 'LIKE', "%{$search}%")
                  ->orWhere('sender_first_name', 'LIKE', "%{$search}%")
                  ->orWhere('sender_last_name', 'LIKE', "%{$search}%")
                  ->orWhere('beneficiary_first_name', 'LIKE', "%{$search}%")
                  ->orWhere('beneficiary_last_name', 'LIKE', "%{$search}%")
                  ->orWhere('amount', 'LIKE', "%{$search}%");
            });
        }

        if ($request->transactionType) $transactions->where('transaction_type', $request->transactionType);
        if ($request->channel) $transactions->where('channel', $request->channel);

        if ($request->date) {
            $date = explode('to', $request->date);
            $startDate = trim($date[0] ?? '');
            $endDate = trim($date[1] ?? '');
            if ($endDate) {
                $transactions->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            } elseif ($startDate) {
                $transactions->whereDate('created_at', Carbon::parse($startDate));
            }
        }

        return $transactions;
    }

    public function showTransaction($id)
    {
        $transaction = Transaction::with(['risk_scores.customer', 'aiScores', 'flaggedCases.transaction_rule'])->find($id);
        if (!$transaction) return redirect(route('transactions.index'))->with('error', 'Transaction not found.');
        return view('users.transactions.show', compact('transaction'));
    }

}
