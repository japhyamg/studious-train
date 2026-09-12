<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskLevelChange extends Model
{
    protected $fillable = [
        'customer_id', 'from_level', 'to_level', 'score', 'driver',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
