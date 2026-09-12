<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CustomerDetailsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customer-list|customer-create|customer-update|customer-delete|customer-import', only: ['index', 'show']),
            new Middleware('permission:customer-create', only: ['create', 'store']),
            new Middleware('permission:customer-update', only: ['update']),
            new Middleware('permission:customer-delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $customers = $this->filterCustomers()->paginate(getPaginate(15));
        return view('users.customer_details.index', compact('customers'));
    }

    public function create()
    {
        return view('users.customer_details.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_first_name' => 'required',
            'customer_last_name' => 'required',
            'customer_account_no' => 'required|digits:10',
            'customer_d_of_b' => 'required',
            'customer_gender' => 'required',
            'customer_type' => 'required',
            'customer_account_type' => 'required',
            'customer_tier_level' => 'required',
            'customer_ispep' => 'required',
            'customer_sor' => 'required',
            'customer_lga' => 'required',
        ]);

        Customer::create([
            'first_name' => $request->customer_first_name,
            'middle_name' => $request->customer_middle_name,
            'last_name' => $request->customer_last_name,
            'account_number' => $request->customer_account_no,
            'date_of_birth' => $request->customer_d_of_b,
            'gender' => $request->customer_gender,
            'bvn' => $request->customer_bvn,
            'nin' => $request->customer_nin,
            'account_type' => $request->customer_account_type,
            'customer_type' => $request->customer_type,
            'tier_level' => $request->customer_tier_level,
            'isPep' => $request->customer_ispep == 'yes' ? 'yes' : 'no',
            'state_of_residence' => $request->customer_sor,
            'local_govt_area' => $request->customer_lga,
        ]);

        return redirect(route('customers.all'))->with('success', 'Customer added successfully.');
    }

    public function show($id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return redirect(route('customers.all'))->with('error', 'Customer not found.');
        }

        $acctNo = $customer->account_number;

        // Transaction stats
        $txnStats = $customer->transactionStats();

        // STR/CTR counts (via transaction relationship)
        $strCount = $customer->strCount();
        $ctrCount = $customer->ctrCount();
        $totalCases = $customer->flaggedCases()->count();

        // Paginated transactions
        $txns = \App\Models\Transaction::where(function($q) use ($acctNo) {
                $q->where('sender_account_no', $acctNo)
                  ->orWhere('beneficiary_account_no', $acctNo);
            })
            ->orderBy('transaction_datetime', 'desc')
            ->paginate(10, ['*'], 'txn_page')
            ->withQueryString();

        // Paginated flagged cases (via transaction IDs)
        $txnIds = \App\Models\Transaction::where('sender_account_no', $acctNo)
            ->orWhere('beneficiary_account_no', $acctNo)
            ->pluck('id');

        $cases = \App\Models\FlaggedCase::whereIn('transaction_id', $txnIds)
            ->with('transaction_rule')
            ->latest()
            ->paginate(10, ['*'], 'case_page')
            ->withQueryString();

        return view('users.customer_details.show', compact(
            'customer', 'txnStats', 'strCount', 'ctrCount', 'totalCases', 'txns', 'cases'
        ));
    }

    public function ajaxshow(Request $request)
    {
        if ($request->has('id')) {
            $customer = Customer::find($request->id);
            if ($customer) {
                return Response::json(['status' => 'success', 'data' => $customer]);
            }
        }
        return Response::json(['status' => 'failed']);
    }

    public function update(Request $request)
    {
        $request->validate([
            'customer_id' => 'required',
            'customer_first_name' => 'required',
            'customer_last_name' => 'required',
            'customer_account_no' => 'required|digits:10',
        ]);

        $customer = Customer::find($request->customer_id);
        if ($customer) {
            $customer->update([
                'first_name' => $request->customer_first_name,
                'middle_name' => $request->customer_middle_name,
                'last_name' => $request->customer_last_name,
                'account_number' => $request->customer_account_no,
                'date_of_birth' => $request->customer_d_of_b,
                'gender' => $request->customer_gender,
                'bvn' => $request->customer_bvn,
                'nin' => $request->customer_nin,
                'account_type' => $request->customer_account_type,
                'customer_type' => $request->customer_type,
                'tier_level' => $request->customer_tier_level,
                'isPep' => $request->customer_ispep == 'yes' ? 'yes' : 'no',
                'state_of_residence' => $request->customer_sor,
                'local_govt_area' => $request->customer_lga,
            ]);
            return redirect(route('customers.all'))->with('success', 'Customer updated successfully.');
        }
        return redirect(route('customers.all'))->with('error', 'Unauthorized action.');
    }

    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            $customer = Customer::find($request->id);
            if ($customer) {
                $customer->delete();
                return Response::json(['status' => 'success']);
            }
        }
        return Response::json(['status' => 'failed']);
    }

    protected function filterCustomers()
    {
        $request = request();
        $customers = Customer::orderBy('created_at', 'DESC');

        if ($request->search) {
            $search = $request->search;
            $customers->where(function ($q) use ($search) {
                $q->where('account_number', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('bvn', 'LIKE', "%{$search}%")
                  ->orWhere('nin', 'LIKE', "%{$search}%");
            });
        }

        if ($request->accountType) $customers->where('account_type', $request->accountType);
        if ($request->customerType) $customers->where('customer_type', $request->customerType);
        if ($request->tier) $customers->where('tier_level', $request->tier);
        if ($request->state) $customers->where('state_of_residence', $request->state);
        if ($request->gender) $customers->where('gender', $request->gender);

        return $customers;
    }
}
