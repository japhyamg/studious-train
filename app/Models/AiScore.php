<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiScore extends Model
{
    protected $fillable = [
        'transaction_id', 'account_number', 'transaction_side',
        'is_anomaly', 'anomaly_score', 'severity', 'anomaly_reason', 'raw_response',
    ];

    protected $casts = [
        'is_anomaly' => 'boolean',
        'raw_response' => 'array',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
