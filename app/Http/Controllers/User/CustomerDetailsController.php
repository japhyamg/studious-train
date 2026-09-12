<?php

namespace App\Http\Controllers\User;

use Carbon\Carbon;
use App\Models\Customer;
use App\Models\RiskLevelChange;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            new Middleware('permission:customer-list|customer-create|customer-update|customer-delete|customer-import', only: ['index', 'show', 'search', 'export360']),
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

        // Watchlist standing + risk-level change history for the 360 view.
        $watchlist = $customer->watchlistStatus();
        $riskChanges = RiskLevelChange::where('customer_id', $customer->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('users.customer_details.show', compact(
            'customer', 'txnStats', 'strCount', 'ctrCount', 'totalCases',
            'txns', 'cases', 'watchlist', 'riskChanges'
        ));
    }

    /**
     * Global customer search — resolve a query to the best-matching customer
     * and open their unified 360 view (CBN 5.9(a)(vi)).
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q'));
        if ($q === '') {
            return redirect(route('customers.all'))->with('error', 'Enter a name, account number, BVN or NIN to search.');
        }

        $match = Customer::where('account_number', $q)
            ->orWhere('bvn', $q)
            ->orWhere('nin', $q)
            ->first();

        if (!$match) {
            $match = Customer::where('first_name', 'LIKE', "%{$q}%")
                ->orWhere('last_name', 'LIKE', "%{$q}%")
                ->orderBy('created_at', 'DESC')
                ->first();
        }

        if (!$match) {
            return redirect(route('customers.all', ['search' => $q]))
                ->with('error', "No customer found for \"{$q}\".");
        }

        return redirect(route('customers.show', $match->id));
    }

    /**
     * Export the customer 360 view as CSV or PDF (CBN 5.9(a)(vi)/(vii)).
     */
    public function export360(Request $request, $id)
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return redirect(route('customers.all'))->with('error', 'Customer not found.');
        }

        $format = strtolower($request->input('format', 'csv'));

        $txnStats = $customer->transactionStats();
        $watchlist = $customer->watchlistStatus();
        $riskChanges = RiskLevelChange::where('customer_id', $customer->id)->latest()->get();

        $rows = [
            ['Field', 'Value'],
            ['Full Name', $customer->name],
            ['Account Number', $customer->account_number],
            ['Customer Type', $customer->customer_type ?? '—'],
            ['Tier Level', $customer->tier_level ?? '—'],
            ['Risk Level', $customer->current_risk_level ?? 'Not Rated'],
            ['Risk Score', $customer->current_risk_score ?? '—'],
            ['PEP Status', ($customer->isPep === 'yes' || $customer->isPep == 1) ? 'Yes' : 'No'],
            ['BVN', $customer->bvn ?? '—'],
            ['NIN', $customer->nin ?? '—'],
            ['Gender', $customer->gender ?? '—'],
            ['Date of Birth', $customer->date_of_birth?->format('Y-m-d') ?? '—'],
            ['Phone', $customer->phone_number ?? '—'],
            ['Email', $customer->email ?? '—'],
            ['Address', $customer->address ?? '—'],
            ['Occupation', $customer->occupation ?? '—'],
            ['Employer', $customer->employer_name ?? '—'],
            ['Source of Funds', $customer->source_of_funds ?? '—'],
            ['Income Range', $customer->income_range ?? '—'],
            ['Business Activity', $customer->business_activity ?? '—'],
            ['State of Residence', $customer->state_of_residence ?? '—'],
            ['LGA', $customer->local_govt_area ?? '—'],
            ['Total Transactions', $txnStats['total_count']],
            ['Credit Count', $txnStats['credit_count']],
            ['Credit Value (₦)', number_format($txnStats['credit_value'], 2)],
            ['Debit Count', $txnStats['debit_count']],
            ['Debit Value (₦)', number_format($txnStats['debit_value'], 2)],
            ['STR Count', $customer->strCount()],
            ['CTR Count', $customer->ctrCount()],
            ['Internal Watchlist', $watchlist['internal'] ?? 'Not listed'],
            ['NIBSS Watchlist', $watchlist['nibss'] ?? 'Not listed'],
            ['NIBSS Reason', $watchlist['nibss_reason'] ?? '—'],
            ['Next Review Date', $customer->next_review_date?->format('Y-m-d') ?? '—'],
        ];

        foreach ($riskChanges as $i => $change) {
            $rows[] = ["Risk Change #" . ($i + 1), "{$change->from_level ?? '—'} → {$change->to_level ?? '—'} (score {$change->score}) on {$change->created_at?->format('Y-m-d H:i')} — {$change->driver}"];
        }

        if ($format === 'pdf') {
            if (!class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
                return back()->with('error', 'PDF export is unavailable.');
            }
            $html = '<h4>Customer 360 — ' . e($customer->name) . '</h4><table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:11px;">';
            foreach ($rows as $row) {
                $html .= '<tr><td style="width:35%;font-weight:bold">' . e((string) $row[0]) . '</td><td>' . e((string) $row[1]) . '</td></tr>';
            }
            $html .= '</table>';
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            return $pdf->download('customer_360_' . $customer->account_number . '_' . now()->format('Y-m-d') . '.pdf');
        }

        $filename = 'customer_360_' . $customer->account_number . '_' . now()->format('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
