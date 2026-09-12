<?php

namespace App\Http\Controllers\User;

use App\Models\Setting;
use App\Models\BusinessDetails;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'two_step_verification' => settings('two_step_verification'),
            'case_notification' => settings('case_notification'),
            'case_prefix' => getBusinessDetails('case_prefix'),
        ];

        return view('users.settings.index', $settings);
    }

    public function generateApiKeys()
    {
        if (storeBusinessClientIdAndSecret()) {
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }

    public function toggleEmailOtp(Request $request)
    {
        if ($request->has('action') && in_array($request->action, ['activate', 'deactivate'])) {
            $user = Auth::user();
            $user->email_otp_enabled = $request->action == 'activate' ? 1 : 0;
            $user->save();
            return redirect(route('settings'))->with('success', 'Updated successfully');
        }
        return redirect()->back();
    }

    public function settingsToggleUpdate(Request $request)
    {
        if ($request->has('action') && $request->has('value')) {
            Setting::where(['name' => $request->action])->update(['value' => $request->value]);
            Cache::forget(config('cache.prefix') . '-settings');
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }

    public function saveGovernanceSettings(Request $request)
    {
        $request->validate([
            'str_filing_sla_days' => 'nullable|integer|min:1',
            'audit_retention_days' => 'nullable|integer|min:1',
        ]);

        $fields = [
            'str_filing_sla_days' => $request->str_filing_sla_days,
            'audit_retention_days' => $request->audit_retention_days,
            'maker_checker_enabled' => $request->boolean('maker_checker_enabled') ? 'true' : 'false',
        ];

        foreach ($fields as $name => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(['name' => $name], ['value' => (string) $value]);
            }
        }

        Cache::forget(config('cache.prefix') . '-settings');

        return redirect(route('settings'))->with('success', 'Governance settings saved.');
    }

    public function saveCustomerSyncSettings(Request $request)
    {
        $fields = [
            'customer_sync_host', 'customer_sync_port', 'customer_sync_database',
            'customer_sync_username', 'customer_sync_password', 'customer_sync_table',
            'customer_sync_account_field', 'customer_sync_updated_at_field',
            'customer_sync_interval_hours', 'customer_sync_batch_size',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::updateOrCreate(
                    ['name' => $field],
                    ['value' => $request->input($field, '')]
                );
            }
        }

        Cache::forget(config('cache.prefix') . '-settings');

        return redirect(route('settings'))->with('success', 'Customer sync settings saved.');
    }
}
