<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeerGroupOutlier extends Model
{
    protected $fillable = [
        'transaction_id', 'customer_id', 'peer_group',
        'customer_group', 'threshold', 'exceeded_by', 'is_flagged',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
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
