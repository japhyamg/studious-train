<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlaggedCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_rule_id', 'transaction_id', 'transaction_ids',
        'slug', 'account_no', 'flagged_side', 'customer_id',
        'type', 'trigger_source', 'trigger_details',
        'status', 'classification', 'report_type',
        'indicator_id', 'user_id', 'closed_by',
        'interdiction_status', 'interdicted_at', 'interdicted_by',
        'sla_due_at',
        'proposed_status', 'proposed_by', 'proposed_at',
        'approved_by', 'approved_at',
        'filing_status', 'filed_at', 'filed_by', 'filing_reference',
    ];

    protected $casts = [
        'transaction_ids' => 'array',
        'trigger_details' => 'array',
        'interdicted_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'proposed_at' => 'datetime',
        'approved_at' => 'datetime',
        'filed_at' => 'datetime',
    ];

    // Trigger source constants
    const SOURCE_RULE = 'rule';
    const SOURCE_WATCHLIST = 'watchlist';
    const SOURCE_RISK_SCORE = 'risk_score';
    const SOURCE_AI_ANOMALY = 'ai_anomaly';
    const SOURCE_PEER_GROUP = 'peer_group';
    const SOURCE_PAS = 'pas';
    const SOURCE_PREEMPTIVE = 'preemptive';
    const SOURCE_CTR = 'ctr';
    const SOURCE_MANUAL = 'manual';

    // Interdiction status constants (CBN 5.3(a)(viii))
    const INTERDICTION_NONE = 'none';
    const INTERDICTION_FROZEN = 'frozen';
    const INTERDICTION_LIFTED = 'lifted';

    // Filing status constants (CBN 5.8(a)(i))
    const FILING_DRAFT = 'draft';
    const FILING_FILED = 'filed';

    // Maker-checker proposal targets (must match status enum values)
    const PROPOSABLE_STATUSES = ['escalated', 'closed_filed', 'closed_not_filed'];

    /**
     * Assign the SLA due date when a case is first created (5.7(a)(i)).
     */
    protected static function booted(): void
    {
        static::creating(function (FlaggedCase $case) {
            if ($case->sla_due_at === null) {
                $case->sla_due_at = $case->computeSlaDueAt();
            }
        });
    }

    public function transaction_rule()
    {
        return $this->belongsTo(TransactionRule::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function comments()
    {
        return $this->hasMany(FlaggedCaseComment::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function interdictingUser()
    {
        return $this->belongsTo(User::class, 'interdicted_by');
    }

    public function getInterdictionLabelAttribute(): string
    {
        return match ($this->interdiction_status) {
            self::INTERDICTION_FROZEN => 'Account Frozen',
            self::INTERDICTION_LIFTED => 'Freeze Lifted',
            default => 'No Interdiction',
        };
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function nfiu_indicator()
    {
        return $this->belongsTo(NfiuIndicator::class, 'indicator_id');
    }

    public function getCaseIdAttribute(): string
    {
        return $this->slug;
    }

    public function getTriggerLabelAttribute(): string
    {
        return match($this->trigger_source) {
            self::SOURCE_RULE => 'Transaction Rule',
            self::SOURCE_WATCHLIST => 'Watchlist Match',
            self::SOURCE_RISK_SCORE => $this->riskScoreLabel(),
            self::SOURCE_AI_ANOMALY => 'AI Anomaly Detected',
            self::SOURCE_PEER_GROUP => 'Peer Group Outlier',
            self::SOURCE_PAS => 'PEP / Sanctions Screening',
            self::SOURCE_PREEMPTIVE => 'Pre-emptive Alert',
            self::SOURCE_CTR => 'CTR Detection',
            self::SOURCE_MANUAL => 'Manual',
            default => ucfirst($this->trigger_source ?? 'Unknown'),
        };
    }

    /**
     * Surface the TTR (transaction risk) score as the trigger reason for
     * risk-scored cases, e.g. "TTR 40" (CBN 5.5(a)(iv)).
     */
    protected function riskScoreLabel(): string
    {
        $score = $this->trigger_details['total_score'] ?? null;
        return $score !== null ? 'TTR ' . $score : 'Risk Score Exceeded';
    }

    // ─── Maker-checker relations ──────────────────────────────────────

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function filingUser()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function getHasPendingDispositionAttribute(): bool
    {
        return $this->proposed_status !== null;
    }

    public function getFilingLabelAttribute(): string
    {
        return $this->filing_status === self::FILING_FILED ? 'Filed' : 'Draft';
    }

    // ─── SLA / TAT helpers (5.7(a)(i)) ────────────────────────────────

    /**
     * The risk level that drives this case's SLA — the customer's current
     * risk level when known, otherwise null (caller falls back to a default).
     */
    public function slaRiskLevel(): ?RiskLevel
    {
        $label = $this->customer?->current_risk_level;
        if (!$label) return null;

        return RiskLevel::where('label', $label)->first();
    }

    public function computeSlaDueAt(): ?\Carbon\Carbon
    {
        if (!settingBool('case_sla_enabled', config('governance.sla.enabled', true))) {
            return null;
        }

        $tat = $this->slaRiskLevel()?->case_tat_hours
            ?: (int) settings('case_tat_default_hours', config('governance.sla.default_tat_hours', 48));

        if ($tat <= 0) return null;

        return \Carbon\Carbon::parse($this->created_at ?? now())->addHours($tat);
    }

    /**
     * Returns ['status' => on_track|at_risk|breached, 'due_at', 'remaining' => human,
     * 'hours' => signed hours remaining].
     */
    public function slaSummary(): array
    {
        $due = $this->sla_due_at ?? $this->computeSlaDueAt();

        if (!$due) {
            return ['status' => 'not_applicable', 'due_at' => null, 'remaining' => '—', 'hours' => null];
        }

        $hours = (int) round(now()->diffInHours($due, false));

        if ($hours < 0) {
            $status = 'breached';
            $remaining = 'Overdue ' . abs($hours) . 'h';
        } elseif ($hours <= 8) {
            $status = 'at_risk';
            $remaining = $hours <= 0 ? 'Due now' : "Due in {$hours}h";
        } else {
            $status = 'on_track';
            $remaining = "Due in {$hours}h";
        }

        return ['status' => $status, 'due_at' => $due, 'remaining' => $remaining, 'hours' => $hours];
    }

    /**
     * STR filing deadline from case creation (5.8(a)(i)).
     */
    public function strFilingDueAt(): ?\Carbon\Carbon
    {
        if ($this->report_type !== 'STR') return null;

        $days = (int) settings('str_filing_sla_days', config('governance.filing.str_sla_days', 5));
        return \Carbon\Carbon::parse($this->created_at)->addDays($days);
    }

    public function strFilingStatus(): array
    {
        $due = $this->strFilingDueAt();
        if (!$due) return ['status' => 'not_applicable', 'label' => '—'];

        if ($this->filing_status === self::FILING_FILED) {
            return ['status' => 'filed', 'label' => 'Filed ' . ($this->filed_at?->format('M d, Y') ?? '')];
        }

        $hours = (int) round(now()->diffInHours($due, false));
        if ($hours < 0) {
            return ['status' => 'breached', 'label' => 'Overdue by ' . abs($hours) . 'h'];
        }
        return ['status' => 'pending', 'label' => "Due in {$hours}h"];
    }
}
