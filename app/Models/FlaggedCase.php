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
    ];

    protected $casts = [
        'transaction_ids' => 'array',
        'trigger_details' => 'array',
        'interdicted_at' => 'datetime',
    ];

    // Trigger source constants
    const SOURCE_RULE = 'rule';
    const SOURCE_WATCHLIST = 'watchlist';
    const SOURCE_RISK_SCORE = 'risk_score';
    const SOURCE_AI_ANOMALY = 'ai_anomaly';
    const SOURCE_PEER_GROUP = 'peer_group';
    const SOURCE_PAS = 'pas';
    const SOURCE_MANUAL = 'manual';

    // Interdiction status constants (CBN 5.3(a)(viii))
    const INTERDICTION_NONE = 'none';
    const INTERDICTION_FROZEN = 'frozen';
    const INTERDICTION_LIFTED = 'lifted';

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
            self::SOURCE_RISK_SCORE => 'Risk Score Exceeded',
            self::SOURCE_AI_ANOMALY => 'AI Anomaly Detected',
            self::SOURCE_PEER_GROUP => 'Peer Group Outlier',
            self::SOURCE_PAS => 'PEP / Sanctions Screening',
            self::SOURCE_MANUAL => 'Manual',
            default => ucfirst($this->trigger_source ?? 'Unknown'),
        };
    }
}
