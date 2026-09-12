<?php

namespace App\Http\Controllers\User;

use App\Models\NibssWatchList;
use App\Services\WatchListImportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class NIBSSWatchListController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:nibss-watchlist-list', only: ['index', 'store', 'show', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $watchlist = NibssWatchList::orderBy('created_at', 'DESC')->paginate(15);
        return view('users.watchlist.nibss.index', compact('watchlist'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_bvn' => 'required',
            'customer_first_name' => 'required',
            'customer_last_name' => 'required',
            'customer_category' => 'required',
            'customer_reason' => 'required',
        ]);

        NibssWatchList::create([
            'bvn' => $request->customer_bvn,
            'first_name' => $request->customer_first_name,
            'middle_name' => $request->customer_middle_name,
            'last_name' => $request->customer_last_name,
            'category' => $request->customer_category,
            'reason' => $request->customer_reason,
        ]);

        return redirect(route('watch-list.nibss.all'))->with('success', 'Added to NIBSS watchlist.');
    }

    public function show(Request $request)
    {
        if ($request->has('id')) {
            $customer = NibssWatchList::find($request->id);
            if ($customer) return Response::json(['status' => 'success', 'data' => $customer]);
        }
        return Response::json(['status' => 'failed']);
    }

    public function update(Request $request)
    {
        $request->validate(['customer_bvn' => 'required', 'customer_first_name' => 'required', 'customer_last_name' => 'required']);

        $customer = NibssWatchList::find($request->customer_id);
        if ($customer) {
            $customer->update([
                'bvn' => $request->customer_bvn,
                'first_name' => $request->customer_first_name,
                'middle_name' => $request->customer_middle_name,
                'last_name' => $request->customer_last_name,
                'category' => $request->customer_category,
                'reason' => $request->customer_reason,
            ]);
            return redirect(route('watch-list.nibss.all'))->with('success', 'Updated successfully.');
        }
        return redirect(route('watch-list.nibss.all'))->with('error', 'Unauthorized action.');
    }

    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            $item = NibssWatchList::find($request->id);
            if ($item) { $item->delete(); return Response::json(['status' => 'success']); }
        }
        return Response::json(['status' => 'failed']);
    }
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|extensions:csv,xlsx,xls',
        ]);

        try {
            $stats = (new WatchListImportService())->importNibss($request->file('file'));

            activity()->log("NIBSS watchlist import: {$stats['created']} created, {$stats['skipped']} skipped, {$stats['errors']} errors.");

            return redirect(route('watch-list.nibss.all'))->with(
                'success',
                "Import complete: {$stats['created']} added, {$stats['skipped']} skipped, {$stats['errors']} failed."
            );
        } catch (\Throwable $e) {
            return redirect(route('watch-list.nibss.all'))->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
