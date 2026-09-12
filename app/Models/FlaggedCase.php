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
    ];

    protected $casts = [
        'transaction_ids' => 'array',
        'trigger_details' => 'array',
    ];

    // Trigger source constants
    const SOURCE_RULE = 'rule';
    const SOURCE_WATCHLIST = 'watchlist';
    const SOURCE_RISK_SCORE = 'risk_score';
    const SOURCE_AI_ANOMALY = 'ai_anomaly';
    const SOURCE_PEER_GROUP = 'peer_group';
    const SOURCE_MANUAL = 'manual';

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
            self::SOURCE_MANUAL => 'Manual',
            default => ucfirst($this->trigger_source ?? 'Unknown'),
        };
    }
}
