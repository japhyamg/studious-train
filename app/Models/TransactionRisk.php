<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionRisk extends Model
{
    protected $fillable = [
        'transaction_id', 'customer_id', 'total_score', 'risk_level', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
