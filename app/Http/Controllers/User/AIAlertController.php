<?php

namespace App\Http\Controllers\User;

use App\Models\AiScore;
use App\Models\FlaggedCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AIAlertController extends Controller
{
    public function index()
    {
        $alerts = AiScore::with('transaction')
            ->where('is_anomaly', true)
            ->orderBy('created_at', 'DESC')
            ->paginate(20);

        return view('users.dashboard.ai-alerts', compact('alerts'));
    }

    public function flagAsCase($id)
    {
        $aiScore = AiScore::with('transaction')->findOrFail($id);

        // Check if a case already exists for this transaction from AI
        $existingCase = FlaggedCase::where('transaction_id', $aiScore->transaction_id)
            ->where('trigger_source', 'ai_anomaly')
            ->first();

        if ($existingCase) {
            return redirect()->route('case-management.show', $existingCase->slug)
                ->with('info', 'A case already exists for this AI alert.');
        }

        // Determine flagged side
        $flaggedSide = $aiScore->transaction_side ?? 'transaction';

        // Get account number
        $accountNo = $aiScore->account_number;
        if (!$accountNo && $aiScore->transaction) {
            $accountNo = $flaggedSide === 'beneficiary'
                ? $aiScore->transaction->beneficiary_account_no
                : $aiScore->transaction->sender_account_no;
        }

        // Find customer if exists
        $customer = null;
        if ($accountNo) {
            $customer = \App\Models\Customer::where('account_number', $accountNo)->first();
        }

        // Create the flagged case
        $case = FlaggedCase::create([
            'slug' => createCaseSlug(),
            'transaction_id' => $aiScore->transaction_id,
            'account_no' => $accountNo ?? '',
            'customer_id' => $customer?->id,
            'status' => 'open',
            'classification' => 'true_positive',
            'report_type' => 'STR',
            'user_id' => getReviewer(),
            'trigger_source' => 'ai_anomaly',
            'trigger_details' => [
                'ai_score_id' => $aiScore->id,
                'anomaly_score' => $aiScore->anomaly_score,
                'severity' => $aiScore->severity,
                'reason' => $aiScore->anomaly_reason,
                'transaction_side' => $aiScore->transaction_side,
            ],
            'flagged_side' => $flaggedSide,
        ]);

        // Add initial comment
        $case->comments()->create([
            'comment' => "Case created from AI anomaly alert. Score: {$aiScore->anomaly_score}, Severity: {$aiScore->severity}. Reason: {$aiScore->anomaly_reason}",
            'user_id' => auth()->id(),
        ]);

        activity()->performedOn($case)->log("Case {$case->slug} created from AI alert #{$aiScore->id}");

        return redirect()->route('case-management.show', $case->slug)
            ->with('success', "Case {$case->slug} created from AI alert.");
    }
}
