<?php

namespace App\Http\Controllers\User;

use App\Models\InternalWatchList;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class WatchListController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:internal-watchlist-list', only: ['index', 'store', 'show', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $watchlist = InternalWatchList::orderBy('created_at', 'DESC')->paginate(15);
        return view('users.watchlist.internal.index', compact('watchlist'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_first_name' => 'required',
            'customer_last_name' => 'required',
            'customer_account_no' => 'required',
            'customer_bvn' => 'required',
            'customer_nin' => 'required',
        ]);

        InternalWatchList::create([
            'first_name' => $request->customer_first_name,
            'middle_name' => $request->customer_middle_name,
            'last_name' => $request->customer_last_name,
            'account_no' => $request->customer_account_no,
            'bvn' => $request->customer_bvn,
            'nin' => $request->customer_nin,
        ]);

        return redirect(route('watch-list.internal.all'))->with('success', 'Customer added to watch list.');
    }

    public function show(Request $request)
    {
        if ($request->has('id')) {
            $customer = InternalWatchList::find($request->id);
            if ($customer) return Response::json(['status' => 'success', 'data' => $customer]);
        }
        return Response::json(['status' => 'failed']);
    }

    public function update(Request $request)
    {
        $request->validate(['customer_id' => 'required', 'customer_first_name' => 'required', 'customer_last_name' => 'required']);

        $customer = InternalWatchList::find($request->customer_id);
        if ($customer) {
            $customer->update([
                'first_name' => $request->customer_first_name,
                'middle_name' => $request->customer_middle_name,
                'last_name' => $request->customer_last_name,
                'account_no' => $request->customer_account_no,
                'bvn' => $request->customer_bvn,
                'nin' => $request->customer_nin,
            ]);
            return redirect(route('watch-list.internal.all'))->with('success', 'Customer updated.');
        }
        return redirect(route('watch-list.internal.all'))->with('error', 'Unauthorized action.');
    }

    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            $item = InternalWatchList::find($request->id);
            if ($item) { $item->delete(); return Response::json(['status' => 'success']); }
        }
        return Response::json(['status' => 'failed']);
    }
    public function upload(Request $request)
    {
        $request->validate(["file" => "required|file"]);
        // Import logic placeholder - requires Maatwebsite Excel import class
        return redirect(route("watch-list.internal.all"))->with("success", "File uploaded successfully.");
    }
}
