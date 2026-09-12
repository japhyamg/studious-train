<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\TransactionRule;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TransactionRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:rule-list', only: ['index']),
            new Middleware('permission:rule-create', only: ['create', 'store']),
            new Middleware('permission:rule-edit-value', only: ['edit', 'update', 'editValue', 'storeValue']),
            new Middleware('permission:rule-delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $rules = TransactionRule::orderBy('created_at', 'DESC')->get();
        return view('users.rules.index', compact('rules'));
    }

    public function create()
    {
        return view('users.rules.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'status' => 'required',
            'run_time' => 'required',
            'attribute' => 'required|array',
            'action' => 'required|array',
            'value' => 'required|array',
            'type' => 'required|array',
        ]);

        $conditions = [];
        for ($i = 0; $i < count($request->attribute); $i++) {
            if ($request->attribute[$i] == 'transaction_time' && is_string($request->value[$i])) {
                $range = explode(',', $request->value[$i]);
                $from = Carbon::parse(rtrim($range[0] ?? '', ' (West Africa Standard Time)'))->toTimeString();
                $to = Carbon::parse(rtrim($range[1] ?? '', ' (West Africa Standard Time)'))->toTimeString();
                $conditions[] = [
                    'attribute' => $request->attribute[$i],
                    'action' => $request->action[$i],
                    'value' => ['from' => $from, 'to' => $to],
                    'type' => $request->type[$i],
                ];
            } else {
                $conditions[] = [
                    'attribute' => $request->attribute[$i],
                    'action' => $request->action[$i],
                    'value' => $request->value[$i],
                    'type' => $request->type[$i],
                ];
            }
        }

        TransactionRule::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status == 'true' ? 1 : 0,
            'run_time' => $request->run_time,
            'report_type' => $request->report_type ?? 'STR',
            'search_attributes' => json_encode($conditions),
        ]);

        activity()->log('Transaction rule created: ' . $request->name);

        return redirect(route('transaction-rules.index'))->with('success', 'Transaction rule added successfully.');
    }

    public function edit($id)
    {
        $rule = TransactionRule::find($id);
        if (!$rule) return redirect(route('transaction-rules.index'))->with('error', 'Rule not found.');
        return view('users.rules.edit', compact('rule'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'attribute' => 'required|array',
            'action' => 'required|array',
            'value' => 'required|array',
            'type' => 'required|array',
        ]);

        $rule = TransactionRule::find($id);
        if (!$rule) return redirect(route('transaction-rules.index'))->with('error', 'Rule not found.');

        $conditions = [];
        for ($i = 0; $i < count($request->attribute); $i++) {
            $conditions[] = [
                'attribute' => $request->attribute[$i],
                'action' => $request->action[$i],
                'value' => $request->value[$i],
                'type' => $request->type[$i],
            ];
        }

        $rule->update([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status == 'true' ? 1 : 0,
            'run_time' => $request->run_time,
            'report_type' => $request->report_type ?? 'STR',
            'search_attributes' => json_encode($conditions),
        ]);

        activity()->log('Transaction rule updated: ' . $rule->name);

        return redirect(route('transaction-rules.index'))->with('success', 'Transaction rule updated successfully.');
    }

    public function editValue($id)
    {
        $rule = TransactionRule::find($id);
        if ($rule) return view('users.rules.edit', compact('rule'));
        return redirect(route('transaction-rules.index'))->with('error', 'Unauthorized Action!!');
    }

    public function storeValue($id, Request $request)
    {
        $rule = TransactionRule::with('rule_settings')->find($id);
        if ($rule) {
            $conditions = [];
            for ($i = 0; $i < count($request->attribute); $i++) {
                $conditions[] = [
                    'attribute' => $request->attribute[$i],
                    'value' => $request->value[$i],
                ];
            }
            $rule->rule_settings()->updateOrCreate(
                ['transaction_rule_id' => $rule->id],
                ['search_attributes' => json_encode($conditions), 'status' => $request->status]
            );
            return redirect(route('transaction-rules.edit-value', $rule->id))->with('success', 'Changes saved.');
        }
        return redirect(route('transaction-rules.index'))->with('error', 'Unauthorized Action.');
    }

    public function activate($id)
    {
        $rule = TransactionRule::find($id);
        if ($rule) {
            $rule->update(['status' => true]);
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }

    public function deactivate($id)
    {
        $rule = TransactionRule::find($id);
        if ($rule) {
            $rule->update(['status' => false]);
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }

    public function destroy($id)
    {
        $rule = TransactionRule::find($id);
        if ($rule) {
            $name = $rule->name;
            $rule->delete();
            activity()->log('Transaction rule deleted: ' . $name);
            return Response::json(['status' => 'success']);
        }
        return Response::json(['status' => 'failed']);
    }
}
